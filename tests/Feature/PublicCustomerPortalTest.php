<?php

namespace Tests\Feature;

use App\Models\BookingRequest;
use App\Models\ChecklistItem;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\User;
use App\Models\Visit;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PublicCustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_and_registration_page_render(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Public/Lead'));

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Register'));
    }

    public function test_customer_registration_creates_customer_account_and_site(): void
    {
        $this->post('/register', [
            'name' => 'Sara Customer',
            'email' => 'sara.customer@example.test',
            'phone' => '+966500111222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'customer_type' => 'individual',
            'city' => 'Riyadh',
            'district' => 'Olaya',
            'address' => 'King Fahd Road',
            'preferred_locale' => 'en',
        ])->assertRedirect('/app/customer');

        $user = User::query()->where('email', 'sara.customer@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('customer', $user->role);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'email' => 'sara.customer@example.test',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('customer_sites', [
            'name' => 'Primary site',
            'city' => 'Riyadh',
            'district' => 'Olaya',
        ]);
    }

    public function test_login_redirects_each_role_to_the_correct_portal(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner-login@example.test',
            'role' => 'owner',
            'is_active' => true,
        ]);
        $worker = User::factory()->create([
            'email' => 'worker-login@example.test',
            'role' => 'worker',
            'is_active' => true,
        ]);
        $customer = User::factory()->create([
            'email' => 'customer-login@example.test',
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect('/app/dashboard');
        $this->post('/logout');

        $this->post('/login', ['email' => $worker->email, 'password' => 'password'])
            ->assertRedirect('/app/worker/today');
        $this->post('/logout');

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect('/app/customer');
    }

    public function test_admin_cannot_access_customer_portal(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->get('/app/customer')
            ->assertForbidden();
    }

    public function test_customer_can_update_profile_and_manage_saved_sites(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'name' => 'Old Customer Name',
            'email' => 'old.customer@example.test',
            'phone' => '+966500000001',
            'locale' => 'en',
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'name' => 'Old Customer Name',
            'email' => $user->email,
            'phone' => $user->phone,
            'preferred_locale' => 'en',
        ]);
        $primarySite = CustomerSite::factory()->for($customer)->create([
            'name' => 'Old Villa',
            'city' => 'Riyadh',
            'district' => 'Olaya',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->get('/app/customer/profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/Profile')
                ->where('customer.name', 'Old Customer Name')
                ->where('customer.sites.0.name', 'Old Villa')
                ->where('customer.sites.0.is_default', true));

        $this->actingAs($user)
            ->patch('/app/customer/profile', [
                'customer_type' => 'company',
                'name' => 'Updated Customer Name',
                'phone' => '+966500000999',
                'email' => 'updated.customer@example.test',
                'preferred_channel' => 'email',
                'preferred_locale' => 'ar',
                'vat_number' => '300000000000003',
            ])
            ->assertRedirect('/app/customer/profile');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Customer Name',
            'phone' => '+966500000999',
            'email' => 'updated.customer@example.test',
            'preferred_channel' => 'email',
            'preferred_locale' => 'ar',
            'vat_number' => '300000000000003',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Customer Name',
            'email' => 'updated.customer@example.test',
            'phone' => '+966500000999',
            'locale' => 'ar',
        ]);

        $this->actingAs($user)
            ->post('/app/customer/sites', [
                'name' => 'North Riyadh Villa',
                'country_code' => 'SA',
                'city' => 'Riyadh',
                'district' => 'Al Nakheel',
                'address' => 'Villa 12',
                'latitude' => '24.7742650',
                'longitude' => '46.7385860',
                'google_place_id' => 'place-north-villa',
                'formatted_address' => 'Villa 12, Al Nakheel, Riyadh',
                'contact_name' => 'Site Contact',
                'contact_phone' => '+966511111111',
                'is_default' => true,
            ])
            ->assertRedirect('/app/customer/profile?tab=sites');

        $newSite = CustomerSite::query()
            ->where('customer_id', $customer->id)
            ->where('name', 'North Riyadh Villa')
            ->firstOrFail();

        $this->assertTrue((bool) $newSite->is_default);
        $this->assertDatabaseHas('customer_sites', [
            'id' => $primarySite->id,
            'is_default' => false,
        ]);

        $this->actingAs($user)
            ->patch("/app/customer/sites/{$newSite->id}", [
                'name' => 'North Riyadh Villa Updated',
                'country_code' => 'SA',
                'city' => 'Riyadh',
                'district' => 'Al Muruj',
                'address' => 'Villa 14',
                'latitude' => '24.8000000',
                'longitude' => '46.7000000',
                'google_place_id' => 'place-updated-villa',
                'formatted_address' => 'Villa 14, Al Muruj, Riyadh',
                'contact_name' => 'Updated Contact',
                'contact_phone' => '+966522222222',
                'is_default' => false,
            ])
            ->assertRedirect('/app/customer/profile?tab=sites');

        $this->assertDatabaseHas('customer_sites', [
            'id' => $newSite->id,
            'name' => 'North Riyadh Villa Updated',
            'district' => 'Al Muruj',
            'latitude' => '24.8000000',
            'longitude' => '46.7000000',
            'is_default' => false,
        ]);

        $this->actingAs($user)
            ->patch("/app/customer/sites/{$newSite->id}/default")
            ->assertRedirect('/app/customer/profile?tab=sites');

        $this->assertDatabaseHas('customer_sites', [
            'id' => $newSite->id,
            'is_default' => true,
        ]);

        $otherSite = CustomerSite::factory()->create();

        $this->actingAs($user)
            ->patch("/app/customer/sites/{$otherSite->id}", [
                'name' => 'Wrong Site',
                'country_code' => 'SA',
                'city' => 'Riyadh',
            ])
            ->assertNotFound();
    }

    public function test_customer_booking_can_use_saved_site_without_retyping_address(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => '+966500222333',
        ]);
        $site = CustomerSite::factory()->for($customer)->create([
            'name' => 'Saved Riyadh Villa',
            'city' => 'Riyadh',
            'district' => 'Al Nakheel',
            'address' => 'Saved address',
            'is_default' => true,
        ]);
        Worker::factory()->count(2)->create(['status' => 'available']);
        $service = Service::factory()->create([
            'title' => 'Saved Site Cleaning',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post('/app/customer/bookings', [
                'service_id' => $service->id,
                'customer_site_id' => $site->id,
                'requested_for' => now()->addDay()->toDateString(),
                'starts_at' => '08:00',
                'notes' => 'Use the saved entrance instructions.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('booking_requests', [
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'status' => 'pending',
            'starts_at' => '08:00',
            'customer_note' => 'Use the saved entrance instructions.',
        ]);
        $this->assertSame(1, CustomerSite::query()->where('customer_id', $customer->id)->count());
    }

    public function test_customer_dashboard_lists_packages_and_accepts_booking_request(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'name' => 'Riyadh Home Customer',
            'email' => $user->email,
            'phone' => '+966500222333',
        ]);
        Worker::factory()->count(2)->create(['status' => 'available']);
        $service = Service::factory()->create([
            'title' => 'Facility Cleaning',
            'is_active' => true,
            'base_price_halalas' => 250000,
        ]);
        $package = ServicePackage::factory()->for($service)->create([
            'name' => 'Weekly Facility Care',
            'price_halalas' => 900000,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/app/customer')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/Dashboard')
                ->where('customer.id', $customer->id)
                ->where('services.0.title', 'Facility Cleaning')
                ->where('services.0.packages.0.name', 'Weekly Facility Care')
                ->has('availability.0.slots'));

        $this->actingAs($user)
            ->post('/app/customer/bookings', [
                'service_id' => $service->id,
                'service_package_id' => $package->id,
                'site_name' => 'North Riyadh Villa',
                'city' => 'Riyadh',
                'district' => 'Al Nakheel',
                'address' => 'Villa 12',
                'requested_for' => now()->addDay()->toDateString(),
                'starts_at' => '08:00',
                'notes' => 'Prefer morning visit.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('booking_requests', [
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'status' => 'pending',
            'starts_at' => '08:00',
        ]);
        $this->assertSame(1, BookingRequest::query()->where('customer_id', $customer->id)->count());
    }

    public function test_customer_dashboard_surfaces_summary_and_next_actions(): void
    {
        $this->withoutVite();

        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => 'Dashboard Customer',
        ]);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Dashboard Villa']);
        $service = Service::factory()->create(['title' => 'Residential Cleaning']);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'reference' => 'CON-DASHBOARD',
            'status' => 'active',
        ]);
        $visit = Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => Worker::factory()->create()->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'status' => 'scheduled',
        ]);
        $invoice = Invoice::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'number' => 'INV-DASHBOARD',
            'status' => 'issued',
            'gross_total_halalas' => 230000,
            'paid_total_halalas' => 30000,
            'due_date' => now()->subDay()->toDateString(),
        ]);
        BookingRequest::create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'requested_for' => now()->addDays(3)->toDateString(),
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'worker_count' => 1,
            'duration_minutes' => 120,
            'status' => 'pending',
            'customer_note' => 'Waiting for operations confirmation.',
        ]);

        $this->actingAs($user)
            ->get('/app/customer')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/Dashboard')
                ->where('dashboardSummary.contracts_count', 1)
                ->where('dashboardSummary.active_contracts_count', 1)
                ->where('dashboardSummary.upcoming_visits_count', 1)
                ->where('dashboardSummary.open_invoices_count', 1)
                ->where('dashboardSummary.unpaid_balance_halalas', 200000)
                ->where('dashboardSummary.unpaid_balance_sar', '2,000.00')
                ->where('dashboardSummary.pending_requests_count', 1)
                ->where('dashboardSummary.saved_sites_count', 1)
                ->where('dashboardActions.0.key', 'payment_due')
                ->where('dashboardActions.0.href', "/app/customer/invoices/{$invoice->id}")
                ->where('dashboardActions.1.key', 'next_visit')
                ->where('dashboardActions.1.href', "/app/customer/contracts/{$contract->id}")
                ->where('dashboardActions.1.meta.visit_id', $visit->id)
                ->where('dashboardActions.2.key', 'booking_pending'));
    }

    public function test_admin_approves_booking_request_and_opens_prefilled_contract_wizard(): void
    {
        $admin = User::factory()->create([
            'role' => 'operations',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create(['name' => 'Approved Booking Customer']);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Riyadh Villa']);
        Worker::factory()->count(2)->create(['status' => 'available']);
        $service = Service::factory()->create([
            'title' => 'Home Cleaning',
            'base_price_halalas' => 50000,
            'is_active' => true,
        ]);
        $package = ServicePackage::factory()->for($service)->create([
            'name' => 'Weekly Home Care',
            'worker_count' => 2,
            'duration_minutes' => 120,
            'price_halalas' => 150000,
            'is_active' => true,
        ]);
        $bookingRequest = BookingRequest::create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'requested_for' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'ends_at' => '12:00',
            'worker_count' => 2,
            'duration_minutes' => 120,
            'status' => 'pending',
            'customer_note' => 'Please call before arrival.',
        ]);

        $this->actingAs($admin)
            ->get('/app/operations?tab=requests')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->where('bookingRequests.summary.pending_count', 1)
                ->where('bookingRequests.items.0.customer.name', 'Approved Booking Customer'));

        $this->actingAs($admin)
            ->post("/app/booking-requests/{$bookingRequest->id}/approve")
            ->assertRedirect("/app/contracts/create?booking_request_id={$bookingRequest->id}");

        $this->assertDatabaseHas('booking_requests', [
            'id' => $bookingRequest->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get("/app/contracts/create?booking_request_id={$bookingRequest->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Form')
                ->where('initialForm.booking_request_id', $bookingRequest->id)
                ->where('initialForm.customer_id', $customer->id)
                ->where('initialForm.customer_site_id', $site->id)
                ->where('initialForm.service_id', $service->id)
                ->where('initialForm.service_package_id', $package->id)
                ->where('initialForm.starts_on', $bookingRequest->requested_for->toDateString()));
    }

    public function test_customer_can_view_booking_request_detail_with_status_timeline(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $site = CustomerSite::factory()->for($customer)->create([
            'name' => 'Timeline Villa',
            'city' => 'Riyadh',
            'district' => 'Al Yasmin',
        ]);
        $service = Service::factory()->create([
            'title' => 'Residential Cleaning',
            'category' => 'cleaning',
        ]);
        $package = ServicePackage::factory()->for($service)->create([
            'name' => 'Weekly Home Care',
        ]);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-BOOKING-01',
        ]);
        $bookingRequest = BookingRequest::create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'contract_id' => $contract->id,
            'requested_for' => now()->addDays(3)->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '12:00',
            'worker_count' => 2,
            'duration_minutes' => 180,
            'status' => 'converted',
            'customer_note' => 'Please use the side entrance.',
            'admin_note' => 'Approved and converted to contract.',
            'approved_at' => now()->subHour(),
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/app/customer')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/Dashboard')
                ->where('bookingRequests.0.detail_url', "/app/customer/bookings/{$bookingRequest->id}"));

        $this->actingAs($user)
            ->get("/app/customer/bookings/{$bookingRequest->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/BookingRequestShow')
                ->where('bookingRequest.id', $bookingRequest->id)
                ->where('bookingRequest.service.title', 'Residential Cleaning')
                ->where('bookingRequest.package.name', 'Weekly Home Care')
                ->where('bookingRequest.site.name', 'Timeline Villa')
                ->where('bookingRequest.contract.reference', 'CON-BOOKING-01')
                ->where('bookingRequest.contract.detail_url', "/app/customer/contracts/{$contract->id}")
                ->where('timeline.0.key', 'submitted')
                ->where('timeline.0.status', 'complete')
                ->where('timeline.2.key', 'approved')
                ->where('timeline.2.status', 'complete')
                ->where('timeline.3.key', 'contract_created')
                ->where('timeline.3.status', 'complete'));
    }

    public function test_customer_cannot_view_another_customer_booking_request(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $otherCustomer = Customer::factory()->create();
        $otherSite = CustomerSite::factory()->for($otherCustomer)->create();
        $otherService = Service::factory()->create();
        $bookingRequest = BookingRequest::create([
            'customer_id' => $otherCustomer->id,
            'customer_site_id' => $otherSite->id,
            'service_id' => $otherService->id,
            'requested_for' => now()->addDays(2)->toDateString(),
            'starts_at' => '11:00',
            'ends_at' => '13:00',
            'worker_count' => 1,
            'duration_minutes' => 120,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get("/app/customer/bookings/{$bookingRequest->id}")
            ->assertNotFound();
    }

    public function test_customer_views_contract_details_upcoming_visits_and_invoice_details(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Customer Villa']);
        $service = Service::factory()->create(['title' => 'Premium Home Cleaning']);
        $package = ServicePackage::factory()->for($service)->create(['name' => 'Weekly Premium']);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-CUSTOMER-01',
            'service_scope' => [
                ['area' => 'Villa', 'tasks' => 'Bedrooms and living room'],
            ],
        ]);
        $worker = Worker::factory()->create(['name' => 'Portal Worker']);
        $visit = Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'status' => 'scheduled',
        ]);
        ChecklistItem::create([
            'visit_id' => $visit->id,
            'label' => 'Arrival photo',
            'status' => 'pending',
            'is_required' => true,
        ]);
        $invoice = Invoice::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'number' => 'INV-CUSTOMER-01',
            'gross_total_halalas' => 230000,
            'paid_total_halalas' => 100000,
        ]);
        InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'visit_id' => $visit->id,
            'line_type' => 'service',
            'description' => 'Weekly cleaning visit',
            'quantity' => 1,
            'unit_label' => 'visit',
            'unit_price_halalas' => 200000,
            'vat_rate' => 15,
            'net_total_halalas' => 200000,
            'vat_total_halalas' => 30000,
            'gross_total_halalas' => 230000,
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_halalas' => 100000,
            'method' => 'bank_transfer',
            'reference' => 'PAY-001',
            'received_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/app/customer')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/Dashboard')
                ->where('contracts.0.detail_url', "/app/customer/contracts/{$contract->id}")
                ->where('upcomingVisits.0.contract.reference', 'CON-CUSTOMER-01')
                ->where('invoices.0.detail_url', "/app/customer/invoices/{$invoice->id}"));

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/ContractShow')
                ->where('contract.reference', 'CON-CUSTOMER-01')
                ->where('contract.service.title', 'Premium Home Cleaning')
                ->where('upcomingVisits.0.worker.name', 'Portal Worker')
                ->where('upcomingVisits.0.checklist.0.label', 'Arrival photo')
                ->where('invoices.0.number', 'INV-CUSTOMER-01'));

        $this->actingAs($user)
            ->get("/app/customer/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/InvoiceShow')
                ->where('invoice.number', 'INV-CUSTOMER-01')
                ->where('invoice.line_items.0.description', 'Weekly cleaning visit')
                ->where('invoice.payments.0.reference', 'PAY-001')
                ->where('invoice.contract.reference', 'CON-CUSTOMER-01'));

        $this->actingAs($user)
            ->get("/app/customer/invoices/{$invoice->id}/print")
            ->assertOk()
            ->assertSee('INV-CUSTOMER-01');
    }

    public function test_customer_can_rate_completed_contract_visit(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Rated Villa']);
        $service = Service::factory()->create(['title' => 'Residential Cleaning']);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'reference' => 'CON-RATING-01',
        ]);
        $worker = Worker::factory()->create(['name' => 'Feedback Worker']);
        $visit = Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => now()->subDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'status' => 'completed',
            'checked_in_at' => now()->subDay()->setTime(9, 0),
            'checked_out_at' => now()->subDay()->setTime(11, 0),
        ]);

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recentVisits.0.id', $visit->id)
                ->where('recentVisits.0.can_submit_feedback', true)
                ->where('recentVisits.0.feedback', null));

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$contract->id}/visits/{$visit->id}/feedback", [
                'rating' => 5,
                'comment' => 'The team arrived on time and the villa was spotless.',
            ])->assertRedirect();

        $this->assertDatabaseHas('visit_feedback', [
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'visit_id' => $visit->id,
            'rating' => 5,
            'comment' => 'The team arrived on time and the villa was spotless.',
        ]);

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recentVisits.0.feedback.rating', 5)
                ->where('recentVisits.0.feedback.comment', 'The team arrived on time and the villa was spotless.')
                ->where('recentVisits.0.can_submit_feedback', false));
    }

    public function test_low_customer_visit_rating_creates_operations_follow_up_once(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Low Rating Villa']);
        $service = Service::factory()->create(['title' => 'Residential Cleaning']);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'reference' => 'CON-LOW-RATING',
        ]);
        $visit = Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => Worker::factory()->create()->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => now()->subDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'status' => 'completed',
            'checked_in_at' => now()->subDay()->setTime(9, 0),
            'checked_out_at' => now()->subDay()->setTime(11, 0),
        ]);

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$contract->id}/visits/{$visit->id}/feedback", [
                'rating' => 2,
                'comment' => 'The bathrooms were not finished.',
            ])->assertRedirect();

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$contract->id}/visits/{$visit->id}/feedback", [
                'rating' => 1,
                'comment' => 'Still waiting for a supervisor call.',
            ])->assertRedirect();

        $this->assertDatabaseHas('service_requests', [
            'type' => 'quality_follow_up',
            'status' => 'pending',
            'priority' => 'urgent',
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'visit_id' => $visit->id,
            'subject' => 'Low customer rating follow-up',
        ]);
        $this->assertSame(1, DB::table('service_requests')
            ->where('type', 'quality_follow_up')
            ->where('visit_id', $visit->id)
            ->count());

        $operations = User::factory()->create(['role' => 'operations']);

        $this->actingAs($operations)
            ->get('/app/operations?date='.$visit->scheduled_for->toDateString())
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('serviceRequests.items.0.type', 'quality_follow_up')
                ->where('serviceRequests.items.0.priority', 'urgent')
                ->where('serviceRequests.items.0.visit.id', $visit->id));
    }

    public function test_customer_cannot_rate_unfinished_or_other_customer_visit(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $site = CustomerSite::factory()->for($customer)->create();
        $service = Service::factory()->create();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
        ]);
        $worker = Worker::factory()->create();
        $scheduledVisit = Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => now()->addDay()->toDateString(),
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'status' => 'scheduled',
        ]);

        $otherCustomer = Customer::factory()->create();
        $otherSite = CustomerSite::factory()->for($otherCustomer)->create();
        $otherContract = Contract::factory()->create([
            'customer_id' => $otherCustomer->id,
            'customer_site_id' => $otherSite->id,
            'service_id' => $service->id,
        ]);
        $otherVisit = Visit::create([
            'contract_id' => $otherContract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $otherSite->id,
            'service_id' => $service->id,
            'scheduled_for' => now()->subDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$contract->id}/visits/{$scheduledVisit->id}/feedback", [
                'rating' => 4,
                'comment' => 'Too early.',
            ])->assertSessionHasErrors('visit');

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$contract->id}/visits/{$otherVisit->id}/feedback", [
                'rating' => 5,
            ])->assertNotFound();
    }

    public function test_customer_can_view_notification_center_for_their_contracts_finance_visits_and_requests(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Notification Villa']);
        $service = Service::factory()->create(['title' => 'Residential Cleaning']);
        $package = ServicePackage::factory()->for($service)->create(['name' => 'Weekly Home Care']);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-NOTIFY-01',
            'status' => 'draft',
        ]);
        $worker = Worker::factory()->create(['name' => 'Notification Worker']);
        $invoice = Invoice::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'number' => 'INV-NOTIFY-01',
            'status' => 'issued',
            'due_date' => now()->subDay()->toDateString(),
            'gross_total_halalas' => 250000,
            'paid_total_halalas' => 0,
        ]);
        Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'status' => 'scheduled',
        ]);
        $bookingRequest = BookingRequest::create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'contract_id' => $contract->id,
            'requested_for' => now()->addDays(3)->toDateString(),
            'starts_at' => '10:00',
            'ends_at' => '12:00',
            'worker_count' => 2,
            'duration_minutes' => 120,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/app/customer/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/Notifications')
                ->where('summary.total_count', 4)
                ->where('summary.action_count', 2)
                ->where('summary.contract_count', 1)
                ->where('summary.finance_count', 1)
                ->where('summary.schedule_count', 1)
                ->where('summary.request_count', 1)
                ->where('groups.contracts.0.type', 'contract_acceptance')
                ->where('groups.contracts.0.href', "/app/customer/contracts/{$contract->id}")
                ->where('groups.finance.0.type', 'invoice_due')
                ->where('groups.finance.0.href', "/app/customer/invoices/{$invoice->id}")
                ->where('groups.schedule.0.type', 'visit_upcoming')
                ->where('groups.schedule.0.href', "/app/customer/contracts/{$contract->id}")
                ->where('groups.requests.0.type', 'booking_request')
                ->where('groups.requests.0.href', "/app/customer/bookings/{$bookingRequest->id}"));
    }

    public function test_customer_notification_center_is_customer_only_and_scoped_to_their_records(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        CustomerSite::factory()->for($customer)->create();
        $otherContract = Contract::factory()->create(['status' => 'draft']);
        Invoice::factory()->create([
            'contract_id' => $otherContract->id,
            'customer_id' => $otherContract->customer_id,
            'customer_site_id' => $otherContract->customer_site_id,
            'status' => 'issued',
            'due_date' => now()->subDay()->toDateString(),
            'gross_total_halalas' => 125000,
            'paid_total_halalas' => 0,
        ]);

        $this->actingAs($owner)
            ->get('/app/customer/notifications')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/app/customer/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/Notifications')
                ->where('summary.total_count', 0)
                ->has('groups.contracts', 0)
                ->has('groups.finance', 0)
                ->has('groups.schedule', 0)
                ->has('groups.requests', 0));
    }

    public function test_customer_can_submit_payment_proof_for_own_invoice(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $site = CustomerSite::factory()->for($customer)->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'number' => 'INV-PROOF-001',
            'gross_total_halalas' => 230000,
            'paid_total_halalas' => 0,
        ]);

        $this->actingAs($user)
            ->post("/app/customer/invoices/{$invoice->id}/payment-proofs", [
                'amount_sar' => '500.50',
                'method' => 'bank_transfer',
                'reference' => 'TRX-7788',
                'paid_on' => now()->toDateString(),
                'customer_note' => 'Paid from Riyad Bank.',
                'proof_file' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $proof = DB::table('payment_proofs')->where('invoice_id', $invoice->id)->first();

        $this->assertNotNull($proof);
        $this->assertSame($customer->id, (int) $proof->customer_id);
        $this->assertSame(50050, (int) $proof->amount_halalas);
        $this->assertSame('bank_transfer', $proof->method);
        $this->assertSame('TRX-7788', $proof->reference);
        $this->assertSame('pending', $proof->status);
        Storage::disk('public')->assertExists($proof->proof_path);

        $this->actingAs($user)
            ->get("/app/customer/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/InvoiceShow')
                ->where('invoice.payment_proofs.0.reference', 'TRX-7788')
                ->where('invoice.payment_proofs.0.status', 'pending'));

        $otherInvoice = Invoice::factory()->create();

        $this->actingAs($user)
            ->post("/app/customer/invoices/{$otherInvoice->id}/payment-proofs", [
                'amount_sar' => '10.00',
                'method' => 'cash',
                'reference' => 'WRONG',
                'paid_on' => now()->toDateString(),
                'proof_file' => UploadedFile::fake()->create('wrong.pdf', 10, 'application/pdf'),
            ])
            ->assertNotFound();
    }

    public function test_customer_can_accept_contract_with_typed_signature(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => 'Signing Customer',
        ]);
        $site = CustomerSite::factory()->for($customer)->create();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'reference' => 'CON-SIGN-001',
        ]);

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$contract->id}/decisions", [
                'decision' => 'accepted',
                'signer_name' => 'Signing Customer',
                'signer_title' => 'Owner',
                'signature_text' => 'Signing Customer',
                'accepted_terms' => true,
                'customer_note' => 'Approved for the agreed service start.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contract_decisions', [
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'decision' => 'accepted',
            'status' => 'pending_review',
            'signer_name' => 'Signing Customer',
            'signer_title' => 'Owner',
            'signature_text' => 'Signing Customer',
            'customer_note' => 'Approved for the agreed service start.',
        ]);
        $this->assertNotNull(DB::table('contract_decisions')->where('contract_id', $contract->id)->value('accepted_at'));

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/ContractShow')
                ->where('contractDecisions.0.decision', 'accepted')
                ->where('contractDecisions.0.status', 'pending_review')
                ->where('contractDecisions.0.signer_name', 'Signing Customer'));
    }

    public function test_customer_can_print_and_download_signed_contract_document(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => 'Printable Customer',
        ]);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Printable Villa']);
        $service = Service::factory()->create(['title' => 'Residential Contract Cleaning']);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'reference' => 'CON-CUSTOMER-PDF',
            'terms_and_conditions' => 'Customer printable contract terms.',
        ]);
        DB::table('contract_decisions')->insert([
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'decision' => 'accepted',
            'status' => 'pending_review',
            'signer_name' => 'Printable Customer',
            'signer_title' => 'Owner',
            'signature_text' => 'Printable Customer',
            'customer_note' => 'Accepted for printable PDF.',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/ContractShow')
                ->where('contract.print_url', "/app/customer/contracts/{$contract->id}/print")
                ->where('contract.download_url', "/app/customer/contracts/{$contract->id}/download"));

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$contract->id}/print")
            ->assertOk()
            ->assertSee('CON-CUSTOMER-PDF')
            ->assertSee('Printable Customer')
            ->assertSee('Customer printable contract terms.');

        $download = $this->actingAs($user)
            ->get("/app/customer/contracts/{$contract->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="CON-CUSTOMER-PDF.pdf"');

        $this->assertStringStartsWith('%PDF-', $download->getContent());
        $this->assertStringContainsString('CON-CUSTOMER-PDF', $download->getContent());
        $this->assertStringContainsString('Printable Customer', $download->getContent());
    }

    public function test_customer_can_request_contract_changes_for_own_contract(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $site = CustomerSite::factory()->for($customer)->create();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'reference' => 'CON-CHANGE-001',
        ]);

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$contract->id}/decisions", [
                'decision' => 'change_requested',
                'customer_note' => 'Please update the visit timing before I sign.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contract_decisions', [
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'decision' => 'change_requested',
            'status' => 'pending_review',
            'customer_note' => 'Please update the visit timing before I sign.',
        ]);

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/ContractShow')
                ->where('contractDecisions.0.decision', 'change_requested')
                ->where('contractDecisions.0.customer_note', 'Please update the visit timing before I sign.'));
    }

    public function test_customer_cannot_submit_contract_decision_for_another_customer_contract(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $otherContract = Contract::factory()->create();

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$otherContract->id}/decisions", [
                'decision' => 'accepted',
                'signer_name' => 'Wrong Customer',
                'signature_text' => 'Wrong Customer',
                'accepted_terms' => true,
            ])
            ->assertNotFound();
    }

    public function test_customer_can_request_visit_reschedule_and_open_support_ticket_for_own_contract(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Customer Apartment']);
        $service = Service::factory()->create(['title' => 'Apartment Cleaning']);
        $worker = Worker::factory()->create();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'reference' => 'CON-REQUEST-01',
        ]);
        $visit = Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'status' => 'scheduled',
        ]);

        $requestedFor = now()->addDays(3)->toDateString();

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$contract->id}/service-requests", [
                'type' => 'reschedule',
                'visit_id' => $visit->id,
                'requested_for' => $requestedFor,
                'starts_at' => '13:00',
                'ends_at' => '15:00',
                'description' => 'Please move this visit to the afternoon.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('service_requests', [
            'type' => 'reschedule',
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'visit_id' => $visit->id,
            'status' => 'pending',
            'requested_for' => "{$requestedFor} 00:00:00",
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'description' => 'Please move this visit to the afternoon.',
        ]);

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$contract->id}/service-requests", [
                'type' => 'support_ticket',
                'priority' => 'high',
                'subject' => 'Need supervisor call',
                'description' => 'Please call me before the next visit.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('service_requests', [
            'type' => 'support_ticket',
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'status' => 'pending',
            'priority' => 'high',
            'subject' => 'Need supervisor call',
            'description' => 'Please call me before the next visit.',
        ]);

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/ContractShow')
                ->has('serviceRequests', 2)
                ->where('serviceRequests.0.type', 'support_ticket')
                ->where('serviceRequests.1.type', 'reschedule'));

        $otherContract = Contract::factory()->create();

        $this->actingAs($user)
            ->post("/app/customer/contracts/{$otherContract->id}/service-requests", [
                'type' => 'support_ticket',
                'priority' => 'normal',
                'subject' => 'Wrong contract',
                'description' => 'This should not be accepted.',
            ])
            ->assertNotFound();
    }

    public function test_operations_can_review_service_requests_and_approve_reschedule(): void
    {
        $admin = User::factory()->create([
            'role' => 'operations',
            'is_active' => true,
        ]);
        $customerUser = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $customerUser->id,
            'email' => $customerUser->email,
            'name' => 'Service Request Customer',
        ]);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Riyadh Office']);
        $service = Service::factory()->create(['title' => 'Office Cleaning']);
        $worker = Worker::factory()->create();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'reference' => 'CON-REQUEST-OPS',
        ]);
        $visit = Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => now()->addDay()->toDateString(),
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'scheduled',
        ]);

        $newDate = now()->addDays(4)->toDateString();
        $rescheduleId = DB::table('service_requests')->insertGetId([
            'type' => 'reschedule',
            'status' => 'pending',
            'priority' => 'normal',
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'visit_id' => $visit->id,
            'subject' => 'Reschedule visit',
            'description' => 'Customer requested a later slot.',
            'requested_for' => $newDate,
            'starts_at' => '14:00',
            'ends_at' => '16:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $supportId = DB::table('service_requests')->insertGetId([
            'type' => 'support_ticket',
            'status' => 'pending',
            'priority' => 'urgent',
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'subject' => 'Access instructions',
            'description' => 'Security needs the team names.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/app/operations?tab=requests')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->where('serviceRequests.summary.pending_count', 2)
                ->where('serviceRequests.items.0.contract.reference', 'CON-REQUEST-OPS'));

        $this->actingAs($admin)
            ->patch("/app/service-requests/{$rescheduleId}/approve", [
                'admin_note' => 'Approved with operations.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'scheduled_for' => "{$newDate} 00:00:00",
            'starts_at' => '14:00',
            'ends_at' => '16:00',
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('service_requests', [
            'id' => $rescheduleId,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'admin_note' => 'Approved with operations.',
        ]);

        $this->actingAs($admin)
            ->patch("/app/service-requests/{$supportId}/in-review", [
                'admin_note' => 'Calling supervisor.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('service_requests', [
            'id' => $supportId,
            'status' => 'in_review',
            'reviewed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch("/app/service-requests/{$supportId}/resolve", [
                'admin_note' => 'Supervisor called customer.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('service_requests', [
            'id' => $supportId,
            'status' => 'resolved',
            'admin_note' => 'Supervisor called customer.',
        ]);
    }

    public function test_customer_cannot_view_another_customer_contract_or_invoice(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $otherContract = Contract::factory()->create();
        $otherInvoice = Invoice::factory()->create([
            'contract_id' => $otherContract->id,
            'customer_id' => $otherContract->customer_id,
            'customer_site_id' => $otherContract->customer_site_id,
        ]);

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$otherContract->id}")
            ->assertNotFound();

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$otherContract->id}/print")
            ->assertNotFound();

        $this->actingAs($user)
            ->get("/app/customer/contracts/{$otherContract->id}/download")
            ->assertNotFound();

        $this->actingAs($user)
            ->get("/app/customer/invoices/{$otherInvoice->id}")
            ->assertNotFound();

        $this->actingAs($user)
            ->get("/app/customer/invoices/{$otherInvoice->id}/print")
            ->assertNotFound();
    }
}
