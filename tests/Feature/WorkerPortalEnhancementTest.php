<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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

    public function test_worker_today_mobile_payload_includes_summary_active_visit_and_guided_workflow(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 07:30:00');

        [$user, $worker] = $this->workerAccount('Mobile Worker');
        $service = Service::factory()->create(['title' => 'Villa Cleaning']);
        $contract = Contract::factory()->create([
            'service_id' => $service->id,
            'reference' => 'CON-MOBILE-01',
        ]);
        $contract->site()->update([
            'name' => 'Riyadh Villa',
            'city' => 'Riyadh',
            'district' => 'Al Olaya',
            'address' => 'King Fahd Road',
            'contact_name' => 'Site Contact',
            'contact_phone' => '+966500000111',
            'latitude' => '24.7136000',
            'longitude' => '46.6753000',
            'formatted_address' => 'King Fahd Road, Riyadh',
        ]);

        $completedVisit = $this->visit($contract, $service, $worker, [
            'starts_at' => '05:00',
            'ends_at' => '06:00',
            'status' => 'completed',
            'checked_in_at' => '2026-06-16 05:00:00',
            'checked_out_at' => '2026-06-16 06:00:00',
            'planned_minutes' => 60,
            'actual_minutes' => 60,
        ]);
        $runningVisit = $this->visit($contract, $service, $worker, [
            'starts_at' => '06:15',
            'ends_at' => '08:15',
            'status' => 'in_progress',
            'checked_in_at' => '2026-06-16 06:15:00',
        ]);
        $scheduledVisit = $this->visit($contract, $service, $worker, [
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'status' => 'scheduled',
        ]);
        $scheduledVisit->checklistItems()->create([
            'label' => 'Clean majlis',
            'is_required' => true,
            'status' => 'pending',
        ]);
        $scheduledVisit->checklistItems()->create([
            'label' => 'Refresh towels',
            'is_required' => false,
            'status' => 'done',
            'completed_at' => now(),
        ]);
        $runningVisit->checklistItems()->create([
            'label' => 'Arrival photo',
            'is_required' => true,
            'status' => 'done',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)->get('/app/worker/today')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Worker/Today')
                ->where('summary.total_visits', 3)
                ->where('summary.scheduled_visits', 1)
                ->where('summary.in_progress_visits', 1)
                ->where('summary.completed_visits', 1)
                ->where('summary.open_required_tasks', 1)
                ->where('activeVisitId', $runningVisit->id)
                ->where('nextVisitId', $runningVisit->id)
                ->where('visits.0.id', $completedVisit->id)
                ->where('visits.1.workflow.primary_action', 'finish')
                ->where('visits.1.checklist_summary.completed_required', 1)
                ->where('visits.2.id', $scheduledVisit->id)
                ->where('visits.2.workflow.primary_action', 'start')
                ->where('visits.2.checklist_summary.open_required', 1)
                ->where('visits.2.contract.reference', 'CON-MOBILE-01')
                ->where('visits.2.site.contact_phone', '+966500000111')
                ->where('visits.2.site.maps_url', 'https://www.google.com/maps/search/?api=1&query=24.7136000,46.6753000')
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

    public function test_worker_start_and_finish_records_gps_evidence_and_uploaded_visit_photos(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        Carbon::setTestNow('2026-06-16 08:05:00');

        [$user, $worker] = $this->workerAccount();
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, $worker, [
            'starts_at' => '08:00',
            'ends_at' => '10:00',
        ]);
        $checklistItem = $visit->checklistItems()->create([
            'label' => 'Arrival evidence',
            'is_required' => true,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->post("/app/worker/visits/{$visit->id}/start", [
            'check_in_latitude' => '24.7136000',
            'check_in_longitude' => '46.6753000',
            'check_in_accuracy_meters' => 18,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $visit->refresh();

        $this->assertSame('in_progress', $visit->status);
        $this->assertEquals(24.7136000, (float) $visit->check_in_latitude);
        $this->assertEquals(46.6753000, (float) $visit->check_in_longitude);
        $this->assertSame(18, $visit->check_in_accuracy_meters);

        Carbon::setTestNow('2026-06-16 10:05:00');

        $this->actingAs($user)->post("/app/worker/visits/{$visit->id}/finish", [
            'completed_items' => [$checklistItem->id],
            'materials_used' => [],
            'photos' => ['worker-photos/manual-note.jpg'],
            'photo_files' => [
                UploadedFile::fake()->image('after.jpg', 640, 480),
            ],
            'execution_notes' => 'Uploaded after photo from mobile camera.',
            'check_out_latitude' => '24.7137000',
            'check_out_longitude' => '46.6754000',
            'check_out_accuracy_meters' => 21,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $visit->refresh();

        $uploadedPhoto = collect($visit->photos)
            ->first(fn (string $path): bool => str_starts_with($path, 'worker-photos/visits/'));

        $this->assertSame('completed', $visit->status);
        $this->assertContains('worker-photos/manual-note.jpg', $visit->photos);
        $this->assertNotNull($uploadedPhoto);
        Storage::disk('public')->assertExists($uploadedPhoto);
        $this->assertEquals(24.7137000, (float) $visit->check_out_latitude);
        $this->assertEquals(46.6754000, (float) $visit->check_out_longitude);
        $this->assertSame(21, $visit->check_out_accuracy_meters);

        $this->actingAs($user)->get('/app/worker/today')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('visits.0.check_in_location.latitude', '24.7136000')
                ->where('visits.0.check_in_location.longitude', '46.6753000')
                ->where('visits.0.check_in_location.accuracy_meters', 18)
                ->where('visits.0.check_out_location.latitude', '24.7137000')
                ->where('visits.0.check_out_location.longitude', '46.6754000')
                ->where('visits.0.check_out_location.accuracy_meters', 21));
    }

    public function test_worker_can_save_checklist_notes_and_photo_evidence_from_mobile_flow(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 08:30:00');

        [$user, $worker] = $this->workerAccount();
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, $worker, [
            'status' => 'in_progress',
            'checked_in_at' => '2026-06-16 08:00:00',
        ]);
        $requiredItem = $visit->checklistItems()->create([
            'label' => 'Clean reception',
            'is_required' => true,
            'status' => 'pending',
        ]);
        $optionalItem = $visit->checklistItems()->create([
            'label' => 'Polish glass',
            'is_required' => false,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->post("/app/worker/visits/{$visit->id}/checklist", [
            'items' => [
                [
                    'id' => $requiredItem->id,
                    'status' => 'done',
                    'notes' => 'Reception completed before 08:30.',
                    'photo_path' => 'worker-photos/reception-after.jpg',
                ],
                [
                    'id' => $optionalItem->id,
                    'status' => 'skipped',
                    'notes' => 'Customer asked to skip glass today.',
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $requiredItem->refresh();
        $optionalItem->refresh();

        $this->assertSame('done', $requiredItem->status);
        $this->assertSame('Reception completed before 08:30.', $requiredItem->notes);
        $this->assertSame('worker-photos/reception-after.jpg', $requiredItem->photo_path);
        $this->assertSame('2026-06-16 08:30:00', $requiredItem->completed_at->toDateTimeString());
        $this->assertSame('skipped', $optionalItem->status);
        $this->assertSame('Customer asked to skip glass today.', $optionalItem->notes);
        $this->assertNull($optionalItem->completed_at);
    }

    public function test_worker_can_upload_checklist_and_issue_photos_from_mobile_camera(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        Carbon::setTestNow('2026-06-16 08:30:00');

        [$user, $worker] = $this->workerAccount();
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, $worker, [
            'status' => 'in_progress',
            'checked_in_at' => '2026-06-16 08:00:00',
        ]);
        $checklistItem = $visit->checklistItems()->create([
            'label' => 'Arrival photo',
            'is_required' => true,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->post("/app/worker/visits/{$visit->id}/checklist", [
            'items' => [
                [
                    'id' => $checklistItem->id,
                    'status' => 'done',
                    'notes' => 'Arrival uploaded from camera.',
                    'photo_file' => UploadedFile::fake()->image('arrival.jpg', 640, 480),
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $checklistItem->refresh();

        $this->assertSame('done', $checklistItem->status);
        $this->assertStringStartsWith('worker-photos/checklist/', $checklistItem->photo_path);
        Storage::disk('public')->assertExists($checklistItem->photo_path);

        $this->actingAs($user)->post("/app/worker/visits/{$visit->id}/issue", [
            'issue_note' => 'Customer asked to add a locked storage room.',
            'photos' => [],
            'photo_files' => [
                UploadedFile::fake()->image('locked-room.jpg', 640, 480),
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $visit->refresh();

        $uploadedIssuePhoto = collect($visit->photos)
            ->first(fn (string $path): bool => str_starts_with($path, 'worker-photos/issues/'));

        $this->assertSame('issue_reported', $visit->status);
        $this->assertNotNull($uploadedIssuePhoto);
        Storage::disk('public')->assertExists($uploadedIssuePhoto);
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

    public function test_worker_cannot_finish_visit_before_starting_it(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 08:30:00');

        [$user, $worker] = $this->workerAccount();
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, $worker, [
            'status' => 'scheduled',
            'checked_in_at' => null,
        ]);

        $this->actingAs($user)->from('/app/worker/today')
            ->post("/app/worker/visits/{$visit->id}/finish", [
                'completed_items' => [],
                'materials_used' => [],
                'photos' => [],
            ])
            ->assertRedirect('/app/worker/today')
            ->assertSessionHasErrors('visit');

        $visit->refresh();

        $this->assertSame('scheduled', $visit->status);
        $this->assertNull($visit->checked_out_at);
    }

    public function test_worker_cannot_finish_visit_until_required_tasks_are_complete(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 09:30:00');

        [$user, $worker] = $this->workerAccount();
        $service = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $service->id]);
        $visit = $this->visit($contract, $service, $worker, [
            'status' => 'in_progress',
            'checked_in_at' => '2026-06-16 08:00:00',
        ]);
        $visit->checklistItems()->create([
            'label' => 'Required arrival photo',
            'is_required' => true,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->from('/app/worker/today')
            ->post("/app/worker/visits/{$visit->id}/finish", [
                'completed_items' => [],
                'materials_used' => [],
                'photos' => [],
            ])
            ->assertRedirect('/app/worker/today')
            ->assertSessionHasErrors('completed_items');

        $visit->refresh();

        $this->assertSame('in_progress', $visit->status);
        $this->assertNull($visit->checked_out_at);
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
