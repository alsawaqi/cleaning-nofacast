<?php

namespace App\Services\Integrations;

use App\Models\Invoice;

interface EInvoiceIssuer
{
    public function issue(Invoice $invoice): Invoice;
}
