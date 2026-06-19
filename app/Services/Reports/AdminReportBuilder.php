<?php

namespace App\Services\Reports;

use App\Models\Cheque;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Visit;
use App\Models\Worker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminReportBuilder
{
    /**
     * @return array{0: string, 1: string}
     */
    public function normalizeRange(?string $from, ?string $to): array
    {
        return [
            $from ?: now()->startOfMonth()->toDateString(),
            $to ?: now()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $from, string $to): array
    {
        return [
            'summary' => $this->summary($from, $to),
            'finance' => $this->finance($from, $to),
            'operations' => $this->operations($from, $to),
            'workerPerformance' => $this->workerPerformance($from, $to),
            'contractRevenue' => $this->contractRevenue($from, $to),
            'profitability' => $this->profitability($from, $to),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function summary(string $from, string $to): array
    {
        [$start, $end] = $this->periodBounds($from, $to);
        $openInvoices = $this->openInvoices();
        $periodVisits = Visit::query()
            ->whereBetween('scheduled_for', [$from, $to])
            ->get(['id', 'status']);
        $visitCount = $periodVisits->count();
        $completedVisits = $periodVisits->where('status', 'completed')->count();
        $profitTotals = $this->visitProfitTotals($from, $to);
        $expenseTotals = $this->expenseTotals($from, $to);
        $grossProfit = $profitTotals['gross_profit_halalas'] - $expenseTotals['direct_cost_halalas'];
        $netProfit = $grossProfit - $expenseTotals['operating_expenses_halalas'];

        return [
            'revenue' => (int) Invoice::query()
                ->whereBetween('issue_date', [$from, $to])
                ->sum('gross_total_halalas'),
            'collected' => (int) Payment::query()
                ->whereBetween('received_at', [$start, $end])
                ->sum('amount_halalas'),
            'openReceivables' => (int) $openInvoices->sum('balance_halalas'),
            'overdueReceivables' => (int) $openInvoices
                ->filter(fn (Invoice $invoice): bool => $invoice->due_date->lt(now()->startOfDay()))
                ->sum('balance_halalas'),
            'heldCheques' => (int) Cheque::query()->where('status', 'held')->sum('amount_halalas'),
            'activeContracts' => Contract::query()->where('status', 'active')->count(),
            'completedVisits' => $completedVisits,
            'missedVisits' => $periodVisits->where('status', 'missed')->count(),
            'workerUtilizationPercent' => $visitCount > 0 ? (int) round(($completedVisits / $visitCount) * 100) : 0,
            'newLeads' => Lead::query()->where('status', 'new')->count(),
            'plannedRevenue' => $profitTotals['planned_revenue_halalas'],
            'billableOvertime' => $profitTotals['billable_overtime_halalas'],
            'directExpenses' => $expenseTotals['direct_cost_halalas'],
            'operatingExpenses' => $expenseTotals['operating_expenses_halalas'],
            'totalExpenses' => $profitTotals['labor_cost_halalas'] + $profitTotals['material_cost_halalas'] + $expenseTotals['direct_cost_halalas'] + $expenseTotals['operating_expenses_halalas'],
            'grossProfit' => $grossProfit,
            'grossMarginPercent' => $this->marginPercent($grossProfit, $profitTotals['revenue_halalas']),
            'netProfit' => $netProfit,
            'netMarginPercent' => $this->marginPercent($netProfit, $profitTotals['revenue_halalas']),
            'laborCost' => $profitTotals['labor_cost_halalas'],
            'materialCost' => $profitTotals['material_cost_halalas'],
            'overtimeMinutes' => $profitTotals['overtime_minutes'],
            'pendingOvertimeVisits' => $profitTotals['pending_overtime_visits'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function finance(string $from, string $to): array
    {
        [$start, $end] = $this->periodBounds($from, $to);
        $invoices = Invoice::query()
            ->with(['customer', 'contract', 'creditNotes'])
            ->whereBetween('issue_date', [$from, $to])
            ->orderBy('due_date')
            ->get();

        return [
            'aging' => $this->aging($invoices),
            'paymentsByMethod' => Payment::query()
                ->select('method', DB::raw('SUM(amount_halalas) as amount_halalas'), DB::raw('COUNT(*) as count'))
                ->whereBetween('received_at', [$start, $end])
                ->groupBy('method')
                ->orderByDesc('amount_halalas')
                ->get()
                ->map(fn (Payment $payment): array => [
                    'method' => $payment->method,
                    'amount_halalas' => (int) $payment->amount_halalas,
                    'amount_sar' => $this->formatSar((int) $payment->amount_halalas),
                    'count' => (int) $payment->count,
                ])
                ->values()
                ->all(),
            'chequeStatuses' => Cheque::query()
                ->select('status', DB::raw('SUM(amount_halalas) as amount_halalas'), DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn (Cheque $cheque): array => [
                    'status' => $cheque->status,
                    'amount_halalas' => (int) $cheque->amount_halalas,
                    'amount_sar' => $this->formatSar((int) $cheque->amount_halalas),
                    'count' => (int) $cheque->count,
                ])
                ->values()
                ->all(),
            'expenseSummary' => $this->expenseTotals($from, $to),
            'expensesByCategory' => $this->expensesByCategory($from, $to),
            'expenses' => $this->expenseRows($from, $to),
            'invoices' => $invoices
                ->map(fn (Invoice $invoice): array => $this->invoiceRow($invoice))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function operations(string $from, string $to): array
    {
        $statuses = [
            'scheduled' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'missed' => 0,
            'issue_reported' => 0,
        ];

        Visit::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('scheduled_for', [$from, $to])
            ->groupBy('status')
            ->get()
            ->each(function (Visit $visit) use (&$statuses): void {
                $statuses[$visit->status] = (int) $visit->total;
            });

        return [
            'statuses' => $statuses,
            'dailyVisits' => $this->dailyVisits($from, $to),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function workerPerformance(string $from, string $to): array
    {
        return Worker::query()
            ->with([
                'targets' => fn ($query) => $query
                    ->whereDate('period_start', '<=', $to)
                    ->whereDate('period_end', '>=', $from),
                'visits' => fn ($query) => $query
                    ->whereBetween('scheduled_for', [$from, $to]),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Worker $worker) use ($from, $to): array {
                $actual = $this->workerActualHalalas($worker, $from, $to);
                $target = (int) $worker->targets->sum('target_halalas');
                $visits = $worker->visits;
                $completed = $visits->where('status', 'completed')->count();
                $visitCount = $visits->count();

                return [
                    'worker_id' => $worker->id,
                    'worker' => $worker->name,
                    'status' => $worker->status,
                    'target_halalas' => $target,
                    'target_sar' => $this->formatSar($target),
                    'actual_halalas' => $actual,
                    'actual_sar' => $this->formatSar($actual),
                    'pace_percent' => $target > 0 ? (int) round(($actual / $target) * 100) : 0,
                    'completed_visits' => $completed,
                    'visits_count' => $visitCount,
                    'utilization_percent' => $visitCount > 0 ? (int) round(($completed / $visitCount) * 100) : 0,
                ];
            })
            ->filter(fn (array $row): bool => $row['target_halalas'] > 0 || $row['actual_halalas'] > 0 || $row['visits_count'] > 0)
            ->sortByDesc('actual_halalas')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function contractRevenue(string $from, string $to): array
    {
        return Contract::query()
            ->with([
                'customer',
                'invoices' => fn ($query) => $query
                    ->with(['payments' => fn ($paymentQuery) => $paymentQuery->whereBetween('received_at', $this->periodBounds($from, $to)), 'creditNotes'])
                    ->whereBetween('issue_date', [$from, $to]),
            ])
            ->where(fn ($query) => $query
                ->where('status', 'active')
                ->orWhereHas('invoices', fn ($invoiceQuery) => $invoiceQuery->whereBetween('issue_date', [$from, $to]))
            )
            ->orderBy('reference')
            ->get()
            ->map(function (Contract $contract): array {
                $invoiced = (int) $contract->invoices->sum('gross_total_halalas');
                $collected = (int) $contract->invoices->sum(fn (Invoice $invoice): int => (int) $invoice->payments->sum('amount_halalas'));
                $balance = (int) $contract->invoices->sum('balance_halalas');

                return [
                    'id' => $contract->id,
                    'reference' => $contract->reference,
                    'customer' => $contract->customer?->name,
                    'status' => $contract->status,
                    'monthly_fee_halalas' => $contract->monthly_fee_halalas,
                    'monthly_fee_sar' => $this->formatSar($contract->monthly_fee_halalas),
                    'invoiced_halalas' => $invoiced,
                    'invoiced_sar' => $this->formatSar($invoiced),
                    'collected_halalas' => $collected,
                    'collected_sar' => $this->formatSar($collected),
                    'balance_halalas' => $balance,
                    'balance_sar' => $this->formatSar($balance),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function profitability(string $from, string $to): array
    {
        $visits = $this->profitabilityVisits($from, $to);
        $expenseTotals = $this->expenseTotals($from, $to);

        return [
            'totals' => $this->profitabilityTotals($visits, $expenseTotals),
            'byContract' => $this->profitabilityByContract($visits),
            'byCustomer' => $this->profitabilityByCustomer($visits),
            'byWorker' => $this->profitabilityByWorker($visits),
            'byService' => $this->profitabilityByService($visits),
            'materialUsage' => $this->materialUsage($visits),
            'overtimeTrend' => $this->overtimeTrend($visits),
            'contractPerformance' => $this->contractPerformance($visits),
        ];
    }

    /**
     * @return array<int, array<string>>
     */
    public function csvRows(string $report, string $from, string $to): array
    {
        return match ($report) {
            'finance' => [
                ['Invoice', 'Customer', 'Due Date', 'Gross SAR', 'Paid SAR', 'Balance SAR', 'Status'],
                ...collect($this->finance($from, $to)['invoices'])
                    ->map(fn (array $invoice): array => [
                        $invoice['number'],
                        $invoice['customer'],
                        $invoice['due_date'],
                        $invoice['gross_total_sar'],
                        $invoice['paid_total_sar'],
                        $invoice['balance_sar'],
                        $invoice['status'],
                    ])
                    ->all(),
            ],
            'operations' => [
                ['Date', 'Scheduled', 'In Progress', 'Completed', 'Missed', 'Issues'],
                ...collect($this->operations($from, $to)['dailyVisits'])
                    ->map(fn (array $day): array => [
                        $day['date'],
                        (string) $day['scheduled'],
                        (string) $day['in_progress'],
                        (string) $day['completed'],
                        (string) $day['missed'],
                        (string) $day['issue_reported'],
                    ])
                    ->all(),
            ],
            'workers' => [
                ['Worker', 'Target SAR', 'Actual SAR', 'Pace %', 'Completed Visits', 'Utilization %'],
                ...collect($this->workerPerformance($from, $to))
                    ->map(fn (array $worker): array => [
                        $worker['worker'],
                        $worker['target_sar'],
                        $worker['actual_sar'],
                        (string) $worker['pace_percent'],
                        (string) $worker['completed_visits'],
                        (string) $worker['utilization_percent'],
                    ])
                    ->all(),
            ],
            'contracts' => [
                ['Contract', 'Customer', 'Monthly SAR', 'Invoiced SAR', 'Collected SAR', 'Balance SAR', 'Status'],
                ...collect($this->contractRevenue($from, $to))
                    ->map(fn (array $contract): array => [
                        $contract['reference'],
                        $contract['customer'] ?? '',
                        $contract['monthly_fee_sar'],
                        $contract['invoiced_sar'],
                        $contract['collected_sar'],
                        $contract['balance_sar'],
                        $contract['status'],
                    ])
                    ->all(),
            ],
            'profitability' => [
                ['Contract', 'Customer', 'Revenue SAR', 'Labor SAR', 'Materials SAR', 'Gross Profit SAR', 'Margin %', 'Completed Visits', 'Overtime Minutes'],
                ...collect($this->profitability($from, $to)['byContract'])
                    ->map(fn (array $contract): array => [
                        $contract['reference'],
                        $contract['customer'] ?? '',
                        $contract['revenue_sar'],
                        $contract['labor_cost_sar'],
                        $contract['material_cost_sar'],
                        $contract['gross_profit_sar'],
                        (string) $contract['gross_margin_percent'],
                        (string) $contract['completed_visits'],
                        (string) $contract['overtime_minutes'],
                    ])
                    ->all(),
            ],
            default => [],
        };
    }

    /**
     * @return array<int, array{label: string, billed: int, collected: int}>
     */
    public function collectionTrend(string $from, string $to): array
    {
        [$start, $end] = $this->periodBounds($from, $to);
        $billed = Invoice::query()
            ->select('issue_date', DB::raw('SUM(gross_total_halalas) as amount'))
            ->whereBetween('issue_date', [$from, $to])
            ->groupBy('issue_date')
            ->pluck('amount', 'issue_date');
        $collected = Payment::query()
            ->select(DB::raw('DATE(received_at) as paid_date'), DB::raw('SUM(amount_halalas) as amount'))
            ->whereBetween('received_at', [$start, $end])
            ->groupBy('paid_date')
            ->pluck('amount', 'paid_date');

        return collect($this->periodDates($from, $to))
            ->map(fn (string $date): array => [
                'label' => Carbon::parse($date)->format('M j'),
                'billed' => (int) ($billed[$date] ?? 0),
                'collected' => (int) ($collected[$date] ?? 0),
            ])
            ->all();
    }

    public function formatSar(int $halalas): string
    {
        return number_format($halalas / 100, 2, '.', '');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodBounds(string $from, string $to): array
    {
        return [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
        ];
    }

    private function openInvoices()
    {
        return Invoice::query()
            ->with('creditNotes')
            ->whereNotIn('status', ['paid', 'cancelled', 'void'])
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->balance_halalas > 0)
            ->values();
    }

    /**
     * @param  iterable<int, Invoice>  $invoices
     * @return array{current: int, oneToThirty: int, thirtyOneToSixty: int, sixtyPlus: int}
     */
    private function aging(iterable $invoices): array
    {
        $aging = [
            'current' => 0,
            'oneToThirty' => 0,
            'thirtyOneToSixty' => 0,
            'sixtyPlus' => 0,
        ];

        foreach ($invoices as $invoice) {
            $bucket = $this->agingBucket($invoice);
            $aging[$bucket] += $invoice->balance_halalas;
        }

        return $aging;
    }

    private function agingBucket(Invoice $invoice): string
    {
        if ($invoice->balance_halalas <= 0 || $invoice->due_date->gte(now()->startOfDay())) {
            return 'current';
        }

        $days = (int) $invoice->due_date->diffInDays(now()->startOfDay());

        if ($days <= 30) {
            return 'oneToThirty';
        }

        if ($days <= 60) {
            return 'thirtyOneToSixty';
        }

        return 'sixtyPlus';
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRow(Invoice $invoice): array
    {
        $status = $invoice->status !== 'paid' && $invoice->balance_halalas > 0 && $invoice->due_date->lt(now()->startOfDay())
            ? 'overdue'
            : $invoice->status;

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'customer' => $invoice->customer?->name,
            'contract' => $invoice->contract?->reference,
            'status' => $status,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'gross_total_halalas' => $invoice->gross_total_halalas,
            'gross_total_sar' => $this->formatSar($invoice->gross_total_halalas),
            'paid_total_halalas' => $invoice->paid_total_halalas,
            'paid_total_sar' => $this->formatSar($invoice->paid_total_halalas),
            'balance_halalas' => $invoice->balance_halalas,
            'balance_sar' => $this->formatSar($invoice->balance_halalas),
        ];
    }

    private function workerActualHalalas(Worker $worker, string $from, string $to): int
    {
        $actual = DB::table('assignments')
            ->join('contracts', 'assignments.contract_id', '=', 'contracts.id')
            ->where('assignments.worker_id', $worker->id)
            ->where('assignments.status', 'active')
            ->where('contracts.status', 'active')
            ->whereDate('contracts.starts_on', '<=', $to)
            ->where(fn ($query) => $query
                ->whereNull('contracts.ends_on')
                ->orWhereDate('contracts.ends_on', '>=', $from)
            )
            ->sum(DB::raw('(contracts.monthly_fee_halalas * assignments.share_percent) / 100'));

        return (int) round((float) $actual);
    }

    /**
     * @return array<string, int>
     */
    private function visitProfitTotals(string $from, string $to): array
    {
        $totals = Visit::query()
            ->whereBetween('scheduled_for', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(planned_revenue_halalas), 0) as planned_revenue_halalas,
                COALESCE(SUM(billable_overtime_halalas), 0) as billable_overtime_halalas,
                COALESCE(SUM(labor_cost_halalas), 0) as labor_cost_halalas,
                COALESCE(SUM(material_cost_halalas), 0) as material_cost_halalas,
                COALESCE(SUM(gross_profit_halalas), 0) as gross_profit_halalas,
                COALESCE(SUM(overtime_minutes), 0) as overtime_minutes,
                SUM(CASE WHEN overtime_status = "pending_approval" THEN 1 ELSE 0 END) as pending_overtime_visits
            ')
            ->first();

        $revenue = (int) $totals->planned_revenue_halalas + (int) $totals->billable_overtime_halalas;
        $grossProfit = (int) $totals->gross_profit_halalas;

        return [
            'planned_revenue_halalas' => (int) $totals->planned_revenue_halalas,
            'billable_overtime_halalas' => (int) $totals->billable_overtime_halalas,
            'revenue_halalas' => $revenue,
            'labor_cost_halalas' => (int) $totals->labor_cost_halalas,
            'material_cost_halalas' => (int) $totals->material_cost_halalas,
            'gross_profit_halalas' => $grossProfit,
            'gross_margin_percent' => $this->marginPercent($grossProfit, $revenue),
            'overtime_minutes' => (int) $totals->overtime_minutes,
            'pending_overtime_visits' => (int) $totals->pending_overtime_visits,
        ];
    }

    /**
     * @return Collection<int, Visit>
     */
    private function profitabilityVisits(string $from, string $to): Collection
    {
        return Visit::query()
            ->with(['contract.customer', 'site.customer', 'service', 'worker'])
            ->whereBetween('scheduled_for', [$from, $to])
            ->orderBy('scheduled_for')
            ->get();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return array<string, mixed>
     */
    private function profitabilityTotals(Collection $visits, array $expenseTotals): array
    {
        $aggregate = $this->profitAggregate($visits);
        $grossProfit = $aggregate['gross_profit_halalas'] - $expenseTotals['direct_cost_halalas'];
        $netProfit = $grossProfit - $expenseTotals['operating_expenses_halalas'];

        return [
            ...$aggregate,
            'direct_expenses_halalas' => $expenseTotals['direct_cost_halalas'],
            'direct_expenses_sar' => $this->formatSar($expenseTotals['direct_cost_halalas']),
            'operating_expenses_halalas' => $expenseTotals['operating_expenses_halalas'],
            'operating_expenses_sar' => $this->formatSar($expenseTotals['operating_expenses_halalas']),
            'gross_profit_halalas' => $grossProfit,
            'gross_profit_sar' => $this->formatSar($grossProfit),
            'gross_margin_percent' => $this->marginPercent($grossProfit, $aggregate['revenue_halalas']),
            'net_profit_halalas' => $netProfit,
            'net_profit_sar' => $this->formatSar($netProfit),
            'net_margin_percent' => $this->marginPercent($netProfit, $aggregate['revenue_halalas']),
            'visits_count' => $visits->count(),
            'completed_visits' => $visits->where('status', 'completed')->count(),
            'average_profit_per_visit_halalas' => $visits->count() > 0 ? (int) round($grossProfit / $visits->count()) : 0,
        ];
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, mixed>>
     */
    private function profitabilityByContract(Collection $visits): array
    {
        return $visits
            ->groupBy(fn (Visit $visit): string => (string) ($visit->contract_id ?: 'none'))
            ->map(function (Collection $contractVisits): array {
                /** @var Visit $first */
                $first = $contractVisits->first();

                return [
                    'id' => $first->contract?->id,
                    'reference' => $first->contract?->reference ?? 'No contract',
                    'customer' => $first->contract?->customer?->name ?? $first->site?->customer?->name,
                    'status' => $first->contract?->status ?? 'unassigned',
                    'worker_count' => $contractVisits->pluck('worker_id')->filter()->unique()->count(),
                    ...$this->profitAggregate($contractVisits),
                ];
            })
            ->sortByDesc('gross_profit_halalas')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, mixed>>
     */
    private function profitabilityByCustomer(Collection $visits): array
    {
        return $visits
            ->groupBy(fn (Visit $visit): string => (string) ($visit->contract?->customer_id ?? $visit->site?->customer_id ?? 'none'))
            ->map(function (Collection $customerVisits): array {
                /** @var Visit $first */
                $first = $customerVisits->first();

                return [
                    'id' => $first->contract?->customer_id ?? $first->site?->customer_id,
                    'customer' => $first->contract?->customer?->name ?? $first->site?->customer?->name ?? 'No customer',
                    ...$this->profitAggregate($customerVisits),
                ];
            })
            ->sortByDesc('gross_profit_halalas')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, mixed>>
     */
    private function profitabilityByWorker(Collection $visits): array
    {
        return $visits
            ->filter(fn (Visit $visit): bool => $visit->worker !== null)
            ->groupBy(fn (Visit $visit): string => (string) $visit->worker_id)
            ->map(function (Collection $workerVisits): array {
                /** @var Visit $first */
                $first = $workerVisits->first();
                $aggregate = $this->profitAggregate($workerVisits);
                $actualMinutes = (int) $workerVisits->sum('actual_minutes');
                $profitPerHour = $actualMinutes > 0
                    ? (int) round($aggregate['gross_profit_halalas'] / ($actualMinutes / 60))
                    : 0;

                return [
                    'worker_id' => $first->worker_id,
                    'worker' => $first->worker?->name,
                    'status' => $first->worker?->status,
                    'actual_minutes' => $actualMinutes,
                    'profit_per_hour_halalas' => $profitPerHour,
                    'profit_per_hour_sar' => $this->formatSar($profitPerHour),
                    ...$aggregate,
                ];
            })
            ->sortByDesc('gross_profit_halalas')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, mixed>>
     */
    private function profitabilityByService(Collection $visits): array
    {
        return $visits
            ->groupBy(fn (Visit $visit): string => (string) ($visit->service_id ?: 'none'))
            ->map(function (Collection $serviceVisits): array {
                /** @var Visit $first */
                $first = $serviceVisits->first();

                return [
                    'service_id' => $first->service_id,
                    'service' => $first->service?->title ?? 'No service',
                    'category' => $first->service?->category,
                    ...$this->profitAggregate($serviceVisits),
                ];
            })
            ->sortByDesc('gross_profit_halalas')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, mixed>>
     */
    private function materialUsage(Collection $visits): array
    {
        return $visits
            ->flatMap(function (Visit $visit): array {
                return collect($visit->materials_used ?? [])
                    ->map(fn (array $item): array => [
                        'name' => trim((string) ($item['name'] ?? '')),
                        'quantity' => filled($item['quantity'] ?? null) ? trim((string) $item['quantity']) : null,
                        'cost_halalas' => (int) ($item['cost_halalas'] ?? 0),
                        'visit_id' => $visit->id,
                    ])
                    ->filter(fn (array $item): bool => $item['name'] !== '')
                    ->values()
                    ->all();
            })
            ->groupBy(fn (array $item): string => mb_strtolower($item['name']))
            ->map(function (Collection $items): array {
                $first = $items->first();
                $cost = (int) $items->sum('cost_halalas');

                return [
                    'name' => $first['name'],
                    'rows_count' => $items->count(),
                    'visits_count' => $items->pluck('visit_id')->unique()->count(),
                    'total_cost_halalas' => $cost,
                    'total_cost_sar' => $this->formatSar($cost),
                    'quantities' => $items->pluck('quantity')->filter()->unique()->values()->all(),
                ];
            })
            ->sortByDesc('total_cost_halalas')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, mixed>>
     */
    private function overtimeTrend(Collection $visits): array
    {
        return $visits
            ->filter(fn (Visit $visit): bool => (int) $visit->overtime_minutes > 0)
            ->groupBy(fn (Visit $visit): string => $visit->scheduled_for->toDateString())
            ->map(function (Collection $dayVisits, string $date): array {
                $approved = $dayVisits->where('overtime_status', 'approved')->sum('overtime_minutes');
                $pending = $dayVisits->where('overtime_status', 'pending_approval')->sum('overtime_minutes');
                $rejected = $dayVisits->where('overtime_status', 'rejected')->sum('overtime_minutes');
                $billable = (int) $dayVisits->sum('billable_overtime_halalas');

                return [
                    'date' => $date,
                    'label' => Carbon::parse($date)->format('M j'),
                    'visits_count' => $dayVisits->count(),
                    'overtime_minutes' => (int) $dayVisits->sum('overtime_minutes'),
                    'approved_minutes' => (int) $approved,
                    'pending_minutes' => (int) $pending,
                    'rejected_minutes' => (int) $rejected,
                    'billable_overtime_halalas' => $billable,
                    'billable_overtime_sar' => $this->formatSar($billable),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, mixed>>
     */
    private function contractPerformance(Collection $visits): array
    {
        return collect($this->profitabilityByContract($visits))
            ->map(function (array $contract): array {
                $visitsCount = max(1, (int) $contract['visits_count']);
                $completed = (int) $contract['completed_visits'];
                $overtimeVisits = (int) $contract['overtime_visits'];

                return [
                    ...$contract,
                    'completion_rate_percent' => (int) round(($completed / $visitsCount) * 100),
                    'overtime_rate_percent' => (int) round(($overtimeVisits / $visitsCount) * 100),
                    'average_profit_per_visit_halalas' => (int) round($contract['gross_profit_halalas'] / $visitsCount),
                    'average_profit_per_visit_sar' => $this->formatSar((int) round($contract['gross_profit_halalas'] / $visitsCount)),
                ];
            })
            ->sortByDesc('gross_profit_halalas')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return array<string, mixed>
     */
    private function profitAggregate(Collection $visits): array
    {
        $plannedRevenue = (int) $visits->sum('planned_revenue_halalas');
        $billableOvertime = (int) $visits->sum('billable_overtime_halalas');
        $revenue = $plannedRevenue + $billableOvertime;
        $laborCost = (int) $visits->sum('labor_cost_halalas');
        $materialCost = (int) $visits->sum('material_cost_halalas');
        $grossProfit = (int) $visits->sum('gross_profit_halalas');

        return [
            'visits_count' => $visits->count(),
            'completed_visits' => $visits->where('status', 'completed')->count(),
            'planned_revenue_halalas' => $plannedRevenue,
            'planned_revenue_sar' => $this->formatSar($plannedRevenue),
            'billable_overtime_halalas' => $billableOvertime,
            'billable_overtime_sar' => $this->formatSar($billableOvertime),
            'revenue_halalas' => $revenue,
            'revenue_sar' => $this->formatSar($revenue),
            'labor_cost_halalas' => $laborCost,
            'labor_cost_sar' => $this->formatSar($laborCost),
            'material_cost_halalas' => $materialCost,
            'material_cost_sar' => $this->formatSar($materialCost),
            'gross_profit_halalas' => $grossProfit,
            'gross_profit_sar' => $this->formatSar($grossProfit),
            'gross_margin_percent' => $this->marginPercent($grossProfit, $revenue),
            'overtime_minutes' => (int) $visits->sum('overtime_minutes'),
            'overtime_visits' => $visits->filter(fn (Visit $visit): bool => (int) $visit->overtime_minutes > 0)->count(),
            'pending_overtime_visits' => $visits->where('overtime_status', 'pending_approval')->count(),
        ];
    }

    private function marginPercent(int $grossProfit, int $revenue): int
    {
        return $revenue > 0 ? (int) round(($grossProfit / $revenue) * 100) : 0;
    }

    /**
     * @return array{direct_cost_halalas: int, operating_expenses_halalas: int, vat_halalas: int, total_halalas: int}
     */
    private function expenseTotals(string $from, string $to): array
    {
        $expenses = $this->reportableExpenses($from, $to)->get();
        $direct = (int) $expenses->where('expense_type', 'direct_cost')->sum('amount_halalas');
        $operating = (int) $expenses->where('expense_type', 'operating_expense')->sum('amount_halalas');

        return [
            'direct_cost_halalas' => $direct,
            'operating_expenses_halalas' => $operating,
            'vat_halalas' => (int) $expenses->sum('vat_halalas'),
            'total_halalas' => $direct + $operating,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function expensesByCategory(string $from, string $to): array
    {
        return $this->reportableExpenses($from, $to)
            ->select('category', 'expense_type', DB::raw('SUM(amount_halalas) as amount_halalas'), DB::raw('SUM(vat_halalas) as vat_halalas'), DB::raw('COUNT(*) as count'))
            ->groupBy('category', 'expense_type')
            ->orderByDesc('amount_halalas')
            ->get()
            ->map(fn (Expense $expense): array => [
                'category' => $expense->category,
                'expense_type' => $expense->expense_type,
                'amount_halalas' => (int) $expense->amount_halalas,
                'amount_sar' => $this->formatSar((int) $expense->amount_halalas),
                'vat_halalas' => (int) $expense->vat_halalas,
                'vat_sar' => $this->formatSar((int) $expense->vat_halalas),
                'count' => (int) $expense->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function expenseRows(string $from, string $to): array
    {
        return $this->reportableExpenses($from, $to)
            ->with(['customer', 'contract'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date ? substr((string) $expense->expense_date, 0, 10) : null,
                'expense_type' => $expense->expense_type,
                'category' => $expense->category,
                'vendor' => $expense->vendor,
                'amount_halalas' => $expense->amount_halalas,
                'amount_sar' => $this->formatSar($expense->amount_halalas),
                'vat_halalas' => $expense->vat_halalas,
                'vat_sar' => $this->formatSar($expense->vat_halalas),
                'status' => $expense->status,
                'customer' => $expense->customer?->name,
                'contract' => $expense->contract?->reference,
            ])
            ->values()
            ->all();
    }

    private function reportableExpenses(string $from, string $to)
    {
        return Expense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->whereIn('status', ['approved', 'paid']);
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function dailyVisits(string $from, string $to): array
    {
        $counts = Visit::query()
            ->select('scheduled_for', 'status', DB::raw('COUNT(*) as total'))
            ->whereBetween('scheduled_for', [$from, $to])
            ->groupBy('scheduled_for', 'status')
            ->get()
            ->groupBy(fn (Visit $visit): string => $visit->scheduled_for->toDateString());

        return collect($this->periodDates($from, $to))
            ->map(function (string $date) use ($counts): array {
                $statusCounts = [
                    'scheduled' => 0,
                    'in_progress' => 0,
                    'completed' => 0,
                    'missed' => 0,
                    'issue_reported' => 0,
                ];

                foreach ($counts->get($date, collect()) as $visit) {
                    $statusCounts[$visit->status] = (int) $visit->total;
                }

                return [
                    'date' => $date,
                    'label' => Carbon::parse($date)->format('M j'),
                    ...$statusCounts,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function periodDates(string $from, string $to): array
    {
        $dates = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
