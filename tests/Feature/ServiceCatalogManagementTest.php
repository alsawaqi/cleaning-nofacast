<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ServiceCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_services_with_catalog_packages_and_pricing_rules(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create([
            'title' => 'AC Maintenance',
            'category' => 'maintenance',
            'pricing_type' => 'per_visit',
        ]);

        $this->actingAs($manager)->get('/app/services')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Services/Index')
                ->where('catalog.categories.0.key', 'cleaning')
                ->where('catalog.pricingTypes.0.key', 'fixed')
                ->where('createUrl', '/app/services/create')
                ->where('services.0.edit_url', "/app/services/{$service->id}/edit")
                ->has('services.0.packages')
                ->has('services.0.pricing_rules')
            );
    }

    public function test_manager_uses_dedicated_service_wizard_pages_for_create_and_edit(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create([
            'title' => 'Dedicated Service Wizard',
            'category' => 'maintenance',
            'pricing_type' => 'per_visit',
            'base_price_halalas' => 12345,
        ]);

        $this->actingAs($manager)->get('/app/services/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Services/Form')
                ->where('mode', 'create')
                ->where('service', null)
                ->where('submitUrl', '/app/services')
                ->where('backUrl', '/app/services')
                ->where('steps.0.key', 'basics')
                ->where('steps.1.key', 'pricing')
                ->where('steps.2.key', 'packages')
                ->where('steps.3.key', 'pricing_rules')
                ->where('catalog.categories.0.key', 'cleaning')
            );

        $this->actingAs($manager)->get("/app/services/{$service->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Services/Form')
                ->where('mode', 'edit')
                ->where('service.id', $service->id)
                ->where('service.title', 'Dedicated Service Wizard')
                ->where('service.base_price_sar', '123.45')
                ->where('submitUrl', "/app/services/{$service->id}")
                ->where('backUrl', '/app/services')
                ->where('steps.0.key', 'basics')
            );
    }

    public function test_manager_can_submit_service_money_values_as_sar_decimals(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);

        $this->actingAs($manager)->post('/app/services', $this->servicePayload([
            'title' => 'Decimal SAR Service',
            'base_price_halalas' => null,
            'base_price_sar' => '750.25',
            'packages' => [
                [
                    'name' => 'Decimal SAR Package',
                    'description' => 'Uses Riyal decimal input.',
                    'billing_cycle' => 'monthly',
                    'visit_frequency' => 'monthly',
                    'worker_count' => 2,
                    'duration_minutes' => 120,
                    'price_sar' => '850.75',
                    'vat_rate' => 15,
                    'prices_include_vat' => false,
                    'is_active' => true,
                    'checklist_template' => [],
                ],
            ],
            'pricing_rules' => [
                [
                    'name' => 'Decimal SAR Rule',
                    'pricing_type' => 'per_unit',
                    'unit_label' => 'unit',
                    'unit_price_sar' => '15.25',
                    'minimum_quantity' => 1,
                    'maximum_quantity' => 10,
                    'vat_rate' => 15,
                    'prices_include_vat' => false,
                    'applies_to' => ['office'],
                    'is_active' => true,
                ],
            ],
        ]))->assertRedirect('/app/services');

        $service = Service::query()->where('slug', 'decimal-sar-service')->firstOrFail();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'base_price_halalas' => 75025,
        ]);
        $this->assertDatabaseHas('service_packages', [
            'service_id' => $service->id,
            'name' => 'Decimal SAR Package',
            'price_halalas' => 85075,
        ]);
        $this->assertDatabaseHas('service_pricing_rules', [
            'service_id' => $service->id,
            'name' => 'Decimal SAR Rule',
            'unit_price_halalas' => 1525,
        ]);
    }

    public function test_manager_can_create_advanced_service_pricing_assumptions(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);

        $this->actingAs($manager)->post('/app/services', $this->servicePayload([
            'title' => 'Advanced Office Cleaning',
            'pricing_type' => 'per_hour',
            'base_price_sar' => '125.50',
            'minimum_billable_minutes' => 120,
            'default_workers' => 3,
            'default_duration_minutes' => 240,
            'default_material_cost_sar' => '37.25',
            'material_policy' => 'company_supplied_billable',
            'included_materials_text' => 'Detergent, microfiber cloths, sanitizer',
            'extra_hour_rate_sar' => '95.75',
            'overtime_policy' => 'charge_after_planned_time',
            'packages' => [
                [
                    'name' => 'Three Days Weekly',
                    'description' => 'Three weekly visits, four hours each.',
                    'billing_cycle' => 'monthly',
                    'visit_frequency' => 'weekly',
                    'visits_per_week' => 3,
                    'hours_per_visit' => '4.50',
                    'worker_count' => 2,
                    'duration_minutes' => 270,
                    'expected_labor_minutes' => 540,
                    'material_cost_sar' => '52.30',
                    'price_sar' => '2400.75',
                    'vat_rate' => 15,
                    'prices_include_vat' => false,
                    'is_active' => true,
                    'checklist_template' => [],
                ],
            ],
            'pricing_rules' => [
                [
                    'name' => 'Extra approved hour',
                    'pricing_type' => 'extra_hour',
                    'unit_label' => 'hour',
                    'unit_price_sar' => '95.75',
                    'minimum_quantity' => 1,
                    'maximum_quantity' => null,
                    'vat_rate' => 15,
                    'prices_include_vat' => false,
                    'applies_to' => ['overtime'],
                    'is_active' => true,
                ],
            ],
        ]))->assertRedirect('/app/services');

        $service = Service::query()->where('slug', 'advanced-office-cleaning')->firstOrFail();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'pricing_type' => 'per_hour',
            'minimum_billable_minutes' => 120,
            'default_workers' => 3,
            'default_duration_minutes' => 240,
            'default_material_cost_halalas' => 3725,
            'material_policy' => 'company_supplied_billable',
            'extra_hour_rate_halalas' => 9575,
            'overtime_policy' => 'charge_after_planned_time',
        ]);
        $this->assertSame(['Detergent', 'microfiber cloths', 'sanitizer'], $service->fresh()->included_materials);
        $this->assertDatabaseHas('service_packages', [
            'service_id' => $service->id,
            'name' => 'Three Days Weekly',
            'visits_per_week' => 3,
            'hours_per_visit' => '4.50',
            'expected_labor_minutes' => 540,
            'material_cost_halalas' => 5230,
            'price_halalas' => 240075,
        ]);
        $this->assertDatabaseHas('service_pricing_rules', [
            'service_id' => $service->id,
            'name' => 'Extra approved hour',
            'pricing_type' => 'extra_hour',
            'unit_price_halalas' => 9575,
        ]);

        $this->actingAs($manager)->get("/app/services/{$service->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('service.default_material_cost_sar', '37.25')
                ->where('service.extra_hour_rate_sar', '95.75')
                ->where('service.packages.0.material_cost_sar', '52.30')
                ->where('service.packages.0.hours_per_visit', '4.50')
            );
    }

    public function test_manager_can_create_service_with_package_pricing_rule_and_audit_log(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);

        $this->actingAs($manager)->post('/app/services', $this->servicePayload([
            'title' => 'AC Filter Maintenance',
            'category' => 'maintenance',
            'pricing_type' => 'per_visit',
            'base_price_halalas' => 75000,
            'packages' => [
                [
                    'name' => 'Monthly AC Care',
                    'description' => 'Scheduled AC cleaning and filter care.',
                    'billing_cycle' => 'monthly',
                    'visit_frequency' => 'monthly',
                    'worker_count' => 2,
                    'duration_minutes' => 120,
                    'price_halalas' => 85000,
                    'vat_rate' => 15,
                    'prices_include_vat' => false,
                    'is_active' => true,
                    'checklist_template' => [
                        ['label' => 'Capture before photo', 'is_required' => true],
                    ],
                ],
            ],
            'pricing_rules' => [
                [
                    'name' => 'Extra AC unit',
                    'pricing_type' => 'per_unit',
                    'unit_label' => 'unit',
                    'unit_price_halalas' => 15000,
                    'minimum_quantity' => 1,
                    'maximum_quantity' => 20,
                    'vat_rate' => 15,
                    'prices_include_vat' => false,
                    'applies_to' => ['residential', 'commercial'],
                    'is_active' => true,
                ],
            ],
        ]))->assertRedirect('/app/services');

        $service = Service::query()->where('slug', 'ac-filter-maintenance')->firstOrFail();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'category' => 'maintenance',
            'pricing_type' => 'per_visit',
            'base_price_halalas' => 75000,
        ]);
        $this->assertDatabaseHas('service_packages', [
            'service_id' => $service->id,
            'name' => 'Monthly AC Care',
            'price_halalas' => 85000,
        ]);
        $this->assertDatabaseHas('service_pricing_rules', [
            'service_id' => $service->id,
            'name' => 'Extra AC unit',
            'unit_price_halalas' => 15000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'service.created',
            'auditable_type' => Service::class,
            'auditable_id' => $service->id,
        ]);
    }

    public function test_manager_can_update_service_packages_and_pricing_rules(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create([
            'title' => 'Home Cleaning',
            'slug' => 'home-cleaning',
            'category' => 'cleaning',
            'pricing_type' => 'fixed',
            'base_price_halalas' => 45000,
        ]);

        $this->actingAs($manager)->patch("/app/services/{$service->id}", $this->servicePayload([
            'title' => 'Home Deep Cleaning',
            'category' => 'cleaning',
            'pricing_type' => 'per_area',
            'base_price_halalas' => 55000,
            'materials_included' => false,
            'packages' => [
                [
                    'name' => 'Villa Deep Clean',
                    'description' => 'Full day deep cleaning package.',
                    'billing_cycle' => 'one_time',
                    'visit_frequency' => 'once',
                    'worker_count' => 4,
                    'duration_minutes' => 360,
                    'price_halalas' => 145000,
                    'vat_rate' => 15,
                    'prices_include_vat' => false,
                    'is_active' => true,
                    'checklist_template' => [
                        ['label' => 'Kitchen deep clean', 'is_required' => true],
                    ],
                ],
            ],
            'pricing_rules' => [
                [
                    'name' => 'Extra square meter',
                    'pricing_type' => 'per_area',
                    'unit_label' => 'm2',
                    'unit_price_halalas' => 250,
                    'minimum_quantity' => 50,
                    'maximum_quantity' => null,
                    'vat_rate' => 15,
                    'prices_include_vat' => false,
                    'applies_to' => ['villa'],
                    'is_active' => true,
                ],
            ],
        ]))->assertRedirect('/app/services');

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'title' => 'Home Deep Cleaning',
            'pricing_type' => 'per_area',
            'base_price_halalas' => 55000,
            'materials_included' => false,
        ]);
        $this->assertDatabaseHas('service_packages', [
            'service_id' => $service->id,
            'name' => 'Villa Deep Clean',
            'worker_count' => 4,
        ]);
        $this->assertDatabaseHas('service_pricing_rules', [
            'service_id' => $service->id,
            'name' => 'Extra square meter',
            'unit_label' => 'm2',
        ]);

        $audit = AuditLog::query()
            ->where('action', 'service.updated')
            ->where('auditable_id', $service->id)
            ->firstOrFail();

        $this->assertSame('fixed', $audit->changes['pricing_type']['old']);
        $this->assertSame('per_area', $audit->changes['pricing_type']['new']);
    }

    public function test_users_without_service_permission_cannot_manage_catalog(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);
        $service = Service::factory()->create();

        $this->actingAs($accountant)->get('/app/services')->assertForbidden();
        $this->actingAs($accountant)->get('/app/services/create')->assertForbidden();
        $this->actingAs($accountant)->get("/app/services/{$service->id}/edit")->assertForbidden();
        $this->actingAs($accountant)->post('/app/services', $this->servicePayload())->assertForbidden();
        $this->actingAs($accountant)->patch("/app/services/{$service->id}", $this->servicePayload())->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function servicePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'Office Contract Cleaning',
            'category' => 'cleaning',
            'description' => 'Recurring cleaning service for offices and facilities.',
            'pricing_type' => 'per_worker_month',
            'base_price_halalas' => 920000,
            'vat_rate' => 15,
            'prices_include_vat' => false,
            'materials_included' => true,
            'allowed_frequencies' => ['weekly', 'monthly'],
            'required_certificates' => ['infection_control'],
            'checklist_template' => [
                ['label' => 'Sweep and mop floors', 'is_required' => true],
                ['label' => 'Sanitize bathrooms', 'is_required' => true],
            ],
            'is_active' => true,
            'packages' => [],
            'pricing_rules' => [],
        ], $overrides);
    }
}
