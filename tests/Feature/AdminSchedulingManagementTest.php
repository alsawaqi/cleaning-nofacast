<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\CustomerSite;
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
                ->where('metrics.activeAssignments', 1)
                ->where('metrics.weeklyVisits', 1)
                ->where('calendarDays.0.date', '2026-06-15')
                ->where('calendarDays.0.visits.0.worker.name', 'Ahmed Hassan')
                ->where('workload.0.worker.name', 'Ahmed Hassan')
                ->where('workload.0.assignment_minutes', 240)
                ->where('assignments.0.worker.name', 'Ahmed Hassan')
                ->where('contractOptions', fn ($options): bool => collect($options)->contains(fn ($option): bool => $option['id'] === $contract->id))
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
