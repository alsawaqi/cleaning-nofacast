<?php

namespace Tests\Feature;

use App\Models\BookingRequest;
use App\Models\Contract;
use App\Models\ContractDecision;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\PaymentProof;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\User;
use App\Models\Visit;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FullAdminBusinessFlowUatTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_uat_flow_from_service_to_contract_operations_sla_finance_and_reports(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-15 07:30:00');

        $admin = User::factory()->create(['role' => 'owner']);
        $workerUser = User::factory()->create(['role' => 'worker']);
        $mainWorker = Worker::factory()->create([
            'user_id' => $workerUser->id,
            'name' => 'Ali Main Cleaner',
            'employee_code' => 'UAT-WRK-001',
            'cost_rate_halalas' => 249600,
        ]);
        $supportWorker = Worker::factory()->create([
            'name' => 'Sara Support Cleaner',
            'employee_code' => 'UAT-WRK-002',
            'cost_rate_halalas' => 249600,
        ]);

        $this->actingAs($admin)->get('/app/services/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Services/Form')
                ->where('steps.0.key', 'basics')
                ->where('steps.3.key', 'pricing_rules')
            );

        $this->actingAs($admin)->post('/app/services', $this->servicePayload())
            ->assertRedirect('/app/services');

        $service = Service::query()->where('slug', 'uat-facility-cleaning')->firstOrFail();
        $package = ServicePackage::query()->where('service_id', $service->id)->where('name', 'Weekly Facility Care')->firstOrFail();

        $this->assertSame('Attendance', $package->sla_kpi_template[0]['label']);
        $this->assertDatabaseHas('service_packages', [
            'service_id' => $service->id,
            'name' => 'Weekly Facility Care',
            'visits_per_week' => 1,
            'price_halalas' => 240000,
        ]);

        $this->actingAs($admin)->post('/app/customers', $this->customerPayload())
            ->assertRedirect('/app/customers');

        $customer = Customer::query()->where('email', 'uat-facilities@example.test')->firstOrFail();
        $site = CustomerSite::query()->where('customer_id', $customer->id)->where('name', 'Riyadh HQ')->firstOrFail();

        $this->assertSame('24.7135517', (string) $site->latitude);
        $this->assertSame('46.6752957', (string) $site->longitude);

        $this->actingAs($admin)->get('/app/contracts/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Form')
                ->where('steps.0.key', 'customer_service')
                ->where('steps.5.key', 'review')
                ->has('servicePackages', fn (AssertableInertia $packages) => $packages
                    ->where('0.id', $package->id)
                    ->where('0.service_id', $service->id)
                )
            );

        $this->actingAs($admin)->post('/app/contracts', $this->contractPayload($customer, $site, $service, $package))
            ->assertRedirect('/app/contracts');

        $contract = Contract::query()->where('reference', 'UAT-CON-001')->firstOrFail();

        $this->assertSame($package->id, $contract->service_package_id);
        $this->assertSame('Attendance', $contract->sla_kpi_template[0]['label']);
        $this->assertSame(240000, $contract->monthly_fee_halalas);
        $this->assertSame(240, $contract->planned_weekly_minutes);

        $this->actingAs($admin)->get("/app/contracts/{$contract->id}?panel=assignments")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Show')
                ->where('assignmentAssessment.required_visits_per_week', 1)
                ->where('assignmentAssessment.covered_visits_per_week', 0)
                ->where('assignmentStoreUrl', "/app/contracts/{$contract->id}/assignments")
                ->where('initialPanel', 'assignments')
            );

        $this->actingAs($admin)->post("/app/contracts/{$contract->id}/assignments", [
            'worker_id' => $mainWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'share_percent' => 70,
            'status' => 'active',
            'team_role' => 'main',
            'task_instructions' => [
                ['label' => 'Clean reception and meeting rooms', 'is_required' => true],
            ],
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->post("/app/contracts/{$contract->id}/assignments", [
            'worker_id' => $supportWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'share_percent' => 30,
            'status' => 'active',
            'team_role' => 'support',
            'task_instructions' => [
                ['label' => 'Restock consumables', 'is_required' => true],
            ],
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->get("/app/contracts/{$contract->id}?panel=assignments")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assignmentAssessment.covered_visits_per_week', 1)
                ->where('assignmentAssessment.ready_slots_count', 1)
                ->where('assignments.0.worker.name', 'Ali Main Cleaner')
                ->where('assignments.1.worker.name', 'Sara Support Cleaner')
            );

        $this->actingAs($admin)->post('/app/operations/generate-visits', [
            'contract_id' => $contract->id,
            'from' => '2026-06-15',
            'to' => '2026-06-21',
        ])->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Generated 1 visits.');

        $visit = Visit::query()->where('contract_id', $contract->id)->firstOrFail();
        $visit->load('checklistItems');

        $this->assertSame('2026-06-15', $visit->scheduled_for->toDateString());
        $this->assertSame($mainWorker->id, $visit->worker_id);
        $this->assertCount(2, $visit->checklistItems);
        $this->assertSame('Clean reception and meeting rooms', $visit->checklistItems[0]->label);
        $this->assertSame('Restock consumables', $visit->checklistItems[1]->label);

        $this->actingAs($admin)->get('/app/operations?date=2026-06-15')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->where('contractActionBoard.summary.today_count', 1)
                ->where('contractActionBoard.items.0.contract.reference', 'UAT-CON-001')
                ->where('contractActionBoard.items.0.action_url', "/app/contracts/{$contract->id}?panel=assignments")
                ->where('visits.0.id', $visit->id)
                ->missing('assignmentStoreUrl')
            );

        Carbon::setTestNow('2026-06-15 08:00:00');

        $this->actingAs($workerUser)->post("/app/worker/visits/{$visit->id}/start")
            ->assertRedirect();

        Carbon::setTestNow('2026-06-15 10:45:00');

        $completedItemIds = $visit->checklistItems()->pluck('id')->all();

        $this->actingAs($workerUser)->post("/app/worker/visits/{$visit->id}/finish", [
            'completed_items' => $completedItemIds,
            'materials_used' => [
                ['name' => 'Disinfectant', 'quantity' => '1 bottle', 'cost_sar' => '30.00'],
            ],
            'photos' => [
                'worker-photos/uat-before-after.jpg',
            ],
            'execution_notes' => 'Completed weekly facility visit with extra reception polishing.',
            'checked_out_at' => '2026-06-15 10:45:00',
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame('completed', $visit->status);
        $this->assertSame(120, $visit->planned_minutes);
        $this->assertSame(165, $visit->actual_minutes);
        $this->assertSame(45, $visit->overtime_minutes);
        $this->assertSame('pending_approval', $visit->overtime_status);
        $this->assertSame(['worker-photos/uat-before-after.jpg'], $visit->photos);

        $this->actingAs($admin)->post("/app/operations/visits/{$visit->id}/acknowledge", [
            'supervisor_note' => 'Supervisor inspection accepted.',
        ])->assertRedirect();

        $this->actingAs($admin)->patch("/app/operations/visits/{$visit->id}/execution", [
            'checked_in_at' => '2026-06-15 08:00:00',
            'checked_out_at' => '2026-06-15 10:45:00',
            'actual_minutes' => 165,
            'material_cost_sar' => '30.00',
            'materials_used' => [
                ['name' => 'Disinfectant', 'quantity' => '1 bottle', 'cost_sar' => '30.00'],
            ],
            'overtime_status' => 'approved',
            'execution_notes' => 'Approved overtime after supervisor review.',
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame('approved', $visit->overtime_status);
        $this->assertSame(45, $visit->billable_overtime_minutes);
        $this->assertSame(9000, $visit->billable_overtime_halalas);

        $this->actingAs($admin)->get("/app/contracts/{$contract->id}?panel=sla&report_date=2026-06-15")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Show')
                ->where('initialPanel', 'sla')
                ->where('slaReports.weekly.metrics.scheduled_visits', 1)
                ->where('slaReports.weekly.metrics.completed_visits', 1)
                ->where('slaReports.weekly.metrics.checklist_completion_rate', 100)
                ->where('slaReports.weekly.metrics.photo_count', 1)
                ->where('slaReports.weekly.metrics.supervisor_review_rate', 100)
                ->where('slaReports.weekly.kpi_results.0.passed', true)
                ->where('slaReports.weekly.kpi_results.1.passed', true)
                ->where('slaReports.weekly.kpi_results.2.passed', true)
            );

        $this->actingAs($admin)->get('/app/finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Finance/Index')
                ->where('billableExtras.0.visit_id', $visit->id)
                ->where('billableExtras.0.contract', 'UAT-CON-001')
                ->where('billableExtras.0.amount_halalas', 9000)
            );

        $this->actingAs($admin)->post("/app/finance/billable-extras/visits/{$visit->id}/invoice")
            ->assertRedirect();

        $invoice = Invoice::query()->where('contract_id', $contract->id)->where('gross_total_halalas', 10350)->firstOrFail();

        $this->assertSame($invoice->id, $visit->refresh()->overtime_invoice_id);
        $this->actingAs($admin)->get("/app/finance/invoices/{$invoice->id}/print")
            ->assertOk()
            ->assertSee('Extra time: 45 minutes')
            ->assertSee('SAR 90.00')
            ->assertSee('SAR 103.50');

        $this->actingAs($admin)->post('/app/finance/cheques', [
            'invoice_id' => $invoice->id,
            'cheque_number' => 'UAT-CHQ-001',
            'amount_sar' => '103.50',
            'due_date' => '2026-06-16',
        ])->assertRedirect();

        $chequeId = (int) DB::table('cheques')->where('cheque_number', 'UAT-CHQ-001')->value('id');

        $this->actingAs($admin)->patch("/app/finance/cheques/{$chequeId}/status", [
            'status' => 'cleared',
            'cleared_date' => '2026-06-16',
        ])->assertRedirect();

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame(10350, $invoice->paid_total_halalas);

        $this->actingAs($admin)->post('/app/expenses/categories', [
            'name' => 'Saudi Office Rent',
            'code' => 'saudi_office_rent',
            'expense_type' => 'operating_expense',
            'description' => 'UAT monthly office rent.',
            'is_active' => true,
        ])->assertRedirect();

        $categoryId = (int) DB::table('expense_categories')->where('code', 'saudi_office_rent')->value('id');

        $this->actingAs($admin)->post('/app/expenses', [
            'expense_date' => '2026-06-15',
            'expense_category_id' => $categoryId,
            'vendor' => 'UAT Facility Landlord',
            'description' => 'Office rent for UAT month.',
            'amount_sar' => '500.00',
            'vat_sar' => '75.00',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'UAT-EXP-001',
            'status' => 'paid',
            'receipt_path' => 'expense-receipts/uat-rent.pdf',
        ])->assertRedirect();

        $this->actingAs($admin)->get('/app/expenses/records')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Expenses/Records')
                ->where('expenses.0.vendor', 'UAT Facility Landlord')
                ->where('summary.operating_expenses_halalas', 50000)
            );

        $this->actingAs($admin)->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('metrics')
                ->has('visits')
                ->has('workerPerformance')
            );

        $this->actingAs($admin)->get('/app/reports')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Index')
                ->has('finance')
                ->has('operations')
                ->has('workerPerformance')
                ->has('profitability')
            );
    }

    public function test_full_role_uat_flow_from_customer_booking_to_worker_visit_feedback_and_finance_approval(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        Carbon::setTestNow('2026-06-21 07:30:00');

        $operations = User::factory()->create(['role' => 'operations']);
        $finance = User::factory()->create(['role' => 'accountant']);
        $workerUser = User::factory()->create(['role' => 'worker']);
        $mainWorker = Worker::factory()->create([
            'user_id' => $workerUser->id,
            'name' => 'Role Flow Main Cleaner',
            'employee_code' => 'ROLE-WRK-001',
            'status' => 'available',
            'cost_rate_halalas' => 249600,
        ]);
        $supportWorker = Worker::factory()->create([
            'name' => 'Role Flow Support Cleaner',
            'employee_code' => 'ROLE-WRK-002',
            'status' => 'available',
            'cost_rate_halalas' => 249600,
        ]);
        $service = Service::factory()->create([
            'title' => 'Role Flow Facility Cleaning',
            'slug' => 'role-flow-facility-cleaning',
            'base_price_halalas' => 240000,
            'default_workers' => 2,
            'default_duration_minutes' => 120,
            'default_material_cost_halalas' => 3000,
            'extra_hour_rate_halalas' => 12000,
            'overtime_policy' => 'requires_approval',
            'checklist_template' => [
                ['label' => 'Clean customer reception', 'is_required' => true],
                ['label' => 'Take completion photos', 'is_required' => true],
            ],
            'sla_kpi_template' => [
                ['code' => 'attendance_rate', 'label' => 'Attendance', 'target' => 100, 'unit' => 'percent', 'weight' => 40, 'direction' => 'at_least'],
                ['code' => 'quality_pass_rate', 'label' => 'Quality pass rate', 'target' => 90, 'unit' => 'percent', 'weight' => 60, 'direction' => 'at_least'],
            ],
        ]);
        $package = ServicePackage::factory()->for($service)->create([
            'name' => 'Role Flow Weekly Care',
            'visit_frequency' => 'weekly',
            'visits_per_week' => 1,
            'hours_per_visit' => '2.00',
            'worker_count' => 2,
            'duration_minutes' => 120,
            'expected_labor_minutes' => 240,
            'material_cost_halalas' => 3000,
            'price_halalas' => 240000,
            'checklist_template' => [
                ['label' => 'Clean reception and meeting rooms', 'is_required' => true],
                ['label' => 'Restock consumables', 'is_required' => true],
            ],
            'sla_kpi_template' => [
                ['code' => 'attendance_rate', 'label' => 'Attendance', 'target' => 100, 'unit' => 'percent', 'weight' => 40, 'direction' => 'at_least'],
                ['code' => 'quality_pass_rate', 'label' => 'Quality pass rate', 'target' => 90, 'unit' => 'percent', 'weight' => 60, 'direction' => 'at_least'],
            ],
        ]);

        $this->post('/register', [
            'name' => 'Role Flow Customer',
            'email' => 'role-flow-customer@example.test',
            'phone' => '+966500777888',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'customer_type' => 'company',
            'city' => 'Riyadh',
            'district' => 'Olaya',
            'address' => 'King Fahd Road',
            'preferred_locale' => 'en',
        ])->assertRedirect('/app/customer');

        $customerUser = User::query()->where('email', 'role-flow-customer@example.test')->firstOrFail();
        $customer = Customer::query()->where('user_id', $customerUser->id)->firstOrFail();
        $site = CustomerSite::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($customerUser)->post('/app/customer/bookings', [
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'customer_site_id' => $site->id,
            'requested_for' => '2026-06-22',
            'starts_at' => '08:00',
            'notes' => 'Please send the same team every week.',
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $bookingRequest = BookingRequest::query()
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $this->actingAs($operations)->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notifications.pendingBookingRequests', 1)
                ->where('notifications.bookingRequests.0.customer', 'Role Flow Customer')
                ->where('notifications.bookingRequests.0.href', '/app/operations?tab=requests')
            );

        $this->actingAs($operations)
            ->post("/app/booking-requests/{$bookingRequest->id}/approve")
            ->assertRedirect("/app/contracts/create?booking_request_id={$bookingRequest->id}");

        $this->actingAs($operations)
            ->get("/app/contracts/create?booking_request_id={$bookingRequest->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Form')
                ->where('initialForm.booking_request_id', $bookingRequest->id)
                ->where('initialForm.customer_id', $customer->id)
                ->where('initialForm.customer_site_id', $site->id)
                ->where('initialForm.service_id', $service->id)
                ->where('initialForm.service_package_id', $package->id)
            );

        $this->actingAs($operations)->post('/app/contracts', [
            ...$this->roleFlowContractPayload($customer, $site, $service, $package),
            'booking_request_id' => $bookingRequest->id,
        ])->assertRedirect('/app/contracts')
            ->assertSessionHasNoErrors();

        $contract = Contract::query()->where('reference', 'UAT-ROLE-CON-001')->firstOrFail();

        $this->assertSame('converted', $bookingRequest->refresh()->status);
        $this->assertSame($contract->id, $bookingRequest->contract_id);

        $this->actingAs($customerUser)->get('/app/customer/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.action_required.0.type', 'contract_acceptance')
                ->where('groups.action_required.0.href', "/app/customer/contracts/{$contract->id}")
            );

        $this->actingAs($customerUser)
            ->post("/app/customer/contracts/{$contract->id}/decisions", [
                'decision' => 'accepted',
                'signer_name' => 'Role Flow Customer',
                'signer_title' => 'Facility Manager',
                'signature_text' => 'Role Flow Customer',
                'accepted_terms' => true,
                'customer_note' => 'Accepted for weekly service.',
            ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $contractDecision = ContractDecision::query()->where('contract_id', $contract->id)->firstOrFail();

        $this->assertSame('active', $contract->refresh()->status);
        $this->actingAs($operations)->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notifications.pendingContractDecisions', 1)
                ->where('notifications.contractDecisions.0.contract', 'UAT-ROLE-CON-001')
            );

        $this->actingAs($operations)
            ->patch("/app/contract-decisions/{$contractDecision->id}/review", [
                'admin_note' => 'Signature reviewed by operations.',
            ])->assertRedirect("/app/contracts/{$contract->id}?panel=acceptance");

        $this->actingAs($operations)->post("/app/contracts/{$contract->id}/assignments", [
            'worker_id' => $mainWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'share_percent' => 70,
            'status' => 'active',
            'team_role' => 'main',
            'task_instructions' => [
                ['label' => 'Clean reception and meeting rooms', 'is_required' => true],
            ],
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($operations)->post("/app/contracts/{$contract->id}/assignments", [
            'worker_id' => $supportWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'share_percent' => 30,
            'status' => 'active',
            'team_role' => 'support',
            'task_instructions' => [
                ['label' => 'Restock consumables', 'is_required' => true],
            ],
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($operations)->post('/app/operations/generate-visits', [
            'contract_id' => $contract->id,
            'from' => '2026-06-22',
            'to' => '2026-06-22',
        ])->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Generated 1 visits.');

        $visit = Visit::query()->where('contract_id', $contract->id)->firstOrFail();

        $this->actingAs($operations)->get('/app/operations?date=2026-06-22')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->where('contractActionBoard.summary.today_count', 1)
                ->where('contractActionBoard.items.0.action_url', "/app/contracts/{$contract->id}?panel=assignments")
                ->where('visits.0.id', $visit->id)
                ->missing('assignmentStoreUrl')
            );

        $this->actingAs($customerUser)->get('/app/customer')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboardSummary.active_contracts_count', 1)
                ->where('dashboardSummary.upcoming_visits_count', 1)
                ->where('dashboardActions.0.key', 'next_visit')
                ->where('upcomingVisits.0.id', $visit->id)
            );

        Carbon::setTestNow('2026-06-22 08:00:00');

        $this->actingAs($workerUser)->get('/app/worker/today')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Worker/Today')
                ->where('worker.id', $mainWorker->id)
                ->where('visits.0.id', $visit->id)
                ->where('visits.0.workflow.phase', 'arrival')
            );

        $this->actingAs($workerUser)->post("/app/worker/visits/{$visit->id}/start", [
            'check_in_latitude' => '24.7135517',
            'check_in_longitude' => '46.6752957',
            'check_in_accuracy_meters' => 12,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-06-22 10:45:00');

        $completedItemIds = $visit->checklistItems()->pluck('id')->all();

        $this->actingAs($workerUser)->post("/app/worker/visits/{$visit->id}/finish", [
            'completed_items' => $completedItemIds,
            'materials_used' => [
                ['name' => 'Disinfectant', 'quantity' => '1 bottle', 'cost_sar' => '30.00'],
            ],
            'photo_files' => [
                UploadedFile::fake()->image('role-flow-after.jpg'),
            ],
            'execution_notes' => 'Completed with additional polishing requested by customer.',
            'checked_out_at' => '2026-06-22 10:45:00',
            'check_out_latitude' => '24.7136000',
            'check_out_longitude' => '46.6753000',
            'check_out_accuracy_meters' => 15,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $visit->refresh();
        $this->assertSame('completed', $visit->status);
        $this->assertSame(45, $visit->overtime_minutes);
        $this->assertSame('pending_approval', $visit->overtime_status);
        $this->assertSame('24.7135517', (string) $visit->check_in_latitude);
        $this->assertSame('46.6753000', (string) $visit->check_out_longitude);
        $this->assertCount(1, $visit->photos);
        Storage::disk('public')->assertExists($visit->photos[0]);

        $this->actingAs($operations)->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notifications.pendingVisitReviews', 1)
                ->where('notifications.visitReviews.0.visit_id', $visit->id)
            );

        $this->actingAs($operations)->patch("/app/operations/visits/{$visit->id}/completion-review", [
            'decision' => 'approved',
            'supervisor_note' => 'Evidence, GPS, and photos reviewed.',
            'quality_status' => 'passed',
            'quality_score' => 94,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($operations)->patch("/app/operations/visits/{$visit->id}/execution", [
            'checked_in_at' => '2026-06-22 08:00:00',
            'checked_out_at' => '2026-06-22 10:45:00',
            'actual_minutes' => 165,
            'material_cost_sar' => '30.00',
            'materials_used' => [
                ['name' => 'Disinfectant', 'quantity' => '1 bottle', 'cost_sar' => '30.00'],
            ],
            'overtime_status' => 'approved',
            'execution_notes' => 'Approved overtime after supervisor review.',
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($finance)->get('/app/finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('billableExtras.0.visit_id', $visit->id)
                ->where('billableExtras.0.amount_halalas', 9000)
            );

        $this->actingAs($finance)->post("/app/finance/billable-extras/visits/{$visit->id}/invoice")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()
            ->where('contract_id', $contract->id)
            ->where('gross_total_halalas', 10350)
            ->firstOrFail();

        $this->actingAs($customerUser)->get("/app/customer/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Customer/InvoiceShow')
                ->where('invoice.id', $invoice->id)
                ->where('invoice.balance_sar', '103.50')
            );

        $this->actingAs($customerUser)->post("/app/customer/invoices/{$invoice->id}/payment-proofs", [
            'amount_sar' => '103.50',
            'method' => 'bank_transfer',
            'reference' => 'ROLE-PAY-001',
            'paid_on' => '2026-06-22',
            'customer_note' => 'Paid by bank transfer.',
            'proof_file' => UploadedFile::fake()->create('role-payment.pdf', 120, 'application/pdf'),
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $paymentProof = PaymentProof::query()->where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($finance)->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notifications.pendingPaymentProofs', 1)
                ->where('notifications.paymentProofs.0.invoice', $invoice->number)
            );

        $this->actingAs($finance)->patch("/app/finance/payment-proofs/{$paymentProof->id}/approve", [
            'admin_note' => 'Matched bank transfer.',
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame(10350, $invoice->paid_total_halalas);
        $this->assertSame('approved', $paymentProof->refresh()->status);

        $this->actingAs($customerUser)
            ->post("/app/customer/contracts/{$contract->id}/visits/{$visit->id}/feedback", [
                'rating' => 2,
                'comment' => 'The visit finished, but the team needs follow-up on glass marks.',
            ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($operations)->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notifications.pendingServiceRequests', 1)
                ->where('notifications.serviceRequests.0.type', 'quality_follow_up')
                ->where('notifications.serviceRequests.0.priority', 'urgent')
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function servicePayload(): array
    {
        return [
            'title' => 'UAT Facility Cleaning',
            'category' => 'cleaning',
            'description' => 'UAT recurring cleaning service for offices and facilities.',
            'pricing_type' => 'per_worker_month',
            'base_price_sar' => '2400.00',
            'vat_rate' => 15,
            'prices_include_vat' => false,
            'materials_included' => true,
            'minimum_billable_minutes' => 120,
            'default_workers' => 2,
            'default_duration_minutes' => 120,
            'default_material_cost_sar' => '30.00',
            'material_policy' => 'company_supplied_included',
            'included_materials_text' => 'Disinfectant, garbage bags',
            'extra_hour_rate_sar' => '120.00',
            'overtime_policy' => 'requires_approval',
            'allowed_frequencies' => ['weekly', 'monthly'],
            'required_certificates' => ['infection_control'],
            'checklist_template' => [
                ['label' => 'Clean reception', 'is_required' => true],
                ['label' => 'Take completion photos', 'is_required' => true],
            ],
            'sla_kpi_template' => [
                ['code' => 'attendance_rate', 'label' => 'Attendance', 'target' => 100, 'unit' => 'percent', 'weight' => 40, 'direction' => 'at_least'],
                ['code' => 'checklist_completion_rate', 'label' => 'Checklist completion', 'target' => 100, 'unit' => 'percent', 'weight' => 30, 'direction' => 'at_least'],
                ['code' => 'supervisor_acknowledgement_rate', 'label' => 'Supervisor inspection', 'target' => 100, 'unit' => 'percent', 'weight' => 30, 'direction' => 'at_least'],
            ],
            'is_active' => true,
            'packages' => [
                [
                    'name' => 'Weekly Facility Care',
                    'description' => 'One team visit per week with supervisor SLA review.',
                    'billing_cycle' => 'monthly',
                    'visit_frequency' => 'weekly',
                    'visits_per_week' => 1,
                    'hours_per_visit' => '2.00',
                    'worker_count' => 2,
                    'duration_minutes' => 120,
                    'expected_labor_minutes' => 240,
                    'material_cost_sar' => '30.00',
                    'price_sar' => '2400.00',
                    'vat_rate' => 15,
                    'prices_include_vat' => false,
                    'is_active' => true,
                    'checklist_template' => [
                        ['label' => 'Clean reception and meeting rooms', 'is_required' => true],
                        ['label' => 'Restock consumables', 'is_required' => true],
                    ],
                    'sla_kpi_template' => [
                        ['code' => 'attendance_rate', 'label' => 'Attendance', 'target' => 100, 'unit' => 'percent', 'weight' => 40, 'direction' => 'at_least'],
                        ['code' => 'checklist_completion_rate', 'label' => 'Checklist completion', 'target' => 100, 'unit' => 'percent', 'weight' => 30, 'direction' => 'at_least'],
                        ['code' => 'supervisor_acknowledgement_rate', 'label' => 'Supervisor inspection', 'target' => 100, 'unit' => 'percent', 'weight' => 30, 'direction' => 'at_least'],
                    ],
                ],
            ],
            'pricing_rules' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(): array
    {
        return [
            'customer_type' => 'company',
            'name' => 'UAT Facility Client',
            'phone' => '+966500009900',
            'email' => 'uat-facilities@example.test',
            'preferred_channel' => 'whatsapp',
            'preferred_locale' => 'ar',
            'vat_number' => '300000000000900',
            'status' => 'active',
            'sites' => [
                [
                    'name' => 'Riyadh HQ',
                    'country_code' => 'SA',
                    'city' => 'Riyadh',
                    'district' => 'Al Malaz',
                    'address' => 'King Fahd Road',
                    'contact_name' => 'UAT Facility Manager',
                    'contact_phone' => '+966500009901',
                    'latitude' => '24.7135517',
                    'longitude' => '46.6752957',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function roleFlowContractPayload(Customer $customer, CustomerSite $site, Service $service, ServicePackage $package): array
    {
        return [
            ...$this->contractPayload($customer, $site, $service, $package),
            'reference' => 'UAT-ROLE-CON-001',
            'status' => 'draft',
            'starts_on' => '2026-06-22',
            'ends_on' => '2026-12-31',
            'terms_and_conditions' => 'Role-flow terms generated from the approved customer booking.',
            'special_terms' => 'Created after customer booking approval.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contractPayload(Customer $customer, CustomerSite $site, Service $service, ServicePackage $package): array
    {
        return [
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'UAT-CON-001',
            'status' => 'active',
            'starts_on' => '2026-06-15',
            'ends_on' => '2026-12-31',
            'monthly_fee_sar' => '2400.00',
            'vat_rate' => 15,
            'prices_include_vat' => false,
            'pricing_model' => 'package',
            'agreed_workers' => 2,
            'visits_per_week' => 1,
            'hours_per_visit' => '2.00',
            'planned_weekly_minutes' => 240,
            'included_materials' => true,
            'material_policy' => 'company_supplied_included',
            'estimated_material_cost_sar' => '30.00',
            'extra_hour_rate_sar' => '120.00',
            'overtime_policy' => 'requires_approval',
            'service_scope' => [
                ['area' => 'Reception and meeting rooms', 'tasks' => 'Clean floors, tables, glass, and restock consumables.'],
            ],
            'terms_and_conditions' => 'Auto-filled UAT terms inherited from the selected package.',
            'sla_kpi_template' => [],
            'payment_plan' => [
                ['label' => 'Monthly invoice', 'day' => 1, 'percent' => 100],
            ],
            'notice_days' => 30,
            'auto_renews' => true,
            'billing_cycle' => 'monthly',
            'special_terms' => 'UAT package agreement.',
            'addendums' => [
                [
                    'number' => 1,
                    'title' => 'UAT site instruction',
                    'summary' => 'Security access must be confirmed before each weekly visit.',
                    'effective_on' => '2026-06-15',
                ],
            ],
        ];
    }
}
