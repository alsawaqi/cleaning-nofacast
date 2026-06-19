<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
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

    public function test_accountant_can_record_operating_expense_with_vat_and_receipt(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);

        $this->actingAs($accountant)->post('/app/finance/expenses', [
            'expense_date' => '2026-06-18',
            'expense_type' => 'operating_expense',
            'category' => 'office_rent',
            'vendor' => 'Riyadh Business Center',
            'description' => 'Monthly office rent',
            'amount_sar' => '850.75',
            'vat_sar' => '110.97',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'EXP-TRX-1001',
            'status' => 'paid',
            'receipt_path' => 'expense-receipts/rent-june.pdf',
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'expense_date' => '2026-06-18',
            'expense_type' => 'operating_expense',
            'category' => 'office_rent',
            'vendor' => 'Riyadh Business Center',
            'amount_halalas' => 85075,
            'vat_halalas' => 11097,
            'payment_method' => 'bank_transfer',
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $accountant->id,
            'action' => 'expense.created',
        ]);

        $this->actingAs($accountant)->get('/app/finance')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('expenseSummary.operating_expenses_halalas', 85075)
                ->where('expenseSummary.vat_halalas', 11097)
                ->where('expenses.0.vendor', 'Riyadh Business Center')
                ->where('expenses.0.amount_sar', '850.75')
            );
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
