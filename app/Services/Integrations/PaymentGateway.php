<?php

namespace App\Services\Integrations;

use App\Models\Invoice;

interface PaymentGateway
{
    /**
     * @return array{provider: string, status: string, reference: string, checkout_url: string|null}
     */
    public function createCheckout(Invoice $invoice): array;
}
