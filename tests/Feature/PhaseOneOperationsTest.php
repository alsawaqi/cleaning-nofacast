<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Models\Worker;
use App\Services\Finance\PaymentRecorder;
use App\Services\Operations\ScheduleGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseOneOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_cannot_be_double_booked_for_overlapping_assignments(): void
    {
        $worker = Worker::factory()->create();
        $site = CustomerSite::factory()->create();
        $otherSite = CustomerSite::factory()->create();

        Assignment::factory()->create([
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
        ]);

        $this->expectException(ValidationException::class);

        app(ScheduleGenerator::class)->createAssignment([
            'worker_id' => $worker->id,
            'customer_site_id' => $otherSite->id,
            'weekday' => 1,
            'starts_at' => '11:30',
            'ends_at' => '14:00',
            'share_percent' => 40,
        ]);
    }

    public function test_contract_assignments_generate_visit_checklists_for_the_date_range(): void
    {
        $service = Service::factory()->create([
            'checklist_template' => [
                ['label' => 'Sweep floors'],
                ['label' => 'Clean glass'],
            ],
        ]);
        $contract = Contract::factory()->create([
            'starts_on' => '2026-06-15',
            'ends_on' => '2026-06-29',
        ]);
        $assignment = Assignment::factory()->for($contract)->create([
            'service_id' => $service->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
        ]);

        $visits = app(ScheduleGenerator::class)->generateVisits(
            $contract,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-29'),
        );

        $this->assertCount(3, $visits);
        $this->assertSame($assignment->id, $visits->first()->assignment_id);
        $this->assertSame(['Sweep floors', 'Clean glass'], $visits->first()->checklistItems->pluck('label')->all());
    }

    public function test_assignment_task_instructions_override_service_checklist_when_generating_visits(): void
    {
        $service = Service::factory()->create([
            'checklist_template' => [
                ['label' => 'Generic sweep'],
                ['label' => 'Generic mop'],
            ],
        ]);
        $contract = Contract::factory()->create([
            'starts_on' => '2026-06-15',
            'ends_on' => '2026-06-22',
        ]);
        $assignment = Assignment::factory()->for($contract)->create([
            'service_id' => $service->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'task_instructions' => [
                ['label' => 'Clean reception glass', 'is_required' => true],
                ['label' => 'Sanitize bathrooms', 'is_required' => true],
                ['label' => 'Remove waste bags', 'is_required' => false],
            ],
        ]);

        $visits = app(ScheduleGenerator::class)->generateVisits(
            $contract,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-22'),
        );

        $this->assertCount(2, $visits);
        $this->assertSame($assignment->id, $visits->first()->assignment_id);
        $this->assertSame(
            ['Clean reception glass', 'Sanitize bathrooms', 'Remove waste bags'],
            $visits->first()->checklistItems->pluck('label')->all()
        );
        $this->assertSame([true, true, false], $visits->first()->checklistItems->pluck('is_required')->all());
    }

    public function test_team_assignment_generates_one_main_worker_visit_with_merged_team_tasks(): void
    {
        $service = Service::factory()->create([
            'checklist_template' => [
                ['label' => 'Generic sweep'],
            ],
        ]);
        $contract = Contract::factory()->create([
            'starts_on' => '2026-06-15',
            'ends_on' => '2026-06-22',
        ]);
        $mainWorker = Worker::factory()->create();
        $supportWorker = Worker::factory()->create();
        $mainAssignment = Assignment::factory()->for($contract)->create([
            'service_id' => $service->id,
            'worker_id' => $mainWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'team_role' => 'main',
            'task_instructions' => [
                ['label' => 'Open site and start timer', 'is_required' => true],
            ],
        ]);
        Assignment::factory()->for($contract)->create([
            'service_id' => $service->id,
            'worker_id' => $supportWorker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'team_role' => 'support',
            'task_instructions' => [
                ['label' => 'Clean rear stairwell', 'is_required' => false],
            ],
        ]);

        $visits = app(ScheduleGenerator::class)->generateVisits(
            $contract,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-15'),
        );

        $this->assertCount(1, $visits);
        $this->assertSame($mainAssignment->id, $visits->first()->assignment_id);
        $this->assertSame($mainWorker->id, $visits->first()->worker_id);
        $this->assertSame(
            ['Open site and start timer', 'Clean rear stairwell'],
            $visits->first()->checklistItems->pluck('label')->all()
        );
        $this->assertSame([true, false], $visits->first()->checklistItems->pluck('is_required')->all());
    }

    public function test_payment_recorder_marks_invoice_partial_and_paid(): void
    {
        $invoice = Invoice::factory()->create([
            'gross_total_halalas' => 11500,
            'paid_total_halalas' => 0,
            'status' => 'issued',
        ]);

        app(PaymentRecorder::class)->record($invoice, 5000, 'bank_transfer', 'TXN-1');

        $this->assertSame('partial', $invoice->refresh()->status);
        $this->assertSame(5000, $invoice->paid_total_halalas);

        app(PaymentRecorder::class)->record($invoice, 6500, 'cash', 'RCPT-2');

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame(11500, $invoice->paid_total_halalas);
    }

    public function test_accountant_can_record_invoice_payment_as_sar_decimal(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);
        $invoice = Invoice::factory()->create([
            'gross_total_halalas' => 11500,
            'paid_total_halalas' => 0,
            'status' => 'issued',
        ]);

        $this->actingAs($accountant)->post("/app/finance/invoices/{$invoice->id}/payments", [
            'amount_sar' => '50.25',
            'method' => 'bank_transfer',
            'reference' => 'SAR-1',
        ])->assertRedirect();

        $this->assertSame('partial', $invoice->refresh()->status);
        $this->assertSame(5025, $invoice->paid_total_halalas);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount_halalas' => 5025,
            'method' => 'bank_transfer',
            'reference' => 'SAR-1',
        ]);
    }

    public function test_back_office_dashboard_requires_authenticated_staff(): void
    {
        $this->get('/app/dashboard')->assertRedirect('/login');

        $this->withoutVite();

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->get('/app/dashboard')->assertOk();
    }
}
