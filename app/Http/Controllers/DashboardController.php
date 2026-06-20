<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Visit;
use App\Models\WorkerRevenueTarget;
use App\Services\Reports\AdminReportBuilder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const TIMELINE_START_MINUTES = 480;

    private const TIMELINE_END_MINUTES = 1200;

    public function __invoke(AdminReportBuilder $reports): Response
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $summary = $reports->summary($monthStart, $monthEnd);
        $todayVisits = Visit::whereDate('scheduled_for', $today)->count();
        $completedToday = Visit::whereDate('scheduled_for', $today)->where('status', 'completed')->count();
        $missedToday = Visit::whereDate('scheduled_for', $today)->where('status', 'missed')->count();
        $workerPerformance = collect($reports->workerPerformance($monthStart, $monthEnd))
            ->take(6)
            ->map(fn (array $worker): array => [
                'id' => $worker['worker_id'],
                'name' => $worker['worker'],
                'status' => $worker['status'],
                'target' => $worker['target_halalas'],
                'actual' => $worker['actual_halalas'],
                'pace' => min(140, $worker['pace_percent']),
                'completed_visits' => $worker['completed_visits'],
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'metrics' => [
                'revenue' => $summary['revenue'],
                'monthlyCollected' => $summary['collected'],
                'openReceivables' => $summary['openReceivables'],
                'overdue' => $summary['overdueReceivables'],
                'heldCheques' => $summary['heldCheques'],
                'workerUtilization' => $summary['workerUtilizationPercent'],
                'grossProfit' => $summary['grossProfit'],
                'grossMarginPercent' => $summary['grossMarginPercent'],
                'materialCost' => $summary['materialCost'],
                'overtimeMinutes' => $summary['overtimeMinutes'],
                'activeContracts' => $summary['activeContracts'],
                'todayVisits' => Visit::whereDate('scheduled_for', $today)->count(),
                'completedToday' => Visit::whereDate('scheduled_for', $today)->where('status', 'completed')->count(),
                'missedToday' => Visit::whereDate('scheduled_for', $today)->where('status', 'missed')->count(),
                'newLeads' => Lead::where('status', 'new')->count(),
            ],
            'dashboardDate' => $today,
            'topStats' => $this->topStats($summary, $today, $todayVisits, $completedToday),
            'collectionTrend' => $reports->collectionTrend($monthStart, $monthEnd),
            'visits' => Visit::query()
                ->with(['worker', 'site.customer', 'service'])
                ->whereDate('scheduled_for', $today)
                ->orderBy('starts_at')
                ->limit(10)
                ->get()
                ->map(fn (Visit $visit): array => [
                    'id' => $visit->id,
                    'time' => substr((string) $visit->starts_at, 0, 5),
                    'status' => $visit->status,
                    'worker' => $visit->worker->name,
                    'site' => $visit->site->name,
                    'customer' => $visit->site->customer->name,
                    'service' => $visit->service?->title ?? 'Contract cleaning',
                ]),
            'visitTimeline' => $this->visitTimeline($today),
            'invoiceSummary' => $this->invoiceSummary(),
            'invoices' => Invoice::query()
                ->with(['customer', 'creditNotes'])
                ->whereNotIn('status', ['paid', 'cancelled', 'void'])
                ->orderBy('due_date')
                ->limit(8)
                ->get()
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'customer' => $invoice->customer->name,
                    'status' => $invoice->status !== 'paid' && $invoice->due_date->lt(now()->startOfDay()) ? 'overdue' : $invoice->status,
                    'due_date' => $invoice->due_date->toDateString(),
                    'balance' => $invoice->balance_halalas,
                ]),
            'overdueInvoices' => $this->overdueInvoices(),
            'onlineWorker' => $this->onlineWorker($today),
            'workerPerformance' => $workerPerformance,
            'targetWindow' => WorkerRevenueTarget::query()->exists() ? [$monthStart, $monthEnd] : null,
            'managementLinks' => [
                'reports' => route('reports.index', absolute: false),
                'finance' => route('finance.index', absolute: false),
                'operations' => route('operations.index', absolute: false),
            ],
        ]);
    }

    /**
     * @param  array<string, int>  $summary
     * @return array<int, array<string, mixed>>
     */
    private function topStats(array $summary, string $today, int $todayVisits, int $completedToday): array
    {
        $presentToday = Visit::whereDate('scheduled_for', $today)
            ->whereIn('status', ['completed', 'in_progress'])
            ->count();
        $attendanceRate = $todayVisits > 0 ? (int) round(($presentToday / $todayVisits) * 100) : 0;
        $expiringContracts = Contract::query()
            ->where('status', 'active')
            ->whereBetween('ends_on', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->count();

        return [
            [
                'key' => 'revenue',
                'label' => 'dashboard.topStats.revenue',
                'value' => $summary['revenue'],
                'format' => 'money',
                'tone' => 'success',
                'helper' => 'dashboard.topStats.thisMonth',
            ],
            [
                'key' => 'overdue',
                'label' => 'dashboard.topStats.overdue',
                'value' => $summary['overdueReceivables'],
                'format' => 'money',
                'tone' => 'danger',
                'helper_value' => $this->overdueInvoices()->count(),
                'helper' => 'dashboard.topStats.overdueCustomers',
            ],
            [
                'key' => 'active_contracts',
                'label' => 'dashboard.topStats.activeContracts',
                'value' => $summary['activeContracts'],
                'format' => 'number',
                'tone' => 'success',
                'helper_value' => $expiringContracts,
                'helper' => 'dashboard.topStats.expiringSoon',
            ],
            [
                'key' => 'attendance_rate',
                'label' => 'dashboard.topStats.attendanceRate',
                'value' => $attendanceRate,
                'format' => 'percent',
                'tone' => $attendanceRate >= 90 ? 'success' : 'warning',
                'helper_value' => "{$presentToday} / {$todayVisits}",
                'helper' => 'dashboard.topStats.present',
            ],
            [
                'key' => 'today_visits',
                'label' => 'dashboard.topStats.todayVisits',
                'value' => $todayVisits,
                'format' => 'number',
                'tone' => 'info',
                'helper_value' => $completedToday,
                'helper' => 'dashboard.topStats.completed',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function visitTimeline(string $today): array
    {
        return Visit::query()
            ->with(['worker', 'site.customer', 'service'])
            ->whereDate('scheduled_for', $today)
            ->orderBy('starts_at')
            ->limit(8)
            ->get()
            ->map(fn (Visit $visit): array => [
                'id' => $visit->id,
                'status' => $visit->status,
                'starts_at' => substr((string) $visit->starts_at, 0, 5),
                'ends_at' => substr((string) $visit->ends_at, 0, 5),
                'timeline_start_percent' => $this->timelineStartPercent((string) $visit->starts_at),
                'timeline_width_percent' => $this->timelineWidthPercent((string) $visit->starts_at, (string) $visit->ends_at),
                'worker' => $visit->worker?->name,
                'worker_count' => $visit->worker_id ? 1 : 0,
                'site' => $visit->site->name,
                'customer' => $visit->site->customer->name,
                'city' => $visit->site->city,
                'district' => $visit->site->district,
                'service' => $visit->service?->title ?? 'Contract cleaning',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function invoiceSummary(): array
    {
        $invoices = Invoice::query()
            ->with('creditNotes')
            ->whereNotIn('status', ['cancelled', 'void'])
            ->get();
        $total = (int) $invoices->sum('gross_total_halalas');
        $paid = (int) $invoices->sum('paid_total_halalas');
        $open = (int) $invoices->sum('balance_halalas');
        $overdue = (int) $invoices
            ->filter(fn (Invoice $invoice): bool => $invoice->status !== 'paid' && $invoice->due_date->lt(now()->startOfDay()))
            ->sum('balance_halalas');
        $pending = max(0, $open - $overdue);

        return [
            'total_halalas' => $total,
            'paid_halalas' => $paid,
            'open_halalas' => $open,
            'pending_halalas' => $pending,
            'overdue_halalas' => $overdue,
            'paid_percent' => $total > 0 ? (int) round(($paid / $total) * 100) : 0,
            'pending_percent' => $total > 0 ? (int) round(($pending / $total) * 100) : 0,
            'overdue_percent' => $total > 0 ? (int) round(($overdue / $total) * 100) : 0,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function overdueInvoices()
    {
        return Invoice::query()
            ->with(['customer', 'creditNotes'])
            ->whereNotIn('status', ['paid', 'cancelled', 'void'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'customer' => $invoice->customer->name,
                'due_date' => $invoice->due_date->toDateString(),
                'days_overdue' => (int) $invoice->due_date->diffInDays(now()->startOfDay()),
                'balance' => $invoice->balance_halalas,
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function onlineWorker(string $today): ?array
    {
        $visit = Visit::query()
            ->with(['worker', 'site.customer', 'service'])
            ->whereDate('scheduled_for', $today)
            ->whereNotNull('worker_id')
            ->orderByRaw("case status when 'in_progress' then 0 when 'completed' then 1 when 'scheduled' then 2 else 3 end")
            ->orderBy('starts_at')
            ->first();

        if (! $visit || ! $visit->worker) {
            return null;
        }

        $progress = match ($visit->status) {
            'completed' => 100,
            'in_progress' => $visit->planned_minutes > 0 ? min(100, (int) round(($visit->actual_minutes / $visit->planned_minutes) * 100)) : 50,
            default => 0,
        };

        return [
            'id' => $visit->worker->id,
            'name' => $visit->worker->name,
            'status' => $visit->worker->status,
            'job_role' => $visit->worker->job_role,
            'current_visit' => [
                'id' => $visit->id,
                'site' => $visit->site->name,
                'customer' => $visit->site->customer->name,
                'service' => $visit->service?->title ?? 'Contract cleaning',
                'starts_at' => substr((string) $visit->starts_at, 0, 5),
                'ends_at' => substr((string) $visit->ends_at, 0, 5),
                'status' => $visit->status,
                'progress_percent' => $progress,
            ],
        ];
    }

    private function timelineStartPercent(string $time): int
    {
        $window = self::TIMELINE_END_MINUTES - self::TIMELINE_START_MINUTES;
        $minutes = $this->timeToMinutes($time);

        return (int) round(max(0, min(100, (($minutes - self::TIMELINE_START_MINUTES) / $window) * 100)));
    }

    private function timelineWidthPercent(string $startsAt, string $endsAt): int
    {
        $window = self::TIMELINE_END_MINUTES - self::TIMELINE_START_MINUTES;
        $duration = max(60, $this->timeToMinutes($endsAt) - $this->timeToMinutes($startsAt));

        return (int) round(max(8, min(100, ($duration / $window) * 100)));
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_pad(explode(':', substr($time, 0, 5)), 2, 0);

        return ((int) $hour * 60) + (int) $minute;
    }
}
