<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentRecorder
{
    public function record(Invoice $invoice, int $amountHalalas, string $method, ?string $reference = null): Payment
    {
        return DB::transaction(function () use ($invoice, $amountHalalas, $method, $reference): Payment {
            $payment = $invoice->payments()->create([
                'amount_halalas' => $amountHalalas,
                'method' => $method,
                'reference' => $reference,
                'received_at' => now(),
            ]);

            $creditTotal = (int) $invoice->creditNotes()
                ->where('status', 'approved')
                ->sum('amount_halalas');
            $payable = max(0, $invoice->gross_total_halalas - $creditTotal);
            $paid = min($payable, $invoice->payments()->sum('amount_halalas'));

            $invoice->forceFill([
                'paid_total_halalas' => $paid,
                'status' => $paid >= $payable ? 'paid' : 'partial',
            ])->save();

            return $payment;
        });
    }
}
