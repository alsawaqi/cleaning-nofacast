<?php

namespace App\Services\Integrations;

use App\Models\Invoice;

class HtmlPdfExporter implements PdfExporter
{
    public function invoiceUrl(Invoice $invoice): string
    {
        return route('finance.invoice.print', $invoice);
    }
}
