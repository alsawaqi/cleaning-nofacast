<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Visit;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminFinanceEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_accountant_views_receivables_aging_cheques_and_credit_notes(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 10:00:00');

        $accountant = User::factory()->create(['role' => 'accountant']);
        $current = Invoice::factory()->create([
            'number' => 'INV-CURRENT',
            'due_date' => '2026-06-20',
            'gross_total_halalas' => 10000,
            'paid_total_halalas' => 0,
        ]);
        $oneToThirty = Invoice::factory()->create([
            'number' => 'INV-1-30',
            'due_date' => '2026-06-01',
            'gross_total_halalas' => 20000,
            'paid_total_halalas' => 5000,
        ]);
        Invoice::factory()->create([
            'number' => 'INV-31-60',
            'due_date' => '2026-05-01',
            'gross_total_halalas' => 30000,
            'paid_total_halalas' => 0,
        ]);
        Invoice::factory()->create([
            'number' => 'INV-61-PLUS',
            'due_date' => '2026-03-20',
            'gross_total_halalas' => 40000,
            'paid_total_halalas' => 0,
        ]);

        DB::table('cheques')->insert([
            'customer_id' => $current->customer_id,
            'invoice_id' => $current->id,
            'cheque_number' => 'CHQ-1001',
            'amount_halalas' => 7500,
            'due_date' => '2026-06-25',
            'status' => 'held',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('credit_notes')->insert([
            'invoice_id' => $oneToThirty->id,
            'number' => 'CN-2026-00001',
            'amount_halalas' => 2500,
            'status' => 'approved',
            'reason' => 'Service credit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($accountant)->get('/app/finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Finance/Index')
                ->where('aging.current', 10000)
                ->where('aging.oneToThirty', 12500)
                ->where('aging.thirtyOneToSixty', 30000)
                ->where('aging.sixtyPlus', 40000)
                ->where('invoices.1.aging_bucket', '1-30')
                ->where('invoices.1.days_overdue', 15)
                ->where('cheques.0.cheque_number', 'CHQ-1001')
                ->where('cheques.0.amount_sar', '75.00')
                ->where('creditNotes.0.number', 'CN-2026-00001')
                ->where('creditNotes.0.amount_sar', '25.00')
                ->missing('expenses')
                ->missing('expenseSummary')
                ->missing('expenseCategories')
                ->missing('expenseTypes')
                ->missing('expenseStatuses')
            );
    }

    public function test_accountant_can_create_cheque_against_invoice(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);
        $invoice = Invoice::factory()->create();

        $this->actingAs($accountant)->post('/app/finance/cheques', [
            'invoice_id' => $invoice->id,
            'cheque_number' => 'CHQ-2002',
            'amount_sar' => '250.75',
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('cheques', [
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'cheque_number' => 'CHQ-2002',
            'amount_halalas' => 25075,
            'status' => 'held',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $accountant->id,
            'action' => 'cheque.created',
        ]);
    }

    public function test_accountant_can_review_customer_payment_proofs(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-20 12:00:00');

        $accountant = User::factory()->create(['role' => 'accountant']);
        $invoice = Invoice::factory()->create([
            'number' => 'INV-PROOF-ADMIN',
            'gross_total_halalas' => 115000,
            'paid_total_halalas' => 0,
            'status' => 'sent',
        ]);
        $approveId = DB::table('payment_proofs')->insertGetId([
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'amount_halalas' => 65000,
            'method' => 'bank_transfer',
            'reference' => 'BANK-PROOF-01',
            'paid_on' => '2026-06-19',
            'proof_path' => 'payment-proofs/bank-proof-01.pdf',
            'customer_note' => 'First transfer',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rejectId = DB::table('payment_proofs')->insertGetId([
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'amount_halalas' => 50000,
            'method' => 'cash',
            'reference' => 'CASH-PROOF-02',
            'paid_on' => '2026-06-19',
            'proof_path' => 'payment-proofs/cash-proof-02.pdf',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($accountant)
            ->get('/app/finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Finance/Index')
                ->where('metrics.pendingPaymentProofs', 115000)
                ->where('paymentProofs.0.invoice_number', 'INV-PROOF-ADMIN')
                ->where('paymentProofs.0.reference', 'BANK-PROOF-01'));

        $this->actingAs($accountant)
            ->patch("/app/finance/payment-proofs/{$approveId}/approve", [
                'admin_note' => 'Verified in bank statement.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_proofs', [
            'id' => $approveId,
            'status' => 'approved',
            'reviewed_by' => $accountant->id,
            'admin_note' => 'Verified in bank statement.',
        ]);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount_halalas' => 65000,
            'method' => 'bank_transfer',
            'reference' => 'BANK-PROOF-01',
        ]);
        $this->assertSame(65000, $invoice->refresh()->paid_total_halalas);
        $this->assertSame('partial', $invoice->status);

        $this->actingAs($accountant)
            ->patch("/app/finance/payment-proofs/{$rejectId}/reject", [
                'admin_note' => 'Receipt is not readable.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_proofs', [
            'id' => $rejectId,
            'status' => 'rejected',
            'reviewed_by' => $accountant->id,
            'admin_note' => 'Receipt is not readable.',
        ]);
        $this->assertSame(65000, $invoice->refresh()->paid_total_halalas);
    }

    public function test_accountant_can_invoice_approved_overtime_once(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-19 11:00:00');

        $accountant = User::factory()->create(['role' => 'accountant']);
        $contract = Contract::factory()->create([
            'reference' => 'CON-OT-001',
            'extra_hour_rate_halalas' => 12000,
            'overtime_policy' => 'charge_after_planned_time',
            'prices_include_vat' => false,
            'vat_rate' => 15,
        ]);
        $worker = Worker::factory()->create();
        $visit = Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-18',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'completed',
            'checked_in_at' => '2026-06-18 08:00:00',
            'checked_out_at' => '2026-06-18 10:30:00',
            'planned_minutes' => 120,
            'actual_minutes' => 150,
            'variance_minutes' => 30,
            'overtime_minutes' => 30,
            'billable_overtime_minutes' => 30,
            'overtime_status' => 'approved',
            'billable_overtime_halalas' => 6000,
        ]);

        $this->actingAs($accountant)->get('/app/finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('billableExtras.0.visit_id', $visit->id)
                ->where('billableExtras.0.contract', 'CON-OT-001')
                ->where('billableExtras.0.minutes', 30)
                ->where('billableExtras.0.amount_halalas', 6000)
                ->where('billableExtras.0.amount_sar', '60.00')
            );

        $this->actingAs($accountant)->post("/app/finance/billable-extras/visits/{$visit->id}/invoice")
            ->assertRedirect();

        $invoice = Invoice::query()
            ->where('contract_id', $contract->id)
            ->where('gross_total_halalas', 6900)
            ->firstOrFail();

        $this->assertSame(6000, $invoice->net_total_halalas);
        $this->assertSame(900, $invoice->vat_total_halalas);
        $this->assertSame($invoice->id, $visit->refresh()->overtime_invoice_id);
        $this->assertNotNull($visit->overtime_billed_at);
        $this->assertDatabaseHas('invoice_line_items', [
            'invoice_id' => $invoice->id,
            'visit_id' => $visit->id,
            'line_type' => 'overtime',
            'description' => 'Extra time: 30 minutes',
            'quantity' => 30,
            'unit_label' => 'minute',
            'unit_price_halalas' => 200,
            'net_total_halalas' => 6000,
            'vat_total_halalas' => 900,
            'gross_total_halalas' => 6900,
        ]);

        $this->actingAs($accountant)->get("/app/finance/invoices/{$invoice->id}/print")
            ->assertOk()
            ->assertSee('Extra time: 30 minutes')
            ->assertSee('SAR 60.00')
            ->assertSee('SAR 69.00');

        $this->actingAs($accountant)->get('/app/finance')
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('billableExtras.0'));

        $this->actingAs($accountant)->post("/app/finance/billable-extras/visits/{$visit->id}/invoice")
            ->assertSessionHasErrors('visit_id');
    }

    public function test_inclusive_vat_overtime_invoice_keeps_net_line_unit_price(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-19 11:00:00');

        $accountant = User::factory()->create(['role' => 'accountant']);
        $contract = Contract::factory()->create([
            'prices_include_vat' => true,
            'vat_rate' => 15,
        ]);
        $worker = Worker::factory()->create();
        $visit = Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-18',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'completed',
            'planned_minutes' => 120,
            'actual_minutes' => 150,
            'variance_minutes' => 30,
            'overtime_minutes' => 30,
            'billable_overtime_minutes' => 30,
            'overtime_status' => 'approved',
            'billable_overtime_halalas' => 6900,
        ]);

        $this->actingAs($accountant)->post("/app/finance/billable-extras/visits/{$visit->id}/invoice")
            ->assertRedirect();

        $invoice = Invoice::query()->where('contract_id', $contract->id)->firstOrFail();

        $this->assertSame(6000, $invoice->net_total_halalas);
        $this->assertSame(900, $invoice->vat_total_halalas);
        $this->assertSame(6900, $invoice->gross_total_halalas);
        $this->assertDatabaseHas('invoice_line_items', [
            'invoice_id' => $invoice->id,
            'visit_id' => $visit->id,
            'line_type' => 'overtime',
            'quantity' => 30,
            'unit_price_halalas' => 200,
            'net_total_halalas' => 6000,
            'vat_total_halalas' => 900,
            'gross_total_halalas' => 6900,
        ]);
    }

    public function test_clearing_cheque_records_invoice_payment(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);
        $invoice = Invoice::factory()->create([
            'gross_total_halalas' => 11500,
            'paid_total_halalas' => 0,
            'status' => 'issued',
        ]);
        $chequeId = DB::table('cheques')->insertGetId([
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'cheque_number' => 'CHQ-3003',
            'amount_halalas' => 5000,
            'due_date' => now()->toDateString(),
            'status' => 'held',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($accountant)->patch("/app/finance/cheques/{$chequeId}/status", [
            'status' => 'cleared',
            'cleared_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('cheques', [
            'id' => $chequeId,
            'status' => 'cleared',
        ]);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount_halalas' => 5000,
            'method' => 'cheque',
            'reference' => 'CHQ-3003',
        ]);
        $this->assertSame(5000, $invoice->refresh()->paid_total_halalas);
        $this->assertSame('partial', $invoice->status);
    }

    public function test_accountant_can_create_and_approve_credit_note(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);
        $invoice = Invoice::factory()->create([
            'gross_total_halalas' => 11500,
            'paid_total_halalas' => 0,
            'status' => 'issued',
        ]);

        $this->actingAs($accountant)->post("/app/finance/invoices/{$invoice->id}/credit-notes", [
            'amount_sar' => '15.25',
            'reason' => 'Customer approved service reduction.',
        ])->assertRedirect();

        $creditNote = DB::table('credit_notes')->where('invoice_id', $invoice->id)->first();

        $this->assertNotNull($creditNote);
        $this->assertSame('pending_approval', $creditNote->status);
        $this->assertSame(1525, $creditNote->amount_halalas);

        $this->actingAs($accountant)->patch("/app/finance/credit-notes/{$creditNote->id}/approve")
            ->assertRedirect();

        $this->assertDatabaseHas('credit_notes', [
            'id' => $creditNote->id,
            'status' => 'approved',
        ]);

        $this->actingAs($accountant)->get('/app/finance')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('invoices.0.balance_sar', '99.75')
                ->where('invoices.0.credit_total_sar', '15.25')
            );
    }

    public function test_accountant_can_update_invoice_status(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);
        $invoice = Invoice::factory()->create(['status' => 'issued']);

        $this->actingAs($accountant)->patch("/app/finance/invoices/{$invoice->id}/status", [
            'status' => 'sent',
        ])->assertRedirect();

        $this->assertSame('sent', $invoice->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $accountant->id,
            'action' => 'invoice.status_updated',
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
        ]);
    }
}
