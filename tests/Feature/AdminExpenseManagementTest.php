<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_accountant_can_update_and_delete_expense_types_and_records(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);

        $this->actingAs($accountant)->post('/app/expenses/categories', [
            'name' => 'Rent',
            'code' => 'rent',
            'expense_type' => 'operating_expense',
            'description' => 'Office rent.',
            'is_active' => true,
        ])->assertRedirect();

        $categoryId = (int) DB::table('expense_categories')->where('code', 'rent')->value('id');

        $this->actingAs($accountant)->patch("/app/expenses/categories/{$categoryId}", [
            'name' => 'Office Rent',
            'code' => 'office_rent',
            'expense_type' => 'operating_expense',
            'description' => 'Monthly office rent.',
            'is_active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('expense_categories', [
            'id' => $categoryId,
            'name' => 'Office Rent',
            'code' => 'office_rent',
            'is_active' => false,
        ]);

        $this->actingAs($accountant)->post('/app/expenses', [
            'expense_date' => '2026-06-19',
            'expense_category_id' => $categoryId,
            'vendor' => 'Tower Offices',
            'description' => 'June rent.',
            'amount_sar' => '850.00',
            'vat_sar' => '127.50',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'RENT-JUN',
            'status' => 'approved',
            'receipt_path' => 'expense-receipts/rent-june.pdf',
        ])->assertRedirect();

        $expenseId = (int) DB::table('expenses')->where('payment_reference', 'RENT-JUN')->value('id');

        $this->actingAs($accountant)->patch("/app/expenses/{$expenseId}", [
            'expense_date' => '2026-06-20',
            'expense_category_id' => $categoryId,
            'vendor' => 'Tower Offices LLC',
            'description' => 'June rent and service charge.',
            'amount_sar' => '900.25',
            'vat_sar' => '135.04',
            'payment_method' => 'cheque',
            'payment_reference' => 'RENT-JUN-UPDATED',
            'status' => 'paid',
            'receipt_path' => 'expense-receipts/rent-june-updated.pdf',
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'expense_date' => '2026-06-20',
            'vendor' => 'Tower Offices LLC',
            'amount_halalas' => 90025,
            'vat_halalas' => 13504,
            'payment_method' => 'cheque',
            'status' => 'paid',
        ]);

        $this->actingAs($accountant)->delete("/app/expenses/{$expenseId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);

        $this->actingAs($accountant)->delete("/app/expenses/categories/{$categoryId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('expense_categories', ['id' => $categoryId]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'expense_category.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.deleted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense_category.deleted']);
    }

    public function test_manager_can_update_and_delete_cost_catalog_and_service_cost_links(): void
    {
        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create([
            'title' => 'Clinic Cleaning',
            'base_price_halalas' => 50000,
        ]);

        $this->actingAs($manager)->post('/app/expenses/cost-items', [
            'name' => 'Disinfectant',
            'item_type' => 'material',
            'unit' => 'bottle',
            'purchase_cost_sar' => '150.00',
            'estimated_life_months' => 3,
            'estimated_monthly_uses' => 30,
            'default_cost_per_use_sar' => '1.67',
            'notes' => 'Initial estimate.',
            'is_active' => true,
        ])->assertRedirect();

        $costItemId = (int) DB::table('cost_items')->where('name', 'Disinfectant')->value('id');

        $this->actingAs($manager)->patch("/app/expenses/cost-items/{$costItemId}", [
            'name' => 'Clinic Disinfectant',
            'item_type' => 'material',
            'unit' => 'liter',
            'purchase_cost_sar' => '180.00',
            'estimated_life_months' => 4,
            'estimated_monthly_uses' => 40,
            'default_cost_per_use_sar' => '1.13',
            'notes' => 'Updated estimate.',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('cost_items', [
            'id' => $costItemId,
            'name' => 'Clinic Disinfectant',
            'purchase_cost_halalas' => 18000,
            'default_cost_per_use_halalas' => 113,
        ]);

        $this->actingAs($manager)->post('/app/expenses/service-cost-items', [
            'service_id' => $service->id,
            'cost_item_id' => $costItemId,
            'quantity' => '2.00',
            'cost_per_use_sar' => '1.13',
            'charge_customer' => false,
            'notes' => 'Two liters per visit.',
        ])->assertRedirect();

        $serviceCostItemId = (int) DB::table('service_cost_items')->where('cost_item_id', $costItemId)->value('id');

        $this->actingAs($manager)->patch("/app/expenses/service-cost-items/{$serviceCostItemId}", [
            'service_id' => $service->id,
            'cost_item_id' => $costItemId,
            'quantity' => '3.50',
            'cost_per_use_sar' => '1.20',
            'charge_customer' => true,
            'notes' => 'Higher consumption for clinic rooms.',
        ])->assertRedirect();

        $this->assertDatabaseHas('service_cost_items', [
            'id' => $serviceCostItemId,
            'quantity' => '3.50',
            'cost_per_use_halalas' => 120,
            'line_total_halalas' => 420,
            'charge_customer' => true,
        ]);

        $this->actingAs($manager)->delete("/app/expenses/service-cost-items/{$serviceCostItemId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('service_cost_items', ['id' => $serviceCostItemId]);

        $this->actingAs($manager)->delete("/app/expenses/cost-items/{$costItemId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('cost_items', ['id' => $costItemId]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'cost_item.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'service_cost_item.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'service_cost_item.deleted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cost_item.deleted']);
    }

    public function test_expense_admin_pages_expose_edit_and_delete_controls(): void
    {
        $pages = [
            resource_path('js/Pages/Expenses/Records.vue') => [
                'expenseForm.patch(`/app/expenses/${editingExpenseId.value}`',
                'deleteForm.delete(`/app/expenses/${expense.id}`',
            ],
            resource_path('js/Pages/Expenses/Categories.vue') => [
                'categoryForm.patch(`/app/expenses/categories/${editingCategoryId.value}`',
                'deleteForm.delete(`/app/expenses/categories/${category.id}`',
            ],
            resource_path('js/Pages/Expenses/CostItems.vue') => [
                'costItemForm.patch(`/app/expenses/cost-items/${editingCostItemId.value}`',
                'deleteForm.delete(`/app/expenses/cost-items/${item.id}`',
            ],
            resource_path('js/Pages/Expenses/ServiceCosts.vue') => [
                'serviceCostForm.patch(`/app/expenses/service-cost-items/${editingServiceCostItemId.value}`',
                'deleteForm.delete(`/app/expenses/service-cost-items/${item.id}`',
            ],
        ];

        foreach ($pages as $path => $needles) {
            $contents = file_get_contents($path);

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $contents, "{$path} is missing {$needle}");
            }
        }
    }
}
