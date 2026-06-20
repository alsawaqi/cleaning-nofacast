<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSarMoney;
use App\Models\AuditLog;
use App\Models\Cheque;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\PaymentProof;
use App\Models\Visit;
use App\Services\Finance\PaymentRecorder;
use App\Services\Integrations\PdfExporter;
use App\Services\Support\NumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->with(['customer', 'contract', 'creditNotes', 'lineItems'])
            ->orderByDesc('due_date')
            ->get()
            ->map(fn (Invoice $invoice): array => $this->serializeInvoice($invoice, $pdfExporter));

        return Inertia::render('Finance/Index', [
            'invoices' => $invoices,
            'aging' => $this->aging($invoices),
            'expectedCollections' => $this->expectedCollections($invoices),
            'billableExtras' => $this->billableExtraRows(),
            'cheques' => $this->chequeRows(),
            'creditNotes' => $this->creditNoteRows(),
            'paymentProofs' => $this->paymentProofRows(),
            'metrics' => [
                'due' => $invoices->sum('balance_halalas'),
                'paid' => $invoices->sum('paid_total_halalas'),
                'overdue' => $invoices->where('status', '!=', 'paid')->filter(fn ($invoice) => $invoice['due_date'] < now()->toDateString())->sum('balance_halalas'),
                'expected30' => $invoices->filter(fn ($invoice) => $invoice['due_date'] <= now()->addDays(30)->toDateString())->sum('balance_halalas'),
                'heldCheques' => Cheque::query()->where('status', 'held')->sum('amount_halalas'),
                'pendingCreditNotes' => CreditNote::query()->where('status', 'pending_approval')->sum('amount_halalas'),
                'pendingPaymentProofs' => PaymentProof::query()->where('status', 'pending')->sum('amount_halalas'),
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

    public function approvePaymentProof(Request $request, PaymentProof $paymentProof, PaymentRecorder $recorder): RedirectResponse
    {
        if ($paymentProof->status !== 'pending') {
            throw ValidationException::withMessages([
                'payment_proof' => __('Only pending payment proofs can be approved.'),
            ]);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $invoice = $paymentProof->invoice;

        if ($paymentProof->amount_halalas > $invoice->balance_halalas) {
            throw ValidationException::withMessages([
                'payment_proof' => __('Payment proof amount is greater than the current invoice balance.'),
            ]);
        }

        $payment = $recorder->record(
            $invoice,
            $paymentProof->amount_halalas,
            $paymentProof->method,
            $paymentProof->reference,
        );

        $old = $paymentProof->only(['status', 'payment_id', 'admin_note', 'reviewed_by', 'reviewed_at']);
        $paymentProof->forceFill([
            'status' => 'approved',
            'payment_id' => $payment->id,
            'admin_note' => $validated['admin_note'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit($request, 'payment_proof.approved', $paymentProof, $old, $paymentProof->only(['status', 'payment_id', 'admin_note', 'reviewed_by', 'reviewed_at']));

        return back()->with('success', __('Payment proof approved and payment recorded.'));
    }

    public function rejectPaymentProof(Request $request, PaymentProof $paymentProof): RedirectResponse
    {
        if ($paymentProof->status !== 'pending') {
            throw ValidationException::withMessages([
                'payment_proof' => __('Only pending payment proofs can be rejected.'),
            ]);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $old = $paymentProof->only(['status', 'admin_note', 'reviewed_by', 'reviewed_at']);

        $paymentProof->forceFill([
            'status' => 'rejected',
            'admin_note' => $validated['admin_note'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit($request, 'payment_proof.rejected', $paymentProof, $old, $paymentProof->only(['status', 'admin_note', 'reviewed_by', 'reviewed_at']));

        return back()->with('success', __('Payment proof rejected.'));
    }

    public function downloadPaymentProof(PaymentProof $paymentProof)
    {
        return response()->download(storage_path("app/public/{$paymentProof->proof_path}"));
    }

    public function storeBillableOvertimeInvoice(Request $request, Visit $visit, NumberGenerator $numbers): RedirectResponse
    {
        $invoice = DB::transaction(function () use ($visit, $numbers): Invoice {
            $visit = Visit::query()
                ->with(['contract', 'site.customer', 'service'])
                ->whereKey($visit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->isUnbilledApprovedOvertime($visit)) {
                throw ValidationException::withMessages([
                    'visit_id' => __('This visit has no approved unbilled overtime.'),
                ]);
            }

            $amount = (int) $visit->billable_overtime_halalas;
            $vatRate = (int) ($visit->contract?->vat_rate ?? $visit->service?->vat_rate ?? 15);
            $pricesIncludeVat = (bool) ($visit->contract?->prices_include_vat ?? $visit->service?->prices_include_vat ?? false);
            $totals = $this->taxTotals($amount, $vatRate, $pricesIncludeVat);

            $invoice = Invoice::create([
                'contract_id' => $visit->contract_id,
                'customer_id' => $visit->contract?->customer_id ?? $visit->site->customer_id,
                'customer_site_id' => $visit->customer_site_id,
                'number' => $numbers->next('INV', Invoice::class),
                'status' => 'draft',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'net_total_halalas' => $totals['net'],
                'vat_total_halalas' => $totals['vat'],
                'gross_total_halalas' => $totals['gross'],
                'paid_total_halalas' => 0,
                'vat_rate' => $vatRate,
            ]);

            $invoice->lineItems()->create([
                'visit_id' => $visit->id,
                'line_type' => 'overtime',
                'description' => __('Extra time: :minutes minutes', ['minutes' => $visit->billable_overtime_minutes]),
                'quantity' => $visit->billable_overtime_minutes,
                'unit_label' => 'minute',
                'unit_price_halalas' => (int) round($totals['net'] / max(1, (int) $visit->billable_overtime_minutes)),
                'vat_rate' => $vatRate,
                'net_total_halalas' => $totals['net'],
                'vat_total_halalas' => $totals['vat'],
                'gross_total_halalas' => $totals['gross'],
                'metadata' => [
                    'scheduled_for' => $visit->scheduled_for?->toDateString(),
                    'starts_at' => (string) $visit->starts_at,
                    'ends_at' => (string) $visit->ends_at,
                    'prices_include_vat' => $pricesIncludeVat,
                ],
            ]);

            $visit->forceFill([
                'overtime_invoice_id' => $invoice->id,
                'overtime_billed_at' => now(),
            ])->save();

            return $invoice;
        });

        $this->audit($request, 'billable_overtime.invoiced', $invoice, [], $invoice->only([
            'number',
            'contract_id',
            'customer_id',
            'net_total_halalas',
            'vat_total_halalas',
            'gross_total_halalas',
        ]));

        return back()->with('success', __('Overtime invoice :invoice created.', ['invoice' => $invoice->number]));
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
            'invoice' => $invoice->load(['customer', 'contract.site', 'lineItems']),
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
            'line_items' => $invoice->lineItems->map(fn (InvoiceLineItem $line): array => $this->serializeInvoiceLineItem($line))->values(),
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

    private function billableExtraRows(): array
    {
        return Visit::query()
            ->with(['contract.customer', 'site.customer', 'service', 'worker'])
            ->where('overtime_status', 'approved')
            ->where('billable_overtime_minutes', '>', 0)
            ->where('billable_overtime_halalas', '>', 0)
            ->whereNull('overtime_invoice_id')
            ->orderBy('scheduled_for')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Visit $visit): array => [
                'visit_id' => $visit->id,
                'scheduled_for' => $visit->scheduled_for?->toDateString(),
                'customer' => $visit->contract?->customer?->name ?? $visit->site->customer?->name,
                'site' => $visit->site?->name,
                'contract' => $visit->contract?->reference,
                'service' => $visit->service?->title,
                'worker' => $visit->worker?->name,
                'minutes' => $visit->billable_overtime_minutes,
                'amount_halalas' => $visit->billable_overtime_halalas,
                'amount_sar' => $this->halalasToSarString($visit->billable_overtime_halalas),
                'invoice_url' => route('finance.billable-extras.overtime.store', $visit, absolute: false),
            ])
            ->values()
            ->all();
    }

    private function isUnbilledApprovedOvertime(Visit $visit): bool
    {
        return $visit->overtime_status === 'approved'
            && (int) $visit->billable_overtime_minutes > 0
            && (int) $visit->billable_overtime_halalas > 0
            && $visit->overtime_invoice_id === null;
    }

    /**
     * @return array{net: int, vat: int, gross: int}
     */
    private function taxTotals(int $amountHalalas, int $vatRate, bool $pricesIncludeVat): array
    {
        if ($pricesIncludeVat) {
            $vat = $vatRate > 0 ? (int) round($amountHalalas * $vatRate / (100 + $vatRate)) : 0;

            return [
                'net' => $amountHalalas - $vat,
                'vat' => $vat,
                'gross' => $amountHalalas,
            ];
        }

        $vat = (int) round($amountHalalas * $vatRate / 100);

        return [
            'net' => $amountHalalas,
            'vat' => $vat,
            'gross' => $amountHalalas + $vat,
        ];
    }

    private function serializeInvoiceLineItem(InvoiceLineItem $line): array
    {
        return [
            'id' => $line->id,
            'visit_id' => $line->visit_id,
            'line_type' => $line->line_type,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_label' => $line->unit_label,
            'unit_price_halalas' => $line->unit_price_halalas,
            'unit_price_sar' => $this->halalasToSarString($line->unit_price_halalas),
            'vat_rate' => $line->vat_rate,
            'net_total_halalas' => $line->net_total_halalas,
            'net_total_sar' => $this->halalasToSarString($line->net_total_halalas),
            'vat_total_halalas' => $line->vat_total_halalas,
            'vat_total_sar' => $this->halalasToSarString($line->vat_total_halalas),
            'gross_total_halalas' => $line->gross_total_halalas,
            'gross_total_sar' => $this->halalasToSarString($line->gross_total_halalas),
        ];
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

    private function paymentProofRows(): array
    {
        return PaymentProof::query()
            ->with(['customer', 'invoice.contract'])
            ->where('status', 'pending')
            ->orderBy('paid_on')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PaymentProof $proof): array => [
                'id' => $proof->id,
                'customer' => $proof->customer?->name,
                'invoice_id' => $proof->invoice_id,
                'invoice_number' => $proof->invoice?->number,
                'contract' => $proof->invoice?->contract?->reference,
                'amount_halalas' => $proof->amount_halalas,
                'amount_sar' => $this->halalasToSarString($proof->amount_halalas),
                'method' => $proof->method,
                'reference' => $proof->reference,
                'paid_on' => $proof->paid_on?->toDateString(),
                'proof_url' => route('finance.payment-proofs.download', $proof, absolute: false),
                'customer_note' => $proof->customer_note,
                'status' => $proof->status,
                'approve_url' => route('finance.payment-proofs.approve', $proof, absolute: false),
                'reject_url' => route('finance.payment-proofs.reject', $proof, absolute: false),
            ])
            ->values()
            ->all();
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
