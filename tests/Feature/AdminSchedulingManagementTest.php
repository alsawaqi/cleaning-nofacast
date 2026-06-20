<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminSchedulingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_scheduling_command_center_with_calendar_and_workload(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create(['title' => 'Office Contract Cleaning']);
        $contract = Contract::factory()->create([
            'service_id' => $service->id,
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
        ]);
        $worker = Worker::factory()->create(['name' => 'Ahmed Hassan']);
        $assignment = Assignment::factory()->for($contract)->create([
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'worker_id' => $worker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'share_percent' => 80,
        ]);

        Visit::create([
            'contract_id' => $contract->id,
            'assignment_id' => $assignment->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'scheduled_for' => '2026-06-15',
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'status' => 'scheduled',
        ]);

        $this->actingAs($manager)->get('/app/operations?date=2026-06-15')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->where('filters.date', '2026-06-15')
                ->where('weekStart', '2026-06-15')
                ->where('metrics.weeklyVisits', 1)
                ->where('calendarDays.0.date', '2026-06-15')
                ->where('calendarDays.0.visits.0.worker.name', 'Ahmed Hassan')
                ->where('workload.0.worker.name', 'Ahmed Hassan')
                ->where('workload.0.assignment_minutes', 240)
                ->where('assignments.0.worker.name', 'Ahmed Hassan')
                ->where('workerOptions', fn ($options): bool => collect($options)->contains(fn ($option): bool => $option['id'] === $worker->id))
            );
    }

    public function test_manager_views_capacity_calendar_with_worker_lanes_and_slot_availability(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create(['title' => 'Deep Cleaning']);
        $contract = Contract::factory()->create([
            'service_id' => $service->id,
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
        ]);
        $bookedWorker = Worker::factory()->create(['name' => 'Booked Worker', 'status' => 'assigned']);
        Worker::factory()->create(['name' => 'Available Worker', 'status' => 'available']);
        Worker::factory()->create(['name' => 'Inactive Worker', 'status' => 'inactive']);

        $assignment = Assignment::factory()->for($contract)->create([
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'worker_id' => $bookedWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'status' => 'active',
        ]);

        Visit::create([
            'contract_id' => $contract->id,
            'assignment_id' => $assignment->id,
            'worker_id' => $bookedWorker->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'scheduled_for' => '2026-06-15',
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'status' => 'scheduled',
        ]);

        $this->actingAs($manager)->get('/app/operations?date=2026-06-15')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->where('availabilityBoard.summary.total_workers', 2)
                ->where('availabilityBoard.summary.booked_workers', 1)
                ->where('availabilityBoard.summary.available_workers', 1)
                ->where('availabilityBoard.summary.capacity_minutes', 960)
                ->where('availabilityBoard.summary.booked_minutes', 240)
                ->where('availabilityBoard.summary.utilization_percent', 25)
                ->where('availabilityBoard.timeSlots.0.label', '08:00')
                ->where('availabilityBoard.timeSlots.0.booked_workers', 1)
                ->where('availabilityBoard.timeSlots.0.available_workers', 1)
                ->where('availabilityBoard.timeSlots.0.status', 'available')
                ->where('availabilityBoard.workerLanes.0.worker.name', 'Available Worker')
                ->where('availabilityBoard.workerLanes.0.available_minutes', 480)
                ->where('availabilityBoard.workerLanes.1.worker.name', 'Booked Worker')
                ->where('availabilityBoard.workerLanes.1.booked_minutes', 240)
                ->where('availabilityBoard.workerLanes.1.events.0.service.title', 'Deep Cleaning')
            );
    }

    public function test_manager_views_contract_assignment_follow_up_queue(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create(['title' => 'Office Weekly Cleaning']);
        $contract = Contract::factory()->create([
            'reference' => 'CON-FOLLOW-001',
            'service_id' => $service->id,
            'status' => 'active',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
            'visits_per_week' => 3,
            'agreed_workers' => 2,
        ]);
        $supportWorker = Worker::factory()->create(['name' => 'Support Worker']);

        Assignment::create([
            'contract_id' => $contract->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'worker_id' => $supportWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'active',
            'team_role' => 'support',
        ]);

        $this->actingAs($manager)->get('/app/operations?date=2026-06-15')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->where('assignmentFollowUps.week_start', '2026-06-15')
                ->where('assignmentFollowUps.summary.contracts_due', 1)
                ->where('assignmentFollowUps.summary.open_items', 4)
                ->where('assignmentFollowUps.summary.missing_visit_slots', 2)
                ->where('assignmentFollowUps.summary.missing_main_worker_slots', 1)
                ->where('assignmentFollowUps.summary.understaffed_slots', 1)
                ->where('assignmentFollowUps.items.0.contract.reference', 'CON-FOLLOW-001')
                ->where('assignmentFollowUps.items.0.contract.detail_url', "/app/contracts/{$contract->id}?panel=assignments")
                ->where('assignmentFollowUps.items.0.required_visits_per_week', 3)
                ->where('assignmentFollowUps.items.0.covered_visits_per_week', 1)
                ->where('assignmentFollowUps.items.0.missing_visit_slots', 2)
                ->where('assignmentFollowUps.items.0.missing_main_worker_slots', 1)
                ->where('assignmentFollowUps.items.0.understaffed_slots', 1)
                ->where('assignmentFollowUps.items.0.next_due_on', '2026-06-15')
                ->where('assignmentFollowUps.items.0.slots.0.weekday', 1)
                ->where('assignmentFollowUps.items.0.slots.0.workers_count', 1)
                ->where('assignmentFollowUps.items.0.slots.0.missing_support_workers', 1)
                ->where('assignmentFollowUps.items.0.slots.0.has_main_worker', false)
            );
    }

    public function test_operations_board_shows_today_and_tomorrow_contract_action_queue(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create(['title' => 'Villa Cleaning']);
        $todayContract = Contract::factory()->create([
            'reference' => 'CON-TODAY-001',
            'service_id' => $service->id,
            'status' => 'active',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
            'visits_per_week' => 1,
            'agreed_workers' => 1,
        ]);
        $tomorrowContract = Contract::factory()->create([
            'reference' => 'CON-TOMORROW-001',
            'service_id' => $service->id,
            'status' => 'active',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
            'visits_per_week' => 1,
            'agreed_workers' => 2,
        ]);
        $mainWorker = Worker::factory()->create(['name' => 'Main Worker']);
        $supportWorker = Worker::factory()->create(['name' => 'Support Worker']);

        $todayAssignment = Assignment::create([
            'contract_id' => $todayContract->id,
            'customer_site_id' => $todayContract->customer_site_id,
            'service_id' => $service->id,
            'worker_id' => $mainWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'active',
            'team_role' => 'main',
            'task_instructions' => [
                ['label' => 'Clean reception', 'is_required' => true],
            ],
        ]);

        Assignment::create([
            'contract_id' => $tomorrowContract->id,
            'customer_site_id' => $tomorrowContract->customer_site_id,
            'service_id' => $service->id,
            'worker_id' => $supportWorker->id,
            'weekday' => 2,
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'status' => 'active',
            'team_role' => 'support',
        ]);

        Visit::create([
            'contract_id' => $todayContract->id,
            'assignment_id' => $todayAssignment->id,
            'worker_id' => $mainWorker->id,
            'customer_site_id' => $todayContract->customer_site_id,
            'service_id' => $service->id,
            'scheduled_for' => '2026-06-15',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'scheduled',
        ]);

        $this->actingAs($manager)->get('/app/operations?date=2026-06-15')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->where('contractActionBoard.window_start', '2026-06-15')
                ->where('contractActionBoard.window_end', '2026-06-16')
                ->where('contractActionBoard.summary.total_contracts', 2)
                ->where('contractActionBoard.summary.today_count', 1)
                ->where('contractActionBoard.summary.tomorrow_count', 1)
                ->where('contractActionBoard.summary.open_items', 2)
                ->where('contractActionBoard.items.0.notice_key', 'today')
                ->where('contractActionBoard.items.0.contract.reference', 'CON-TODAY-001')
                ->where('contractActionBoard.items.0.action_url', "/app/contracts/{$todayContract->id}?panel=assignments")
                ->where('contractActionBoard.items.0.visits_count', 1)
                ->where('contractActionBoard.items.0.assigned_workers_count', 1)
                ->where('contractActionBoard.items.0.task_count', 1)
                ->where('contractActionBoard.items.1.notice_key', 'tomorrow')
                ->where('contractActionBoard.items.1.contract.reference', 'CON-TOMORROW-001')
                ->where('contractActionBoard.items.1.action_url', "/app/contracts/{$tomorrowContract->id}?panel=assignments")
                ->where('contractActionBoard.items.1.open_items', 2)
                ->where('contractActionBoard.items.1.missing_main_worker_slots', 1)
                ->where('contractActionBoard.items.1.understaffed_slots', 1)
            );
    }

    public function test_operations_board_surfaces_payment_attention_for_due_and_overdue_contracts(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $overdueContract = Contract::factory()->create([
            'reference' => 'CON-PAY-OVERDUE',
            'status' => 'active',
        ]);
        $dueSoonContract = Contract::factory()->create([
            'reference' => 'CON-PAY-SOON',
            'status' => 'active',
        ]);

        Invoice::factory()->for($overdueContract)->create([
            'customer_id' => $overdueContract->customer_id,
            'customer_site_id' => $overdueContract->customer_site_id,
            'number' => 'INV-OVERDUE',
            'status' => 'issued',
            'due_date' => '2026-06-10',
            'gross_total_halalas' => 10000,
            'paid_total_halalas' => 1000,
        ]);
        Invoice::factory()->for($dueSoonContract)->create([
            'customer_id' => $dueSoonContract->customer_id,
            'customer_site_id' => $dueSoonContract->customer_site_id,
            'number' => 'INV-DUE-SOON',
            'status' => 'issued',
            'due_date' => '2026-06-16',
            'gross_total_halalas' => 7000,
            'paid_total_halalas' => 0,
        ]);

        $this->actingAs($manager)->get('/app/operations?date=2026-06-15')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->where('paymentAttention.summary.overdue_count', 1)
                ->where('paymentAttention.summary.due_soon_count', 1)
                ->where('paymentAttention.summary.outstanding_halalas', 16000)
                ->where('paymentAttention.items.0.invoice.number', 'INV-OVERDUE')
                ->where('paymentAttention.items.0.severity', 'danger')
                ->where('paymentAttention.items.0.days_overdue', 5)
                ->where('paymentAttention.items.0.action_url', "/app/contracts/{$overdueContract->id}?panel=finance")
                ->where('paymentAttention.items.1.invoice.number', 'INV-DUE-SOON')
                ->where('paymentAttention.items.1.severity', 'warning')
                ->where('paymentAttention.items.1.due_label', 'tomorrow')
                ->where('paymentAttention.items.1.action_url', "/app/contracts/{$dueSoonContract->id}?panel=finance")
            );
    }

    public function test_operations_board_does_not_expose_assignment_creation_payload(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create();
        $contract = Contract::factory()->create([
            'service_id' => $service->id,
            'status' => 'active',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
        ]);
        $worker = Worker::factory()->create(['name' => 'Ahmed Hassan']);

        Assignment::factory()->for($contract)->create([
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'worker_id' => $worker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'status' => 'active',
        ]);

        $this->actingAs($manager)->get('/app/operations?date=2026-06-15')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->has('assignmentFollowUps')
                ->missing('contractOptions')
                ->missing('serviceOptions')
                ->missing('assignmentStatuses')
            );
    }

    public function test_operations_board_does_not_expose_worker_execution_controls(): void
    {
        $contents = file_get_contents(resource_path('js/Pages/Operations/Index.vue'));

        $this->assertStringNotContainsString('/check-in', $contents);
        $this->assertStringNotContainsString('/check-out', $contents);
        $this->assertStringNotContainsString('/checklist', $contents);
        $this->assertStringNotContainsString('completeChecklist(', $contents);
        $this->assertStringNotContainsString('operations.checkIn', $contents);
        $this->assertStringNotContainsString('operations.checkOut', $contents);
        $this->assertStringNotContainsString('operations.completeChecklist', $contents);
        $this->assertStringNotContainsString('Actual check-in', $contents);
        $this->assertStringNotContainsString('Actual check-out', $contents);
        $this->assertStringNotContainsString('Late check-in', $contents);
        $this->assertStringNotContainsString('updateExecution(', $contents);
        $this->assertStringNotContainsString('updateChecklistEvidence(', $contents);
        $this->assertStringNotContainsString('saveExecution', $contents);
        $this->assertStringNotContainsString('v-model="executionForm', $contents);
        $this->assertStringNotContainsString('v-model="checklistEvidenceForm', $contents);
    }

    public function test_manager_can_create_assignment_from_admin_scheduler(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create();
        $contract = Contract::factory()->create([
            'service_id' => $service->id,
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
        ]);
        $worker = Worker::factory()->create();

        $this->actingAs($manager)->post('/app/operations/assignments', [
            'contract_id' => $contract->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'worker_id' => $worker->id,
            'weekday' => 3,
            'starts_at' => '09:00',
            'ends_at' => '13:30',
            'share_percent' => 75,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('assignments', [
            'contract_id' => $contract->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'worker_id' => $worker->id,
            'weekday' => 3,
            'starts_at' => '09:00',
            'ends_at' => '13:30',
            'share_percent' => 75,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'assignment.created',
            'auditable_type' => Assignment::class,
        ]);
    }

    public function test_assignment_scheduler_validates_contract_site_and_worker_availability(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $otherSite = CustomerSite::factory()->create();
        $worker = Worker::factory()->create();

        Assignment::factory()->for($contract)->create([
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'worker_id' => $worker->id,
            'weekday' => 2,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'status' => 'active',
        ]);

        $this->actingAs($manager)->post('/app/operations/assignments', [
            'contract_id' => $contract->id,
            'customer_site_id' => $otherSite->id,
            'service_id' => $service->id,
            'worker_id' => $worker->id,
            'weekday' => 2,
            'starts_at' => '10:00',
            'ends_at' => '14:00',
            'share_percent' => 100,
            'status' => 'active',
        ])->assertSessionHasErrors(['customer_site_id', 'worker_id']);
    }

    public function test_manager_can_generate_visits_for_contract_assignments(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create([
            'checklist_template' => [
                ['label' => 'Clean reception glass'],
                ['label' => 'Sanitize bathrooms'],
            ],
        ]);
        $contract = Contract::factory()->create([
            'service_id' => $service->id,
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
        ]);

        Assignment::factory()->for($contract)->create([
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'worker_id' => Worker::factory()->create()->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'status' => 'active',
        ]);

        $this->actingAs($manager)->post('/app/operations/generate-visits', [
            'contract_id' => $contract->id,
            'from' => '2026-06-15',
            'to' => '2026-06-29',
        ])->assertRedirect();

        $this->assertSame(3, Visit::query()->where('contract_id', $contract->id)->count());
        $this->assertDatabaseHas('checklist_items', ['label' => 'Clean reception glass']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'visits.generated',
            'auditable_type' => Contract::class,
            'auditable_id' => $contract->id,
        ]);
    }
}
