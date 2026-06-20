<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSarMoney;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\BookingRequest;
use App\Models\ChecklistItem;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Visit;
use App\Models\Worker;
use App\Services\Operations\ScheduleGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    use HandlesSarMoney;

    public function index(Request $request): Response
    {
        $date = Carbon::parse($request->query('date', now()->toDateString()));
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        $filters = [
            'date' => $date->toDateString(),
            'week_start' => $weekStart->toDateString(),
            'worker_id' => $request->query('worker_id'),
            'customer_id' => $request->query('customer_id'),
            'customer_site_id' => $request->query('customer_site_id'),
            'status' => $request->query('status'),
        ];

        $weeklyVisits = $this->visitQuery($filters)
            ->whereBetween('scheduled_for', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('scheduled_for')
            ->orderBy('starts_at')
            ->get();
        $noticeEnd = $date->copy()->addDay();
        $noticeVisits = $this->visitQuery($filters)
            ->whereBetween('scheduled_for', [$date->toDateString(), $noticeEnd->toDateString()])
            ->orderBy('scheduled_for')
            ->orderBy('starts_at')
            ->get();

        $dailyVisits = $weeklyVisits
            ->filter(fn (Visit $visit): bool => $visit->scheduled_for?->toDateString() === $date->toDateString())
            ->sortBy(fn (Visit $visit): string => $this->visitAttentionPriority($visit).'|'.$this->time($visit->starts_at))
            ->values();

        $assignments = $this->assignmentQuery($filters)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get();
        $assignmentFollowUps = $this->assignmentFollowUps($weekStart, $weekEnd);

        return Inertia::render('Operations/Index', [
            'date' => $date->toDateString(),
            'weekStart' => $weekStart->toDateString(),
            'filters' => $filters,
            'visits' => $dailyVisits->map(fn (Visit $visit): array => $this->serializeVisit($visit))->values(),
            'assignments' => $assignments->map(fn (Assignment $assignment): array => $this->serializeAssignment($assignment))->values(),
            'calendarDays' => $this->calendarDays($weekStart, $weeklyVisits, $assignments),
            'availabilityBoard' => $this->availabilityBoard($date, $dailyVisits, $assignments),
            'workload' => $this->workload($assignments, $weeklyVisits),
            'dailyControl' => $this->dailyControl($date, $dailyVisits),
            'assignmentFollowUps' => $assignmentFollowUps,
            'contractActionBoard' => $this->contractActionBoard($date, $noticeVisits, $assignments, $assignmentFollowUps),
            'paymentAttention' => $this->paymentAttention($date, $filters),
            'bookingRequests' => $this->bookingRequestsBoard(),
            'serviceRequests' => $this->serviceRequestsBoard(),
            'metrics' => [
                'visitsToday' => $dailyVisits->count(),
                'weeklyVisits' => $weeklyVisits->count(),
                'workersScheduled' => $assignments->pluck('worker_id')->unique()->count(),
                'completedThisWeek' => $weeklyVisits->where('status', 'completed')->count(),
                'lateVisits' => $dailyVisits->filter(fn (Visit $visit): bool => $this->isLate($visit))->count(),
                'missedVisits' => $dailyVisits->where('status', 'missed')->count(),
                'openIssues' => $dailyVisits->where('status', 'issue_reported')->count(),
                'awaitingAcknowledgement' => $dailyVisits->filter(fn (Visit $visit): bool => $this->requiresAcknowledgement($visit))->count(),
                'pendingOvertime' => $dailyVisits->where('overtime_status', 'pending_approval')->count(),
            ],
            'workerOptions' => $this->workerOptions(),
            'customerOptions' => $this->customerOptions(),
            'siteOptions' => $this->siteOptions(),
            'statusOptions' => $this->statusOptions(),
            'overtimeStatuses' => $this->overtimeStatuses(),
            'weekdayOptions' => $this->weekdayOptions(),
        ]);
    }

    public function storeAssignment(Request $request, ScheduleGenerator $schedule): RedirectResponse
    {
        $validated = $request->validate([
            'contract_id' => ['required', 'integer', 'exists:contracts,id'],
            'customer_site_id' => ['required', 'integer', 'exists:customer_sites,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'share_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::in(array_column($this->assignmentStatuses(), 'key'))],
            'team_role' => ['nullable', Rule::in(['main', 'support'])],
            'task_instructions' => ['array', 'max:30'],
            'task_instructions.*.label' => ['required', 'string', 'max:180'],
            'task_instructions.*.is_required' => ['nullable', 'boolean'],
        ]);
        $validated['team_role'] = $validated['team_role'] ?? 'main';
        $validated['task_instructions'] = $this->normalizeTaskInstructions($validated['task_instructions'] ?? []);

        $contract = Contract::query()->findOrFail($validated['contract_id']);
        $errors = [
            ...$this->contractAssignmentErrors($contract, $validated),
            ...$schedule->availabilityErrors(
                (int) $validated['worker_id'],
                (int) $validated['weekday'],
                (string) $validated['starts_at'],
                (string) $validated['ends_at'],
            ),
        ];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $assignment = $schedule->createAssignment($validated);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'assignment.created',
            'auditable_type' => Assignment::class,
            'auditable_id' => $assignment->id,
            'changes' => [
                'created' => [
                    'old' => null,
                    'new' => $this->serializeAssignment($assignment->load(['worker', 'site.customer', 'service', 'contract.customer'])),
                ],
            ],
        ]);

        return back()->with('success', __('Assignment created.'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     * @return list<array{label: string, is_required: bool}>
     */
    private function normalizeTaskInstructions(array $tasks): array
    {
        return collect($tasks)
            ->map(function (array $task): ?array {
                $label = trim((string) ($task['label'] ?? ''));

                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'is_required' => (bool) ($task['is_required'] ?? true),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function generateVisits(Request $request, ScheduleGenerator $schedule): RedirectResponse
    {
        $validated = $request->validate([
            'contract_id' => ['required', 'integer', 'exists:contracts,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);

        if ($from->diffInDays($to) > 90) {
            throw ValidationException::withMessages([
                'to' => __('Visit generation is limited to 90 days at a time.'),
            ]);
        }

        $contract = Contract::query()->findOrFail($validated['contract_id']);
        $visits = $schedule->generateVisits($contract, $from, $to);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'visits.generated',
            'auditable_type' => Contract::class,
            'auditable_id' => $contract->id,
            'changes' => [
                'range' => [
                    'old' => null,
                    'new' => [
                        'from' => $from->toDateString(),
                        'to' => $to->toDateString(),
                        'visits' => $visits->count(),
                    ],
                ],
            ],
        ]);

        return back()->with('success', __('Generated :count visits.', ['count' => $visits->count()]));
    }

    public function checkIn(Visit $visit): RedirectResponse
    {
        $visit->forceFill([
            'status' => 'in_progress',
            'checked_in_at' => now(),
        ])->save();

        return back();
    }

    public function checkOut(Visit $visit): RedirectResponse
    {
        $visit->forceFill([
            'status' => 'completed',
            'checked_out_at' => now(),
            'completion_review_status' => 'pending_review',
            'completion_reviewed_at' => null,
            'completion_reviewed_by' => null,
            'completion_review_note' => null,
        ])->save();

        return back();
    }

    public function updateExecution(Request $request, Visit $visit): RedirectResponse
    {
        $this->normalizeExecutionRequest($request);

        $validated = $request->validate([
            'checked_in_at' => ['required', 'date'],
            'checked_out_at' => ['required', 'date', 'after:checked_in_at'],
            'actual_minutes' => ['nullable', 'integer', 'min:0', 'max:2880'],
            'material_cost_sar' => $this->sarMoneyRules(),
            'materials_used' => ['array', 'max:20'],
            'materials_used.*.name' => ['required', 'string', 'max:160'],
            'materials_used.*.quantity' => ['nullable', 'string', 'max:80'],
            'materials_used.*.cost_sar' => $this->sarMoneyRules(),
            'overtime_status' => ['required', Rule::in(array_column($this->overtimeStatuses(), 'key'))],
            'execution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $visit->loadMissing(['contract', 'service', 'worker']);

        $old = $visit->only($this->executionFields());
        $plannedMinutes = $this->durationMinutes($visit->starts_at, $visit->ends_at);
        $actualMinutes = (int) ($validated['actual_minutes'] ?? $this->actualMinutes($validated['checked_in_at'], $validated['checked_out_at']));
        $varianceMinutes = $actualMinutes - $plannedMinutes;
        $overtimeMinutes = max(0, $varianceMinutes);
        $overtimeStatus = $overtimeMinutes > 0
            ? ($validated['overtime_status'] === 'not_required' ? 'pending_approval' : $validated['overtime_status'])
            : 'not_required';
        $billableOvertimeMinutes = $overtimeStatus === 'approved' ? $overtimeMinutes : 0;
        $materialCostHalalas = $this->sarToHalalas($validated['material_cost_sar']);
        $plannedRevenueHalalas = $this->plannedVisitRevenue($visit);
        $laborCostHalalas = $this->laborCost($visit->worker, $actualMinutes);
        $billableOvertimeHalalas = $this->billableOvertime($visit, $billableOvertimeMinutes);
        $grossProfitHalalas = $plannedRevenueHalalas + $billableOvertimeHalalas - $laborCostHalalas - $materialCostHalalas;

        $visit->forceFill([
            'status' => 'completed',
            'checked_in_at' => $validated['checked_in_at'],
            'checked_out_at' => $validated['checked_out_at'],
            'planned_minutes' => $plannedMinutes,
            'actual_minutes' => $actualMinutes,
            'variance_minutes' => $varianceMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'billable_overtime_minutes' => $billableOvertimeMinutes,
            'overtime_status' => $overtimeStatus,
            'materials_used' => $this->normalizeMaterialsUsed($validated['materials_used'] ?? []),
            'planned_revenue_halalas' => $plannedRevenueHalalas,
            'labor_cost_halalas' => $laborCostHalalas,
            'material_cost_halalas' => $materialCostHalalas,
            'billable_overtime_halalas' => $billableOvertimeHalalas,
            'gross_profit_halalas' => $grossProfitHalalas,
            'execution_notes' => $validated['execution_notes'] ?? null,
            'completion_review_status' => 'pending_review',
            'completion_reviewed_at' => null,
            'completion_reviewed_by' => null,
            'completion_review_note' => null,
        ])->save();

        $this->audit($request, 'visit.execution_updated', $visit, $old, $visit->only($this->executionFields()));

        return back()->with('success', __('Visit execution updated.'));
    }

    public function completeChecklist(Request $request, Visit $visit): RedirectResponse
    {
        $itemIds = collect($request->input('items', []))->map(fn ($id) => (int) $id)->all();

        $visit->checklistItems()
            ->whereIn('id', $itemIds)
            ->update([
                'status' => 'done',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        return back();
    }

    public function reportIssue(Request $request, Visit $visit): RedirectResponse
    {
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

        $this->audit($request, 'visit.issue_reported', $visit, $old, $visit->only(['status', 'issue_note', 'photos']));

        return back()->with('success', __('Visit issue recorded.'));
    }

    public function markMissed(Request $request, Visit $visit): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $old = $visit->only(['status', 'issue_note']);

        $visit->forceFill([
            'status' => 'missed',
            'issue_note' => $validated['reason'],
        ])->save();

        $this->audit($request, 'visit.marked_missed', $visit, $old, $visit->only(['status', 'issue_note']));

        return back()->with('success', __('Visit marked missed.'));
    }

    public function acknowledge(Request $request, Visit $visit): RedirectResponse
    {
        $validated = $request->validate([
            'supervisor_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->applyCompletionReview(
            $request,
            $visit,
            'approved',
            $validated['supervisor_note'] ?? null,
            null,
            null,
            false,
            'visit.acknowledged',
        );

        return back()->with('success', __('Visit acknowledged.'));
    }

    public function reviewCompletion(Request $request, Visit $visit): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'needs_correction'])],
            'supervisor_note' => ['nullable', 'string', 'max:1000'],
            'quality_status' => ['nullable', Rule::in(array_column($this->qualityStatuses(), 'key'))],
            'quality_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'create_follow_up' => ['nullable', 'boolean'],
        ]);

        $this->applyCompletionReview(
            $request,
            $visit,
            $validated['decision'],
            $validated['supervisor_note'] ?? null,
            $validated['quality_status'] ?? null,
            $validated['quality_score'] ?? null,
            (bool) ($validated['create_follow_up'] ?? false),
            'visit.completion_reviewed',
        );

        return back()->with('success', __('Visit completion reviewed.'));
    }

    public function updateChecklistItem(Request $request, ChecklistItem $checklistItem): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'done', 'blocked'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo_path' => ['nullable', 'string', 'max:255'],
        ]);

        $old = $checklistItem->only(['status', 'notes', 'photo_path', 'completed_at']);

        $checklistItem->forceFill([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'photo_path' => $validated['photo_path'] ?? null,
            'completed_at' => $validated['status'] === 'done' ? now() : null,
        ])->save();

        $this->audit($request, 'checklist_item.updated', $checklistItem, $old, $checklistItem->only(['status', 'notes', 'photo_path', 'completed_at']));

        return back()->with('success', __('Checklist evidence updated.'));
    }

    private function applyCompletionReview(
        Request $request,
        Visit $visit,
        string $decision,
        ?string $note,
        ?string $qualityStatus,
        ?int $qualityScore,
        bool $createFollowUp,
        string $auditAction
    ): void {
        if ($visit->status !== 'completed') {
            throw ValidationException::withMessages([
                'decision' => __('Only completed visits can be reviewed.'),
            ]);
        }

        $old = $visit->only($this->completionReviewFields());
        $reviewedAt = now();
        $reviewerId = $request->user()?->id;
        $qualityStatus ??= $decision === 'approved' ? 'passed' : 'needs_correction';
        $followUpRequired = $createFollowUp || $qualityStatus === 'customer_follow_up';

        $visit->forceFill([
            'completion_review_status' => $decision,
            'completion_reviewed_at' => $reviewedAt,
            'completion_reviewed_by' => $reviewerId,
            'completion_review_note' => $note,
            'supervisor_acknowledged_at' => $decision === 'approved' ? $reviewedAt : null,
            'supervisor_acknowledged_by' => $decision === 'approved' ? $reviewerId : null,
            'supervisor_note' => $note,
            'quality_status' => $qualityStatus,
            'quality_score' => $qualityScore,
            'quality_reviewed_at' => $reviewedAt,
            'quality_reviewed_by' => $reviewerId,
            'quality_notes' => $note,
            'quality_follow_up_required' => $followUpRequired,
        ])->save();

        if ($followUpRequired) {
            $this->createQualityFollowUp($visit, 'high', 'Quality follow-up required', $note);
        }

        $this->audit($request, $auditAction, $visit, $old, $visit->only($this->completionReviewFields()));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function visitQuery(array $filters)
    {
        return Visit::query()
            ->with(['contract', 'worker', 'site.customer', 'service', 'checklistItems', 'acknowledgedBy', 'completionReviewedBy', 'qualityReviewedBy', 'feedback'])
            ->when($filters['worker_id'] ?? null, fn ($query, $workerId) => $query->where('worker_id', $workerId))
            ->when($filters['customer_site_id'] ?? null, fn ($query, $siteId) => $query->where('customer_site_id', $siteId))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->whereHas('site', fn ($siteQuery) => $siteQuery->where('customer_id', $customerId)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function assignmentQuery(array $filters)
    {
        return Assignment::query()
            ->with(['worker', 'site.customer', 'service', 'contract.customer'])
            ->when($filters['worker_id'] ?? null, fn ($query, $workerId) => $query->where('worker_id', $workerId))
            ->when($filters['customer_site_id'] ?? null, fn ($query, $siteId) => $query->where('customer_site_id', $siteId))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->whereHas('site', fn ($siteQuery) => $siteQuery->where('customer_id', $customerId)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
    }

    /**
     * @param  Collection<int, Visit>  $dailyVisits
     * @return array<string, mixed>
     */
    private function dailyControl(Carbon $date, Collection $dailyVisits): array
    {
        $alerts = $dailyVisits
            ->map(fn (Visit $visit): ?array => $this->dailyAlert($visit))
            ->filter()
            ->values();
        $awaitingAcknowledgement = $dailyVisits
            ->filter(fn (Visit $visit): bool => $this->requiresAcknowledgement($visit))
            ->map(fn (Visit $visit): array => $this->serializeVisit($visit))
            ->values();

        return [
            'date' => $date->toDateString(),
            'alerts' => $alerts,
            'awaitingAcknowledgement' => $awaitingAcknowledgement,
            'evidence' => [
                'open_issues' => $dailyVisits->where('status', 'issue_reported')->count(),
                'photo_count' => $dailyVisits->sum(fn (Visit $visit): int => count($visit->photos ?? [])),
                'checklist_done' => $dailyVisits->sum(fn (Visit $visit): int => $visit->checklistItems->where('status', 'done')->count()),
                'checklist_pending' => $dailyVisits->sum(fn (Visit $visit): int => $visit->checklistItems->where('status', 'pending')->count()),
                'pending_reviews' => $dailyVisits->filter(fn (Visit $visit): bool => $this->requiresAcknowledgement($visit))->count(),
                'approved_reviews' => $dailyVisits->filter(fn (Visit $visit): bool => $this->completionReviewStatus($visit) === 'approved')->count(),
                'corrections_requested' => $dailyVisits->filter(fn (Visit $visit): bool => $this->completionReviewStatus($visit) === 'needs_correction')->count(),
                'quality_reviews' => $dailyVisits->filter(fn (Visit $visit): bool => $visit->quality_reviewed_at !== null || filled($visit->quality_status))->count(),
                'quality_passed' => $dailyVisits->where('quality_status', 'passed')->count(),
                'quality_failed' => $dailyVisits->filter(fn (Visit $visit): bool => in_array($visit->quality_status, ['failed', 'needs_correction'], true))->count(),
                'quality_follow_ups' => $dailyVisits->filter(fn (Visit $visit): bool => $visit->quality_follow_up_required || $visit->quality_status === 'customer_follow_up')->count(),
            ],
            'costControl' => [
                'planned_revenue_halalas' => $dailyVisits->sum('planned_revenue_halalas'),
                'labor_cost_halalas' => $dailyVisits->sum('labor_cost_halalas'),
                'material_cost_halalas' => $dailyVisits->sum('material_cost_halalas'),
                'billable_overtime_halalas' => $dailyVisits->sum('billable_overtime_halalas'),
                'gross_profit_halalas' => $dailyVisits->sum('gross_profit_halalas'),
                'overtime_minutes' => $dailyVisits->sum('overtime_minutes'),
                'pending_overtime' => $dailyVisits->where('overtime_status', 'pending_approval')->count(),
            ],
        ];
    }

    private function assignmentFollowUps(Carbon $weekStart, Carbon $weekEnd): array
    {
        $contracts = Contract::query()
            ->with([
                'customer',
                'site.customer',
                'service',
                'assignments' => fn ($query) => $query
                    ->with(['worker', 'service'])
                    ->orderBy('weekday')
                    ->orderBy('starts_at'),
            ])
            ->where('status', 'active')
            ->where('visits_per_week', '>', 0)
            ->whereDate('starts_on', '<=', $weekEnd->toDateString())
            ->where(function ($query) use ($weekStart): void {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $weekStart->toDateString());
            })
            ->orderBy('starts_on')
            ->orderBy('reference')
            ->get();

        $items = $contracts
            ->map(fn (Contract $contract): array => $this->assignmentFollowUpItem($contract, $weekStart, $weekEnd))
            ->sortBy([
                ['open_items', 'desc'],
                ['next_due_on', 'asc'],
                ['contract.reference', 'asc'],
            ])
            ->values();

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'summary' => [
                'contracts_due' => $items->count(),
                'open_items' => $items->sum('open_items'),
                'missing_visit_slots' => $items->sum('missing_visit_slots'),
                'missing_main_worker_slots' => $items->sum('missing_main_worker_slots'),
                'understaffed_slots' => $items->sum('understaffed_slots'),
                'ready_contracts' => $items->where('open_items', 0)->count(),
            ],
            'items' => $items->all(),
        ];
    }

    /**
     * @param  Collection<int, Visit>  $noticeVisits
     * @param  Collection<int, Assignment>  $assignments
     * @param  array<string, mixed>  $assignmentFollowUps
     * @return array<string, mixed>
     */
    private function contractActionBoard(Carbon $date, Collection $noticeVisits, Collection $assignments, array $assignmentFollowUps): array
    {
        $windowEnd = $date->copy()->addDay();
        $noticeDates = collect([
            $date->toDateString() => 'today',
            $windowEnd->toDateString() => 'tomorrow',
        ]);
        $followUpsByContract = collect($assignmentFollowUps['items'] ?? [])
            ->keyBy(fn (array $item): int => (int) data_get($item, 'contract.id'));
        $seeds = collect();

        foreach ($noticeVisits as $visit) {
            if ($visit->contract_id && $visit->scheduled_for) {
                $seeds->push([
                    'contract_id' => (int) $visit->contract_id,
                    'date' => $visit->scheduled_for->toDateString(),
                ]);
            }
        }

        foreach ($assignments as $assignment) {
            $assignmentDate = $this->assignmentNoticeDate($assignment, $date, $windowEnd);

            if ($assignment->contract_id && $assignmentDate) {
                $seeds->push([
                    'contract_id' => (int) $assignment->contract_id,
                    'date' => $assignmentDate->toDateString(),
                ]);
            }
        }

        foreach ($followUpsByContract as $contractId => $followUp) {
            $nextDueOn = (string) ($followUp['next_due_on'] ?? '');

            if ($noticeDates->has($nextDueOn)) {
                $seeds->push([
                    'contract_id' => (int) $contractId,
                    'date' => $nextDueOn,
                ]);
            }
        }

        $seeds = $seeds
            ->filter(fn (array $seed): bool => $noticeDates->has($seed['date']))
            ->unique(fn (array $seed): string => $seed['contract_id'].'|'.$seed['date'])
            ->values();
        $contractIds = $seeds->pluck('contract_id')->unique()->values();
        $contracts = $contractIds->isEmpty()
            ? collect()
            : Contract::query()
                ->with(['customer', 'site.customer', 'service'])
                ->whereIn('id', $contractIds->all())
                ->get()
                ->keyBy('id');

        $items = $seeds
            ->map(function (array $seed) use ($noticeVisits, $assignments, $contracts, $followUpsByContract, $noticeDates, $date, $windowEnd): ?array {
                /** @var Contract|null $contract */
                $contract = $contracts->get($seed['contract_id']);

                if (! $contract) {
                    return null;
                }

                $noticeDate = (string) $seed['date'];
                $dayVisits = $noticeVisits
                    ->filter(fn (Visit $visit): bool => (int) $visit->contract_id === (int) $contract->id
                        && $visit->scheduled_for?->toDateString() === $noticeDate)
                    ->values();
                $dayAssignments = $assignments
                    ->filter(function (Assignment $assignment) use ($contract, $noticeDate, $date, $windowEnd): bool {
                        $assignmentDate = $this->assignmentNoticeDate($assignment, $date, $windowEnd);

                        return (int) $assignment->contract_id === (int) $contract->id
                            && $assignmentDate?->toDateString() === $noticeDate;
                    })
                    ->values();
                $followUp = $followUpsByContract->get($contract->id);
                $startsAt = $this->firstTime($dayVisits->pluck('starts_at')->merge($dayAssignments->pluck('starts_at')));
                $endsAt = $this->lastTime($dayVisits->pluck('ends_at')->merge($dayAssignments->pluck('ends_at')));
                $workers = $dayVisits
                    ->pluck('worker')
                    ->merge($dayAssignments->pluck('worker'))
                    ->filter()
                    ->unique('id')
                    ->map(fn (Worker $worker): array => $this->serializeWorker($worker))
                    ->values()
                    ->all();
                $openItems = (int) ($followUp['open_items'] ?? 0);

                return [
                    'notice_date' => $noticeDate,
                    'notice_key' => $noticeDates->get($noticeDate),
                    'severity' => $this->contractActionSeverity($followUp),
                    'contract' => $this->serializeContractSummary($contract, 'assignments'),
                    'action_url' => "/app/contracts/{$contract->id}?panel=assignments",
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'visits_count' => $dayVisits->count(),
                    'assignment_slots_count' => $dayAssignments
                        ->unique(fn (Assignment $assignment): string => $this->assignmentSlotKey($assignment))
                        ->count(),
                    'assigned_workers_count' => count($workers),
                    'task_count' => $dayAssignments->sum(fn (Assignment $assignment): int => count($assignment->task_instructions ?? []))
                        + $dayVisits->sum(fn (Visit $visit): int => $visit->checklistItems->count()),
                    'open_items' => $openItems,
                    'missing_visit_slots' => (int) ($followUp['missing_visit_slots'] ?? 0),
                    'missing_main_worker_slots' => (int) ($followUp['missing_main_worker_slots'] ?? 0),
                    'understaffed_slots' => (int) ($followUp['understaffed_slots'] ?? 0),
                    'follow_ups' => $followUp['follow_ups'] ?? [],
                    'workers' => $workers,
                ];
            })
            ->filter()
            ->sortBy(fn (array $item): string => $item['notice_date'].'|'.($item['starts_at'] ?? '99:99').'|'.$item['contract']['reference'])
            ->values();

        return [
            'window_start' => $date->toDateString(),
            'window_end' => $windowEnd->toDateString(),
            'summary' => [
                'total_contracts' => $items->count(),
                'today_count' => $items->where('notice_key', 'today')->count(),
                'tomorrow_count' => $items->where('notice_key', 'tomorrow')->count(),
                'open_items' => $items->sum('open_items'),
            ],
            'items' => $items->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function paymentAttention(Carbon $date, array $filters): array
    {
        $windowEnd = $date->copy()->addDay();
        $invoices = Invoice::query()
            ->with(['contract.customer', 'contract.site.customer', 'contract.service', 'customer', 'creditNotes'])
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->whereDate('due_date', '<=', $windowEnd->toDateString())
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['customer_site_id'] ?? null, fn ($query, $siteId) => $query->where('customer_site_id', $siteId))
            ->orderBy('due_date')
            ->orderBy('number')
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->balance_halalas > 0)
            ->values();
        $items = $invoices
            ->map(fn (Invoice $invoice): array => $this->paymentAttentionItem($invoice, $date, $windowEnd))
            ->sortBy(fn (array $item): string => ($item['severity'] === 'danger' ? '0' : '1').'|'.$item['invoice']['due_date'].'|'.$item['invoice']['number'])
            ->values();

        return [
            'window_start' => $date->toDateString(),
            'window_end' => $windowEnd->toDateString(),
            'summary' => [
                'overdue_count' => $items->where('due_label', 'overdue')->count(),
                'due_soon_count' => $items->whereIn('due_label', ['today', 'tomorrow'])->count(),
                'outstanding_halalas' => $items->sum('invoice.balance_halalas'),
            ],
            'items' => $items->all(),
        ];
    }

    private function bookingRequestsBoard(): array
    {
        $items = BookingRequest::query()
            ->with(['customer', 'site', 'service', 'servicePackage'])
            ->whereIn('status', ['pending', 'approved'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderBy('requested_for')
            ->orderBy('starts_at')
            ->limit(40)
            ->get()
            ->map(fn (BookingRequest $bookingRequest): array => $this->bookingRequestItem($bookingRequest))
            ->values();

        return [
            'summary' => [
                'pending_count' => BookingRequest::query()->where('status', 'pending')->count(),
                'approved_count' => BookingRequest::query()->where('status', 'approved')->count(),
                'converted_count' => BookingRequest::query()->where('status', 'converted')->count(),
            ],
            'items' => $items->all(),
        ];
    }

    private function bookingRequestItem(BookingRequest $bookingRequest): array
    {
        return [
            'id' => $bookingRequest->id,
            'status' => $bookingRequest->status,
            'requested_for' => $bookingRequest->requested_for?->toDateString(),
            'starts_at' => $this->time($bookingRequest->starts_at),
            'ends_at' => $this->time($bookingRequest->ends_at),
            'worker_count' => $bookingRequest->worker_count,
            'duration_minutes' => $bookingRequest->duration_minutes,
            'customer_note' => $bookingRequest->customer_note,
            'admin_note' => $bookingRequest->admin_note,
            'customer' => $bookingRequest->customer ? [
                'id' => $bookingRequest->customer->id,
                'name' => $bookingRequest->customer->name,
            ] : null,
            'site' => $bookingRequest->site ? [
                'id' => $bookingRequest->site->id,
                'name' => $bookingRequest->site->name,
                'city' => $bookingRequest->site->city,
                'district' => $bookingRequest->site->district,
            ] : null,
            'service' => $bookingRequest->service ? [
                'id' => $bookingRequest->service->id,
                'title' => $bookingRequest->service->title,
                'category' => $bookingRequest->service->category,
            ] : null,
            'service_package' => $bookingRequest->servicePackage ? [
                'id' => $bookingRequest->servicePackage->id,
                'name' => $bookingRequest->servicePackage->name,
            ] : null,
            'approve_url' => "/app/booking-requests/{$bookingRequest->id}/approve",
            'reject_url' => "/app/booking-requests/{$bookingRequest->id}/reject",
            'contract_create_url' => "/app/contracts/create?booking_request_id={$bookingRequest->id}",
        ];
    }

    private function serviceRequestsBoard(): array
    {
        $items = ServiceRequest::query()
            ->with(['customer', 'contract.customer', 'contract.site', 'contract.service', 'visit.worker', 'visit.feedback'])
            ->whereIn('status', ['pending', 'in_review'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->limit(40)
            ->get()
            ->map(fn (ServiceRequest $serviceRequest): array => $this->serviceRequestItem($serviceRequest))
            ->values();

        return [
            'summary' => [
                'pending_count' => ServiceRequest::query()->where('status', 'pending')->count(),
                'in_review_count' => ServiceRequest::query()->where('status', 'in_review')->count(),
                'approved_count' => ServiceRequest::query()->where('status', 'approved')->count(),
                'resolved_count' => ServiceRequest::query()->where('status', 'resolved')->count(),
                'rejected_count' => ServiceRequest::query()->where('status', 'rejected')->count(),
            ],
            'items' => $items->all(),
        ];
    }

    private function serviceRequestItem(ServiceRequest $serviceRequest): array
    {
        return [
            'id' => $serviceRequest->id,
            'type' => $serviceRequest->type,
            'status' => $serviceRequest->status,
            'priority' => $serviceRequest->priority,
            'subject' => $serviceRequest->subject,
            'description' => $serviceRequest->description,
            'requested_for' => $serviceRequest->requested_for?->toDateString(),
            'starts_at' => $this->time($serviceRequest->starts_at),
            'ends_at' => $this->time($serviceRequest->ends_at),
            'admin_note' => $serviceRequest->admin_note,
            'created_at' => $serviceRequest->created_at?->toDateTimeString(),
            'resolved_at' => $serviceRequest->resolved_at?->toDateTimeString(),
            'customer' => $serviceRequest->customer ? [
                'id' => $serviceRequest->customer->id,
                'name' => $serviceRequest->customer->name,
            ] : null,
            'contract' => $serviceRequest->contract ? [
                'id' => $serviceRequest->contract->id,
                'reference' => $serviceRequest->contract->reference,
                'status' => $serviceRequest->contract->status,
                'detail_url' => "/app/contracts/{$serviceRequest->contract->id}",
                'customer' => $serviceRequest->contract->customer ? [
                    'id' => $serviceRequest->contract->customer->id,
                    'name' => $serviceRequest->contract->customer->name,
                ] : null,
                'site' => $serviceRequest->contract->site ? [
                    'id' => $serviceRequest->contract->site->id,
                    'name' => $serviceRequest->contract->site->name,
                    'city' => $serviceRequest->contract->site->city,
                    'district' => $serviceRequest->contract->site->district,
                ] : null,
                'service' => $serviceRequest->contract->service ? [
                    'id' => $serviceRequest->contract->service->id,
                    'title' => $serviceRequest->contract->service->title,
                    'category' => $serviceRequest->contract->service->category,
                ] : null,
            ] : null,
            'visit' => $serviceRequest->visit ? [
                'id' => $serviceRequest->visit->id,
                'scheduled_for' => $serviceRequest->visit->scheduled_for?->toDateString(),
                'starts_at' => $this->time($serviceRequest->visit->starts_at),
                'ends_at' => $this->time($serviceRequest->visit->ends_at),
                'status' => $serviceRequest->visit->status,
                'worker' => $serviceRequest->visit->worker ? [
                    'id' => $serviceRequest->visit->worker->id,
                    'name' => $serviceRequest->visit->worker->name,
                ] : null,
                'feedback' => $serviceRequest->visit->feedback ? [
                    'id' => $serviceRequest->visit->feedback->id,
                    'rating' => $serviceRequest->visit->feedback->rating,
                    'comment' => $serviceRequest->visit->feedback->comment,
                    'submitted_at' => $serviceRequest->visit->feedback->submitted_at?->toDateTimeString(),
                ] : null,
            ] : null,
            'approve_url' => "/app/service-requests/{$serviceRequest->id}/approve",
            'reject_url' => "/app/service-requests/{$serviceRequest->id}/reject",
            'in_review_url' => "/app/service-requests/{$serviceRequest->id}/in-review",
            'resolve_url' => "/app/service-requests/{$serviceRequest->id}/resolve",
        ];
    }

    private function assignmentNoticeDate(Assignment $assignment, Carbon $date, Carbon $windowEnd): ?Carbon
    {
        foreach ([$date, $windowEnd] as $candidate) {
            if ((int) $assignment->weekday === (int) $candidate->dayOfWeekIso) {
                return $candidate->copy();
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, mixed>  $times
     */
    private function firstTime(Collection $times): ?string
    {
        return $times
            ->map(fn ($time): ?string => is_string($time) ? $this->time($time) : null)
            ->filter()
            ->sort()
            ->first();
    }

    /**
     * @param  Collection<int, mixed>  $times
     */
    private function lastTime(Collection $times): ?string
    {
        return $times
            ->map(fn ($time): ?string => is_string($time) ? $this->time($time) : null)
            ->filter()
            ->sort()
            ->last();
    }

    /**
     * @param  array<string, mixed>|null  $followUp
     */
    private function contractActionSeverity(?array $followUp): string
    {
        if ((int) ($followUp['missing_main_worker_slots'] ?? 0) > 0) {
            return 'danger';
        }

        if ((int) ($followUp['open_items'] ?? 0) > 0) {
            return 'warning';
        }

        return 'success';
    }

    private function paymentAttentionItem(Invoice $invoice, Carbon $date, Carbon $windowEnd): array
    {
        $dueDate = $invoice->due_date?->toDateString();
        $dueLabel = 'today';
        $daysOverdue = 0;
        $severity = 'warning';

        if ($invoice->due_date && $invoice->due_date->lt($date->copy()->startOfDay())) {
            $dueLabel = 'overdue';
            $daysOverdue = (int) $invoice->due_date->diffInDays($date);
            $severity = 'danger';
        } elseif ($dueDate === $windowEnd->toDateString()) {
            $dueLabel = 'tomorrow';
        }

        return [
            'severity' => $severity,
            'due_label' => $dueLabel,
            'days_overdue' => $daysOverdue,
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status,
                'due_date' => $dueDate,
                'gross_total_halalas' => (int) $invoice->gross_total_halalas,
                'paid_total_halalas' => (int) $invoice->paid_total_halalas,
                'balance_halalas' => (int) $invoice->balance_halalas,
            ],
            'contract' => $invoice->contract ? $this->serializeContractSummary($invoice->contract, 'finance') : null,
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
            ] : null,
            'action_url' => $invoice->contract_id ? "/app/contracts/{$invoice->contract_id}?panel=finance" : '/app/finance',
        ];
    }

    private function serializeContractSummary(Contract $contract, string $panel): array
    {
        return [
            'id' => $contract->id,
            'reference' => $contract->reference,
            'status' => $contract->status,
            'starts_on' => $contract->starts_on?->toDateString(),
            'ends_on' => $contract->ends_on?->toDateString(),
            'detail_url' => "/app/contracts/{$contract->id}?panel={$panel}",
            'customer' => $contract->customer ? [
                'id' => $contract->customer->id,
                'name' => $contract->customer->name,
            ] : null,
            'site' => $contract->site ? $this->serializeSite($contract->site) : null,
            'service' => $contract->service ? [
                'id' => $contract->service->id,
                'title' => $contract->service->title,
            ] : null,
        ];
    }

    private function assignmentFollowUpItem(Contract $contract, Carbon $weekStart, Carbon $weekEnd): array
    {
        $requiredVisits = max(0, (int) ($contract->visits_per_week ?? 0));
        $requiredWorkers = max(1, (int) ($contract->agreed_workers ?? 1));
        $activeAssignments = $contract->assignments
            ->where('status', 'active')
            ->values();

        $slots = $activeAssignments
            ->groupBy(fn (Assignment $assignment): string => $this->assignmentSlotKey($assignment))
            ->map(fn (Collection $assignments): array => $this->assignmentFollowUpSlot($assignments, $requiredWorkers))
            ->sortBy([
                ['weekday', 'asc'],
                ['starts_at', 'asc'],
                ['ends_at', 'asc'],
            ])
            ->values();

        $missingVisitSlots = max(0, $requiredVisits - $slots->count());
        $missingMainWorkerSlots = $slots->where('has_main_worker', false)->count();
        $understaffedSlots = $slots->where('has_required_workers', false)->count();
        $openItems = $missingVisitSlots + $missingMainWorkerSlots + $understaffedSlots;

        return [
            'contract' => [
                'id' => $contract->id,
                'reference' => $contract->reference,
                'status' => $contract->status,
                'starts_on' => $contract->starts_on?->toDateString(),
                'ends_on' => $contract->ends_on?->toDateString(),
                'detail_url' => "/app/contracts/{$contract->id}?panel=assignments",
                'customer' => $contract->customer ? [
                    'id' => $contract->customer->id,
                    'name' => $contract->customer->name,
                ] : null,
                'site' => $contract->site ? $this->serializeSite($contract->site) : null,
                'service' => $contract->service ? [
                    'id' => $contract->service->id,
                    'title' => $contract->service->title,
                ] : null,
            ],
            'next_due_on' => $this->nextContractDueDate($contract, $activeAssignments, $weekStart, $weekEnd),
            'severity' => $missingMainWorkerSlots > 0 ? 'danger' : ($openItems > 0 ? 'warning' : 'success'),
            'open_items' => $openItems,
            'required_visits_per_week' => $requiredVisits,
            'covered_visits_per_week' => $slots->count(),
            'missing_visit_slots' => $missingVisitSlots,
            'required_workers_per_visit' => $requiredWorkers,
            'ready_slots_count' => $slots->where('ready', true)->count(),
            'missing_main_worker_slots' => $missingMainWorkerSlots,
            'understaffed_slots' => $understaffedSlots,
            'follow_ups' => $this->assignmentFollowUpTypes($missingVisitSlots, $missingMainWorkerSlots, $understaffedSlots),
            'slots' => $slots->all(),
        ];
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     */
    private function assignmentFollowUpSlot(Collection $assignments, int $requiredWorkers): array
    {
        $firstAssignment = $assignments->first();
        $mainAssignment = $assignments->firstWhere('team_role', 'main');
        $workersCount = $assignments->pluck('worker_id')->unique()->count();
        $hasMainWorker = $mainAssignment instanceof Assignment;
        $hasRequiredWorkers = $workersCount >= $requiredWorkers;

        return [
            'key' => $this->assignmentSlotKey($firstAssignment),
            'weekday' => (int) $firstAssignment->weekday,
            'starts_at' => $this->time($firstAssignment->starts_at),
            'ends_at' => $this->time($firstAssignment->ends_at),
            'workers_count' => $workersCount,
            'missing_support_workers' => max(0, $requiredWorkers - $workersCount),
            'task_count' => $assignments->sum(fn (Assignment $assignment): int => count($assignment->task_instructions ?? [])),
            'has_main_worker' => $hasMainWorker,
            'has_required_workers' => $hasRequiredWorkers,
            'ready' => $hasMainWorker && $hasRequiredWorkers,
            'main_worker' => $mainAssignment?->worker ? $this->serializeWorker($mainAssignment->worker) : null,
            'workers' => $assignments
                ->map(fn (Assignment $assignment): array => [
                    'assignment_id' => $assignment->id,
                    'team_role' => $assignment->team_role ?? 'main',
                    'worker' => $assignment->worker ? $this->serializeWorker($assignment->worker) : null,
                ])
                ->values()
                ->all(),
        ];
    }

    private function assignmentSlotKey(Assignment $assignment): string
    {
        return implode('|', [
            $assignment->weekday,
            $this->time($assignment->starts_at),
            $this->time($assignment->ends_at),
            $assignment->service_id ?? 'none',
        ]);
    }

    private function assignmentFollowUpTypes(int $missingVisitSlots, int $missingMainWorkerSlots, int $understaffedSlots): array
    {
        return collect([
            $missingVisitSlots > 0 ? ['type' => 'missing_visit_slots', 'severity' => 'warning', 'count' => $missingVisitSlots] : null,
            $missingMainWorkerSlots > 0 ? ['type' => 'missing_main_worker', 'severity' => 'danger', 'count' => $missingMainWorkerSlots] : null,
            $understaffedSlots > 0 ? ['type' => 'team_understaffed', 'severity' => 'warning', 'count' => $understaffedSlots] : null,
        ])->filter()->values()->all();
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     */
    private function nextContractDueDate(Contract $contract, Collection $assignments, Carbon $weekStart, Carbon $weekEnd): string
    {
        $start = $weekStart->copy();

        if ($contract->starts_on && $contract->starts_on->greaterThan($start)) {
            $start = $contract->starts_on->copy();
        }

        $weekdays = $assignments
            ->pluck('weekday')
            ->filter()
            ->unique()
            ->values();

        for ($date = $start->copy(); $date->lte($weekEnd); $date->addDay()) {
            if ($weekdays->isEmpty() || $weekdays->contains($date->dayOfWeekIso)) {
                return $date->toDateString();
            }
        }

        return $start->toDateString();
    }

    private function dailyAlert(Visit $visit): ?array
    {
        if ($visit->status === 'issue_reported') {
            return $this->alert($visit, 'issue', 'Issue reported', 'Visit has an open issue note or photo evidence.', 'error');
        }

        if ($visit->status === 'missed' || $this->isMissedCandidate($visit)) {
            return $this->alert($visit, 'missed', 'Missed visit', 'Scheduled time has passed without attendance.', 'error');
        }

        if ($this->isLate($visit)) {
            return $this->alert($visit, 'late', 'Late check-in', 'Worker has not checked in after the scheduled start.', 'warning');
        }

        if ($visit->overtime_status === 'pending_approval') {
            return $this->alert($visit, 'overtime', 'Overtime pending', 'Actual work exceeded planned time and needs approval.', 'warning');
        }

        if ($this->completionReviewStatus($visit) === 'needs_correction') {
            return $this->alert($visit, 'correction', 'Correction requested', 'Supervisor requested corrected visit evidence before approval.', 'warning');
        }

        if ($visit->quality_follow_up_required || $visit->quality_status === 'customer_follow_up') {
            return $this->alert($visit, 'quality_follow_up', 'Quality follow-up', 'Supervisor marked this visit for customer follow-up.', 'warning');
        }

        return null;
    }

    private function alert(Visit $visit, string $type, string $title, string $message, string $severity): array
    {
        return [
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'visit_id' => $visit->id,
            'site' => $visit->site?->name,
            'worker' => $visit->worker?->name,
            'starts_at' => $this->time($visit->starts_at),
            'ends_at' => $this->time($visit->ends_at),
        ];
    }

    private function visitAttentionPriority(Visit $visit): int
    {
        if ($visit->status === 'issue_reported') {
            return 0;
        }

        if ($visit->status === 'missed' || $this->isMissedCandidate($visit)) {
            return 1;
        }

        if ($this->isLate($visit)) {
            return 2;
        }

        if ($this->requiresAcknowledgement($visit)) {
            return 3;
        }

        if ($this->completionReviewStatus($visit) === 'needs_correction') {
            return 4;
        }

        if ($visit->overtime_status === 'pending_approval') {
            return 5;
        }

        return 6;
    }

    private function isLate(Visit $visit): bool
    {
        if ($visit->status !== 'scheduled' || $visit->checked_in_at) {
            return false;
        }

        $startsAt = $this->scheduledDateTime($visit, $visit->starts_at)?->addMinutes(15);
        $endsAt = $this->scheduledDateTime($visit, $visit->ends_at);

        return $startsAt && $endsAt && now()->greaterThan($startsAt) && now()->lessThanOrEqualTo($endsAt);
    }

    private function isMissedCandidate(Visit $visit): bool
    {
        if ($visit->status !== 'scheduled' || $visit->checked_in_at) {
            return false;
        }

        $endsAt = $this->scheduledDateTime($visit, $visit->ends_at);

        return $endsAt && now()->greaterThan($endsAt);
    }

    private function requiresAcknowledgement(Visit $visit): bool
    {
        return $visit->status === 'completed' && $this->completionReviewStatus($visit) === 'pending_review';
    }

    private function completionReviewStatus(Visit $visit): string
    {
        if ($visit->completion_review_status) {
            return $visit->completion_review_status;
        }

        return $visit->supervisor_acknowledged_at ? 'approved' : 'pending_review';
    }

    private function scheduledDateTime(Visit $visit, ?string $time): ?Carbon
    {
        if (! $visit->scheduled_for || ! $time) {
            return null;
        }

        return Carbon::parse($visit->scheduled_for->toDateString().' '.$this->time($time));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    private function contractAssignmentErrors(Contract $contract, array $attributes): array
    {
        $errors = [];

        if ((int) $contract->customer_site_id !== (int) $attributes['customer_site_id']) {
            $errors['customer_site_id'] = __('The selected site does not belong to this contract.');
        }

        if ($contract->service_id && (int) $contract->service_id !== (int) $attributes['service_id']) {
            $errors['service_id'] = __('The selected service does not belong to this contract.');
        }

        return $errors;
    }

    /**
     * @param  Collection<int, Visit>  $weeklyVisits
     * @param  Collection<int, Assignment>  $assignments
     * @return list<array<string, mixed>>
     */
    private function calendarDays(Carbon $weekStart, Collection $weeklyVisits, Collection $assignments): array
    {
        return collect(range(0, 6))
            ->map(function (int $offset) use ($weekStart, $weeklyVisits, $assignments): array {
                $date = $weekStart->copy()->addDays($offset);
                $dayVisits = $weeklyVisits
                    ->filter(fn (Visit $visit): bool => $visit->scheduled_for?->toDateString() === $date->toDateString())
                    ->values();
                $dayAssignments = $assignments
                    ->where('weekday', $date->dayOfWeekIso)
                    ->values();

                return [
                    'date' => $date->toDateString(),
                    'weekday' => $date->dayOfWeekIso,
                    'label' => $date->format('D'),
                    'day' => $date->format('d'),
                    'visits_count' => $dayVisits->count(),
                    'completed_count' => $dayVisits->where('status', 'completed')->count(),
                    'scheduled_count' => $dayVisits->where('status', 'scheduled')->count(),
                    'visits' => $dayVisits->map(fn (Visit $visit): array => $this->serializeVisit($visit))->values(),
                    'assignments' => $dayAssignments->map(fn (Assignment $assignment): array => $this->serializeAssignment($assignment))->values(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @param  Collection<int, Visit>  $weeklyVisits
     * @return list<array<string, mixed>>
     */
    private function workload(Collection $assignments, Collection $weeklyVisits): array
    {
        $workers = Worker::query()->whereIn('id', $assignments->pluck('worker_id')->merge($weeklyVisits->pluck('worker_id'))->unique())->orderBy('name')->get();

        return $workers->map(function (Worker $worker) use ($assignments, $weeklyVisits): array {
            $workerAssignments = $assignments->where('worker_id', $worker->id);
            $assignmentMinutes = $workerAssignments->sum(fn (Assignment $assignment): int => $this->durationMinutes($assignment->starts_at, $assignment->ends_at));
            $workerVisits = $weeklyVisits->where('worker_id', $worker->id);
            $visitMinutes = $workerVisits->sum(fn (Visit $visit): int => $this->durationMinutes($visit->starts_at, $visit->ends_at));
            $utilization = min(100, (int) round(($assignmentMinutes / 2400) * 100));

            return [
                'worker' => $this->serializeWorker($worker),
                'assignment_minutes' => $assignmentMinutes,
                'visit_minutes' => $visitMinutes,
                'visits_count' => $workerVisits->count(),
                'completed_visits' => $workerVisits->where('status', 'completed')->count(),
                'utilization_percent' => $utilization,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, Visit>  $dailyVisits
     * @param  Collection<int, Assignment>  $assignments
     * @return array<string, mixed>
     */
    private function availabilityBoard(Carbon $date, Collection $dailyVisits, Collection $assignments): array
    {
        $workers = Worker::query()
            ->whereIn('status', ['available', 'assigned'])
            ->orderBy('name')
            ->get();
        $events = $this->availabilityEvents($date, $dailyVisits, $assignments);
        $capacityMinutes = $workers->count() * 480;
        $bookedMinutes = (int) $events->sum('minutes');
        $bookedWorkers = $events->pluck('worker.id')->filter()->unique()->count();
        $availableWorkers = max(0, $workers->count() - $bookedWorkers);

        return [
            'date' => $date->toDateString(),
            'summary' => [
                'total_workers' => $workers->count(),
                'booked_workers' => $bookedWorkers,
                'available_workers' => $availableWorkers,
                'capacity_minutes' => $capacityMinutes,
                'booked_minutes' => $bookedMinutes,
                'available_minutes' => max(0, $capacityMinutes - $bookedMinutes),
                'utilization_percent' => $capacityMinutes > 0 ? min(100, (int) round(($bookedMinutes / $capacityMinutes) * 100)) : 0,
            ],
            'timeSlots' => $this->availabilityTimeSlots($workers, $events),
            'workerLanes' => $this->workerLanes($workers, $events),
        ];
    }

    /**
     * @param  Collection<int, Visit>  $dailyVisits
     * @param  Collection<int, Assignment>  $assignments
     * @return Collection<int, array<string, mixed>>
     */
    private function availabilityEvents(Carbon $date, Collection $dailyVisits, Collection $assignments): Collection
    {
        $visitAssignmentIds = $dailyVisits->pluck('assignment_id')->filter()->unique();
        $visitEvents = collect($dailyVisits
            ->filter(fn (Visit $visit): bool => $visit->worker !== null)
            ->map(fn (Visit $visit): array => [
                'id' => 'visit-'.$visit->id,
                'source' => 'visit',
                'status' => $visit->status,
                'starts_at' => $this->time($visit->starts_at),
                'ends_at' => $this->time($visit->ends_at),
                'minutes' => $this->durationMinutes($visit->starts_at, $visit->ends_at),
                'worker' => $visit->worker ? $this->serializeWorker($visit->worker) : null,
                'site' => $visit->site ? $this->serializeSite($visit->site) : null,
                'service' => $visit->service ? [
                    'id' => $visit->service->id,
                    'title' => $visit->service->title,
                ] : null,
                'contract' => $visit->contract ? [
                    'id' => $visit->contract->id,
                    'reference' => $visit->contract->reference,
                ] : null,
            ])
            ->values()
            ->all());

        $assignmentEvents = collect($assignments
            ->where('weekday', $date->dayOfWeekIso)
            ->filter(fn (Assignment $assignment): bool => $assignment->status === 'active' && ! $visitAssignmentIds->contains($assignment->id))
            ->map(fn (Assignment $assignment): array => [
                'id' => 'assignment-'.$assignment->id,
                'source' => 'assignment',
                'status' => $assignment->status,
                'starts_at' => $this->time($assignment->starts_at),
                'ends_at' => $this->time($assignment->ends_at),
                'minutes' => $this->durationMinutes($assignment->starts_at, $assignment->ends_at),
                'worker' => $assignment->worker ? $this->serializeWorker($assignment->worker) : null,
                'site' => $assignment->site ? $this->serializeSite($assignment->site) : null,
                'service' => $assignment->service ? [
                    'id' => $assignment->service->id,
                    'title' => $assignment->service->title,
                ] : null,
                'contract' => $assignment->contract ? [
                    'id' => $assignment->contract->id,
                    'reference' => $assignment->contract->reference,
                ] : null,
            ])
            ->values()
            ->all());

        return $visitEvents
            ->merge($assignmentEvents)
            ->filter(fn (array $event): bool => $event['worker'] !== null)
            ->sortBy(fn (array $event): string => $event['starts_at'].'|'.$event['worker']['name'])
            ->values();
    }

    /**
     * @param  Collection<int, Worker>  $workers
     * @param  Collection<int, array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function availabilityTimeSlots(Collection $workers, Collection $events): array
    {
        $totalWorkers = $workers->count();

        return collect(range(8, 17))
            ->map(function (int $hour) use ($events, $totalWorkers): array {
                $slotStart = sprintf('%02d:00', $hour);
                $slotEnd = sprintf('%02d:00', $hour + 1);
                $slotEvents = $events->filter(fn (array $event): bool => $this->timesOverlap($event['starts_at'], $event['ends_at'], $slotStart, $slotEnd));
                $bookedWorkers = $slotEvents->pluck('worker.id')->unique()->count();
                $availableWorkers = max(0, $totalWorkers - $bookedWorkers);

                return [
                    'label' => $slotStart,
                    'starts_at' => $slotStart,
                    'ends_at' => $slotEnd,
                    'total_workers' => $totalWorkers,
                    'booked_workers' => $bookedWorkers,
                    'available_workers' => $availableWorkers,
                    'capacity_percent' => $totalWorkers > 0 ? (int) round(($bookedWorkers / $totalWorkers) * 100) : 0,
                    'status' => $availableWorkers === 0 && $totalWorkers > 0
                        ? 'full'
                        : ($bookedWorkers > 0 ? 'available' : 'open'),
                    'events_count' => $slotEvents->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Worker>  $workers
     * @param  Collection<int, array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function workerLanes(Collection $workers, Collection $events): array
    {
        return $workers
            ->map(function (Worker $worker) use ($events): array {
                $workerEvents = $events
                    ->filter(fn (array $event): bool => (int) $event['worker']['id'] === (int) $worker->id)
                    ->values();
                $bookedMinutes = (int) $workerEvents->sum('minutes');

                return [
                    'worker' => $this->serializeWorker($worker),
                    'booked_minutes' => $bookedMinutes,
                    'available_minutes' => max(0, 480 - $bookedMinutes),
                    'utilization_percent' => min(100, (int) round(($bookedMinutes / 480) * 100)),
                    'events' => $workerEvents->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function timesOverlap(string $startsAt, string $endsAt, string $slotStart, string $slotEnd): bool
    {
        return $startsAt < $slotEnd && $endsAt > $slotStart;
    }

    private function durationMinutes(string $startsAt, string $endsAt): int
    {
        $start = Carbon::createFromFormat('H:i:s', strlen($startsAt) === 5 ? $startsAt.':00' : $startsAt);
        $end = Carbon::createFromFormat('H:i:s', strlen($endsAt) === 5 ? $endsAt.':00' : $endsAt);

        return max(0, $start->diffInMinutes($end));
    }

    private function actualMinutes(string $checkedInAt, string $checkedOutAt): int
    {
        return max(0, (int) Carbon::parse($checkedInAt)->diffInMinutes(Carbon::parse($checkedOutAt)));
    }

    private function normalizeExecutionRequest(Request $request): void
    {
        $this->mergeSarFromHalalas($request, 'material_cost_sar', 'material_cost_halalas');

        if (is_array($request->input('materials_used'))) {
            $request->merge([
                'materials_used' => collect($request->input('materials_used', []))
                    ->map(function (array $item): array {
                        if (! array_key_exists('cost_sar', $item) && array_key_exists('cost_halalas', $item)) {
                            $item['cost_sar'] = $this->halalasToSarString((int) $item['cost_halalas']);
                        }

                        $item['cost_sar'] ??= '0.00';

                        return $item;
                    })
                    ->all(),
            ]);
        }

        if (! $request->has('material_cost_sar')) {
            $request->merge([
                'material_cost_sar' => $this->halalasToSarString($this->materialRowsTotal($request->input('materials_used', []))),
            ]);
        }

        $request->merge([
            'materials_used' => $request->input('materials_used', []),
            'overtime_status' => $request->input('overtime_status', 'pending_approval'),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $materials
     */
    private function materialRowsTotal(array $materials): int
    {
        return collect($materials)
            ->sum(fn (array $item): int => $this->sarToHalalas($item['cost_sar'] ?? '0.00'));
    }

    private function plannedVisitRevenue(Visit $visit): int
    {
        if ($visit->contract) {
            $visitsPerWeek = max(1, (int) ($visit->contract->visits_per_week ?: 1));

            return (int) round($visit->contract->monthly_fee_halalas / max(1, $visitsPerWeek * 4));
        }

        return (int) ($visit->service?->base_price_halalas ?? 0);
    }

    private function laborCost(?Worker $worker, int $actualMinutes): int
    {
        if (! $worker || $actualMinutes === 0) {
            return 0;
        }

        $monthlyWorkingMinutes = 26 * 8 * 60;

        return (int) round(($worker->cost_rate_halalas / $monthlyWorkingMinutes) * $actualMinutes);
    }

    private function billableOvertime(Visit $visit, int $billableOvertimeMinutes): int
    {
        if ($billableOvertimeMinutes === 0) {
            return 0;
        }

        $rate = (int) ($visit->contract?->extra_hour_rate_halalas ?: $visit->service?->extra_hour_rate_halalas ?: 0);

        return (int) round(($rate / 60) * $billableOvertimeMinutes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $materials
     * @return list<array{name: string, quantity: string|null, cost_halalas: int, cost_sar: string}>
     */
    private function normalizeMaterialsUsed(array $materials): array
    {
        return collect($materials)
            ->map(function (array $item): array {
                $costHalalas = $this->sarToHalalas($item['cost_sar'] ?? '0.00');

                return [
                    'name' => trim((string) $item['name']),
                    'quantity' => filled($item['quantity'] ?? null) ? trim((string) $item['quantity']) : null,
                    'cost_halalas' => $costHalalas,
                    'cost_sar' => $this->halalasToSarString($costHalalas),
                ];
            })
            ->filter(fn (array $item): bool => $item['name'] !== '')
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
        ];
    }

    /**
     * @return list<string>
     */
    private function completionReviewFields(): array
    {
        return [
            'supervisor_acknowledged_at',
            'supervisor_acknowledged_by',
            'supervisor_note',
            'completion_review_status',
            'completion_reviewed_at',
            'completion_reviewed_by',
            'completion_review_note',
            'quality_status',
            'quality_score',
            'quality_reviewed_at',
            'quality_reviewed_by',
            'quality_notes',
            'quality_follow_up_required',
        ];
    }

    private function createQualityFollowUp(Visit $visit, string $priority, string $subject, ?string $description): ServiceRequest
    {
        $visit->loadMissing('contract');

        return ServiceRequest::query()->firstOrCreate(
            [
                'type' => 'quality_follow_up',
                'visit_id' => $visit->id,
            ],
            [
                'status' => 'pending',
                'priority' => $priority,
                'customer_id' => (int) $visit->contract->customer_id,
                'contract_id' => (int) $visit->contract_id,
                'subject' => $subject,
                'description' => $description ?: __('Supervisor requested quality follow-up for this visit.'),
                'requested_for' => $visit->scheduled_for?->toDateString(),
                'starts_at' => $this->time($visit->starts_at),
                'ends_at' => $this->time($visit->ends_at),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contractOptions(): array
    {
        return Contract::query()
            ->with(['customer', 'site', 'service', 'servicePackage'])
            ->whereIn('status', ['active', 'draft'])
            ->orderBy('reference')
            ->get()
            ->map(fn (Contract $contract): array => [
                'id' => $contract->id,
                'reference' => $contract->reference,
                'status' => $contract->status,
                'starts_on' => $contract->starts_on?->toDateString(),
                'ends_on' => $contract->ends_on?->toDateString(),
                'customer' => $contract->customer ? [
                    'id' => $contract->customer->id,
                    'name' => $contract->customer->name,
                ] : null,
                'site' => $contract->site ? $this->serializeSite($contract->site) : null,
                'service' => $contract->service ? [
                    'id' => $contract->service->id,
                    'title' => $contract->service->title,
                ] : null,
                'service_package' => $contract->servicePackage ? [
                    'id' => $contract->servicePackage->id,
                    'name' => $contract->servicePackage->name,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workerOptions(): array
    {
        return Worker::query()
            ->whereIn('status', ['available', 'assigned'])
            ->orderBy('name')
            ->get()
            ->map(fn (Worker $worker): array => $this->serializeWorker($worker))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customerOptions(): array
    {
        return Customer::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'status' => $customer->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function siteOptions(): array
    {
        return CustomerSite::query()
            ->with('customer')
            ->orderBy('name')
            ->get()
            ->map(fn (CustomerSite $site): array => $this->serializeSite($site))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviceOptions(): array
    {
        return Service::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get()
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'title' => $service->title,
                'category' => $service->category,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['key' => 'scheduled', 'label' => 'Scheduled'],
            ['key' => 'in_progress', 'label' => 'In progress'],
            ['key' => 'completed', 'label' => 'Completed'],
            ['key' => 'missed', 'label' => 'Missed'],
            ['key' => 'issue_reported', 'label' => 'Issue reported'],
        ];
    }

    /**
     * @return list<array{key: int, label: string}>
     */
    private function weekdayOptions(): array
    {
        return [
            ['key' => 1, 'label' => 'Monday'],
            ['key' => 2, 'label' => 'Tuesday'],
            ['key' => 3, 'label' => 'Wednesday'],
            ['key' => 4, 'label' => 'Thursday'],
            ['key' => 5, 'label' => 'Friday'],
            ['key' => 6, 'label' => 'Saturday'],
            ['key' => 7, 'label' => 'Sunday'],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function assignmentStatuses(): array
    {
        return [
            ['key' => 'active', 'label' => 'Active'],
            ['key' => 'paused', 'label' => 'Paused'],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function overtimeStatuses(): array
    {
        return [
            ['key' => 'not_required', 'label' => 'No overtime'],
            ['key' => 'pending_approval', 'label' => 'Pending approval'],
            ['key' => 'approved', 'label' => 'Approved billable'],
            ['key' => 'rejected', 'label' => 'Rejected internal cost'],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function qualityStatuses(): array
    {
        return [
            ['key' => 'passed', 'label' => 'Passed'],
            ['key' => 'failed', 'label' => 'Failed'],
            ['key' => 'needs_correction', 'label' => 'Needs correction'],
            ['key' => 'customer_follow_up', 'label' => 'Customer follow-up'],
        ];
    }

    private function serializeVisit(Visit $visit): array
    {
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
            'planned_minutes' => $visit->planned_minutes ?: $this->durationMinutes($visit->starts_at, $visit->ends_at),
            'actual_minutes' => $visit->actual_minutes,
            'variance_minutes' => $visit->variance_minutes,
            'overtime_minutes' => $visit->overtime_minutes,
            'billable_overtime_minutes' => $visit->billable_overtime_minutes,
            'overtime_status' => $visit->overtime_status,
            'materials_used' => $visit->materials_used ?? [],
            'planned_revenue_halalas' => $visit->planned_revenue_halalas,
            'labor_cost_halalas' => $visit->labor_cost_halalas,
            'material_cost_halalas' => $visit->material_cost_halalas,
            'billable_overtime_halalas' => $visit->billable_overtime_halalas,
            'gross_profit_halalas' => $visit->gross_profit_halalas,
            'execution_notes' => $visit->execution_notes,
            'is_late' => $this->isLate($visit),
            'is_missed_candidate' => $this->isMissedCandidate($visit),
            'requires_acknowledgement' => $this->requiresAcknowledgement($visit),
            'issue_note' => $visit->issue_note,
            'photos' => $visit->photos ?? [],
            'photo_urls' => collect($visit->photos ?? [])
                ->map(fn (string $path): string => $this->publicEvidenceUrl($path))
                ->values(),
            'supervisor_acknowledged_at' => $visit->supervisor_acknowledged_at?->toDateTimeString(),
            'supervisor_note' => $visit->supervisor_note,
            'completion_review_status' => $this->completionReviewStatus($visit),
            'completion_reviewed_at' => $visit->completion_reviewed_at?->toDateTimeString(),
            'completion_review_note' => $visit->completion_review_note,
            'completion_reviewed_by' => $visit->completionReviewedBy ? [
                'id' => $visit->completionReviewedBy->id,
                'name' => $visit->completionReviewedBy->name,
            ] : null,
            'evidence_review' => $this->evidenceReviewPayload($visit),
            'acknowledged_by' => $visit->acknowledgedBy ? [
                'id' => $visit->acknowledgedBy->id,
                'name' => $visit->acknowledgedBy->name,
            ] : null,
            'worker' => $visit->worker ? $this->serializeWorker($visit->worker) : null,
            'site' => $visit->site ? $this->serializeSite($visit->site) : null,
            'service' => $visit->service ? [
                'id' => $visit->service->id,
                'title' => $visit->service->title,
            ] : null,
            'checklist_items' => $visit->checklistItems->map(fn ($item): array => [
                'id' => $item->id,
                'label' => $item->label,
                'status' => $item->status,
                'notes' => $item->notes,
                'photo_path' => $item->photo_path,
                'photo_url' => $item->photo_path ? $this->publicEvidenceUrl($item->photo_path) : null,
                'completed_at' => $item->completed_at?->toDateTimeString(),
            ])->values(),
        ];
    }

    private function evidenceReviewPayload(Visit $visit): array
    {
        $checklist = $visit->checklistItems;
        $reviewer = $visit->completionReviewedBy ?: $visit->acknowledgedBy;
        $qualityReviewer = $visit->qualityReviewedBy ?: $reviewer;
        $feedback = $visit->feedback;
        $photoCount = count($visit->photos ?? []) + $checklist->whereNotNull('photo_path')->count();

        return [
            'status' => $this->completionReviewStatus($visit),
            'needs_review' => $this->requiresAcknowledgement($visit),
            'reviewed_at' => $visit->completion_reviewed_at?->toDateTimeString()
                ?: $visit->supervisor_acknowledged_at?->toDateTimeString(),
            'review_note' => $visit->completion_review_note ?: $visit->supervisor_note,
            'reviewed_by' => $reviewer ? [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
            ] : null,
            'photo_count' => $photoCount,
            'checklist_total' => $checklist->count(),
            'checklist_done' => $checklist->where('status', 'done')->count(),
            'checklist_pending' => $checklist->where('status', 'pending')->count(),
            'checklist_blocked' => $checklist->where('status', 'blocked')->count(),
            'has_check_in_location' => $this->locationPayload($visit, 'check_in') !== null,
            'has_check_out_location' => $this->locationPayload($visit, 'check_out') !== null,
            'has_issue' => filled($visit->issue_note) || $visit->status === 'issue_reported',
            'quality_status' => $visit->quality_status,
            'quality_score' => $visit->quality_score,
            'quality_notes' => $visit->quality_notes,
            'quality_follow_up_required' => (bool) $visit->quality_follow_up_required,
            'quality_reviewed_at' => $visit->quality_reviewed_at?->toDateTimeString(),
            'quality_reviewed_by' => $qualityReviewer ? [
                'id' => $qualityReviewer->id,
                'name' => $qualityReviewer->name,
            ] : null,
            'customer_feedback' => $feedback ? [
                'id' => $feedback->id,
                'rating' => $feedback->rating,
                'comment' => $feedback->comment,
                'submitted_at' => $feedback->submitted_at?->toDateTimeString(),
            ] : null,
        ];
    }

    private function locationPayload(Visit $visit, string $prefix): ?array
    {
        $latitude = $visit->getAttribute("{$prefix}_latitude");
        $longitude = $visit->getAttribute("{$prefix}_longitude");

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return [
            'latitude' => (string) $latitude,
            'longitude' => (string) $longitude,
            'accuracy_meters' => $visit->getAttribute("{$prefix}_accuracy_meters"),
            'maps_url' => "https://www.google.com/maps?q={$latitude},{$longitude}",
        ];
    }

    private function publicEvidenceUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    private function serializeAssignment(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'contract_id' => $assignment->contract_id,
            'customer_site_id' => $assignment->customer_site_id,
            'worker_id' => $assignment->worker_id,
            'service_id' => $assignment->service_id,
            'weekday' => $assignment->weekday,
            'starts_at' => $this->time($assignment->starts_at),
            'ends_at' => $this->time($assignment->ends_at),
            'share_percent' => $assignment->share_percent,
            'status' => $assignment->status,
            'team_role' => $assignment->team_role ?? 'main',
            'task_instructions' => $assignment->task_instructions ?? [],
            'contract' => $assignment->contract ? [
                'id' => $assignment->contract->id,
                'reference' => $assignment->contract->reference,
                'customer' => $assignment->contract->customer ? [
                    'id' => $assignment->contract->customer->id,
                    'name' => $assignment->contract->customer->name,
                ] : null,
            ] : null,
            'worker' => $assignment->worker ? $this->serializeWorker($assignment->worker) : null,
            'site' => $assignment->site ? $this->serializeSite($assignment->site) : null,
            'service' => $assignment->service ? [
                'id' => $assignment->service->id,
                'title' => $assignment->service->title,
            ] : null,
        ];
    }

    private function serializeWorker(Worker $worker): array
    {
        return [
            'id' => $worker->id,
            'name' => $worker->name,
            'employee_code' => $worker->employee_code,
            'status' => $worker->status,
            'job_role' => $worker->job_role,
        ];
    }

    private function serializeSite(CustomerSite $site): array
    {
        return [
            'id' => $site->id,
            'name' => $site->name,
            'city' => $site->city,
            'district' => $site->district,
            'customer' => $site->customer ? [
                'id' => $site->customer->id,
                'name' => $site->customer->name,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function audit(Request $request, string $action, Model $model, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
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
