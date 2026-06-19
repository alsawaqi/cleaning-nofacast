<?php

namespace App\Services\Integrations;

use App\Models\Invoice;

class FakePaymentGateway implements PaymentGateway
{
    public function createCheckout(Invoice $invoice): array
    {
        return [
            'provider' => 'fake',
            'status' => 'ready_for_provider',
            'reference' => 'PAY-'.$invoice->number,
            'checkout_url' => null,
        ];
    }
}
