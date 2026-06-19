<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Cheque;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Models\Worker;
use App\Models\WorkerRevenueTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminReportsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_owner_views_management_dashboard_with_real_phase_six_metrics(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 10:00:00');

        $owner = User::factory()->create(['role' => 'owner']);
        $context = $this->seedManagementScenario();

        $this->actingAs($owner)->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('metrics.revenue', 100000)
                ->where('metrics.monthlyCollected', 25000)
                ->where('metrics.openReceivables', 75000)
                ->where('metrics.overdue', 75000)
                ->where('metrics.heldCheques', 30000)
                ->where('metrics.workerUtilization', 50)
                ->where('metrics.grossProfit', 77500)
                ->where('metrics.grossMarginPercent', 74)
                ->where('metrics.materialCost', 7500)
                ->where('metrics.overtimeMinutes', 30)
                ->where('metrics.completedToday', 1)
                ->where('workerPerformance.0.name', 'A6 Worker')
                ->where('workerPerformance.0.target', 120000)
                ->where('workerPerformance.0.actual', 60000)
                ->where('workerPerformance.0.pace', 50)
                ->where('managementLinks.reports', '/app/reports')
                ->where('invoices.0.number', 'INV-A6-001')
            );

        $this->assertSame('CON-A6-001', $context['contract']->reference);
    }

    public function test_owner_views_admin_reports_with_finance_operations_worker_and_contract_summaries(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 10:00:00');

        $owner = User::factory()->create(['role' => 'owner']);
        $context = $this->seedManagementScenario();

        $this->actingAs($owner)->get('/app/reports?from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Index')
                ->where('filters.from', '2026-06-01')
                ->where('filters.to', '2026-06-30')
                ->where('summary.revenue', 100000)
                ->where('summary.collected', 25000)
                ->where('summary.openReceivables', 75000)
                ->where('summary.overdueReceivables', 75000)
                ->where('summary.activeContracts', 1)
                ->where('summary.completedVisits', 1)
                ->where('summary.workerUtilizationPercent', 50)
                ->where('summary.plannedRevenue', 100000)
                ->where('summary.grossProfit', 77500)
                ->where('summary.grossMarginPercent', 74)
                ->where('summary.materialCost', 7500)
                ->where('summary.overtimeMinutes', 30)
                ->where('finance.aging.oneToThirty', 75000)
                ->where('finance.paymentsByMethod.0.method', 'bank_transfer')
                ->where('finance.paymentsByMethod.0.amount_halalas', 25000)
                ->where('operations.statuses.completed', 1)
                ->where('operations.statuses.scheduled', 1)
                ->where('workerPerformance.0.worker', 'A6 Worker')
                ->where('workerPerformance.0.target_halalas', 120000)
                ->where('workerPerformance.0.actual_halalas', 60000)
                ->where('contractRevenue.0.reference', 'CON-A6-001')
                ->where('contractRevenue.0.monthly_fee_halalas', 100000)
                ->where('contractRevenue.0.invoiced_halalas', 100000)
                ->where('contractRevenue.0.collected_halalas', 25000)
                ->where('contractRevenue.0.balance_halalas', 75000)
                ->where('profitability.totals.planned_revenue_halalas', 100000)
                ->where('profitability.totals.revenue_halalas', 105000)
                ->where('profitability.totals.gross_profit_halalas', 77500)
                ->where('profitability.totals.gross_margin_percent', 74)
                ->where('profitability.byContract.0.reference', 'CON-A6-001')
                ->where('profitability.byContract.0.gross_profit_halalas', 77500)
                ->where('profitability.byCustomer.0.customer', 'A6 Customer')
                ->where('profitability.byWorker.0.worker', 'A6 Worker')
                ->where('profitability.byWorker.0.profit_per_hour_halalas', 17222)
                ->where('profitability.byService.0.service', 'A6 Cleaning')
                ->where('profitability.materialUsage.0.name', 'Disinfectant')
                ->where('profitability.materialUsage.0.total_cost_halalas', 5000)
                ->where('profitability.overtimeTrend.0.date', '2026-06-16')
                ->where('profitability.overtimeTrend.0.approved_minutes', 30)
                ->where('profitability.contractPerformance.0.completion_rate_percent', 50)
                ->where('exportLinks.finance', '/app/reports/export/finance?from=2026-06-01&to=2026-06-30')
                ->where('exportLinks.profitability', '/app/reports/export/profitability?from=2026-06-01&to=2026-06-30')
            );

        $this->assertSame($context['invoice']->number, 'INV-A6-001');
    }

    public function test_reports_include_direct_expenses_operating_expenses_and_net_profit(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 10:00:00');

        $owner = User::factory()->create(['role' => 'owner']);
        $this->seedManagementScenario();

        Expense::create([
            'expense_date' => '2026-06-14',
            'expense_type' => 'direct_cost',
            'category' => 'subcontractor',
            'vendor' => 'Specialist Crew',
            'description' => 'One-off support crew',
            'amount_halalas' => 5000,
            'vat_halalas' => 750,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'DIR-001',
            'status' => 'approved',
        ]);
        Expense::create([
            'expense_date' => '2026-06-15',
            'expense_type' => 'operating_expense',
            'category' => 'office_rent',
            'vendor' => 'Riyadh Business Center',
            'description' => 'Office rent',
            'amount_halalas' => 10000,
            'vat_halalas' => 1500,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'OPEX-001',
            'status' => 'paid',
        ]);

        $this->actingAs($owner)->get('/app/reports?from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.revenue', 100000)
                ->where('summary.directExpenses', 5000)
                ->where('summary.operatingExpenses', 10000)
                ->where('summary.grossProfit', 72500)
                ->where('summary.netProfit', 62500)
                ->where('summary.netMarginPercent', 60)
                ->where('finance.expenseSummary.direct_cost_halalas', 5000)
                ->where('finance.expenseSummary.operating_expenses_halalas', 10000)
                ->where('finance.expensesByCategory.0.category', 'office_rent')
                ->where('profitability.totals.direct_expenses_halalas', 5000)
                ->where('profitability.totals.operating_expenses_halalas', 10000)
                ->where('profitability.totals.net_profit_halalas', 62500)
            );
    }

    public function test_accountant_can_export_finance_report_as_csv(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 10:00:00');

        $accountant = User::factory()->create(['role' => 'accountant']);
        $this->seedManagementScenario();

        $response = $this->actingAs($accountant)
            ->get('/app/reports/export/finance?from=2026-06-01&to=2026-06-30')
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Invoice,Customer,Due Date,Gross SAR,Paid SAR,Balance SAR,Status', $content);
        $this->assertStringContainsString('INV-A6-001', $content);
        $this->assertStringContainsString('1000.00', $content);
        $this->assertStringContainsString('250.00', $content);
        $this->assertStringContainsString('750.00', $content);
    }

    public function test_owner_can_export_profitability_report_as_csv(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 10:00:00');

        $owner = User::factory()->create(['role' => 'owner']);
        $this->seedManagementScenario();

        $response = $this->actingAs($owner)
            ->get('/app/reports/export/profitability?from=2026-06-01&to=2026-06-30')
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Contract,Customer,Revenue SAR,Labor SAR,Materials SAR,Gross Profit SAR,Margin %,Completed Visits,Overtime Minutes', $content);
        $this->assertStringContainsString('CON-A6-001', $content);
        $this->assertStringContainsString('1050.00', $content);
        $this->assertStringContainsString('775.00', $content);
        $this->assertStringContainsString('74', $content);
    }

    public function test_worker_cannot_access_admin_reports(): void
    {
        $this->withoutVite();

        $worker = User::factory()->create(['role' => 'worker']);

        $this->actingAs($worker)->get('/app/reports')->assertForbidden();
        $this->actingAs($worker)->get('/app/reports/export/finance')->assertForbidden();
    }

    /**
     * @return array{contract: Contract, invoice: Invoice, worker: Worker}
     */
    private function seedManagementScenario(): array
    {
        $contract = Contract::factory()->create([
            'reference' => 'CON-A6-001',
            'status' => 'active',
            'monthly_fee_halalas' => 100000,
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-12-31',
        ]);
        $contract->customer->forceFill(['name' => 'A6 Customer'])->save();
        $contract->service->forceFill(['title' => 'A6 Cleaning', 'category' => 'cleaning'])->save();

        $worker = Worker::factory()->create([
            'name' => 'A6 Worker',
            'status' => 'assigned',
        ]);
        $assignment = Assignment::factory()->for($contract)->create([
            'customer_site_id' => $contract->customer_site_id,
            'worker_id' => $worker->id,
            'service_id' => $contract->service_id,
            'weekday' => 2,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'share_percent' => 60,
            'status' => 'active',
        ]);

        WorkerRevenueTarget::create([
            'worker_id' => $worker->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'target_halalas' => 120000,
        ]);

        Visit::create([
            'contract_id' => $contract->id,
            'assignment_id' => $assignment->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-16',
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'status' => 'completed',
            'checked_in_at' => '2026-06-16 08:00:00',
            'checked_out_at' => '2026-06-16 12:30:00',
            'planned_minutes' => 240,
            'actual_minutes' => 270,
            'variance_minutes' => 30,
            'overtime_minutes' => 30,
            'billable_overtime_minutes' => 30,
            'overtime_status' => 'approved',
            'materials_used' => [
                ['name' => 'Disinfectant', 'quantity' => '2 bottles', 'cost_halalas' => 5000, 'cost_sar' => '50.00'],
                ['name' => 'Gloves', 'quantity' => '1 pack', 'cost_halalas' => 2500, 'cost_sar' => '25.00'],
            ],
            'planned_revenue_halalas' => 100000,
            'labor_cost_halalas' => 20000,
            'material_cost_halalas' => 7500,
            'billable_overtime_halalas' => 5000,
            'gross_profit_halalas' => 77500,
        ]);
        Visit::create([
            'contract_id' => $contract->id,
            'assignment_id' => null,
            'worker_id' => $worker->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-16',
            'starts_at' => '14:00',
            'ends_at' => '18:00',
            'status' => 'scheduled',
        ]);

        $invoice = Invoice::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $contract->customer_id,
            'customer_site_id' => $contract->customer_site_id,
            'number' => 'INV-A6-001',
            'status' => 'partial',
            'issue_date' => '2026-06-05',
            'due_date' => '2026-06-10',
            'net_total_halalas' => 86957,
            'vat_total_halalas' => 13043,
            'gross_total_halalas' => 100000,
            'paid_total_halalas' => 25000,
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_halalas' => 25000,
            'method' => 'bank_transfer',
            'reference' => 'A6-PAY-001',
            'received_at' => '2026-06-12 09:00:00',
        ]);
        Cheque::create([
            'customer_id' => $contract->customer_id,
            'invoice_id' => $invoice->id,
            'cheque_number' => 'A6-CHQ-001',
            'amount_halalas' => 30000,
            'due_date' => '2026-06-20',
            'status' => 'held',
        ]);

        Contract::query()
            ->whereKeyNot($contract->id)
            ->update(['status' => 'inactive']);

        return [
            'contract' => $contract,
            'invoice' => $invoice,
            'worker' => $worker,
        ];
    }
}
