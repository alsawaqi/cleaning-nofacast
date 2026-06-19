<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_manages_expense_types_and_company_expense_records_on_dedicated_page(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);

        $this->actingAs($accountant)->post('/app/expenses/categories', [
            'name' => 'Salaries',
            'code' => 'salaries',
            'expense_type' => 'operating_expense',
            'description' => 'Monthly staff payroll and allowances.',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('expense_categories', [
            'code' => 'salaries',
            'expense_type' => 'operating_expense',
            'is_active' => true,
        ]);

        $categoryId = (int) $this->app['db']->table('expense_categories')->where('code', 'salaries')->value('id');

        $this->actingAs($accountant)->post('/app/expenses', [
            'expense_date' => '2026-06-19',
            'expense_category_id' => $categoryId,
            'vendor' => 'June Payroll',
            'description' => 'Worker salaries for June',
            'amount_sar' => '12500.50',
            'vat_sar' => '0.00',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'PAY-2026-06',
            'status' => 'paid',
            'receipt_path' => 'expense-receipts/payroll-june.pdf',
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'expense_category_id' => $categoryId,
            'expense_type' => 'operating_expense',
            'category' => 'salaries',
            'vendor' => 'June Payroll',
            'amount_halalas' => 1250050,
            'status' => 'paid',
        ]);

        $this->actingAs($accountant)->get('/app/expenses')
            ->assertRedirect('/app/expenses/records');

        $this->actingAs($accountant)->get('/app/expenses/records')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Expenses/Records')
                ->where('categories.0.code', 'salaries')
                ->where('expenses.0.vendor', 'June Payroll')
                ->where('summary.operating_expenses_halalas', 1250050)
                ->where('expenseStatuses.2.key', 'paid')
            );

        $this->actingAs($accountant)->get('/app/expenses/categories')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Expenses/Categories')
                ->where('categories.0.code', 'salaries')
                ->where('summary.operating_expenses_halalas', 1250050)
                ->where('expenseTypes.1.key', 'operating_expense')
            );
    }

    public function test_manager_links_equipment_and_material_costs_to_services_for_breakdown(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create([
            'title' => 'Office Cleaning',
            'base_price_halalas' => 35000,
        ]);

        $this->actingAs($manager)->post('/app/expenses/cost-items', [
            'name' => 'Vacuum Machine',
            'item_type' => 'equipment',
            'unit' => 'machine',
            'purchase_cost_sar' => '1200.00',
            'estimated_life_months' => 12,
            'estimated_monthly_uses' => 60,
            'default_cost_per_use_sar' => '1.67',
            'is_active' => true,
        ])->assertRedirect();

        $equipmentId = (int) $this->app['db']->table('cost_items')->where('name', 'Vacuum Machine')->value('id');

        $this->actingAs($manager)->post('/app/expenses/service-cost-items', [
            'service_id' => $service->id,
            'cost_item_id' => $equipmentId,
            'quantity' => '1.00',
            'cost_per_use_sar' => '1.67',
            'charge_customer' => false,
            'notes' => 'Estimated depreciation per office visit.',
        ])->assertRedirect();

        $this->assertDatabaseHas('service_cost_items', [
            'service_id' => $service->id,
            'cost_item_id' => $equipmentId,
            'cost_per_use_halalas' => 167,
            'line_total_halalas' => 167,
            'charge_customer' => false,
        ]);

        $this->actingAs($manager)->get('/app/expenses/cost-items')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Expenses/CostItems')
                ->where('costItems.0.name', 'Vacuum Machine')
                ->where('itemTypes.0.key', 'equipment')
            );

        $this->actingAs($manager)->get('/app/expenses/service-costs')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Expenses/ServiceCosts')
                ->where('costItems.0.name', 'Vacuum Machine')
                ->where('serviceCostBreakdowns.0.service.title', 'Office Cleaning')
                ->where('serviceCostBreakdowns.0.items.0.name', 'Vacuum Machine')
                ->where('serviceCostBreakdowns.0.total_cost_halalas', 167)
                ->where('serviceCostBreakdowns.0.gross_profit_estimate_halalas', 34833)
            );

        $this->actingAs($manager)->get('/app/services')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('services.0.cost_breakdown.total_cost_halalas', 167)
                ->where('services.0.cost_breakdown.gross_profit_estimate_halalas', 34833)
                ->where('services.0.cost_items.0.name', 'Vacuum Machine')
            );
    }
}
