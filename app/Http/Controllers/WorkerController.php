<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\TrainingRecord;
use App\Models\Worker;
use App\Models\WorkerDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkerController extends Controller
{
    public function index(): Response
    {
        $workers = Worker::query()
            ->with([
                'user:id,name,email',
                'documents' => fn ($query) => $query->orderBy('document_type'),
                'trainingRecords' => fn ($query) => $query->orderByDesc('completed_on')->orderBy('course_name'),
            ])
            ->withCount(['assignments', 'visits'])
            ->orderByRaw("case when status = 'available' then 0 when status = 'assigned' then 1 when status = 'on_leave' then 2 else 3 end")
            ->orderBy('name')
            ->get();

        return Inertia::render('Workers/Index', [
            'workers' => $workers->map(fn (Worker $worker): array => $this->serializeWorker($worker)),
            'catalog' => $this->catalog(),
            'metrics' => [
                'total' => Worker::query()->count(),
                'available' => Worker::query()->where('status', 'available')->count(),
                'assigned' => Worker::query()->where('status', 'assigned')->count(),
                'expiringDocuments' => $this->expiringDocumentsQuery()->count(),
            ],
            'createUrl' => '/app/workers/create',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Workers/Form', [
            'mode' => 'create',
            'worker' => null,
            'catalog' => $this->catalog(),
            'steps' => $this->formSteps(),
            'submitUrl' => '/app/workers',
            'backUrl' => '/app/workers',
        ]);
    }

    public function edit(Worker $worker): Response
    {
        $worker->load([
            'user:id,name,email',
            'documents' => fn ($query) => $query->orderBy('document_type'),
            'trainingRecords' => fn ($query) => $query->orderByDesc('completed_on')->orderBy('course_name'),
        ]);

        return Inertia::render('Workers/Form', [
            'mode' => 'edit',
            'worker' => $this->serializeWorker($worker),
            'catalog' => $this->catalog(),
            'steps' => $this->formSteps(),
            'submitUrl' => "/app/workers/{$worker->id}",
            'backUrl' => '/app/workers',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateWorker($request);
        $documents = Arr::pull($validated, 'documents', []);
        $trainingRecords = Arr::pull($validated, 'training_records', []);

        $worker = DB::transaction(function () use ($request, $validated, $documents, $trainingRecords): Worker {
            $worker = Worker::create($validated);
            $this->syncDocuments($worker, $documents);
            $this->syncTrainingRecords($worker, $trainingRecords);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'worker.created',
                'auditable_type' => Worker::class,
                'auditable_id' => $worker->id,
                'changes' => [
                    'created' => [
                        'old' => null,
                        'new' => $this->serializeWorker($worker->fresh(['user', 'documents', 'trainingRecords'])),
                    ],
                ],
            ]);

            return $worker;
        });

        return redirect('/app/workers')->with('success', __('Worker :worker created.', ['worker' => $worker->name]));
    }

    public function update(Request $request, Worker $worker): RedirectResponse
    {
        $validated = $this->validateWorker($request, $worker);
        $documents = Arr::pull($validated, 'documents', []);
        $trainingRecords = Arr::pull($validated, 'training_records', []);
        $oldValues = $worker->only($this->workerFields());
        $oldDocumentCount = $worker->documents()->count();
        $oldTrainingCount = $worker->trainingRecords()->count();

        DB::transaction(function () use ($request, $worker, $validated, $documents, $trainingRecords, $oldValues, $oldDocumentCount, $oldTrainingCount): void {
            $worker->fill($validated)->save();
            $this->syncDocuments($worker, $documents);
            $this->syncTrainingRecords($worker, $trainingRecords);

            $worker->refresh();
            $changes = $this->changes($oldValues, $worker->only($this->workerFields()));
            $newDocumentCount = $worker->documents()->count();
            $newTrainingCount = $worker->trainingRecords()->count();

            if ($oldDocumentCount !== $newDocumentCount) {
                $changes['documents_count'] = [
                    'old' => $oldDocumentCount,
                    'new' => $newDocumentCount,
                ];
            }

            if ($oldTrainingCount !== $newTrainingCount) {
                $changes['training_records_count'] = [
                    'old' => $oldTrainingCount,
                    'new' => $newTrainingCount,
                ];
            }

            if ($changes !== []) {
                AuditLog::create([
                    'user_id' => $request->user()?->id,
                    'action' => 'worker.updated',
                    'auditable_type' => Worker::class,
                    'auditable_id' => $worker->id,
                    'changes' => $changes,
                ]);
            }
        });

        return redirect('/app/workers')->with('success', __('Worker :worker updated.', ['worker' => $worker->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWorker(Request $request, ?Worker $worker = null): array
    {
        $catalog = $this->catalog();

        if (! $request->has('cost_rate_sar') && $request->has('cost_rate_halalas')) {
            $request->merge([
                'cost_rate_sar' => $this->halalasToSarString((int) $request->input('cost_rate_halalas', 0)),
            ]);
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_code' => ['required', 'string', 'max:40', Rule::unique('workers', 'employee_code')->ignore($worker?->id)],
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:32'],
            'hired_on' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:120'],
            'role_language' => ['required', Rule::in(config('cleanops.supported_locales', ['ar', 'en']))],
            'job_role' => ['required', Rule::in($this->catalogKeys($catalog['jobRoles']))],
            'status' => ['required', Rule::in($this->catalogKeys($catalog['statuses']))],
            'cost_rate_sar' => ['required', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'skills' => ['array', 'max:20'],
            'skills.*' => ['required', Rule::in($this->catalogKeys($catalog['skills']))],
            'certifications' => ['array', 'max:20'],
            'certifications.*' => ['required', Rule::in($this->catalogKeys($catalog['certifications']))],
            'availability_notes' => ['nullable', 'string', 'max:1000'],
            'documents' => ['array', 'max:20'],
            'documents.*.id' => ['nullable', 'integer', 'exists:worker_documents,id'],
            'documents.*.document_type' => ['required', Rule::in($this->catalogKeys($catalog['documentTypes']))],
            'documents.*.document_number' => ['nullable', 'string', 'max:120'],
            'documents.*.expires_on' => ['nullable', 'date'],
            'documents.*.file_path' => ['nullable', 'string', 'max:500'],
            'training_records' => ['array', 'max:30'],
            'training_records.*.id' => ['nullable', 'integer', 'exists:training_records,id'],
            'training_records.*.course_name' => ['required', 'string', 'max:180'],
            'training_records.*.certificate_code' => ['nullable', 'string', 'max:120'],
            'training_records.*.completed_on' => ['nullable', 'date'],
            'training_records.*.expires_on' => ['nullable', 'date'],
        ]);

        if ($worker) {
            $this->assertOwnedIds(
                $worker,
                collect($validated['documents'] ?? [])->pluck('id')->filter()->values()->all(),
                'documents',
                'One or more documents do not belong to this worker.'
            );

            $this->assertOwnedIds(
                $worker,
                collect($validated['training_records'] ?? [])->pluck('id')->filter()->values()->all(),
                'trainingRecords',
                'One or more training records do not belong to this worker.'
            );
        }

        $validated['cost_rate_halalas'] = $this->sarToHalalas($validated['cost_rate_sar']);
        unset($validated['cost_rate_sar']);

        return $validated;
    }

    private function sarToHalalas(string|int|float $value): int
    {
        $normalized = trim((string) $value);
        [$riyals, $halalas] = array_pad(explode('.', $normalized, 2), 2, '0');

        return (((int) $riyals) * 100) + (int) str_pad(substr($halalas, 0, 2), 2, '0');
    }

    private function halalasToSarString(int $halalas): string
    {
        return intdiv($halalas, 100).'.'.str_pad((string) ($halalas % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function assertOwnedIds(Worker $worker, array $ids, string $relation, string $message): void
    {
        if ($ids === []) {
            return;
        }

        if (count($ids) !== $worker->{$relation}()->whereKey($ids)->count()) {
            throw ValidationException::withMessages([
                $relation => __($message),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     */
    private function syncDocuments(Worker $worker, array $documents): void
    {
        $keptIds = collect();

        foreach ($documents as $document) {
            $documentId = $document['id'] ?? null;
            unset($document['id']);

            if ($documentId) {
                $worker->documents()->whereKey($documentId)->update($document);
                $keptIds->push((int) $documentId);

                continue;
            }

            $created = $worker->documents()->create($document);
            $keptIds->push($created->id);
        }

        $worker->documents()
            ->when($keptIds->isNotEmpty(), fn ($query) => $query->whereKeyNot($keptIds->all()))
            ->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $trainingRecords
     */
    private function syncTrainingRecords(Worker $worker, array $trainingRecords): void
    {
        $keptIds = collect();

        foreach ($trainingRecords as $record) {
            $recordId = $record['id'] ?? null;
            unset($record['id']);

            if ($recordId) {
                $worker->trainingRecords()->whereKey($recordId)->update($record);
                $keptIds->push((int) $recordId);

                continue;
            }

            $created = $worker->trainingRecords()->create($record);
            $keptIds->push($created->id);
        }

        $worker->trainingRecords()
            ->when($keptIds->isNotEmpty(), fn ($query) => $query->whereKeyNot($keptIds->all()))
            ->delete();
    }

    private function expiringDocumentsQuery()
    {
        return WorkerDocument::query()
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '>=', now()->toDateString())
            ->whereDate('expires_on', '<=', now()->addDays(30)->toDateString());
    }

    /**
     * @return list<string>
     */
    private function workerFields(): array
    {
        return [
            'user_id',
            'employee_code',
            'name',
            'phone',
            'hired_on',
            'nationality',
            'role_language',
            'job_role',
            'status',
            'cost_rate_halalas',
            'skills',
            'certifications',
            'availability_notes',
        ];
    }

    /**
     * @return array<string, list<array{key: string, label: string}>>
     */
    private function catalog(): array
    {
        return [
            'statuses' => [
                ['key' => 'available', 'label' => 'Available'],
                ['key' => 'assigned', 'label' => 'Assigned'],
                ['key' => 'on_leave', 'label' => 'On leave'],
                ['key' => 'suspended', 'label' => 'Suspended'],
                ['key' => 'inactive', 'label' => 'Inactive'],
            ],
            'jobRoles' => [
                ['key' => 'cleaner', 'label' => 'Cleaner'],
                ['key' => 'team_lead', 'label' => 'Team lead'],
                ['key' => 'supervisor', 'label' => 'Supervisor'],
                ['key' => 'maintenance_technician', 'label' => 'Maintenance technician'],
                ['key' => 'car_wash_technician', 'label' => 'Car wash technician'],
                ['key' => 'driver', 'label' => 'Driver'],
            ],
            'skills' => [
                ['key' => 'general_cleaning', 'label' => 'General cleaning'],
                ['key' => 'deep_cleaning', 'label' => 'Deep cleaning'],
                ['key' => 'clinic_cleaning', 'label' => 'Clinic cleaning'],
                ['key' => 'site_supervision', 'label' => 'Site supervision'],
                ['key' => 'ac_maintenance', 'label' => 'AC maintenance'],
                ['key' => 'car_wash', 'label' => 'Car wash'],
                ['key' => 'driving', 'label' => 'Driving'],
            ],
            'certifications' => [
                ['key' => 'infection_control', 'label' => 'Infection control'],
                ['key' => 'height_safety', 'label' => 'Height safety'],
                ['key' => 'electrical_safety', 'label' => 'Electrical safety'],
                ['key' => 'hvac_basic', 'label' => 'HVAC basic'],
                ['key' => 'driver_license', 'label' => 'Driver license'],
            ],
            'documentTypes' => [
                ['key' => 'iqama', 'label' => 'Iqama'],
                ['key' => 'passport', 'label' => 'Passport'],
                ['key' => 'contract', 'label' => 'Employment contract'],
                ['key' => 'medical', 'label' => 'Medical certificate'],
                ['key' => 'insurance', 'label' => 'Insurance'],
                ['key' => 'driver_license', 'label' => 'Driver license'],
            ],
            'languages' => [
                ['key' => 'ar', 'label' => 'Arabic'],
                ['key' => 'en', 'label' => 'English'],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function formSteps(): array
    {
        return [
            ['key' => 'profile', 'label' => 'Profile'],
            ['key' => 'skills', 'label' => 'Skills'],
            ['key' => 'documents', 'label' => 'Documents'],
            ['key' => 'training', 'label' => 'Training'],
        ];
    }

    /**
     * @param  list<array{key: string, label: string}>  $items
     * @return list<string>
     */
    private function catalogKeys(array $items): array
    {
        return array_column($items, 'key');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWorker(Worker $worker): array
    {
        return [
            'id' => $worker->id,
            'user_id' => $worker->user_id,
            'user' => $worker->user ? [
                'id' => $worker->user->id,
                'name' => $worker->user->name,
                'email' => $worker->user->email,
            ] : null,
            'employee_code' => $worker->employee_code,
            'name' => $worker->name,
            'phone' => $worker->phone,
            'hired_on' => $worker->hired_on?->toDateString(),
            'nationality' => $worker->nationality,
            'role_language' => $worker->role_language,
            'job_role' => $worker->job_role,
            'status' => $worker->status,
            'cost_rate_halalas' => $worker->cost_rate_halalas,
            'cost_rate_sar' => $this->halalasToSarString($worker->cost_rate_halalas),
            'skills' => $worker->skills ?? [],
            'certifications' => $worker->certifications ?? [],
            'availability_notes' => $worker->availability_notes,
            'edit_url' => "/app/workers/{$worker->id}/edit",
            'assignments_count' => $worker->assignments_count ?? $worker->assignments()->count(),
            'visits_count' => $worker->visits_count ?? $worker->visits()->count(),
            'documents' => $worker->documents->map(fn (WorkerDocument $document): array => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'document_number' => $document->document_number,
                'expires_on' => $document->expires_on?->toDateString(),
                'file_path' => $document->file_path,
                'status' => $this->expiryStatus($document->expires_on),
            ])->values(),
            'training_records' => $worker->trainingRecords->map(fn (TrainingRecord $record): array => [
                'id' => $record->id,
                'course_name' => $record->course_name,
                'certificate_code' => $record->certificate_code,
                'completed_on' => $record->completed_on?->toDateString(),
                'expires_on' => $record->expires_on?->toDateString(),
                'status' => $this->expiryStatus($record->expires_on),
            ])->values(),
            'compliance' => $this->complianceSummary($worker),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function complianceSummary(Worker $worker): array
    {
        $documents = $worker->documents;
        $trainingRecords = $worker->trainingRecords;

        return [
            'documents' => $documents->count(),
            'expired_documents' => $documents->filter(fn (WorkerDocument $document): bool => $this->expiryStatus($document->expires_on) === 'expired')->count(),
            'expiring_documents' => $documents->filter(fn (WorkerDocument $document): bool => $this->expiryStatus($document->expires_on) === 'expiring')->count(),
            'training_records' => $trainingRecords->count(),
            'expired_training' => $trainingRecords->filter(fn (TrainingRecord $record): bool => $this->expiryStatus($record->expires_on) === 'expired')->count(),
            'expiring_training' => $trainingRecords->filter(fn (TrainingRecord $record): bool => $this->expiryStatus($record->expires_on) === 'expiring')->count(),
            'certifications' => count($worker->certifications ?? []),
        ];
    }

    private function expiryStatus(?Carbon $expiresOn): string
    {
        if (! $expiresOn) {
            return 'not_tracked';
        }

        if ($expiresOn->isPast() && ! $expiresOn->isToday()) {
            return 'expired';
        }

        if ($expiresOn->lte(now()->addDays(30))) {
            return 'expiring';
        }

        return 'valid';
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function changes(array $oldValues, array $newValues): array
    {
        $changes = [];

        foreach ($newValues as $key => $newValue) {
            if (($oldValues[$key] ?? null) !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValues[$key] ?? null,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }
}
