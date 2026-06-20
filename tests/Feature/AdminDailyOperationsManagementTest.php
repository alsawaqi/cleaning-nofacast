<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminDailyOperationsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manager_views_daily_control_alerts_and_visit_state(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 09:30:00');

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create(['title' => 'Clinic Cleaning']);
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $worker = Worker::factory()->create(['name' => 'Fatima Noor']);

        $lateVisit = $this->visit($contract, $service, $worker, [
            'scheduled_for' => '2026-06-16',
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'status' => 'scheduled',
        ]);

        $completedVisit = $this->visit($contract, $service, $worker, [
            'scheduled_for' => '2026-06-16',
            'starts_at' => '06:00',
            'ends_at' => '07:30',
            'status' => 'completed',
            'checked_in_at' => now()->subHours(3),
            'checked_out_at' => now()->subHours(2),
        ]);

        $this->actingAs($manager)->get('/app/operations?date=2026-06-16')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Operations/Index')
                ->where('dailyControl.date', '2026-06-16')
                ->where('metrics.lateVisits', 1)
                ->where('metrics.awaitingAcknowledgement', 1)
                ->where('dailyControl.alerts.0.type', 'late')
                ->where('dailyControl.alerts.0.visit_id', $lateVisit->id)
                ->where('dailyControl.awaitingAcknowledgement.0.id', $completedVisit->id)
                ->where('visits.0.is_late', true)
                ->where('visits.1.requires_acknowledgement', true)
            );
    }

    public function test_manager_can_record_visit_issue_with_photo_evidence(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service);

        $this->actingAs($manager)->post("/app/operations/visits/{$visit->id}/issue", [
            'issue_note' => 'Customer requested extra disinfection near reception.',
            'photos' => [
                'visit-photos/reception-before.jpg',
                'visit-photos/reception-after.jpg',
            ],
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame('issue_reported', $visit->status);
        $this->assertSame('Customer requested extra disinfection near reception.', $visit->issue_note);
        $this->assertSame([
            'visit-photos/reception-before.jpg',
            'visit-photos/reception-after.jpg',
        ], $visit->photos);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'visit.issue_reported',
            'auditable_type' => Visit::class,
            'auditable_id' => $visit->id,
        ]);
    }

    public function test_manager_can_mark_visit_missed_with_reason(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, null, [
            'scheduled_for' => now()->toDateString(),
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'status' => 'scheduled',
        ]);

        $this->actingAs($manager)->post("/app/operations/visits/{$visit->id}/missed", [
            'reason' => 'Worker did not arrive and supervisor reassigned the site.',
        ])->assertRedirect();

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => 'missed',
            'issue_note' => 'Worker did not arrive and supervisor reassigned the site.',
        ]);
    }

    public function test_supervisor_can_acknowledge_completed_visit(): void
    {
        $this->withoutVite();

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, null, [
            'status' => 'completed',
            'checked_in_at' => now()->subHours(4),
            'checked_out_at' => now()->subHours(2),
        ]);

        $this->actingAs($supervisor)->post("/app/operations/visits/{$visit->id}/acknowledge", [
            'supervisor_note' => 'Checklist and site photos reviewed.',
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame($supervisor->id, $visit->supervisor_acknowledged_by);
        $this->assertNotNull($visit->supervisor_acknowledged_at);
        $this->assertSame('Checklist and site photos reviewed.', $visit->supervisor_note);
    }

    public function test_supervisor_can_approve_completed_visit_after_reviewing_evidence(): void
    {
        $this->withoutVite();

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, null, [
            'scheduled_for' => '2026-06-16',
            'status' => 'completed',
            'checked_in_at' => '2026-06-16 08:02:00',
            'check_in_latitude' => '24.7136000',
            'check_in_longitude' => '46.6753000',
            'check_in_accuracy_meters' => 18,
            'checked_out_at' => '2026-06-16 10:10:00',
            'check_out_latitude' => '24.7141000',
            'check_out_longitude' => '46.6760000',
            'check_out_accuracy_meters' => 24,
            'photos' => ['worker-photos/visits/lobby-after.jpg'],
        ]);
        $visit->checklistItems()->create([
            'label' => 'Reception sanitised',
            'status' => 'done',
            'notes' => 'Completed and inspected.',
            'photo_path' => 'worker-photos/checklist/reception.jpg',
            'completed_at' => '2026-06-16 09:00:00',
        ]);

        $this->actingAs($supervisor)->patch("/app/operations/visits/{$visit->id}/completion-review", [
            'decision' => 'approved',
            'supervisor_note' => 'GPS, checklist, and photos reviewed.',
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame('approved', $visit->completion_review_status);
        $this->assertSame($supervisor->id, $visit->completion_reviewed_by);
        $this->assertNotNull($visit->completion_reviewed_at);
        $this->assertSame('GPS, checklist, and photos reviewed.', $visit->completion_review_note);
        $this->assertSame($supervisor->id, $visit->supervisor_acknowledged_by);
        $this->assertNotNull($visit->supervisor_acknowledged_at);

        $this->actingAs($supervisor)->get('/app/operations?date=2026-06-16&tab=visits')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('metrics.awaitingAcknowledgement', 0)
                ->where('dailyControl.evidence.approved_reviews', 1)
                ->where('visits.0.evidence_review.status', 'approved')
                ->where('visits.0.evidence_review.photo_count', 2)
                ->where('visits.0.evidence_review.checklist_done', 1)
                ->where('visits.0.check_in_location.latitude', '24.7136000')
                ->where('visits.0.check_out_location.longitude', '46.6760000')
            );
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'action' => 'visit.completion_reviewed',
            'auditable_type' => Visit::class,
            'auditable_id' => $visit->id,
        ]);
    }

    public function test_supervisor_can_request_visit_correction_without_approving_completion(): void
    {
        $this->withoutVite();

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, null, [
            'scheduled_for' => '2026-06-16',
            'status' => 'completed',
            'checked_in_at' => '2026-06-16 08:02:00',
            'checked_out_at' => '2026-06-16 10:10:00',
            'photos' => [],
        ]);
        $visit->checklistItems()->create([
            'label' => 'Kitchen floor cleaned',
            'status' => 'pending',
        ]);

        $this->actingAs($supervisor)->patch("/app/operations/visits/{$visit->id}/completion-review", [
            'decision' => 'needs_correction',
            'supervisor_note' => 'Missing checklist evidence and final photos.',
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame('needs_correction', $visit->completion_review_status);
        $this->assertSame($supervisor->id, $visit->completion_reviewed_by);
        $this->assertSame('Missing checklist evidence and final photos.', $visit->completion_review_note);
        $this->assertNull($visit->supervisor_acknowledged_by);
        $this->assertNull($visit->supervisor_acknowledged_at);

        $this->actingAs($supervisor)->get('/app/operations?date=2026-06-16&tab=visits')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('metrics.awaitingAcknowledgement', 0)
                ->where('dailyControl.evidence.corrections_requested', 1)
                ->where('visits.0.evidence_review.status', 'needs_correction')
                ->where('visits.0.evidence_review.needs_review', false)
                ->where('visits.0.evidence_review.checklist_pending', 1)
            );
    }

    public function test_supervisor_can_record_quality_review_and_customer_follow_up(): void
    {
        $this->withoutVite();

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $service = Service::factory()->create(['title' => 'Villa Deep Cleaning']);
        $contract = Contract::factory()->create([
            'service_id' => $service->id,
            'reference' => 'CON-QUALITY-01',
        ]);
        $visit = $this->visit($contract, $service, null, [
            'scheduled_for' => '2026-06-16',
            'status' => 'completed',
            'checked_in_at' => '2026-06-16 08:00:00',
            'checked_out_at' => '2026-06-16 11:00:00',
            'photos' => ['worker-photos/visits/villa-after.jpg'],
        ]);

        $this->actingAs($supervisor)->patch("/app/operations/visits/{$visit->id}/completion-review", [
            'decision' => 'approved',
            'quality_status' => 'customer_follow_up',
            'quality_score' => 72,
            'supervisor_note' => 'Evidence accepted, but customer asked for supervisor call about bathroom finishing.',
            'create_follow_up' => true,
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame('approved', $visit->completion_review_status);
        $this->assertSame('customer_follow_up', $visit->quality_status);
        $this->assertSame(72, $visit->quality_score);
        $this->assertSame($supervisor->id, $visit->quality_reviewed_by);
        $this->assertNotNull($visit->quality_reviewed_at);
        $this->assertSame('Evidence accepted, but customer asked for supervisor call about bathroom finishing.', $visit->quality_notes);

        $this->assertDatabaseHas('service_requests', [
            'type' => 'quality_follow_up',
            'status' => 'pending',
            'priority' => 'high',
            'customer_id' => $contract->customer_id,
            'contract_id' => $contract->id,
            'visit_id' => $visit->id,
            'subject' => 'Quality follow-up required',
        ]);

        $this->actingAs($supervisor)->get('/app/operations?date=2026-06-16&tab=visits')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dailyControl.evidence.quality_reviews', 1)
                ->where('dailyControl.evidence.quality_follow_ups', 1)
                ->where('visits.0.evidence_review.quality_status', 'customer_follow_up')
                ->where('visits.0.evidence_review.quality_score', 72)
                ->where('serviceRequests.items.0.type', 'quality_follow_up')
                ->where('serviceRequests.items.0.visit.id', $visit->id)
            );
    }

    public function test_manager_can_update_checklist_item_with_notes_and_photo(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service);
        $item = $visit->checklistItems()->create([
            'label' => 'Disinfect reception counter',
            'status' => 'pending',
        ]);

        $this->actingAs($manager)->patch("/app/operations/checklist-items/{$item->id}", [
            'status' => 'done',
            'notes' => 'Completed after customer confirmed access.',
            'photo_path' => 'checklist/reception-counter.jpg',
        ])->assertRedirect();

        $item->refresh();

        $this->assertSame('done', $item->status);
        $this->assertSame('Completed after customer confirmed access.', $item->notes);
        $this->assertSame('checklist/reception-counter.jpg', $item->photo_path);
        $this->assertNotNull($item->completed_at);
    }

    public function test_manager_can_record_visit_execution_costs_and_profitability(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $worker = Worker::factory()->create([
            'name' => 'Nora Field',
            'cost_rate_halalas' => 249600,
        ]);
        $service = Service::factory()->create(['title' => 'Facility Cleaning']);
        $contract = Contract::factory()->create([
            'service_id' => $service->id,
            'monthly_fee_halalas' => 1200000,
            'visits_per_week' => 2,
            'extra_hour_rate_halalas' => 12000,
            'overtime_policy' => 'requires_approval',
        ]);
        $visit = $this->visit($contract, $service, $worker, [
            'scheduled_for' => '2026-06-16',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
        ]);

        $this->actingAs($manager)->patch("/app/operations/visits/{$visit->id}/execution", [
            'checked_in_at' => '2026-06-16 08:05:00',
            'checked_out_at' => '2026-06-16 10:35:00',
            'materials_used' => [
                ['name' => 'Disinfectant', 'quantity' => '2 bottles', 'cost_sar' => '25.50'],
                ['name' => 'Garbage bags', 'quantity' => '1 pack', 'cost_sar' => '20.25'],
            ],
            'overtime_status' => 'pending_approval',
            'execution_notes' => 'Reception required extra sanitation.',
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame('completed', $visit->status);
        $this->assertSame(120, $visit->planned_minutes);
        $this->assertSame(150, $visit->actual_minutes);
        $this->assertSame(30, $visit->variance_minutes);
        $this->assertSame(30, $visit->overtime_minutes);
        $this->assertSame(0, $visit->billable_overtime_minutes);
        $this->assertSame('pending_approval', $visit->overtime_status);
        $this->assertSame(150000, $visit->planned_revenue_halalas);
        $this->assertSame(3000, $visit->labor_cost_halalas);
        $this->assertSame(4575, $visit->material_cost_halalas);
        $this->assertSame(0, $visit->billable_overtime_halalas);
        $this->assertSame(142425, $visit->gross_profit_halalas);
        $this->assertSame('Disinfectant', $visit->materials_used[0]['name']);

        $this->actingAs($manager)->get('/app/operations?date=2026-06-16')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('metrics.pendingOvertime', 1)
                ->where('dailyControl.costControl.planned_revenue_halalas', 150000)
                ->where('dailyControl.costControl.labor_cost_halalas', 3000)
                ->where('dailyControl.costControl.material_cost_halalas', 4575)
                ->where('dailyControl.costControl.gross_profit_halalas', 142425)
                ->where('visits.0.planned_minutes', 120)
                ->where('visits.0.actual_minutes', 150)
                ->where('visits.0.overtime_status', 'pending_approval')
                ->where('visits.0.materials_used.0.name', 'Disinfectant')
            );
    }

    public function test_manager_can_approve_or_reject_billable_overtime(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $worker = Worker::factory()->create(['cost_rate_halalas' => 249600]);
        $service = Service::factory()->create();
        $contract = Contract::factory()->create([
            'service_id' => $service->id,
            'monthly_fee_halalas' => 1200000,
            'visits_per_week' => 2,
            'extra_hour_rate_halalas' => 12000,
            'overtime_policy' => 'charge_after_planned_time',
        ]);
        $visit = $this->visit($contract, $service, $worker, [
            'scheduled_for' => '2026-06-16',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
        ]);

        $this->actingAs($manager)->patch("/app/operations/visits/{$visit->id}/execution", [
            'checked_in_at' => '2026-06-16 08:00:00',
            'checked_out_at' => '2026-06-16 10:30:00',
            'material_cost_sar' => '0.00',
            'overtime_status' => 'approved',
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame('approved', $visit->overtime_status);
        $this->assertSame(30, $visit->billable_overtime_minutes);
        $this->assertSame(6000, $visit->billable_overtime_halalas);
        $this->assertSame(153000, $visit->gross_profit_halalas);

        $this->actingAs($manager)->patch("/app/operations/visits/{$visit->id}/execution", [
            'checked_in_at' => '2026-06-16 08:00:00',
            'checked_out_at' => '2026-06-16 10:30:00',
            'material_cost_sar' => '0.00',
            'overtime_status' => 'rejected',
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame('rejected', $visit->overtime_status);
        $this->assertSame(0, $visit->billable_overtime_minutes);
        $this->assertSame(0, $visit->billable_overtime_halalas);
        $this->assertSame(147000, $visit->gross_profit_halalas);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'visit.execution_updated',
            'auditable_type' => Visit::class,
            'auditable_id' => $visit->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function visit(Contract $contract, Service $service, ?Worker $worker = null, array $overrides = []): Visit
    {
        $worker ??= Worker::factory()->create();

        return Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $service->id,
            'scheduled_for' => now()->toDateString(),
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'status' => 'scheduled',
            ...$overrides,
        ]);
    }
}
