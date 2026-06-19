<?php

namespace App\Services\Integrations;

use App\Models\Invoice;
use App\Services\Finance\ZatcaQrCode;

class LocalEInvoiceIssuer implements EInvoiceIssuer
{
    public function __construct(private readonly ZatcaQrCode $qrCode) {}

    public function issue(Invoice $invoice): Invoice
    {
        $invoice->forceFill([
            'status' => $invoice->status === 'draft' ? 'issued' : $invoice->status,
            'zatca_qr' => $this->qrCode->generate(
                sellerName: config('app.name', 'Nofacast Clean'),
                vatNumber: config('services.zatca.vat_number', '300000000000003'),
                issuedAt: $invoice->created_at?->toAtomString() ?? now()->toAtomString(),
                totalWithVat: number_format($invoice->gross_total_halalas / 100, 2, '.', ''),
                vatTotal: number_format($invoice->vat_total_halalas / 100, 2, '.', ''),
            ),
            'provider_payload' => [
                'provider' => 'local',
                'zatca_phase' => 'provider_ready',
                'issued_at' => now()->toAtomString(),
            ],
        ])->save();

        return $invoice;
    }
}
