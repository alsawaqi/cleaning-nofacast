<?php

namespace App\Services\Integrations;

use App\Models\Invoice;

interface PdfExporter
{
    public function invoiceUrl(Invoice $invoice): string;
}
