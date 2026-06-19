<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Visit;
use App\Models\WorkerRevenueTarget;
use App\Services\Reports\AdminReportBuilder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AdminReportBuilder $reports): Response
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $summary = $reports->summary($monthStart, $monthEnd);
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
            'workerPerformance' => $workerPerformance,
            'targetWindow' => WorkerRevenueTarget::query()->exists() ? [$monthStart, $monthEnd] : null,
            'managementLinks' => [
                'reports' => route('reports.index', absolute: false),
                'finance' => route('finance.index', absolute: false),
                'operations' => route('operations.index', absolute: false),
            ],
        ]);
    }
}
