<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerSiteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_customers_with_sites_metrics_and_catalog(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);
        $customer = Customer::factory()->create([
            'name' => 'Al Noor Pharmacy',
            'customer_type' => 'company',
            'status' => 'active',
        ]);
        CustomerSite::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Olaya Branch',
            'city' => 'Riyadh',
        ]);

        $this->actingAs($manager)->get('/app/customers')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customers/Index')
                ->where('catalog.customerTypes.0.key', 'company')
                ->where('catalog.channels.0.key', 'whatsapp')
                ->where('metrics.total', 1)
                ->where('metrics.sites', 1)
                ->where('createUrl', '/app/customers/create')
                ->where('customers.0.name', 'Al Noor Pharmacy')
                ->where('customers.0.edit_url', "/app/customers/{$customer->id}/edit")
                ->where('customers.0.detail_url', "/app/customers/{$customer->id}")
                ->where('customers.0.sites.0.name', 'Olaya Branch')
            );
    }

    public function test_manager_can_view_customer_detail_with_contracts_workers_sites_finance_and_performance(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-07-15 10:00:00');

        $manager = User::factory()->create(['role' => 'operations']);
        $customer = Customer::factory()->create([
            'name' => 'Al Noor Facility Group',
            'customer_type' => 'company',
            'status' => 'active',
        ]);
        $site = CustomerSite::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Olaya Medical Center',
            'city' => 'Riyadh',
        ]);
        $secondSite = CustomerSite::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Sulimaniyah Offices',
            'city' => 'Riyadh',
        ]);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'reference' => 'CON-CUST-001',
            'status' => 'active',
            'monthly_fee_halalas' => 100000,
        ]);
        $secondContract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $secondSite->id,
            'reference' => 'CON-CUST-002',
            'status' => 'paused',
            'monthly_fee_halalas' => 50000,
        ]);
        $worker = Worker::factory()->create(['name' => 'Ahmed Hassan', 'employee_code' => 'WRK-301']);
        $secondWorker = Worker::factory()->create(['name' => 'Saeed Ali', 'employee_code' => 'WRK-302']);

        Assignment::factory()->for($contract)->create([
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'worker_id' => $worker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '11:00',
            'status' => 'active',
        ]);
        Assignment::factory()->for($contract)->create([
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'worker_id' => $secondWorker->id,
            'weekday' => 2,
            'starts_at' => '09:00',
            'ends_at' => '11:30',
            'status' => 'active',
        ]);

        Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-10',
            'starts_at' => '08:00',
            'ends_at' => '11:00',
            'status' => 'completed',
            'actual_minutes' => 180,
        ]);
        Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $secondWorker->id,
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-11',
            'starts_at' => '09:00',
            'ends_at' => '11:30',
            'status' => 'completed',
            'actual_minutes' => 150,
        ]);
        Visit::create([
            'contract_id' => $secondContract->id,
            'worker_id' => $secondWorker->id,
            'customer_site_id' => $secondSite->id,
            'service_id' => $secondContract->service_id,
            'scheduled_for' => '2026-07-03',
            'starts_at' => '10:00',
            'ends_at' => '12:00',
            'status' => 'scheduled',
        ]);

        $invoice = Invoice::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'number' => 'INV-CUST-001',
            'issue_date' => '2026-06-30',
            'due_date' => '2026-07-01',
            'gross_total_halalas' => 100000,
            'paid_total_halalas' => 40000,
        ]);
        Invoice::factory()->create([
            'contract_id' => $secondContract->id,
            'customer_id' => $customer->id,
            'customer_site_id' => $secondSite->id,
            'number' => 'INV-CUST-002',
            'issue_date' => '2026-07-05',
            'due_date' => '2026-07-25',
            'gross_total_halalas' => 50000,
            'paid_total_halalas' => 50000,
            'status' => 'paid',
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_halalas' => 40000,
            'method' => 'bank_transfer',
            'reference' => 'PAY-CUST-001',
            'received_at' => '2026-07-02 10:00:00',
        ]);

        $this->actingAs($manager)->get("/app/customers/{$customer->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customers/Show')
                ->where('customer.name', 'Al Noor Facility Group')
                ->where('summary.contracts_count', 2)
                ->where('summary.active_contracts_count', 1)
                ->where('summary.assigned_workers_count', 2)
                ->where('summary.completed_visits_count', 2)
                ->where('summary.worked_minutes', 330)
                ->where('finance.gross_total_halalas', 150000)
                ->where('finance.paid_total_halalas', 90000)
                ->where('finance.balance_halalas', 60000)
                ->where('finance.overdue_halalas', 60000)
                ->where('sites.0.name', 'Olaya Medical Center')
                ->where('sites.0.contracts_count', 1)
                ->where('sites.0.workers_count', 2)
                ->where('sites.0.worked_minutes', 330)
                ->where('contracts.0.reference', 'CON-CUST-001')
                ->where('contracts.0.workers_count', 2)
                ->where('contracts.0.completed_visits_count', 2)
                ->where('contracts.0.balance_halalas', 60000)
                ->where('workers.0.name', 'Ahmed Hassan')
                ->where('workers.0.worked_minutes', 180)
                ->where('workers.1.name', 'Saeed Ali')
                ->where('workers.1.worked_minutes', 150)
                ->where('performanceTrend.0.month', '2026-06')
                ->where('performanceTrend.0.completed_visits_count', 2)
                ->where('performanceTrend.0.worked_minutes', 330)
                ->where('invoices.0.number', 'INV-CUST-001')
                ->where('invoices.0.payments.0.reference', 'PAY-CUST-001')
            );
    }

    public function test_manager_uses_dedicated_customer_wizard_pages_for_create_and_edit(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);
        $customer = Customer::factory()->create([
            'name' => 'Dedicated Customer Wizard',
            'status' => 'active',
        ]);
        CustomerSite::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Main Office',
            'city' => 'Riyadh',
        ]);

        $this->actingAs($manager)->get('/app/customers/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customers/Form')
                ->where('mode', 'create')
                ->where('customer', null)
                ->where('submitUrl', '/app/customers')
                ->where('backUrl', '/app/customers')
                ->where('steps.0.key', 'profile')
                ->where('steps.1.key', 'sites')
                ->where('catalog.customerTypes.0.key', 'company')
                ->where('locationCatalog.countries.0.key', 'SA')
                ->where('locationCatalog.cities.0.key', 'Riyadh')
                ->where('locationCatalog.cities.0.districts.0.key', 'Al Malaz')
            );

        $this->actingAs($manager)->get("/app/customers/{$customer->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customers/Form')
                ->where('mode', 'edit')
                ->where('customer.id', $customer->id)
                ->where('customer.name', 'Dedicated Customer Wizard')
                ->where('customer.sites.0.name', 'Main Office')
                ->where('submitUrl', "/app/customers/{$customer->id}")
                ->where('backUrl', '/app/customers')
                ->where('steps.0.key', 'profile')
            );
    }

    public function test_manager_can_create_customer_with_sites_and_audit_log(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);

        $this->actingAs($manager)->post('/app/customers', $this->customerPayload([
            'name' => 'Elite Motors',
            'customer_type' => 'company',
            'phone' => '+966500001111',
            'email' => 'ops@elite-motors.test',
            'preferred_channel' => 'email',
            'preferred_locale' => 'en',
            'vat_number' => '300000000000111',
            'status' => 'active',
            'sites' => [
                [
                    'name' => 'Showroom',
                    'city' => 'Riyadh',
                    'district' => 'Malaz',
                    'address' => 'King Abdulaziz Road',
                    'contact_name' => 'Facility Manager',
                    'contact_phone' => '+966500002222',
                ],
            ],
        ]))->assertRedirect('/app/customers');

        $customer = Customer::query()->where('email', 'ops@elite-motors.test')->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Elite Motors',
            'preferred_channel' => 'email',
            'preferred_locale' => 'en',
        ]);
        $this->assertDatabaseHas('customer_sites', [
            'customer_id' => $customer->id,
            'name' => 'Showroom',
            'district' => 'Malaz',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'customer.created',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
        ]);
    }

    public function test_manager_cannot_create_duplicate_customer_or_user_email(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        Customer::factory()->create(['email' => 'duplicate.customer@example.test']);
        User::factory()->create(['email' => 'existing.user@example.test']);

        $this->actingAs($manager)
            ->post('/app/customers', $this->customerPayload([
                'email' => 'duplicate.customer@example.test',
            ]))
            ->assertSessionHasErrors(['email']);

        $this->actingAs($manager)
            ->post('/app/customers', $this->customerPayload([
                'email' => 'existing.user@example.test',
            ]))
            ->assertSessionHasErrors(['email']);
    }

    public function test_manager_can_save_customer_site_map_location(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);

        $this->actingAs($manager)->post('/app/customers', $this->customerPayload([
            'email' => 'maps@riyadhclinic.test',
            'sites' => [
                [
                    'name' => 'Riyadh Main Office',
                    'country_code' => 'SA',
                    'city' => 'Riyadh',
                    'district' => 'Al Malaz',
                    'address' => 'King Fahd Road',
                    'contact_name' => 'Location Lead',
                    'contact_phone' => '+966500005555',
                    'latitude' => '24.7135517',
                    'longitude' => '46.6752957',
                    'google_place_id' => 'ChIJRcbZaklDXz4RYlEphFBu5r0',
                    'formatted_address' => 'King Fahd Road, Riyadh Saudi Arabia',
                ],
            ],
        ]))->assertRedirect('/app/customers');

        $site = Customer::query()
            ->where('email', 'maps@riyadhclinic.test')
            ->firstOrFail()
            ->sites()
            ->firstOrFail();

        $this->assertSame('SA', $site->country_code);
        $this->assertSame('Al Malaz', $site->district);
        $this->assertEqualsWithDelta(24.7135517, (float) $site->latitude, 0.0000001);
        $this->assertEqualsWithDelta(46.6752957, (float) $site->longitude, 0.0000001);
        $this->assertSame('ChIJRcbZaklDXz4RYlEphFBu5r0', $site->google_place_id);
        $this->assertSame('King Fahd Road, Riyadh Saudi Arabia', $site->formatted_address);
    }

    public function test_customer_site_map_coordinates_must_be_within_valid_ranges(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);

        $this->actingAs($manager)->post('/app/customers', $this->customerPayload([
            'sites' => [
                [
                    'name' => 'Invalid Map Site',
                    'city' => 'Riyadh',
                    'latitude' => '91',
                    'longitude' => '-181',
                ],
            ],
        ]))
            ->assertSessionHasErrors([
                'sites.0.latitude',
                'sites.0.longitude',
            ]);
    }

    public function test_customer_site_country_must_be_supported(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);

        $this->actingAs($manager)->post('/app/customers', $this->customerPayload([
            'sites' => [
                [
                    'name' => 'Unsupported Country Site',
                    'country_code' => 'AE',
                    'city' => 'Dubai',
                ],
            ],
        ]))
            ->assertSessionHasErrors(['sites.0.country_code']);
    }

    public function test_manager_can_update_customer_and_site_details(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);
        $customer = Customer::factory()->create([
            'name' => 'Old Customer Name',
            'status' => 'active',
        ]);
        $site = CustomerSite::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Old Branch',
            'city' => 'Riyadh',
        ]);

        $this->actingAs($manager)->patch("/app/customers/{$customer->id}", $this->customerPayload([
            'name' => 'Updated Customer Name',
            'status' => 'prospect',
            'sites' => [
                [
                    'id' => $site->id,
                    'name' => 'Updated Branch',
                    'city' => 'Jeddah',
                    'district' => 'Corniche',
                    'address' => 'Waterfront',
                    'contact_name' => 'Branch Lead',
                    'contact_phone' => '+966500003333',
                    'latitude' => '21.5433330',
                    'longitude' => '39.1727780',
                    'google_place_id' => 'JeddahWaterfrontPlace',
                    'formatted_address' => 'Jeddah Waterfront, Jeddah Saudi Arabia',
                ],
                [
                    'name' => 'Warehouse',
                    'city' => 'Riyadh',
                    'district' => 'Industrial',
                    'address' => 'Industrial Area',
                    'contact_name' => 'Warehouse Lead',
                    'contact_phone' => '+966500004444',
                ],
            ],
        ]))->assertRedirect('/app/customers');

        $customer->refresh();

        $this->assertSame('Updated Customer Name', $customer->name);
        $this->assertSame('prospect', $customer->status);
        $this->assertDatabaseHas('customer_sites', [
            'id' => $site->id,
            'name' => 'Updated Branch',
            'city' => 'Jeddah',
        ]);

        $site->refresh();
        $this->assertEqualsWithDelta(21.5433330, (float) $site->latitude, 0.0000001);
        $this->assertEqualsWithDelta(39.1727780, (float) $site->longitude, 0.0000001);
        $this->assertSame('JeddahWaterfrontPlace', $site->google_place_id);
        $this->assertSame('Jeddah Waterfront, Jeddah Saudi Arabia', $site->formatted_address);

        $this->assertDatabaseHas('customer_sites', [
            'customer_id' => $customer->id,
            'name' => 'Warehouse',
        ]);

        $audit = AuditLog::query()
            ->where('action', 'customer.updated')
            ->where('auditable_id', $customer->id)
            ->firstOrFail();

        $this->assertSame('Old Customer Name', $audit->changes['name']['old']);
        $this->assertSame('Updated Customer Name', $audit->changes['name']['new']);
        $this->assertSame('active', $audit->changes['status']['old']);
        $this->assertSame('prospect', $audit->changes['status']['new']);
    }

    public function test_users_without_customer_permission_cannot_manage_customers(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);
        $customer = Customer::factory()->create();

        $this->actingAs($accountant)->get('/app/customers')->assertForbidden();
        $this->actingAs($accountant)->get('/app/customers/create')->assertForbidden();
        $this->actingAs($accountant)->get("/app/customers/{$customer->id}/edit")->assertForbidden();
        $this->actingAs($accountant)->post('/app/customers', $this->customerPayload())->assertForbidden();
        $this->actingAs($accountant)->patch("/app/customers/{$customer->id}", $this->customerPayload())->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function customerPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_type' => 'company',
            'name' => 'Riyadh Clinic Group',
            'phone' => '+966500000101',
            'email' => 'facilities@riyadhclinic.test',
            'preferred_channel' => 'whatsapp',
            'preferred_locale' => 'ar',
            'vat_number' => '300000000000003',
            'status' => 'active',
            'sites' => [
                [
                    'name' => 'Olaya Medical Center',
                    'city' => 'Riyadh',
                    'district' => 'Olaya',
                    'address' => 'King Fahd Road',
                    'contact_name' => 'Branch Manager',
                    'contact_phone' => '+966500000202',
                ],
            ],
        ], $overrides);
    }
}
