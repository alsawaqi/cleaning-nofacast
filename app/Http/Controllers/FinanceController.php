<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSarMoney;
use App\Models\AuditLog;
use App\Models\Cheque;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Finance\PaymentRecorder;
use App\Services\Integrations\PdfExporter;
use App\Services\Support\NumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    use HandlesSarMoney;

    public function index(PdfExporter $pdfExporter): Response
    {
        $invoices = Invoice::query()
            ->with(['customer', 'contract', 'creditNotes'])
            ->orderByDesc('due_date')
            ->get()
            ->map(fn (Invoice $invoice): array => $this->serializeInvoice($invoice, $pdfExporter));

        return Inertia::render('Finance/Index', [
            'invoices' => $invoices,
            'aging' => $this->aging($invoices),
            'expectedCollections' => $this->expectedCollections($invoices),
            'cheques' => $this->chequeRows(),
            'creditNotes' => $this->creditNoteRows(),
            'expenses' => $this->expenseRows(),
            'expenseSummary' => $this->expenseSummary(),
            'expenseCategories' => $this->expenseCategories(),
            'expenseTypes' => $this->expenseTypes(),
            'expenseStatuses' => $this->expenseStatuses(),
            'metrics' => [
                'due' => $invoices->sum('balance_halalas'),
                'paid' => $invoices->sum('paid_total_halalas'),
                'overdue' => $invoices->where('status', '!=', 'paid')->filter(fn ($invoice) => $invoice['due_date'] < now()->toDateString())->sum('balance_halalas'),
                'expected30' => $invoices->filter(fn ($invoice) => $invoice['due_date'] <= now()->addDays(30)->toDateString())->sum('balance_halalas'),
                'heldCheques' => Cheque::query()->where('status', 'held')->sum('amount_halalas'),
                'pendingCreditNotes' => CreditNote::query()->where('status', 'pending_approval')->sum('amount_halalas'),
            ],
        ]);
    }

    public function recordPayment(Request $request, Invoice $invoice, PaymentRecorder $recorder): RedirectResponse
    {
        $this->mergeSarFromHalalas($request, 'amount_sar', 'amount_halalas');

        $data = $request->validate([
            'amount_sar' => $this->positiveSarMoneyRules(),
            'method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $recorder->record($invoice, $this->sarToHalalas($data['amount_sar']), $data['method'], $data['reference'] ?? null);

        return back();
    }

    public function updateInvoiceStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'issued', 'sent', 'paid', 'cancelled', 'void'])],
        ]);

        $old = $invoice->only(['status']);
        $invoice->forceFill(['status' => $validated['status']])->save();

        $this->audit($request, 'invoice.status_updated', $invoice, $old, $invoice->only(['status']));

        return back()->with('success', __('Invoice status updated.'));
    }

    public function storeCheque(Request $request): RedirectResponse
    {
        $this->mergeSarFromHalalas($request, 'amount_sar', 'amount_halalas');

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'cheque_number' => ['required', 'string', 'max:80'],
            'amount_sar' => $this->positiveSarMoneyRules(),
            'due_date' => ['required', 'date'],
        ]);

        $invoice = Invoice::query()->findOrFail($validated['invoice_id']);
        $cheque = Cheque::create([
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'cheque_number' => $validated['cheque_number'],
            'amount_halalas' => $this->sarToHalalas($validated['amount_sar']),
            'due_date' => $validated['due_date'],
            'status' => 'held',
        ]);

        $this->audit($request, 'cheque.created', $cheque, [], $cheque->only(['invoice_id', 'cheque_number', 'amount_halalas', 'due_date', 'status']));

        return back()->with('success', __('Cheque recorded.'));
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $this->mergeSarFromHalalas($request, 'amount_sar', 'amount_halalas');
        $this->mergeSarFromHalalas($request, 'vat_sar', 'vat_halalas');

        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'expense_type' => ['required', Rule::in(array_column($this->expenseTypes(), 'key'))],
            'category' => ['required', Rule::in(array_column($this->expenseCategories(), 'key'))],
            'vendor' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount_sar' => $this->positiveSarMoneyRules(),
            'vat_sar' => $this->sarMoneyRules(),
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(array_column($this->expenseStatuses(), 'key'))],
            'receipt_path' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
        ]);

        $expense = Expense::create([
            ...collect($validated)
                ->except(['amount_sar', 'vat_sar'])
                ->all(),
            'amount_halalas' => $this->sarToHalalas($validated['amount_sar']),
            'vat_halalas' => $this->sarToHalalas($validated['vat_sar']),
        ]);

        $this->audit($request, 'expense.created', $expense, [], $expense->only([
            'expense_date',
            'expense_type',
            'category',
            'vendor',
            'amount_halalas',
            'vat_halalas',
            'payment_method',
            'status',
        ]));

        return back()->with('success', __('Expense recorded.'));
    }

    public function updateChequeStatus(Request $request, Cheque $cheque, PaymentRecorder $recorder): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['held', 'cleared', 'bounced', 'returned', 'cancelled'])],
            'cleared_date' => ['nullable', 'date'],
        ]);
        $old = $cheque->only(['status', 'cleared_date']);

        $cheque->forceFill([
            'status' => $validated['status'],
            'cleared_date' => $validated['status'] === 'cleared'
                ? ($validated['cleared_date'] ?? now()->toDateString())
                : null,
        ])->save();

        if ($validated['status'] === 'cleared' && $old['status'] !== 'cleared' && $cheque->invoice) {
            $recorder->record($cheque->invoice, $cheque->amount_halalas, 'cheque', $cheque->cheque_number);
        }

        $this->audit($request, 'cheque.status_updated', $cheque, $old, $cheque->only(['status', 'cleared_date']));

        return back()->with('success', __('Cheque status updated.'));
    }

    public function storeCreditNote(Request $request, Invoice $invoice, NumberGenerator $numbers): RedirectResponse
    {
        $this->mergeSarFromHalalas($request, 'amount_sar', 'amount_halalas');

        $validated = $request->validate([
            'amount_sar' => $this->positiveSarMoneyRules(),
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $amount = $this->sarToHalalas($validated['amount_sar']);

        if ($amount > $invoice->balance_halalas) {
            throw ValidationException::withMessages([
                'amount_sar' => __('Credit note amount cannot exceed the invoice balance.'),
            ]);
        }

        $creditNote = $invoice->creditNotes()->create([
            'number' => $numbers->next('CN', CreditNote::class),
            'amount_halalas' => $amount,
            'status' => 'pending_approval',
            'reason' => $validated['reason'],
        ]);

        $this->audit($request, 'credit_note.created', $creditNote, [], $creditNote->only(['invoice_id', 'number', 'amount_halalas', 'status', 'reason']));

        return back()->with('success', __('Credit note created.'));
    }

    public function approveCreditNote(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $old = $creditNote->only(['status']);
        $creditNote->forceFill(['status' => 'approved'])->save();

        $invoice = $creditNote->invoice;
        $invoice->unsetRelation('creditNotes');
        $invoice->forceFill([
            'status' => $invoice->balance_halalas <= 0
                ? 'paid'
                : ($invoice->paid_total_halalas > 0 || $invoice->credit_total_halalas > 0 ? 'partial' : $invoice->status),
        ])->save();

        $this->audit($request, 'credit_note.approved', $creditNote, $old, $creditNote->only(['status']));

        return back()->with('success', __('Credit note approved.'));
    }

    public function print(Invoice $invoice)
    {
        return view('invoices.print', [
            'invoice' => $invoice->load(['customer', 'contract.site']),
        ]);
    }

    private function serializeInvoice(Invoice $invoice, PdfExporter $pdfExporter): array
    {
        $daysOverdue = $this->daysOverdue($invoice);
        $status = $invoice->status !== 'paid' && $invoice->balance_halalas > 0 && $invoice->due_date->lt(now()->startOfDay())
            ? 'overdue'
            : $invoice->status;

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'customer' => $invoice->customer->name,
            'contract' => $invoice->contract?->reference,
            'status' => $status,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'days_overdue' => $daysOverdue,
            'aging_bucket' => $this->agingBucket($daysOverdue, $invoice->balance_halalas),
            'gross_total_halalas' => $invoice->gross_total_halalas,
            'gross_total_sar' => $this->halalasToSarString($invoice->gross_total_halalas),
            'paid_total_halalas' => $invoice->paid_total_halalas,
            'paid_total_sar' => $this->halalasToSarString($invoice->paid_total_halalas),
            'credit_total_halalas' => $invoice->credit_total_halalas,
            'credit_total_sar' => $this->halalasToSarString($invoice->credit_total_halalas),
            'balance_halalas' => $invoice->balance_halalas,
            'balance_sar' => $this->halalasToSarString($invoice->balance_halalas),
            'print_url' => $pdfExporter->invoiceUrl($invoice),
        ];
    }

    private function daysOverdue(Invoice $invoice): int
    {
        if ($invoice->due_date->gte(now()->startOfDay())) {
            return 0;
        }

        return (int) $invoice->due_date->diffInDays(now()->startOfDay());
    }

    private function agingBucket(int $daysOverdue, int $balance): string
    {
        if ($balance <= 0 || $daysOverdue === 0) {
            return 'current';
        }

        if ($daysOverdue <= 30) {
            return '1-30';
        }

        if ($daysOverdue <= 60) {
            return '31-60';
        }

        return '61+';
    }

    private function aging($invoices): array
    {
        return [
            'current' => $invoices->where('aging_bucket', 'current')->sum('balance_halalas'),
            'oneToThirty' => $invoices->where('aging_bucket', '1-30')->sum('balance_halalas'),
            'thirtyOneToSixty' => $invoices->where('aging_bucket', '31-60')->sum('balance_halalas'),
            'sixtyPlus' => $invoices->where('aging_bucket', '61+')->sum('balance_halalas'),
        ];
    }

    private function expectedCollections($invoices): array
    {
        return $invoices
            ->filter(fn (array $invoice): bool => $invoice['balance_halalas'] > 0 && $invoice['due_date'] <= now()->addDays(30)->toDateString())
            ->sortBy('due_date')
            ->values()
            ->map(fn (array $invoice): array => [
                'invoice_id' => $invoice['id'],
                'number' => $invoice['number'],
                'customer' => $invoice['customer'],
                'due_date' => $invoice['due_date'],
                'balance_halalas' => $invoice['balance_halalas'],
                'balance_sar' => $invoice['balance_sar'],
            ])
            ->all();
    }

    private function chequeRows(): array
    {
        return Cheque::query()
            ->with(['customer', 'invoice'])
            ->orderBy('due_date')
            ->get()
            ->map(fn (Cheque $cheque): array => [
                'id' => $cheque->id,
                'customer' => $cheque->customer?->name,
                'invoice_id' => $cheque->invoice_id,
                'invoice_number' => $cheque->invoice?->number,
                'cheque_number' => $cheque->cheque_number,
                'amount_halalas' => $cheque->amount_halalas,
                'amount_sar' => $this->halalasToSarString($cheque->amount_halalas),
                'due_date' => $cheque->due_date?->toDateString(),
                'cleared_date' => $cheque->cleared_date?->toDateString(),
                'status' => $cheque->status,
            ])
            ->values()
            ->all();
    }

    private function creditNoteRows(): array
    {
        return CreditNote::query()
            ->with(['invoice.customer'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CreditNote $creditNote): array => [
                'id' => $creditNote->id,
                'invoice_id' => $creditNote->invoice_id,
                'invoice_number' => $creditNote->invoice?->number,
                'customer' => $creditNote->invoice?->customer?->name,
                'number' => $creditNote->number,
                'amount_halalas' => $creditNote->amount_halalas,
                'amount_sar' => $this->halalasToSarString($creditNote->amount_halalas),
                'status' => $creditNote->status,
                'reason' => $creditNote->reason,
            ])
            ->values()
            ->all();
    }

    private function expenseRows(): array
    {
        return Expense::query()
            ->with(['customer', 'contract', 'visit.service'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Expense $expense): array => $this->serializeExpense($expense))
            ->values()
            ->all();
    }

    private function serializeExpense(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_date' => $expense->expense_date ? substr((string) $expense->expense_date, 0, 10) : null,
            'expense_type' => $expense->expense_type,
            'category' => $expense->category,
            'vendor' => $expense->vendor,
            'description' => $expense->description,
            'amount_halalas' => $expense->amount_halalas,
            'amount_sar' => $this->halalasToSarString($expense->amount_halalas),
            'vat_halalas' => $expense->vat_halalas,
            'vat_sar' => $this->halalasToSarString($expense->vat_halalas),
            'payment_method' => $expense->payment_method,
            'payment_reference' => $expense->payment_reference,
            'status' => $expense->status,
            'receipt_path' => $expense->receipt_path,
            'customer' => $expense->customer?->name,
            'contract' => $expense->contract?->reference,
            'visit' => $expense->visit ? [
                'id' => $expense->visit->id,
                'scheduled_for' => $expense->visit->scheduled_for?->toDateString(),
                'service' => $expense->visit->service?->title,
            ] : null,
        ];
    }

    private function expenseSummary(): array
    {
        $reportableStatuses = ['approved', 'paid'];

        return [
            'direct_cost_halalas' => (int) Expense::query()
                ->where('expense_type', 'direct_cost')
                ->whereIn('status', $reportableStatuses)
                ->sum('amount_halalas'),
            'operating_expenses_halalas' => (int) Expense::query()
                ->where('expense_type', 'operating_expense')
                ->whereIn('status', $reportableStatuses)
                ->sum('amount_halalas'),
            'vat_halalas' => (int) Expense::query()
                ->whereIn('status', $reportableStatuses)
                ->sum('vat_halalas'),
            'pending_halalas' => (int) Expense::query()
                ->where('status', 'draft')
                ->sum('amount_halalas'),
        ];
    }

    private function expenseCategories(): array
    {
        return [
            ['key' => 'cleaning_materials', 'label' => 'Cleaning materials'],
            ['key' => 'tools_equipment', 'label' => 'Tools and equipment'],
            ['key' => 'fuel_transport', 'label' => 'Fuel and transport'],
            ['key' => 'vehicle_maintenance', 'label' => 'Vehicle maintenance'],
            ['key' => 'worker_accommodation', 'label' => 'Worker accommodation'],
            ['key' => 'subcontractor', 'label' => 'Subcontractor'],
            ['key' => 'office_rent', 'label' => 'Office rent'],
            ['key' => 'utilities', 'label' => 'Utilities'],
            ['key' => 'marketing', 'label' => 'Marketing'],
            ['key' => 'software', 'label' => 'Software'],
            ['key' => 'government_fees', 'label' => 'Government fees'],
            ['key' => 'miscellaneous', 'label' => 'Miscellaneous'],
        ];
    }

    private function expenseTypes(): array
    {
        return [
            ['key' => 'direct_cost', 'label' => 'Direct job cost'],
            ['key' => 'operating_expense', 'label' => 'Operating expense'],
        ];
    }

    private function expenseStatuses(): array
    {
        return [
            ['key' => 'draft', 'label' => 'Draft'],
            ['key' => 'approved', 'label' => 'Approved'],
            ['key' => 'paid', 'label' => 'Paid'],
            ['key' => 'rejected', 'label' => 'Rejected'],
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function audit(Request $request, string $action, Model $model, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'changes' => [
                'updated' => [
                    'old' => $old,
                    'new' => $new,
                ],
            ],
        ]);
    }
}
