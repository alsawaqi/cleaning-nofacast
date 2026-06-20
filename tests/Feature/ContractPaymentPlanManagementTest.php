<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\User;
use App\Models\Worker;
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

    public function test_manager_can_view_contract_detail_with_assignments_and_payment_history(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $worker = Worker::factory()->create(['name' => 'Ahmed Hassan', 'employee_code' => 'WRK-501']);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-DETAIL-001',
            'monthly_fee_halalas' => 100000,
            'starts_on' => '2026-07-01',
            'payment_plan' => [
                ['label' => 'Advance', 'day' => 1, 'percent' => 40],
                ['label' => 'Final', 'day' => 20, 'percent' => 60],
            ],
            'service_scope' => [
                ['area' => 'Lobby', 'tasks' => 'Daily floor cleaning'],
            ],
            'terms_and_conditions' => 'Customer provides secure site access.',
        ]);

        Assignment::factory()->for($contract)->create([
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'worker_id' => $worker->id,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
            'share_percent' => 100,
            'status' => 'active',
        ]);

        $invoice = Invoice::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'number' => 'INV-DETAIL-001',
            'gross_total_halalas' => 100000,
            'paid_total_halalas' => 40000,
            'due_date' => '2026-07-20',
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_halalas' => 40000,
            'method' => 'bank_transfer',
            'reference' => 'BANK-001',
            'received_at' => '2026-07-02 10:00:00',
        ]);

        $this->actingAs($manager)->get("/app/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Show')
                ->where('contract.reference', 'CON-DETAIL-001')
                ->where('contract.customer.name', 'Riyadh Clinic Group')
                ->where('contract.service_scope.0.area', 'Lobby')
                ->where('contract.terms_and_conditions', 'Customer provides secure site access.')
                ->where('assignmentStoreUrl', "/app/contracts/{$contract->id}/assignments")
                ->where('assignments.0.worker.name', 'Ahmed Hassan')
                ->where('assignments.0.weekday', 1)
                ->where('finance.gross_total_halalas', 100000)
                ->where('finance.paid_total_halalas', 40000)
                ->where('finance.balance_halalas', 60000)
                ->where('paymentPlan.0.status', 'paid')
                ->where('paymentPlan.1.status', 'pending')
                ->where('invoices.0.number', 'INV-DETAIL-001')
                ->where('invoices.0.payments.0.reference', 'BANK-001')
                ->has('workerOptions.0')
                ->has('weekdayOptions.0')
                ->has('assignmentStatuses.0')
            );
    }

    public function test_contract_detail_exposes_assignment_assessment_for_required_weekly_visits(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $mainWorker = Worker::factory()->create(['name' => 'Main Worker']);
        $supportWorker = Worker::factory()->create(['name' => 'Support Worker']);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'agreed_workers' => 2,
            'visits_per_week' => 3,
            'hours_per_visit' => '2.00',
            'planned_weekly_minutes' => 720,
        ]);

        Assignment::factory()->for($contract)->create([
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'worker_id' => $mainWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'share_percent' => 70,
            'status' => 'active',
            'team_role' => 'main',
        ]);

        Assignment::factory()->for($contract)->create([
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'worker_id' => $supportWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'share_percent' => 30,
            'status' => 'active',
            'team_role' => 'support',
        ]);

        $this->actingAs($manager)->get("/app/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Show')
                ->where('assignmentAssessment.required_visits_per_week', 3)
                ->where('assignmentAssessment.covered_visits_per_week', 1)
                ->where('assignmentAssessment.missing_visits_per_week', 2)
                ->where('assignmentAssessment.required_workers_per_visit', 2)
                ->where('assignmentAssessment.ready_slots_count', 1)
                ->where('assignmentAssessment.follow_ups.0.type', 'missing_visit_slots')
                ->where('assignmentAssessment.slots.0.weekday', 1)
                ->where('assignmentAssessment.slots.0.main_worker.name', 'Main Worker')
                ->where('assignmentAssessment.slots.0.workers_count', 2)
                ->where('assignments.0.team_role', 'main')
                ->where('assignments.1.team_role', 'support')
            );
    }

    public function test_manager_can_assign_worker_from_contract_detail(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
        ]);
        $worker = Worker::factory()->create();

        $this->actingAs($manager)->post("/app/contracts/{$contract->id}/assignments", [
            'worker_id' => $worker->id,
            'weekday' => 3,
            'starts_at' => '08:30',
            'ends_at' => '11:30',
            'share_percent' => 100,
            'status' => 'active',
            'team_role' => 'main',
            'task_instructions' => [
                ['label' => 'Clean reception glass', 'is_required' => true],
                ['label' => 'Sanitize bathrooms', 'is_required' => true],
                ['label' => 'Remove waste bags', 'is_required' => false],
            ],
        ])->assertRedirect();

        $assignment = Assignment::query()->where([
            'contract_id' => $contract->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'worker_id' => $worker->id,
            'weekday' => 3,
            'starts_at' => '08:30',
            'ends_at' => '11:30',
            'share_percent' => 100,
            'status' => 'active',
            'team_role' => 'main',
        ])->firstOrFail();

        $this->assertSame([
            ['label' => 'Clean reception glass', 'is_required' => true],
            ['label' => 'Sanitize bathrooms', 'is_required' => true],
            ['label' => 'Remove waste bags', 'is_required' => false],
        ], $assignment->task_instructions);

        $this->actingAs($manager)->get("/app/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Show')
                ->where('assignments.0.task_instructions.0.label', 'Clean reception glass')
                ->where('assignments.0.task_instructions.2.is_required', false)
            );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'assignment.created_from_contract',
            'auditable_type' => Assignment::class,
        ]);
    }

    public function test_contract_detail_exposes_assignment_capability_by_role(): void
    {
        $this->withoutVite();

        $operations = User::factory()->create(['role' => 'operations']);
        $sales = User::factory()->create(['role' => 'sales']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
        ]);

        $this->actingAs($operations)->get("/app/contracts/{$contract->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canManageAssignments', true));

        $this->actingAs($sales)->get("/app/contracts/{$contract->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canManageAssignments', false));
    }

    public function test_manager_can_view_customer_contract_decisions_and_notification(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-ACCEPT-ADMIN',
        ]);

        $decisionId = DB::table('contract_decisions')->insertGetId([
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'decision' => 'accepted',
            'status' => 'pending_review',
            'signer_name' => 'Riyadh Clinic Signer',
            'signer_title' => 'Facilities Manager',
            'signature_text' => 'Riyadh Clinic Signer',
            'customer_note' => 'Accepted after commercial review.',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)->get("/app/contracts/{$contract->id}?panel=acceptance")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Show')
                ->where('initialPanel', 'acceptance')
                ->where('contractDecisions.0.id', $decisionId)
                ->where('contractDecisions.0.customer.name', 'Riyadh Clinic Group')
                ->where('contractDecisions.0.decision', 'accepted')
                ->where('contractDecisions.0.status', 'pending_review')
                ->where('contractDecisions.0.signer_name', 'Riyadh Clinic Signer')
                ->where('notifications.pendingContractDecisions', 1)
                ->where('notifications.contractDecisions.0.href', "/app/contracts/{$contract->id}?panel=acceptance"));
    }

    public function test_manager_can_print_and_download_signed_contract_document(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-SIGNED-ADMIN',
            'monthly_fee_halalas' => 1500000,
            'service_scope' => [
                ['area' => 'Reception', 'tasks' => 'Daily lobby and glass cleaning'],
            ],
            'terms_and_conditions' => 'Monthly cleaning agreement terms apply.',
            'payment_plan' => [
                ['label' => 'Advance', 'day' => 1, 'percent' => 60],
                ['label' => 'Balance', 'day' => 15, 'percent' => 40],
            ],
        ]);
        DB::table('contract_decisions')->insert([
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'decision' => 'accepted',
            'status' => 'reviewed',
            'signer_name' => 'Riyadh Clinic Signer',
            'signer_title' => 'Facilities Manager',
            'signature_text' => 'Riyadh Clinic Signer',
            'customer_note' => 'Accepted for signature document.',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)->get("/app/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Show')
                ->where('contract.print_url', "/app/contracts/{$contract->id}/print")
                ->where('contract.download_url', "/app/contracts/{$contract->id}/download"));

        $this->actingAs($manager)
            ->get("/app/contracts/{$contract->id}/print")
            ->assertOk()
            ->assertSee('CON-SIGNED-ADMIN')
            ->assertSee('Riyadh Clinic Signer')
            ->assertSee('Monthly cleaning agreement terms apply.');

        $download = $this->actingAs($manager)
            ->get("/app/contracts/{$contract->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="CON-SIGNED-ADMIN.pdf"');

        $this->assertStringStartsWith('%PDF-', $download->getContent());
        $this->assertStringContainsString('CON-SIGNED-ADMIN', $download->getContent());
        $this->assertStringContainsString('Riyadh Clinic Signer', $download->getContent());
    }

    public function test_manager_can_mark_customer_contract_decision_reviewed(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'sales']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
        ]);
        $decisionId = DB::table('contract_decisions')->insertGetId([
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'decision' => 'change_requested',
            'status' => 'pending_review',
            'customer_note' => 'Please revise the payment schedule.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)
            ->patch("/app/contract-decisions/{$decisionId}/review", [
                'admin_note' => 'Sales will update the payment plan.',
            ])
            ->assertRedirect("/app/contracts/{$contract->id}?panel=acceptance");

        $this->assertDatabaseHas('contract_decisions', [
            'id' => $decisionId,
            'status' => 'reviewed',
            'reviewed_by' => $manager->id,
            'admin_note' => 'Sales will update the payment plan.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'contract_decision.reviewed',
            'auditable_type' => 'App\\Models\\ContractDecision',
            'auditable_id' => $decisionId,
        ]);

        $this->actingAs($manager)->get("/app/contracts/{$contract->id}?panel=acceptance")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contractDecisions.0.status', 'reviewed')
                ->where('contractDecisions.0.admin_note', 'Sales will update the payment plan.')
                ->where('notifications.pendingContractDecisions', 0));
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
