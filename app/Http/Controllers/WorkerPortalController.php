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
use Inertia\Inertia;
use Inertia\Response;

class WorkerPortalController extends Controller
{
    public function today(VisitExecutionService $execution): Response
    {
        $worker = $this->workerForRequest();

        return Inertia::render('Worker/Today', [
            'worker' => $worker ? $this->serializeWorker($worker) : null,
            'visits' => $worker
                ? Visit::query()
                    ->with(['contract', 'site.customer', 'service', 'checklistItems'])
                    ->where('worker_id', $worker->id)
                    ->whereDate('scheduled_for', now()->toDateString())
                    ->orderBy('starts_at')
                    ->get()
                    ->map(fn (Visit $visit): array => $this->serializeVisit($visit, $execution))
                    ->values()
                : [],
        ]);
    }

    public function start(Visit $visit, VisitExecutionService $execution): RedirectResponse
    {
        $visit = $this->ownedVisit($visit);
        $old = $visit->only($this->executionFields());

        $execution->start($visit);

        $this->audit('worker.visit_started', $visit, $old, $visit->only($this->executionFields()));

        return back()->with('success', __('Visit started.'));
    }

    public function checklist(Request $request, Visit $visit): RedirectResponse
    {
        $visit = $this->ownedVisit($visit);
        $validated = $request->validate([
            'items' => ['array'],
            'items.*' => ['integer', 'exists:checklist_items,id'],
        ]);

        $itemIds = collect($validated['items'] ?? [])->map(fn ($id): int => (int) $id)->all();

        $visit->checklistItems()
            ->whereIn('id', $itemIds)
            ->update([
                'status' => 'done',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

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
            'execution_notes' => ['nullable', 'string', 'max:2000'],
            'checked_out_at' => ['nullable', 'date'],
        ]);

        $old = $visit->only($this->executionFields());
        $completedIds = collect($validated['completed_items'] ?? [])->map(fn ($id): int => (int) $id)->all();

        if ($completedIds !== []) {
            $visit->checklistItems()
                ->whereIn('id', $completedIds)
                ->update([
                    'status' => 'done',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        }

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
        ]);
        $old = $visit->only(['status', 'issue_note', 'photos']);

        $visit->forceFill([
            'status' => 'issue_reported',
            'issue_note' => $validated['issue_note'],
            'photos' => collect($validated['photos'] ?? [])->filter()->values()->all(),
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

        return [
            'id' => $visit->id,
            'scheduled_for' => $visit->scheduled_for?->toDateString(),
            'starts_at' => $this->time($visit->starts_at),
            'ends_at' => $this->time($visit->ends_at),
            'status' => $visit->status,
            'checked_in_at' => $visit->checked_in_at?->toDateTimeString(),
            'checked_out_at' => $visit->checked_out_at?->toDateTimeString(),
            'planned_minutes' => $plannedMinutes,
            'actual_minutes' => $visit->actual_minutes,
            'elapsed_minutes' => $elapsedMinutes,
            'variance_minutes' => $visit->variance_minutes,
            'overtime_minutes' => $visit->overtime_minutes,
            'billable_overtime_minutes' => $visit->billable_overtime_minutes,
            'overtime_status' => $visit->overtime_status,
            'timer_state' => $timerState,
            'can_start' => $timerState === 'not_started',
            'can_finish' => $timerState === 'running',
            'materials_used' => $visit->materials_used ?? [],
            'planned_revenue_halalas' => $visit->planned_revenue_halalas,
            'labor_cost_halalas' => $visit->labor_cost_halalas,
            'material_cost_halalas' => $visit->material_cost_halalas,
            'billable_overtime_halalas' => $visit->billable_overtime_halalas,
            'gross_profit_halalas' => $visit->gross_profit_halalas,
            'execution_notes' => $visit->execution_notes,
            'issue_note' => $visit->issue_note,
            'photos' => $visit->photos ?? [],
            'site' => $visit->site ? [
                'id' => $visit->site->id,
                'name' => $visit->site->name,
                'city' => $visit->site->city,
                'district' => $visit->site->district,
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
                'status' => $item->status,
                'notes' => $item->notes,
                'photo_path' => $item->photo_path,
                'completed_at' => $item->completed_at?->toDateTimeString(),
            ])->values(),
        ];
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
     * @return list<string>
     */
    private function executionFields(): array
    {
        return [
            'status',
            'checked_in_at',
            'checked_out_at',
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
