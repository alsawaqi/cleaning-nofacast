<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ChecklistItem;
use App\Models\Visit;
use App\Models\Worker;
use App\Services\Operations\VisitExecutionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkerPortalController extends Controller
{
    public function today(VisitExecutionService $execution): Response
    {
        $worker = $this->workerForRequest();
        $visits = $worker
            ? Visit::query()
                ->with(['contract', 'site.customer', 'service', 'checklistItems'])
                ->where('worker_id', $worker->id)
                ->whereDate('scheduled_for', now()->toDateString())
                ->orderBy('starts_at')
                ->get()
            : collect();

        return Inertia::render('Worker/Today', [
            'worker' => $worker ? $this->serializeWorker($worker) : null,
            'summary' => $this->serializeMobileSummary($visits),
            'activeVisitId' => $this->activeVisitId($visits),
            'nextVisitId' => $this->nextVisitId($visits),
            'visits' => $visits
                ->map(fn (Visit $visit): array => $this->serializeVisit($visit, $execution))
                ->values(),
        ]);
    }

    public function start(Request $request, Visit $visit, VisitExecutionService $execution): RedirectResponse
    {
        $visit = $this->ownedVisit($visit);
        $validated = $request->validate([
            'check_in_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'check_in_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'check_in_accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $old = $visit->only($this->executionFields());

        $execution->start($visit, null, $validated);

        $this->audit('worker.visit_started', $visit, $old, $visit->only($this->executionFields()));

        return back()->with('success', __('Visit started.'));
    }

    public function checklist(Request $request, Visit $visit): RedirectResponse
    {
        $visit = $this->ownedVisit($visit);
        $request->validate(['items' => ['array']]);

        $items = $this->validateChecklistInput($this->normalizeChecklistInput($request->all()['items'] ?? []));

        foreach ($items as $item) {
            $status = (string) $item['status'];
            $photoPath = $item['photo_path'] ?? null;

            if (($item['photo_file'] ?? null) instanceof UploadedFile) {
                $photoPath = $this->storeWorkerPhoto($item['photo_file'], 'worker-photos/checklist');
            }

            $visit->checklistItems()
                ->whereKey((int) $item['id'])
                ->update([
                    'status' => $status,
                    'completed_at' => $status === 'done' ? now() : null,
                    'notes' => $item['notes'] ?? null,
                    'photo_path' => $photoPath,
                    'updated_at' => now(),
                ]);
        }

        return back()->with('success', __('Checklist updated.'));
    }

    public function finish(Request $request, Visit $visit, VisitExecutionService $execution): RedirectResponse
    {
        $visit = $this->ownedVisit($visit);
        $validated = $request->validate([
            'completed_items' => ['array'],
            'completed_items.*' => ['integer', 'exists:checklist_items,id'],
            'materials_used' => ['array', 'max:20'],
            'materials_used.*.name' => ['required', 'string', 'max:160'],
            'materials_used.*.quantity' => ['nullable', 'string', 'max:80'],
            'materials_used.*.cost_sar' => ['required', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'photos' => ['array', 'max:10'],
            'photos.*' => ['required', 'string', 'max:255'],
            'photo_files' => ['array', 'max:10'],
            'photo_files.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'execution_notes' => ['nullable', 'string', 'max:2000'],
            'checked_out_at' => ['nullable', 'date'],
            'check_out_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'check_out_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'check_out_accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $old = $visit->only($this->executionFields());
        $completedIds = collect($validated['completed_items'] ?? [])->map(fn ($id): int => (int) $id)->all();

        if ($this->timerState($visit) !== 'running') {
            throw ValidationException::withMessages([
                'visit' => __('Start the visit before finishing.'),
            ]);
        }

        if ($this->missingRequiredChecklistIds($visit, $completedIds) !== []) {
            throw ValidationException::withMessages([
                'completed_items' => __('Complete all required tasks before finishing the visit.'),
            ]);
        }

        if ($completedIds !== []) {
            $visit->checklistItems()
                ->whereIn('id', $completedIds)
                ->update([
                    'status' => 'done',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $validated['photos'] = $this->mergePhotoEvidence(
            $validated['photos'] ?? [],
            $this->storeUploadedPhotoFiles($request->file('photo_files', []), 'worker-photos/visits')
        );
        unset($validated['photo_files']);

        $execution->finish($visit, $validated);

        $this->audit('worker.visit_finished', $visit, $old, $visit->only($this->executionFields()));

        return back()->with('success', __('Visit finished.'));
    }

    public function issue(Request $request, Visit $visit): RedirectResponse
    {
        $visit = $this->ownedVisit($visit);
        $validated = $request->validate([
            'issue_note' => ['required', 'string', 'max:2000'],
            'photos' => ['array', 'max:10'],
            'photos.*' => ['required', 'string', 'max:255'],
            'photo_files' => ['array', 'max:10'],
            'photo_files.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $old = $visit->only(['status', 'issue_note', 'photos']);
        $photos = $this->mergePhotoEvidence(
            $validated['photos'] ?? [],
            $this->storeUploadedPhotoFiles($request->file('photo_files', []), 'worker-photos/issues')
        );

        $visit->forceFill([
            'status' => 'issue_reported',
            'issue_note' => $validated['issue_note'],
            'photos' => $photos,
        ])->save();

        $this->audit('worker.visit_issue_reported', $visit, $old, $visit->only(['status', 'issue_note', 'photos']));

        return back()->with('success', __('Issue reported.'));
    }

    private function workerForRequest(): ?Worker
    {
        return Worker::query()
            ->where('user_id', auth()->id())
            ->first();
    }

    private function ownedVisit(Visit $visit): Visit
    {
        $worker = $this->workerForRequest();

        abort_unless($worker && (int) $visit->worker_id === (int) $worker->id, 404);

        return $visit->loadMissing(['contract', 'site.customer', 'service', 'checklistItems', 'worker']);
    }

    private function timerState(Visit $visit): string
    {
        if ($visit->checked_out_at || $visit->status === 'completed') {
            return 'finished';
        }

        if ($visit->checked_in_at || $visit->status === 'in_progress') {
            return 'running';
        }

        return 'not_started';
    }

    private function serializeVisit(Visit $visit, VisitExecutionService $execution): array
    {
        $timerState = $this->timerState($visit);
        $plannedMinutes = $visit->planned_minutes ?: $execution->durationMinutes($visit->starts_at, $visit->ends_at);
        $elapsedMinutes = $visit->checked_in_at && ! $visit->checked_out_at
            ? max(0, (int) $visit->checked_in_at->diffInMinutes(now()))
            : $visit->actual_minutes;
        $checklistSummary = $this->checklistSummary($visit);

        return [
            'id' => $visit->id,
            'scheduled_for' => $visit->scheduled_for?->toDateString(),
            'starts_at' => $this->time($visit->starts_at),
            'ends_at' => $this->time($visit->ends_at),
            'status' => $visit->status,
            'checked_in_at' => $visit->checked_in_at?->toDateTimeString(),
            'checked_out_at' => $visit->checked_out_at?->toDateTimeString(),
            'check_in_location' => $this->locationPayload($visit, 'check_in'),
            'check_out_location' => $this->locationPayload($visit, 'check_out'),
            'planned_minutes' => $plannedMinutes,
            'actual_minutes' => $visit->actual_minutes,
            'elapsed_minutes' => $elapsedMinutes,
            'variance_minutes' => $visit->variance_minutes,
            'overtime_minutes' => $visit->overtime_minutes,
            'billable_overtime_minutes' => $visit->billable_overtime_minutes,
            'overtime_status' => $visit->overtime_status,
            'timer_state' => $timerState,
            'can_start' => $timerState === 'not_started' && in_array($visit->status, ['scheduled', 'assigned'], true),
            'can_finish' => $timerState === 'running',
            'can_save_checklist' => $timerState !== 'finished',
            'checklist_summary' => $checklistSummary,
            'workflow' => $this->visitWorkflow($visit, $timerState, $checklistSummary),
            'materials_used' => $visit->materials_used ?? [],
            'planned_revenue_halalas' => $visit->planned_revenue_halalas,
            'labor_cost_halalas' => $visit->labor_cost_halalas,
            'material_cost_halalas' => $visit->material_cost_halalas,
            'billable_overtime_halalas' => $visit->billable_overtime_halalas,
            'gross_profit_halalas' => $visit->gross_profit_halalas,
            'execution_notes' => $visit->execution_notes,
            'issue_note' => $visit->issue_note,
            'photos' => $visit->photos ?? [],
            'photo_urls' => collect($visit->photos ?? [])
                ->map(fn (string $path): string => Storage::disk('public')->url($path))
                ->values(),
            'contract' => $visit->contract ? [
                'id' => $visit->contract->id,
                'reference' => $visit->contract->reference,
                'status' => $visit->contract->status,
            ] : null,
            'site' => $visit->site ? [
                'id' => $visit->site->id,
                'name' => $visit->site->name,
                'city' => $visit->site->city,
                'district' => $visit->site->district,
                'address' => $visit->site->address,
                'contact_name' => $visit->site->contact_name,
                'contact_phone' => $visit->site->contact_phone,
                'latitude' => $visit->site->latitude,
                'longitude' => $visit->site->longitude,
                'formatted_address' => $visit->site->formatted_address,
                'maps_url' => $this->mapsUrl($visit),
                'customer' => $visit->site->customer ? [
                    'id' => $visit->site->customer->id,
                    'name' => $visit->site->customer->name,
                ] : null,
            ] : null,
            'customer' => $visit->site?->customer?->name,
            'service' => $visit->service ? [
                'id' => $visit->service->id,
                'title' => $visit->service->title,
                'category' => $visit->service->category,
            ] : null,
            'checklist_items' => $visit->checklistItems->map(fn (ChecklistItem $item): array => [
                'id' => $item->id,
                'label' => $item->label,
                'is_required' => $item->is_required,
                'status' => $item->status,
                'notes' => $item->notes,
                'photo_path' => $item->photo_path,
                'photo_url' => $item->photo_path ? Storage::disk('public')->url($item->photo_path) : null,
                'completed_at' => $item->completed_at?->toDateTimeString(),
            ])->values(),
        ];
    }

    private function serializeMobileSummary($visits): array
    {
        $checklistSummaries = $visits->map(fn (Visit $visit): array => $this->checklistSummary($visit));

        return [
            'total_visits' => $visits->count(),
            'scheduled_visits' => $visits->where('status', 'scheduled')->count(),
            'in_progress_visits' => $visits->where('status', 'in_progress')->count(),
            'completed_visits' => $visits->where('status', 'completed')->count(),
            'issue_visits' => $visits->where('status', 'issue_reported')->count(),
            'required_tasks' => $checklistSummaries->sum('required'),
            'completed_required_tasks' => $checklistSummaries->sum('completed_required'),
            'open_required_tasks' => $checklistSummaries->sum('open_required'),
            'total_tasks' => $checklistSummaries->sum('total'),
            'completed_tasks' => $checklistSummaries->sum('completed'),
        ];
    }

    private function activeVisitId($visits): ?int
    {
        $visit = $visits->first(fn (Visit $visit): bool => $this->timerState($visit) === 'running')
            ?? $visits->first(fn (Visit $visit): bool => $this->timerState($visit) === 'not_started' && $visit->status === 'scheduled');

        return $visit?->id;
    }

    private function nextVisitId($visits): ?int
    {
        return $this->activeVisitId($visits);
    }

    /**
     * @return array{total: int, required: int, completed: int, completed_required: int, open_required: int, progress_percent: int}
     */
    private function checklistSummary(Visit $visit): array
    {
        $items = $visit->relationLoaded('checklistItems')
            ? $visit->checklistItems
            : $visit->checklistItems()->get();
        $required = $items->where('is_required', true);
        $completed = $items->where('status', 'done');
        $completedRequired = $required->where('status', 'done')->count();
        $total = $items->count();

        return [
            'total' => $total,
            'required' => $required->count(),
            'completed' => $completed->count(),
            'completed_required' => $completedRequired,
            'open_required' => max(0, $required->count() - $completedRequired),
            'progress_percent' => $total > 0 ? (int) round(($completed->count() / $total) * 100) : 0,
        ];
    }

    /**
     * @param  array{total: int, required: int, completed: int, completed_required: int, open_required: int, progress_percent: int}  $checklistSummary
     * @return array<string, mixed>
     */
    private function visitWorkflow(Visit $visit, string $timerState, array $checklistSummary): array
    {
        $tasksComplete = $checklistSummary['open_required'] === 0;
        $hasEvidence = count($visit->photos ?? []) > 0
            || $visit->checklistItems->contains(fn (ChecklistItem $item): bool => filled($item->photo_path));
        $primaryAction = match ($timerState) {
            'not_started' => 'start',
            'running' => $tasksComplete ? 'finish' : 'save_tasks',
            default => null,
        };

        if ($visit->status === 'issue_reported') {
            $primaryAction = 'issue_reported';
        }

        return [
            'phase' => match (true) {
                $visit->status === 'issue_reported' => 'issue',
                $timerState === 'finished' => 'completed',
                $timerState === 'running' => 'execution',
                default => 'arrival',
            },
            'primary_action' => $primaryAction,
            'steps' => [
                ['key' => 'start', 'status' => $timerState === 'not_started' ? 'current' : 'complete'],
                ['key' => 'tasks', 'status' => $tasksComplete ? 'complete' : ($timerState === 'running' ? 'current' : 'pending')],
                ['key' => 'evidence', 'status' => $hasEvidence ? 'complete' : ($tasksComplete && $timerState === 'running' ? 'current' : 'pending')],
                ['key' => 'finish', 'status' => $timerState === 'finished' ? 'complete' : ($tasksComplete && $timerState === 'running' ? 'current' : 'pending')],
            ],
        ];
    }

    private function mapsUrl(Visit $visit): ?string
    {
        if (! $visit->site?->latitude || ! $visit->site?->longitude) {
            return null;
        }

        return "https://www.google.com/maps/search/?api=1&query={$visit->site->latitude},{$visit->site->longitude}";
    }

    /**
     * @return array{latitude: string, longitude: string, accuracy_meters: int|null, maps_url: string}|null
     */
    private function locationPayload(Visit $visit, string $prefix): ?array
    {
        $latitude = $visit->getAttribute("{$prefix}_latitude");
        $longitude = $visit->getAttribute("{$prefix}_longitude");

        if (! $latitude || ! $longitude) {
            return null;
        }

        return [
            'latitude' => (string) $latitude,
            'longitude' => (string) $longitude,
            'accuracy_meters' => $visit->getAttribute("{$prefix}_accuracy_meters"),
            'maps_url' => "https://www.google.com/maps/search/?api=1&query={$latitude},{$longitude}",
        ];
    }

    /**
     * @param  array<int, string>  $manualPhotos
     * @param  array<int, string>  $uploadedPhotos
     * @return array<int, string>
     */
    private function mergePhotoEvidence(array $manualPhotos, array $uploadedPhotos): array
    {
        return collect([...$manualPhotos, ...$uploadedPhotos])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return array<int, string>
     */
    private function storeUploadedPhotoFiles(array|UploadedFile|null $files, string $directory): array
    {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        return collect($files ?? [])
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file): string => $this->storeWorkerPhoto($file, $directory))
            ->values()
            ->all();
    }

    private function storeWorkerPhoto(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    private function serializeWorker(Worker $worker): array
    {
        return [
            'id' => $worker->id,
            'name' => $worker->name,
            'employee_code' => $worker->employee_code,
            'status' => $worker->status,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{id: int, status: string, notes: string|null, photo_path: string|null, photo_file?: UploadedFile|null}>
     */
    private function normalizeChecklistInput(array $items): array
    {
        return collect($items)
            ->map(function (mixed $item): ?array {
                if (is_array($item)) {
                    $id = (int) ($item['id'] ?? 0);

                    return $id > 0 ? [
                        'id' => $id,
                        'status' => (string) ($item['status'] ?? 'done'),
                        'notes' => filled($item['notes'] ?? null) ? (string) $item['notes'] : null,
                        'photo_path' => filled($item['photo_path'] ?? null) ? (string) $item['photo_path'] : null,
                        'photo_file' => ($item['photo_file'] ?? null) instanceof UploadedFile ? $item['photo_file'] : null,
                    ] : null;
                }

                $id = (int) $item;

                return $id > 0 ? [
                    'id' => $id,
                    'status' => 'done',
                    'notes' => null,
                    'photo_path' => null,
                    'photo_file' => null,
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{id: int, status: string, notes: string|null, photo_path: string|null, photo_file?: UploadedFile|null}>  $items
     * @return array<int, array{id: int, status: string, notes: string|null, photo_path: string|null, photo_file?: UploadedFile|null}>
     */
    private function validateChecklistInput(array $items): array
    {
        $validated = validator(['items' => $items], [
            'items' => ['array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.status' => ['required', Rule::in(['pending', 'done', 'skipped'])],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
            'items.*.photo_path' => ['nullable', 'string', 'max:255'],
            'items.*.photo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ])->validate();

        return $validated['items'] ?? [];
    }

    /**
     * @param  array<int, int>  $completedIds
     * @return array<int, int>
     */
    private function missingRequiredChecklistIds(Visit $visit, array $completedIds): array
    {
        return $visit->checklistItems()
            ->where('is_required', true)
            ->get()
            ->filter(fn (ChecklistItem $item): bool => $item->status !== 'done' && ! in_array($item->id, $completedIds, true))
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function executionFields(): array
    {
        return [
            'status',
            'checked_in_at',
            'check_in_latitude',
            'check_in_longitude',
            'check_in_accuracy_meters',
            'checked_out_at',
            'check_out_latitude',
            'check_out_longitude',
            'check_out_accuracy_meters',
            'planned_minutes',
            'actual_minutes',
            'variance_minutes',
            'overtime_minutes',
            'billable_overtime_minutes',
            'overtime_status',
            'materials_used',
            'planned_revenue_halalas',
            'labor_cost_halalas',
            'material_cost_halalas',
            'billable_overtime_halalas',
            'gross_profit_halalas',
            'execution_notes',
            'photos',
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function audit(string $action, Model $model, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'changes' => [
                'updated' => [
                    'old' => $old,
                    'new' => $new,
                ],
            ],
        ]);
    }

    private function time(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
