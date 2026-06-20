<?php

namespace App\Http\Middleware;

use App\Models\BookingRequest;
use App\Models\ContractDecision;
use App\Models\PaymentProof;
use App\Models\ServiceRequest;
use App\Models\Visit;
use App\Models\WorkerAvailabilityBlock;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $locale = app()->getLocale();
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';
        $messages = array_replace_recursive(
            config('cleanops.translations.en', []),
            config('cleanops_extra_translations.en', []),
            config("cleanops.translations.{$locale}", []),
            config("cleanops_extra_translations.{$locale}", []),
        );

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only(['id', 'name', 'email', 'role', 'locale']),
                    'permissions' => $user->permissions(),
                ] : null,
                'roles' => Roles::labels(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'app' => [
                'name' => config('app.name'),
                'locale' => $locale,
                'dir' => $dir,
                'supportedLocales' => config('cleanops.supported_locales', ['ar', 'en']),
            ],
            'i18n' => [
                'locale' => $locale,
                'dir' => $dir,
                'messages' => $messages,
            ],
            'notifications' => fn () => $this->notifications($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notifications(Request $request): array
    {
        $user = $request->user();

        if (! $user || (! $user->hasPermission('manage_operations') && ! $user->hasPermission('manage_contracts') && ! $user->hasPermission('manage_finance'))) {
            return [
                'total' => 0,
                'pendingBookingRequests' => 0,
                'pendingServiceRequests' => 0,
                'pendingPaymentProofs' => 0,
                'pendingContractDecisions' => 0,
                'pendingVisitReviews' => 0,
                'pendingWorkerAvailabilityConflicts' => 0,
                'items' => [],
                'bookingRequests' => [],
                'serviceRequests' => [],
                'paymentProofs' => [],
                'contractDecisions' => [],
                'visitReviews' => [],
                'workerAvailabilityConflicts' => [],
            ];
        }

        $canManageContracts = $user->hasPermission('manage_contracts');
        $bookingRequests = BookingRequest::query()
            ->with(['customer', 'site', 'service', 'servicePackage'])
            ->where('status', 'pending')
            ->orderBy('requested_for')
            ->orderBy('starts_at')
            ->limit(5)
            ->get();
        $serviceRequests = ServiceRequest::query()
            ->with(['customer', 'contract.site', 'contract.service'])
            ->whereIn('status', ['pending', 'in_review'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->limit(5)
            ->get();
        $paymentProofs = PaymentProof::query()
            ->with(['customer', 'invoice'])
            ->where('status', 'pending')
            ->orderBy('paid_on')
            ->orderBy('created_at')
            ->limit(5)
            ->get();
        $contractDecisions = $canManageContracts
            ? ContractDecision::query()
                ->with(['customer', 'contract.site', 'contract.service'])
                ->where('status', 'pending_review')
                ->latest()
                ->limit(5)
                ->get()
            : collect();
        $visitReviews = Visit::query()
            ->with(['contract.customer', 'site', 'service', 'worker'])
            ->where('status', 'completed')
            ->where(function ($query): void {
                $query->whereNull('completion_review_status')
                    ->orWhere('completion_review_status', 'pending_review');
            })
            ->latest('scheduled_for')
            ->orderByDesc('starts_at')
            ->limit(5)
            ->get();
        $workerAvailabilityConflicts = $this->workerAvailabilityConflicts();

        $bookingRequestItems = $bookingRequests->map(fn (BookingRequest $bookingRequest): array => [
            'id' => $bookingRequest->id,
            'customer' => $bookingRequest->customer?->name,
            'site' => $bookingRequest->site?->name,
            'service' => $bookingRequest->service?->title,
            'package' => $bookingRequest->servicePackage?->name,
            'requested_for' => $bookingRequest->requested_for?->toDateString(),
            'starts_at' => $this->time($bookingRequest->starts_at),
            'href' => '/app/operations?tab=requests',
        ])->values();
        $serviceRequestItems = $serviceRequests->map(fn (ServiceRequest $serviceRequest): array => [
            'id' => $serviceRequest->id,
            'type' => $serviceRequest->type,
            'status' => $serviceRequest->status,
            'priority' => $serviceRequest->priority,
            'customer' => $serviceRequest->customer?->name,
            'contract' => $serviceRequest->contract?->reference,
            'site' => $serviceRequest->contract?->site?->name,
            'service' => $serviceRequest->contract?->service?->title,
            'requested_for' => $serviceRequest->requested_for?->toDateString(),
            'starts_at' => $this->time($serviceRequest->starts_at),
            'href' => '/app/operations?tab=requests',
        ])->values();
        $paymentProofItems = $paymentProofs->map(fn (PaymentProof $paymentProof): array => [
            'id' => $paymentProof->id,
            'customer' => $paymentProof->customer?->name,
            'invoice' => $paymentProof->invoice?->number,
            'amount_halalas' => $paymentProof->amount_halalas,
            'paid_on' => $paymentProof->paid_on?->toDateString(),
            'href' => '/app/finance',
        ])->values();
        $contractDecisionItems = $contractDecisions->map(fn (ContractDecision $decision): array => [
            'id' => $decision->id,
            'decision' => $decision->decision,
            'status' => $decision->status,
            'customer' => $decision->customer?->name,
            'contract' => $decision->contract?->reference,
            'site' => $decision->contract?->site?->name,
            'service' => $decision->contract?->service?->title,
            'created_at' => $decision->created_at?->toDateTimeString(),
            'href' => $decision->contract_id ? "/app/contracts/{$decision->contract_id}?panel=acceptance" : '/app/contracts',
        ])->values();
        $visitReviewItems = $visitReviews->map(fn (Visit $visit): array => $this->visitReviewNotification($visit))->values();

        return [
            'total' => BookingRequest::query()->where('status', 'pending')->count()
                + ServiceRequest::query()->whereIn('status', ['pending', 'in_review'])->count()
                + PaymentProof::query()->where('status', 'pending')->count()
                + ($canManageContracts ? ContractDecision::query()->where('status', 'pending_review')->count() : 0)
                + Visit::query()
                    ->where('status', 'completed')
                    ->where(function ($query): void {
                        $query->whereNull('completion_review_status')
                            ->orWhere('completion_review_status', 'pending_review');
                    })
                    ->count()
                + $workerAvailabilityConflicts->count(),
            'pendingBookingRequests' => BookingRequest::query()->where('status', 'pending')->count(),
            'pendingServiceRequests' => ServiceRequest::query()->whereIn('status', ['pending', 'in_review'])->count(),
            'pendingPaymentProofs' => PaymentProof::query()->where('status', 'pending')->count(),
            'pendingContractDecisions' => $canManageContracts
                ? ContractDecision::query()->where('status', 'pending_review')->count()
                : 0,
            'pendingVisitReviews' => Visit::query()
                ->where('status', 'completed')
                ->where(function ($query): void {
                    $query->whereNull('completion_review_status')
                        ->orWhere('completion_review_status', 'pending_review');
                })
                ->count(),
            'pendingWorkerAvailabilityConflicts' => $workerAvailabilityConflicts->count(),
            'items' => $this->notificationItems(
                $bookingRequestItems,
                $serviceRequestItems,
                $paymentProofItems,
                $contractDecisionItems,
                $visitReviewItems,
                $workerAvailabilityConflicts,
            ),
            'bookingRequests' => $bookingRequestItems,
            'serviceRequests' => $serviceRequestItems,
            'paymentProofs' => $paymentProofItems,
            'contractDecisions' => $contractDecisionItems,
            'visitReviews' => $visitReviewItems,
            'workerAvailabilityConflicts' => $workerAvailabilityConflicts->values(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $bookingRequests
     * @param  Collection<int, array<string, mixed>>  $serviceRequests
     * @param  Collection<int, array<string, mixed>>  $paymentProofs
     * @param  Collection<int, array<string, mixed>>  $contractDecisions
     * @param  Collection<int, array<string, mixed>>  $visitReviews
     * @param  Collection<int, array<string, mixed>>  $workerAvailabilityConflicts
     * @return list<array<string, mixed>>
     */
    private function notificationItems(
        Collection $bookingRequests,
        Collection $serviceRequests,
        Collection $paymentProofs,
        Collection $contractDecisions,
        Collection $visitReviews,
        Collection $workerAvailabilityConflicts,
    ): array {
        return collect()
            ->concat($serviceRequests->map(fn (array $item): array => [
                ...$item,
                'type' => 'service_request',
                'title_key' => $item['type'] === 'quality_follow_up' ? 'layout.qualityFollowUpNotification' : 'layout.serviceRequestNotification',
                'body' => trim(implode(' / ', array_filter([$item['customer'] ?? null, $item['contract'] ?? null, $item['site'] ?? null]))),
                'rank' => $this->priorityRank((string) $item['priority']),
            ]))
            ->concat($visitReviews->map(fn (array $item): array => [
                ...$item,
                'type' => 'visit_review',
                'priority' => 'high',
                'title_key' => 'layout.visitReviewNotification',
                'body' => trim(implode(' / ', array_filter([$item['contract'] ?? null, $item['site'] ?? null, $item['worker'] ?? null]))),
                'rank' => 1,
            ]))
            ->concat($workerAvailabilityConflicts->map(fn (array $item): array => [
                ...$item,
                'type' => 'worker_availability_conflict',
                'priority' => 'high',
                'title_key' => 'layout.workerAvailabilityConflictNotification',
                'body' => trim(implode(' / ', array_filter([$item['worker'] ?? null, $item['contract'] ?? null, $item['site'] ?? null]))),
                'rank' => 2,
            ]))
            ->concat($bookingRequests->map(fn (array $item): array => [
                ...$item,
                'type' => 'booking_request',
                'priority' => 'normal',
                'title_key' => 'layout.bookingRequestNotification',
                'body' => trim(implode(' / ', array_filter([$item['customer'] ?? null, $item['site'] ?? null, $item['service'] ?? null]))),
                'rank' => 3,
            ]))
            ->concat($paymentProofs->map(fn (array $item): array => [
                ...$item,
                'type' => 'payment_proof',
                'priority' => 'normal',
                'title_key' => 'layout.paymentProofNotification',
                'body' => trim(implode(' / ', array_filter([$item['customer'] ?? null, $item['invoice'] ?? null]))),
                'rank' => 4,
            ]))
            ->concat($contractDecisions->map(fn (array $item): array => [
                ...$item,
                'type' => 'contract_decision',
                'priority' => 'normal',
                'title_key' => 'layout.contractDecisionNotification',
                'body' => trim(implode(' / ', array_filter([$item['customer'] ?? null, $item['contract'] ?? null, $item['site'] ?? null]))),
                'rank' => 5,
            ]))
            ->sortBy([
                ['rank', 'asc'],
                ['id', 'asc'],
            ])
            ->take(12)
            ->map(function (array $item): array {
                unset($item['rank']);

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function workerAvailabilityConflicts(): Collection
    {
        $from = Carbon::today();
        $to = $from->copy()->addDay();
        $visits = Visit::query()
            ->with(['worker', 'contract.site', 'contract.service', 'site'])
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->whereBetween('scheduled_for', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('worker_id')
            ->orderBy('scheduled_for')
            ->orderBy('starts_at')
            ->limit(80)
            ->get();

        if ($visits->isEmpty()) {
            return collect();
        }

        $blocks = WorkerAvailabilityBlock::query()
            ->with('worker')
            ->whereIn('worker_id', $visits->pluck('worker_id')->filter()->unique())
            ->whereIn('date', $visits->pluck('scheduled_for')->map(fn ($date) => $date?->toDateString())->filter()->unique())
            ->whereIn('status', ['unavailable', 'on_leave', 'training'])
            ->get()
            ->groupBy(fn (WorkerAvailabilityBlock $block): string => $block->worker_id.'|'.Carbon::parse($block->date)->toDateString());

        return $visits
            ->map(function (Visit $visit) use ($blocks): ?array {
                $visitDate = $visit->scheduled_for?->toDateString();

                if (! $visitDate) {
                    return null;
                }

                $block = $blocks
                    ->get($visit->worker_id.'|'.$visitDate, collect())
                    ->first(fn (WorkerAvailabilityBlock $candidate): bool => $this->timeRangesOverlap($visit->starts_at, $visit->ends_at, $candidate->starts_at, $candidate->ends_at));

                if (! $block) {
                    return null;
                }

                return [
                    'visit_id' => $visit->id,
                    'worker_id' => $visit->worker_id,
                    'worker' => $visit->worker?->name,
                    'contract' => $visit->contract?->reference,
                    'site' => $visit->site?->name ?? $visit->contract?->site?->name,
                    'service' => $visit->contract?->service?->title,
                    'scheduled_for' => $visitDate,
                    'starts_at' => $this->time($visit->starts_at),
                    'ends_at' => $this->time($visit->ends_at),
                    'block_status' => $block->status,
                    'block_reason' => $block->reason,
                    'href' => "/app/operations?tab=capacity&date={$visitDate}",
                ];
            })
            ->filter()
            ->values();
    }

    private function visitReviewNotification(Visit $visit): array
    {
        $date = $visit->scheduled_for?->toDateString();

        return [
            'visit_id' => $visit->id,
            'contract' => $visit->contract?->reference,
            'customer' => $visit->contract?->customer?->name,
            'site' => $visit->site?->name,
            'service' => $visit->service?->title,
            'worker' => $visit->worker?->name,
            'scheduled_for' => $date,
            'starts_at' => $this->time($visit->starts_at),
            'ends_at' => $this->time($visit->ends_at),
            'href' => $date ? "/app/operations?tab=visits&date={$date}" : '/app/operations?tab=visits',
        ];
    }

    private function time(mixed $time): ?string
    {
        return $time ? substr((string) $time, 0, 5) : null;
    }

    private function timeRangesOverlap(mixed $startsAt, mixed $endsAt, mixed $blockStartsAt, mixed $blockEndsAt): bool
    {
        if (! $blockStartsAt || ! $blockEndsAt) {
            return true;
        }

        $start = $this->time($startsAt);
        $end = $this->time($endsAt);
        $blockStart = $this->time($blockStartsAt);
        $blockEnd = $this->time($blockEndsAt);

        if (! $start || ! $end || ! $blockStart || ! $blockEnd) {
            return true;
        }

        return $start < $blockEnd && $end > $blockStart;
    }

    private function priorityRank(string $priority): int
    {
        return match ($priority) {
            'urgent' => 0,
            'high' => 1,
            'normal' => 3,
            default => 4,
        };
    }
}
