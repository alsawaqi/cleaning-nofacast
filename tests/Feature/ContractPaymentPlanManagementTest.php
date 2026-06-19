<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ContractPaymentPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_contracts_with_payment_plan_metrics_and_catalog(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);
        [$customer, $site, $service, $package] = $this->contractDependencies();

        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-2026-01001',
            'status' => 'draft',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-12-31',
            'monthly_fee_halalas' => 1200000,
            'payment_plan' => [
                ['label' => 'Advance', 'day' => 1, 'percent' => 60],
                ['label' => 'Completion', 'day' => 20, 'percent' => 40],
            ],
        ]);

        DB::table('contract_addendums')->insert([
            'contract_id' => $contract->id,
            'number' => 1,
            'title' => 'Scope extension',
            'summary' => 'Add reception deep cleaning.',
            'effective_on' => '2026-08-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)->get('/app/contracts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Index')
                ->where('catalog.statuses.0.key', 'draft')
                ->where('catalog.billingCycles.0.key', 'monthly')
                ->where('metrics.total', 1)
                ->where('metrics.draft', 1)
                ->where('metrics.monthlyValue', 1200000)
                ->where('createUrl', '/app/contracts/create')
                ->where('contracts.0.reference', 'CON-2026-01001')
                ->where('contracts.0.edit_url', "/app/contracts/{$contract->id}/edit")
                ->where('contracts.0.customer.name', 'Riyadh Clinic Group')
                ->where('contracts.0.site.name', 'Olaya Medical Center')
                ->where('contracts.0.service.title', 'Office Contract Cleaning')
                ->where('contracts.0.service_package.name', 'Standard Monthly')
                ->where('contracts.0.payment_plan.0.amount_halalas', 720000)
                ->where('contracts.0.payment_plan.0.due_on', '2026-07-01')
                ->where('contracts.0.addendums.0.title', 'Scope extension')
                ->has('customers.0.sites.0')
                ->has('servicePackages.0')
            );
    }

    public function test_manager_uses_dedicated_contract_wizard_pages_for_create_and_edit(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-2026-03001',
            'status' => 'draft',
            'monthly_fee_halalas' => 987654,
        ]);

        $this->actingAs($manager)->get('/app/contracts/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Form')
                ->where('mode', 'create')
                ->where('contract', null)
                ->where('submitUrl', '/app/contracts')
                ->where('backUrl', '/app/contracts')
                ->where('steps.0.key', 'customer_service')
                ->where('steps.1.key', 'terms')
                ->where('steps.2.key', 'pricing_scope')
                ->where('steps.3.key', 'payment_plan')
                ->where('steps.4.key', 'addendums')
                ->where('steps.5.key', 'review')
                ->has('customers.0')
                ->has('serviceOptions.0')
                ->has('servicePackages.0')
            );

        $this->actingAs($manager)->get("/app/contracts/{$contract->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Form')
                ->where('mode', 'edit')
                ->where('contract.id', $contract->id)
                ->where('contract.reference', 'CON-2026-03001')
                ->where('contract.monthly_fee_sar', '9876.54')
                ->where('submitUrl', "/app/contracts/{$contract->id}")
                ->where('backUrl', '/app/contracts')
                ->where('steps.0.key', 'customer_service')
                ->where('steps.5.key', 'review')
                ->has('serviceOptions.0')
                ->has('customers.0')
            );
    }

    public function test_manager_can_submit_contract_monthly_fee_as_sar_decimal(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, $site, $service, $package] = $this->contractDependencies();

        $this->actingAs($manager)->post('/app/contracts', $this->contractPayload([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-2026-SAR01',
            'monthly_fee_halalas' => null,
            'monthly_fee_sar' => '15000.75',
        ]))->assertRedirect('/app/contracts');

        $this->assertDatabaseHas('contracts', [
            'reference' => 'CON-2026-SAR01',
            'monthly_fee_halalas' => 1500075,
        ]);
    }

    public function test_manager_can_create_custom_agreement_without_package(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, $site, $service] = $this->contractDependencies();

        $this->actingAs($manager)->post('/app/contracts', $this->contractPayload([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => null,
            'reference' => 'CON-2026-CUSTOM',
            'pricing_model' => 'custom_quote',
            'monthly_fee_sar' => '1800.50',
            'agreed_workers' => 2,
            'visits_per_week' => 3,
            'hours_per_visit' => '4.00',
            'planned_weekly_minutes' => 1440,
            'included_materials' => false,
            'material_policy' => 'customer_supplied',
            'estimated_material_cost_sar' => '0.00',
            'extra_hour_rate_sar' => '75.25',
            'overtime_policy' => 'charge_after_planned_time',
        ]))->assertRedirect('/app/contracts');

        $this->assertDatabaseHas('contracts', [
            'reference' => 'CON-2026-CUSTOM',
            'service_id' => $service->id,
            'service_package_id' => null,
            'pricing_model' => 'custom_quote',
            'monthly_fee_halalas' => 180050,
            'agreed_workers' => 2,
            'extra_hour_rate_halalas' => 7525,
        ]);
    }

    public function test_contract_review_breakdown_includes_extra_charge_lines(): void
    {
        $contents = file_get_contents(resource_path('js/Pages/Contracts/Form.vue'));

        $this->assertStringContainsString('reviewPriceLines', $contents);
        $this->assertStringContainsString('contractsAdmin.baseAgreementAmount', $contents);
        $this->assertStringContainsString('contractsAdmin.chargeableMaterialEstimate', $contents);
        $this->assertStringContainsString('contractsAdmin.variableExtraCharges', $contents);
    }

    public function test_manager_can_create_contract_with_payment_plan_addendum_and_audit_log(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, $site, $service, $package] = $this->contractDependencies();

        $this->actingAs($manager)->post('/app/contracts', $this->contractPayload([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => '',
            'monthly_fee_halalas' => 1500000,
            'payment_plan' => [
                ['label' => 'Booking', 'day' => 1, 'percent' => 50],
                ['label' => 'Mid-cycle', 'day' => 15, 'percent' => 50],
            ],
            'addendums' => [
                [
                    'number' => 1,
                    'title' => 'Initial scope',
                    'summary' => 'Includes two office floors and pantry area.',
                    'effective_on' => '2026-07-01',
                ],
            ],
        ]))->assertRedirect('/app/contracts');

        $contract = Contract::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertStringStartsWith('CON-2026-', $contract->reference);
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'monthly_fee_halalas' => 1500000,
            'status' => 'active',
        ]);
        $this->assertSame(50, $contract->payment_plan[0]['percent']);
        $this->assertDatabaseHas('contract_addendums', [
            'contract_id' => $contract->id,
            'number' => 1,
            'title' => 'Initial scope',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'contract.created',
            'auditable_type' => Contract::class,
            'auditable_id' => $contract->id,
        ]);
    }

    public function test_manager_can_create_contract_with_advanced_agreement_pricing_terms(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, $site, $service, $package] = $this->contractDependencies();

        $this->actingAs($manager)->post('/app/contracts', $this->contractPayload([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-2026-A7',
            'pricing_model' => 'custom_package',
            'agreed_workers' => 3,
            'visits_per_week' => 5,
            'hours_per_visit' => '6.50',
            'planned_weekly_minutes' => 5850,
            'included_materials' => true,
            'material_policy' => 'company_supplied_included',
            'estimated_material_cost_sar' => '275.50',
            'extra_hour_rate_sar' => '110.25',
            'overtime_policy' => 'requires_approval',
            'service_scope' => [
                ['area' => 'Reception', 'tasks' => 'Sweep, mop, sanitize counters'],
                ['area' => 'Bathrooms', 'tasks' => 'Deep clean and replenish supplies'],
            ],
            'terms_and_conditions' => 'Customer approval is required before billable overtime.',
            'monthly_fee_sar' => '18500.75',
        ]))->assertRedirect('/app/contracts');

        $contract = Contract::query()->where('reference', 'CON-2026-A7')->firstOrFail();

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'pricing_model' => 'custom_package',
            'agreed_workers' => 3,
            'visits_per_week' => 5,
            'hours_per_visit' => '6.50',
            'planned_weekly_minutes' => 5850,
            'included_materials' => true,
            'material_policy' => 'company_supplied_included',
            'estimated_material_cost_halalas' => 27550,
            'extra_hour_rate_halalas' => 11025,
            'overtime_policy' => 'requires_approval',
            'monthly_fee_halalas' => 1850075,
        ]);
        $this->assertSame('Reception', $contract->service_scope[0]['area']);
        $this->assertSame('Customer approval is required before billable overtime.', $contract->terms_and_conditions);

        $this->actingAs($manager)->get("/app/contracts/{$contract->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contract.pricing_model', 'custom_package')
                ->where('contract.estimated_material_cost_sar', '275.50')
                ->where('contract.extra_hour_rate_sar', '110.25')
                ->where('contract.service_scope.0.area', 'Reception')
            );
    }

    public function test_manager_can_update_contract_terms_payment_plan_and_addendums(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);
        [$customer, $site, $service, $package] = $this->contractDependencies();

        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-2026-02001',
            'status' => 'draft',
            'monthly_fee_halalas' => 900000,
            'payment_plan' => [
                ['label' => 'Advance', 'day' => 1, 'percent' => 100],
            ],
        ]);

        $addendumId = DB::table('contract_addendums')->insertGetId([
            'contract_id' => $contract->id,
            'number' => 1,
            'title' => 'Old terms',
            'summary' => 'Original summary.',
            'effective_on' => '2026-07-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)->patch("/app/contracts/{$contract->id}", $this->contractPayload([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-2026-02001',
            'status' => 'active',
            'monthly_fee_halalas' => 1100000,
            'notice_days' => 45,
            'payment_plan' => [
                ['label' => 'Advance', 'day' => 1, 'percent' => 70],
                ['label' => 'Final', 'day' => 25, 'percent' => 30],
            ],
            'addendums' => [
                [
                    'id' => $addendumId,
                    'number' => 1,
                    'title' => 'Updated terms',
                    'summary' => 'Updated service scope.',
                    'effective_on' => '2026-07-15',
                ],
                [
                    'number' => 2,
                    'title' => 'Parking level',
                    'summary' => 'Add parking level cleaning.',
                    'effective_on' => '2026-08-01',
                ],
            ],
        ]))->assertRedirect('/app/contracts');

        $contract->refresh();

        $this->assertSame('active', $contract->status);
        $this->assertSame(1100000, $contract->monthly_fee_halalas);
        $this->assertSame(45, $contract->notice_days);
        $this->assertSame(70, $contract->payment_plan[0]['percent']);
        $this->assertDatabaseHas('contract_addendums', [
            'id' => $addendumId,
            'title' => 'Updated terms',
        ]);
        $this->assertDatabaseHas('contract_addendums', [
            'contract_id' => $contract->id,
            'number' => 2,
            'title' => 'Parking level',
        ]);

        $audit = AuditLog::query()
            ->where('action', 'contract.updated')
            ->where('auditable_id', $contract->id)
            ->firstOrFail();

        $this->assertSame('draft', $audit->changes['status']['old']);
        $this->assertSame('active', $audit->changes['status']['new']);
        $this->assertSame(900000, $audit->changes['monthly_fee_halalas']['old']);
        $this->assertSame(1100000, $audit->changes['monthly_fee_halalas']['new']);
    }

    public function test_contract_site_must_belong_to_selected_customer_and_payment_plan_must_total_100(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, , $service, $package] = $this->contractDependencies();
        $otherSite = CustomerSite::factory()->create();

        $this->actingAs($manager)->post('/app/contracts', $this->contractPayload([
            'customer_id' => $customer->id,
            'customer_site_id' => $otherSite->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'payment_plan' => [
                ['label' => 'Only installment', 'day' => 1, 'percent' => 80],
            ],
        ]))->assertSessionHasErrors(['customer_site_id', 'payment_plan']);
    }

    public function test_users_without_contract_permission_cannot_manage_contracts(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
        ]);

        $this->actingAs($accountant)->get('/app/contracts')->assertForbidden();
        $this->actingAs($accountant)->get('/app/contracts/create')->assertForbidden();
        $this->actingAs($accountant)->get("/app/contracts/{$contract->id}/edit")->assertForbidden();
        $this->actingAs($accountant)->post('/app/contracts', $this->contractPayload())->assertForbidden();
        $this->actingAs($accountant)->patch("/app/contracts/{$contract->id}", $this->contractPayload())->assertForbidden();
    }

    /**
     * @return array{0: Customer, 1: CustomerSite, 2: Service, 3: ServicePackage}
     */
    private function contractDependencies(): array
    {
        $customer = Customer::factory()->create([
            'name' => 'Riyadh Clinic Group',
            'status' => 'active',
        ]);
        $site = CustomerSite::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Olaya Medical Center',
            'city' => 'Riyadh',
            'district' => 'Olaya',
        ]);
        $service = Service::factory()->create([
            'title' => 'Office Contract Cleaning',
            'slug' => 'office-contract-cleaning',
            'pricing_type' => 'per_worker_month',
        ]);
        $package = ServicePackage::factory()->create([
            'service_id' => $service->id,
            'name' => 'Standard Monthly',
            'billing_cycle' => 'monthly',
            'price_halalas' => 1200000,
        ]);

        return [$customer, $site, $service, $package];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function contractPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_id' => 1,
            'customer_site_id' => 1,
            'service_id' => 1,
            'service_package_id' => 1,
            'reference' => 'CON-2026-00001',
            'status' => 'active',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-12-31',
            'monthly_fee_halalas' => 1200000,
            'vat_rate' => 15,
            'prices_include_vat' => false,
            'payment_plan' => [
                ['label' => 'Advance', 'day' => 1, 'percent' => 50],
                ['label' => 'Completion', 'day' => 20, 'percent' => 50],
            ],
            'notice_days' => 30,
            'auto_renews' => true,
            'billing_cycle' => 'monthly',
            'special_terms' => 'Customer may request scope changes through addendums.',
            'addendums' => [],
        ], $overrides);
    }
}
