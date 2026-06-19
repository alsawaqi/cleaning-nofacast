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

class WorkerPortalEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_worker_sees_only_their_own_today_visits_with_timer_context(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 07:30:00');

        [$user, $worker] = $this->workerAccount('Ali Field');
        $otherWorker = Worker::factory()->create(['name' => 'Other Worker']);
        $service = Service::factory()->create(['title' => 'Office Cleaning']);
        $contract = Contract::factory()->create(['service_id' => $service->id]);

        $ownVisit = $this->visit($contract, $service, $worker, [
            'starts_at' => '08:00',
            'ends_at' => '10:00',
        ]);
        $this->visit($contract, $service, $otherWorker, [
            'starts_at' => '11:00',
            'ends_at' => '12:00',
        ]);

        $this->actingAs($user)->get('/app/worker/today')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Worker/Today')
                ->where('worker.id', $worker->id)
                ->where('worker.name', 'Ali Field')
                ->has('visits', 1)
                ->where('visits.0.id', $ownVisit->id)
                ->where('visits.0.planned_minutes', 120)
                ->where('visits.0.timer_state', 'not_started')
                ->where('visits.0.can_start', true)
                ->where('visits.0.can_finish', false)
            );
    }

    public function test_worker_can_start_assigned_visit_from_mobile_portal(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 08:05:00');

        [$user, $worker] = $this->workerAccount();
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, $worker, [
            'starts_at' => '08:00',
            'ends_at' => '10:00',
        ]);

        $this->actingAs($user)->post("/app/worker/visits/{$visit->id}/start")
            ->assertRedirect();

        $visit->refresh();

        $this->assertSame('in_progress', $visit->status);
        $this->assertSame('2026-06-16 08:05:00', $visit->checked_in_at->toDateTimeString());
        $this->assertSame(120, $visit->planned_minutes);
        $this->assertSame('not_required', $visit->overtime_status);
    }

    public function test_worker_can_finish_visit_with_checklist_materials_photos_and_pending_overtime(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 10:45:00');

        [$user, $worker] = $this->workerAccount();
        $worker->forceFill(['cost_rate_halalas' => 249600])->save();
        $service = Service::factory()->create(['title' => 'Facility Cleaning']);
        $contract = Contract::factory()->create([
            'service_id' => $service->id,
            'monthly_fee_halalas' => 1200000,
            'visits_per_week' => 2,
            'extra_hour_rate_halalas' => 12000,
            'overtime_policy' => 'requires_approval',
        ]);
        $visit = $this->visit($contract, $service, $worker, [
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'in_progress',
            'checked_in_at' => '2026-06-16 08:00:00',
        ]);
        $checklistItem = $visit->checklistItems()->create([
            'label' => 'Clean reception',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->post("/app/worker/visits/{$visit->id}/finish", [
            'completed_items' => [$checklistItem->id],
            'materials_used' => [
                ['name' => 'Disinfectant', 'quantity' => '1 bottle', 'cost_sar' => '30.00'],
            ],
            'photos' => [
                'worker-photos/reception-after.jpg',
            ],
            'execution_notes' => 'Extra time spent on reception.',
        ])->assertRedirect();

        $visit->refresh();
        $checklistItem->refresh();

        $this->assertSame('completed', $visit->status);
        $this->assertSame('done', $checklistItem->status);
        $this->assertSame(120, $visit->planned_minutes);
        $this->assertSame(165, $visit->actual_minutes);
        $this->assertSame(45, $visit->overtime_minutes);
        $this->assertSame('pending_approval', $visit->overtime_status);
        $this->assertSame(150000, $visit->planned_revenue_halalas);
        $this->assertSame(3300, $visit->labor_cost_halalas);
        $this->assertSame(3000, $visit->material_cost_halalas);
        $this->assertSame(0, $visit->billable_overtime_halalas);
        $this->assertSame(143700, $visit->gross_profit_halalas);
        $this->assertSame('Disinfectant', $visit->materials_used[0]['name']);
        $this->assertSame(['worker-photos/reception-after.jpg'], $visit->photos);
    }

    public function test_worker_can_report_issue_without_accessing_admin_operations_routes(): void
    {
        $this->withoutVite();

        [$user, $worker] = $this->workerAccount();
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, $worker);

        $this->actingAs($user)->post("/app/worker/visits/{$visit->id}/issue", [
            'issue_note' => 'Customer requested an extra storage room.',
            'photos' => ['worker-photos/storage-request.jpg'],
        ])->assertRedirect();

        $visit->refresh();

        $this->assertSame('issue_reported', $visit->status);
        $this->assertSame('Customer requested an extra storage room.', $visit->issue_note);
        $this->assertSame(['worker-photos/storage-request.jpg'], $visit->photos);

        $this->actingAs($user)->post("/app/operations/visits/{$visit->id}/check-in")
            ->assertForbidden();
    }

    public function test_worker_cannot_touch_another_workers_visit(): void
    {
        $this->withoutVite();

        [$user] = $this->workerAccount();
        $otherWorker = Worker::factory()->create();
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $otherVisit = $this->visit($contract, $service, $otherWorker);

        $this->actingAs($user)->post("/app/worker/visits/{$otherVisit->id}/start")
            ->assertNotFound();

        $this->actingAs($user)->post("/app/worker/visits/{$otherVisit->id}/finish")
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Worker}
     */
    private function workerAccount(string $name = 'Worker User'): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'role' => 'worker',
        ]);
        $worker = Worker::factory()->create([
            'user_id' => $user->id,
            'name' => $name,
        ]);

        return [$user, $worker];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function visit(Contract $contract, Service $service, Worker $worker, array $overrides = []): Visit
    {
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
