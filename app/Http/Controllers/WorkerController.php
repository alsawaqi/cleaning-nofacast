<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\TrainingRecord;
use App\Models\Visit;
use App\Models\Worker;
use App\Models\WorkerAvailabilityBlock;
use App\Models\WorkerDocument;
use App\Models\WorkerRevenueTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
            'statusUrl' => '/app/workers/status',
        ]);
    }

    public function status(Request $request): Response
    {
        [$from, $to, $selectedWorkerIds] = $this->reportFilters($request);

        $workers = Worker::query()
            ->with([
                'user:id,name,email',
                'documents' => fn ($query) => $query->orderBy('document_type'),
                'trainingRecords' => fn ($query) => $query->orderByDesc('completed_on')->orderBy('course_name'),
            ])
            ->withCount(['assignments', 'visits'])
            ->when(
                $selectedWorkerIds->isNotEmpty(),
                fn ($query) => $query->whereIn('id', $selectedWorkerIds->all())
            )
            ->orderByRaw("case when status = 'available' then 0 when status = 'assigned' then 1 when status = 'on_leave' then 2 else 3 end")
            ->orderBy('name')
            ->get();

        $workerIds = $workers->pluck('id')->values();
        $visits = $this->performanceVisits($from, $to, $workerIds);
        $availabilityBlocks = $this->availabilityBlocks($from, $to, $workerIds);
        $targets = $this->targetsForRange($from, $to, $workerIds);
        $rows = $this->workerPerformanceRows($workers, $visits, $targets);

        return Inertia::render('Workers/Status', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'selected_worker_ids' => $selectedWorkerIds->values(),
            ],
            'summary' => $this->workerPerformanceSummary($rows),
            'workers' => $rows->values(),
            'comparison' => $this->comparisonRows($rows, $selectedWorkerIds),
            'trend' => $this->workerTrend($visits, $workers, $from, $to),
            'attendanceCalendar' => $this->attendanceCalendar($workers, $visits, $availabilityBlocks, $from, $to),
            'workerOptions' => $this->workerOptions(),
            'backUrl' => '/app/workers',
        ]);
    }

    public function storeAvailabilityBlock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'date' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'status' => ['required', Rule::in(['unavailable', 'on_leave', 'training', 'available'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $block = WorkerAvailabilityBlock::create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'worker.availability_block.created',
            'auditable_type' => WorkerAvailabilityBlock::class,
            'auditable_id' => $block->id,
            'changes' => [
                'created' => [
                    'old' => null,
                    'new' => $block->only(['worker_id', 'date', 'starts_at', 'ends_at', 'status', 'reason']),
                ],
            ],
        ]);

        return back()->with('success', __('Worker availability updated.'));
    }

    public function show(Request $request, Worker $worker): Response
    {
        [$from, $to] = $this->reportFilters($request);

        $worker->load([
            'user:id,name,email',
            'documents' => fn ($query) => $query->orderBy('document_type'),
            'trainingRecords' => fn ($query) => $query->orderByDesc('completed_on')->orderBy('course_name'),
        ])->loadCount(['assignments', 'visits']);

        $visits = $this->performanceVisits($from, $to, collect([$worker->id]));
        $assignments = Assignment::query()
            ->with(['contract.customer', 'contract.site', 'contract.service', 'site', 'service'])
            ->where('worker_id', $worker->id)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get();
        $contractIds = $assignments->pluck('contract_id')
            ->merge($visits->pluck('contract_id'))
            ->filter()
            ->unique()
            ->values();
        $contracts = Contract::query()
            ->with(['customer', 'site', 'service'])
            ->whereIn('id', $contractIds)
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'paused' THEN 2 ELSE 3 END")
            ->orderBy('reference')
            ->get();
        $targets = $this->targetsForRange($from, $to, collect([$worker->id]));
        $allTargets = WorkerRevenueTarget::query()
            ->where('worker_id', $worker->id)
            ->orderByDesc('period_start')
            ->limit(12)
            ->get();
        $summary = $this->workerPerformanceRow($worker, $visits, $targets);

        return Inertia::render('Workers/Show', [
            'worker' => $this->serializeWorker($worker),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => Arr::except($summary, ['worker']),
            'contracts' => $this->workerContractRows($contracts, $assignments, $visits),
            'assignments' => $assignments->map(fn (Assignment $assignment): array => $this->serializeAssignment($assignment))->values(),
            'visits' => $visits->sortByDesc('scheduled_for')->map(fn (Visit $visit): array => $this->serializePerformanceVisit($visit))->values(),
            'targets' => $allTargets->map(fn (WorkerRevenueTarget $target): array => $this->serializeTarget($target))->values(),
            'trend' => $this->workerTrend($visits, collect([$worker]), $from, $to),
            'catalog' => $this->catalog(),
            'backUrl' => '/app/workers',
            'statusUrl' => '/app/workers/status',
            'targetStoreUrl' => "/app/workers/{$worker->id}/targets",
        ]);
    }

    public function storeTarget(Request $request, Worker $worker): RedirectResponse
    {
        if (! $request->has('target_sar') && $request->has('target_halalas')) {
            $request->merge([
                'target_sar' => $this->halalasToSarString((int) $request->input('target_halalas', 0)),
            ]);
        }

        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'target_sar' => ['required', 'numeric', 'min:0.01', 'max:9999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);

        $target = DB::transaction(function () use ($request, $worker, $validated): WorkerRevenueTarget {
            $target = WorkerRevenueTarget::create([
                'worker_id' => $worker->id,
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'target_halalas' => $this->sarToHalalas($validated['target_sar']),
            ]);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'worker.target.created',
                'auditable_type' => WorkerRevenueTarget::class,
                'auditable_id' => $target->id,
                'changes' => [
                    'created' => [
                        'old' => null,
                        'new' => $target->only(['worker_id', 'period_start', 'period_end', 'target_halalas']),
                    ],
                ],
            ]);

            return $target;
        });

        return redirect("/app/workers/{$worker->id}")->with('success', __('Worker target saved.'));
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
     * @return array{0: Carbon, 1: Carbon, 2: Collection<int, int>}
     */
    private function reportFilters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'workers' => ['array'],
            'workers.*' => ['integer', 'exists:workers,id'],
        ]);

        $from = Carbon::parse($validated['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? now()->endOfMonth()->toDateString())->endOfDay();
        $workerIds = collect($validated['workers'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return [$from, $to, $workerIds];
    }

    /**
     * @param  Collection<int, int>  $workerIds
     * @return Collection<int, Visit>
     */
    private function performanceVisits(Carbon $from, Carbon $to, Collection $workerIds): Collection
    {
        if ($workerIds->isEmpty()) {
            return collect();
        }

        return Visit::query()
            ->with(['contract.customer', 'contract.site', 'contract.service', 'site', 'service'])
            ->whereIn('worker_id', $workerIds->all())
            ->whereBetween('scheduled_for', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('scheduled_for')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $workerIds
     * @return Collection<int, WorkerAvailabilityBlock>
     */
    private function availabilityBlocks(Carbon $from, Carbon $to, Collection $workerIds): Collection
    {
        if ($workerIds->isEmpty()) {
            return collect();
        }

        return WorkerAvailabilityBlock::query()
            ->with('worker')
            ->whereIn('worker_id', $workerIds->all())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @param  Collection<int, Worker>  $workers
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, WorkerAvailabilityBlock>  $blocks
     * @return array<string, mixed>
     */
    private function attendanceCalendar(Collection $workers, Collection $visits, Collection $blocks, Carbon $from, Carbon $to): array
    {
        $days = [];
        $cursor = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $date = $cursor->toDateString();

            $days[] = [
                'date' => $date,
                'label' => $cursor->format('D, M j'),
                'workers' => $workers
                    ->map(fn (Worker $worker): array => $this->attendanceCalendarWorkerDay(
                        $worker,
                        $visits->filter(fn (Visit $visit): bool => (int) $visit->worker_id === (int) $worker->id && $visit->scheduled_for?->toDateString() === $date)->values(),
                        $blocks->filter(fn (WorkerAvailabilityBlock $block): bool => (int) $block->worker_id === (int) $worker->id && $this->availabilityBlockDate($block) === $date)->values(),
                    ))
                    ->values(),
            ];

            $cursor->addDay();
        }

        return [
            'summary' => [
                'scheduled_visits' => $visits->count(),
                'completed_visits' => $visits->where('status', 'completed')->count(),
                'missed_visits' => $visits->where('status', 'missed')->count(),
                'unavailable_blocks' => $blocks->where('status', '!=', 'available')->count(),
                'workers_count' => $workers->count(),
            ],
            'days' => $days,
        ];
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, WorkerAvailabilityBlock>  $blocks
     * @return array<string, mixed>
     */
    private function attendanceCalendarWorkerDay(Worker $worker, Collection $visits, Collection $blocks): array
    {
        return [
            'worker' => $this->serializeWorkerSummary($worker),
            'status' => $this->attendanceStatus($visits, $blocks),
            'scheduled_visits_count' => $visits->count(),
            'completed_visits_count' => $visits->where('status', 'completed')->count(),
            'missed_visits_count' => $visits->where('status', 'missed')->count(),
            'worked_minutes' => (int) $visits->where('status', 'completed')->sum(fn (Visit $visit): int => $this->visitWorkedMinutes($visit)),
            'visits' => $visits->map(fn (Visit $visit): array => $this->serializePerformanceVisit($visit))->values(),
            'blocks' => $blocks->map(fn (WorkerAvailabilityBlock $block): array => $this->serializeAvailabilityBlock($block))->values(),
        ];
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, WorkerAvailabilityBlock>  $blocks
     */
    private function attendanceStatus(Collection $visits, Collection $blocks): string
    {
        if ($visits->where('status', 'missed')->isNotEmpty()) {
            return 'missed';
        }

        if ($visits->where('status', 'completed')->isNotEmpty()) {
            return 'worked';
        }

        if ($visits->where('status', 'in_progress')->isNotEmpty()) {
            return 'in_progress';
        }

        if ($visits->isNotEmpty()) {
            return 'scheduled';
        }

        if ($blocks->where('status', '!=', 'available')->isNotEmpty()) {
            return 'unavailable';
        }

        return 'available';
    }

    /**
     * @param  Collection<int, int>  $workerIds
     * @return Collection<int, WorkerRevenueTarget>
     */
    private function targetsForRange(Carbon $from, Carbon $to, Collection $workerIds): Collection
    {
        if ($workerIds->isEmpty()) {
            return collect();
        }

        return WorkerRevenueTarget::query()
            ->whereIn('worker_id', $workerIds->all())
            ->whereDate('period_start', '<=', $to->toDateString())
            ->whereDate('period_end', '>=', $from->toDateString())
            ->orderBy('period_start')
            ->get();
    }

    /**
     * @param  Collection<int, Worker>  $workers
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, WorkerRevenueTarget>  $targets
     * @return Collection<int, array<string, mixed>>
     */
    private function workerPerformanceRows(Collection $workers, Collection $visits, Collection $targets): Collection
    {
        return $workers
            ->map(fn (Worker $worker): array => $this->workerPerformanceRow($worker, $visits, $targets))
            ->sortByDesc('actual_revenue_halalas')
            ->values();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, WorkerRevenueTarget>  $targets
     * @return array<string, mixed>
     */
    private function workerPerformanceRow(Worker $worker, Collection $visits, Collection $targets): array
    {
        $workerVisits = $visits->where('worker_id', $worker->id)->values();
        $completedVisits = $workerVisits->where('status', 'completed')->values();
        $workerTargets = $targets->where('worker_id', $worker->id)->values();
        $target = (int) $workerTargets->sum('target_halalas');
        $actualRevenue = (int) $completedVisits->sum(fn (Visit $visit): int => $this->actualRevenue($visit));
        $grossProfit = (int) $completedVisits->sum(fn (Visit $visit): int => $this->grossProfit($visit));
        $workedMinutes = (int) $completedVisits->sum(fn (Visit $visit): int => $this->visitWorkedMinutes($visit));
        $targetAttainment = $target > 0 ? (int) round(($actualRevenue / $target) * 100) : 0;

        return [
            'worker_id' => $worker->id,
            'worker' => $this->serializeWorkerSummary($worker),
            'target_halalas' => $target,
            'target_sar' => $this->halalasToSarString($target),
            'actual_revenue_halalas' => $actualRevenue,
            'actual_revenue_sar' => $this->halalasToSarString($actualRevenue),
            'gross_profit_halalas' => $grossProfit,
            'gross_profit_sar' => $this->halalasToSarString($grossProfit),
            'worked_minutes' => $workedMinutes,
            'scheduled_visits_count' => $workerVisits->count(),
            'completed_visits_count' => $completedVisits->count(),
            'missed_visits_count' => $workerVisits->where('status', 'missed')->count(),
            'overtime_minutes' => (int) $completedVisits->sum('overtime_minutes'),
            'billable_overtime_minutes' => (int) $completedVisits->sum('billable_overtime_minutes'),
            'billable_overtime_halalas' => (int) $completedVisits->sum('billable_overtime_halalas'),
            'target_attainment_percent' => $targetAttainment,
            'target_status' => $target === 0 ? 'no_target' : ($actualRevenue >= $target ? 'achieved' : 'behind'),
            'profit_per_hour_halalas' => $workedMinutes > 0 ? (int) round($grossProfit / ($workedMinutes / 60)) : 0,
            'contracts_count' => $workerVisits->pluck('contract_id')->filter()->unique()->count(),
            'sites_count' => $workerVisits->pluck('customer_site_id')->filter()->unique()->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function workerPerformanceSummary(Collection $rows): array
    {
        $targetTotal = (int) $rows->sum('target_halalas');
        $actualTotal = (int) $rows->sum('actual_revenue_halalas');
        $grossTotal = (int) $rows->sum('gross_profit_halalas');

        return [
            'workers_count' => $rows->count(),
            'target_total_halalas' => $targetTotal,
            'target_total_sar' => $this->halalasToSarString($targetTotal),
            'actual_revenue_total_halalas' => $actualTotal,
            'actual_revenue_total_sar' => $this->halalasToSarString($actualTotal),
            'gross_profit_total_halalas' => $grossTotal,
            'gross_profit_total_sar' => $this->halalasToSarString($grossTotal),
            'worked_minutes' => (int) $rows->sum('worked_minutes'),
            'completed_visits_count' => (int) $rows->sum('completed_visits_count'),
            'overtime_minutes' => (int) $rows->sum('overtime_minutes'),
            'achieved_count' => $rows->where('target_status', 'achieved')->count(),
            'behind_count' => $rows->where('target_status', 'behind')->count(),
            'no_target_count' => $rows->where('target_status', 'no_target')->count(),
            'target_attainment_percent' => $targetTotal > 0 ? (int) round(($actualTotal / $targetTotal) * 100) : 0,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, int>  $selectedWorkerIds
     * @return Collection<int, array<string, mixed>>
     */
    private function comparisonRows(Collection $rows, Collection $selectedWorkerIds): Collection
    {
        if ($selectedWorkerIds->isEmpty()) {
            return $rows->take(3)->values();
        }

        return $selectedWorkerIds
            ->map(fn (int $workerId): ?array => $rows->first(fn (array $row): bool => (int) data_get($row, 'worker.id') === $workerId))
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, Worker>  $workers
     * @return list<array<string, mixed>>
     */
    private function workerTrend(Collection $visits, Collection $workers, Carbon $from, Carbon $to): array
    {
        $rows = [];
        $cursor = $from->copy()->startOfMonth();
        $last = $to->copy()->startOfMonth();

        while ($cursor->lte($last)) {
            $month = $cursor->format('Y-m');
            $monthVisits = $visits
                ->filter(fn (Visit $visit): bool => $visit->scheduled_for?->format('Y-m') === $month)
                ->values();
            $completedVisits = $monthVisits->where('status', 'completed')->values();

            $rows[] = [
                'month' => $month,
                'label' => $cursor->format('M Y'),
                'actual_revenue_halalas' => (int) $completedVisits->sum(fn (Visit $visit): int => $this->actualRevenue($visit)),
                'gross_profit_halalas' => (int) $completedVisits->sum(fn (Visit $visit): int => $this->grossProfit($visit)),
                'worked_minutes' => (int) $completedVisits->sum(fn (Visit $visit): int => $this->visitWorkedMinutes($visit)),
                'completed_visits_count' => $completedVisits->count(),
                'workers' => $workers->map(function (Worker $worker) use ($monthVisits): array {
                    $workerVisits = $monthVisits->where('worker_id', $worker->id)->where('status', 'completed')->values();

                    return [
                        'worker_id' => $worker->id,
                        'name' => $worker->name,
                        'actual_revenue_halalas' => (int) $workerVisits->sum(fn (Visit $visit): int => $this->actualRevenue($visit)),
                        'gross_profit_halalas' => (int) $workerVisits->sum(fn (Visit $visit): int => $this->grossProfit($visit)),
                        'worked_minutes' => (int) $workerVisits->sum(fn (Visit $visit): int => $this->visitWorkedMinutes($visit)),
                    ];
                })->values(),
            ];

            $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @param  Collection<int, Assignment>  $assignments
     * @param  Collection<int, Visit>  $visits
     * @return Collection<int, array<string, mixed>>
     */
    private function workerContractRows(Collection $contracts, Collection $assignments, Collection $visits): Collection
    {
        return $contracts->map(function (Contract $contract) use ($assignments, $visits): array {
            $contractAssignments = $assignments->where('contract_id', $contract->id)->values();
            $contractVisits = $visits->where('contract_id', $contract->id)->values();
            $completedVisits = $contractVisits->where('status', 'completed')->values();

            return [
                'id' => $contract->id,
                'reference' => $contract->reference,
                'status' => $contract->status,
                'customer' => $contract->customer ? [
                    'id' => $contract->customer->id,
                    'name' => $contract->customer->name,
                ] : null,
                'site' => $contract->site ? [
                    'id' => $contract->site->id,
                    'name' => $contract->site->name,
                    'city' => $contract->site->city,
                    'district' => $contract->site->district,
                ] : null,
                'service' => $contract->service ? [
                    'id' => $contract->service->id,
                    'title' => $contract->service->title,
                ] : null,
                'assignments_count' => $contractAssignments->count(),
                'visits_count' => $contractVisits->count(),
                'completed_visits_count' => $completedVisits->count(),
                'worked_minutes' => (int) $completedVisits->sum(fn (Visit $visit): int => $this->visitWorkedMinutes($visit)),
                'actual_revenue_halalas' => (int) $completedVisits->sum(fn (Visit $visit): int => $this->actualRevenue($visit)),
                'gross_profit_halalas' => (int) $completedVisits->sum(fn (Visit $visit): int => $this->grossProfit($visit)),
                'detail_url' => "/app/contracts/{$contract->id}",
            ];
        })->values();
    }

    /**
     * @return list<array{id: int, name: string, employee_code: string|null, status: string, job_role: string}>
     */
    private function workerOptions(): array
    {
        return Worker::query()
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'status', 'job_role'])
            ->map(fn (Worker $worker): array => $this->serializeWorkerSummary($worker))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWorkerSummary(Worker $worker): array
    {
        return [
            'id' => $worker->id,
            'name' => $worker->name,
            'employee_code' => $worker->employee_code,
            'status' => $worker->status,
            'job_role' => $worker->job_role,
            'detail_url' => "/app/workers/{$worker->id}",
            'edit_url' => "/app/workers/{$worker->id}/edit",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAssignment(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'weekday' => $assignment->weekday,
            'starts_at' => substr((string) $assignment->starts_at, 0, 5),
            'ends_at' => substr((string) $assignment->ends_at, 0, 5),
            'share_percent' => $assignment->share_percent,
            'status' => $assignment->status,
            'task_instructions' => $assignment->task_instructions ?? [],
            'contract' => $assignment->contract ? [
                'id' => $assignment->contract->id,
                'reference' => $assignment->contract->reference,
                'customer' => $assignment->contract->customer ? [
                    'id' => $assignment->contract->customer->id,
                    'name' => $assignment->contract->customer->name,
                ] : null,
            ] : null,
            'site' => $assignment->site ? [
                'id' => $assignment->site->id,
                'name' => $assignment->site->name,
                'city' => $assignment->site->city,
                'district' => $assignment->site->district,
            ] : null,
            'service' => $assignment->service ? [
                'id' => $assignment->service->id,
                'title' => $assignment->service->title,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePerformanceVisit(Visit $visit): array
    {
        return [
            'id' => $visit->id,
            'scheduled_for' => $visit->scheduled_for?->toDateString(),
            'starts_at' => substr((string) $visit->starts_at, 0, 5),
            'ends_at' => substr((string) $visit->ends_at, 0, 5),
            'status' => $visit->status,
            'actual_minutes' => (int) $visit->actual_minutes,
            'planned_minutes' => (int) $visit->planned_minutes,
            'overtime_minutes' => (int) $visit->overtime_minutes,
            'billable_overtime_minutes' => (int) $visit->billable_overtime_minutes,
            'actual_revenue_halalas' => $this->actualRevenue($visit),
            'gross_profit_halalas' => $this->grossProfit($visit),
            'contract' => $visit->contract ? [
                'id' => $visit->contract->id,
                'reference' => $visit->contract->reference,
                'customer' => $visit->contract->customer ? [
                    'id' => $visit->contract->customer->id,
                    'name' => $visit->contract->customer->name,
                ] : null,
            ] : null,
            'site' => $visit->site ? [
                'id' => $visit->site->id,
                'name' => $visit->site->name,
                'city' => $visit->site->city,
                'district' => $visit->site->district,
            ] : null,
            'service' => $visit->service ? [
                'id' => $visit->service->id,
                'title' => $visit->service->title,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAvailabilityBlock(WorkerAvailabilityBlock $block): array
    {
        return [
            'id' => $block->id,
            'worker_id' => $block->worker_id,
            'date' => $this->availabilityBlockDate($block),
            'starts_at' => $block->starts_at ? substr((string) $block->starts_at, 0, 5) : null,
            'ends_at' => $block->ends_at ? substr((string) $block->ends_at, 0, 5) : null,
            'status' => $block->status,
            'reason' => $block->reason,
        ];
    }

    private function availabilityBlockDate(WorkerAvailabilityBlock $block): ?string
    {
        return $block->date ? Carbon::parse($block->date)->toDateString() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTarget(WorkerRevenueTarget $target): array
    {
        return [
            'id' => $target->id,
            'worker_id' => $target->worker_id,
            'period_start' => $target->period_start?->toDateString(),
            'period_end' => $target->period_end?->toDateString(),
            'target_halalas' => $target->target_halalas,
            'target_sar' => $this->halalasToSarString($target->target_halalas),
        ];
    }

    private function actualRevenue(Visit $visit): int
    {
        return (int) $visit->planned_revenue_halalas + (int) $visit->billable_overtime_halalas;
    }

    private function grossProfit(Visit $visit): int
    {
        return (int) $visit->gross_profit_halalas;
    }

    private function visitWorkedMinutes(Visit $visit): int
    {
        if ((int) $visit->actual_minutes > 0) {
            return (int) $visit->actual_minutes;
        }

        if ($visit->checked_in_at && $visit->checked_out_at) {
            return max(0, $visit->checked_in_at->diffInMinutes($visit->checked_out_at));
        }

        return $this->durationMinutes($visit->starts_at, $visit->ends_at);
    }

    private function durationMinutes(?string $startsAt, ?string $endsAt): int
    {
        if (! $startsAt || ! $endsAt) {
            return 0;
        }

        $start = Carbon::createFromFormat('H:i', substr($startsAt, 0, 5));
        $end = Carbon::createFromFormat('H:i', substr($endsAt, 0, 5));

        return max(0, $start->diffInMinutes($end));
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
            'detail_url' => "/app/workers/{$worker->id}",
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
