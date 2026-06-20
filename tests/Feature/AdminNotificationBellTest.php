<?php

namespace Tests\Feature;

use App\Models\BookingRequest;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\PaymentProof;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Visit;
use App\Models\Worker;
use App\Models\WorkerAvailabilityBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminNotificationBellTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_notification_feed_surfaces_actionable_operations_finance_and_quality_items(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-21 08:30:00');

        $owner = User::factory()->create(['role' => 'owner']);
        $customer = Customer::factory()->create(['name' => 'Riyadh Quality Customer']);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Olaya Tower']);
        $service = Service::factory()->create(['title' => 'Tower Cleaning']);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'reference' => 'CON-BELL-01',
        ]);
        $worker = Worker::factory()->create(['name' => 'Unavailable Worker']);

        BookingRequest::query()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'requested_for' => '2026-06-22',
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'worker_count' => 2,
            'duration_minutes' => 120,
            'status' => 'pending',
            'customer_note' => 'Need approval for tomorrow.',
        ]);

        $reviewVisit = Visit::query()->create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => '2026-06-21',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'completed',
            'checked_in_at' => '2026-06-21 08:00:00',
            'checked_out_at' => '2026-06-21 10:00:00',
            'completion_review_status' => 'pending_review',
        ]);

        $conflictVisit = Visit::query()->create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => '2026-06-21',
            'starts_at' => '14:00',
            'ends_at' => '16:00',
            'status' => 'scheduled',
        ]);

        WorkerAvailabilityBlock::query()->create([
            'worker_id' => $worker->id,
            'date' => '2026-06-21',
            'starts_at' => '13:00',
            'ends_at' => '17:00',
            'status' => 'on_leave',
            'reason' => 'Approved leave',
            'created_by' => $owner->id,
        ]);

        ServiceRequest::query()->create([
            'type' => 'quality_follow_up',
            'status' => 'pending',
            'priority' => 'urgent',
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'visit_id' => $reviewVisit->id,
            'subject' => 'Low customer rating follow-up',
            'description' => 'Customer rated the visit low.',
            'requested_for' => '2026-06-21',
        ]);

        $invoice = Invoice::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'number' => 'INV-BELL-01',
            'gross_total_halalas' => 115000,
        ]);

        PaymentProof::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount_halalas' => 115000,
            'method' => 'bank_transfer',
            'reference' => 'PAY-BELL-01',
            'paid_on' => '2026-06-21',
            'proof_path' => 'payment-proofs/bell.pdf',
            'status' => 'pending',
        ]);

        $this->actingAs($owner)->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notifications.total', 5)
                ->where('notifications.pendingBookingRequests', 1)
                ->where('notifications.pendingServiceRequests', 1)
                ->where('notifications.pendingPaymentProofs', 1)
                ->where('notifications.pendingVisitReviews', 1)
                ->where('notifications.pendingWorkerAvailabilityConflicts', 1)
                ->where('notifications.visitReviews.0.visit_id', $reviewVisit->id)
                ->where('notifications.visitReviews.0.href', '/app/operations?tab=visits&date=2026-06-21')
                ->where('notifications.workerAvailabilityConflicts.0.visit_id', $conflictVisit->id)
                ->where('notifications.workerAvailabilityConflicts.0.worker', 'Unavailable Worker')
                ->where('notifications.workerAvailabilityConflicts.0.href', '/app/operations?tab=capacity&date=2026-06-21')
                ->has('notifications.items', 5)
                ->where('notifications.items.0.type', 'service_request')
                ->where('notifications.items.0.priority', 'urgent')
                ->where('notifications.items.1.type', 'visit_review')
                ->where('notifications.items.2.type', 'worker_availability_conflict'));
    }
}
