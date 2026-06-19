<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $contract = Contract::factory()->create();

        return [
            'contract_id' => $contract->id,
            'customer_id' => $contract->customer_id,
            'customer_site_id' => $contract->customer_site_id,
            'number' => 'INV-'.$this->faker->unique()->numerify('######'),
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'net_total_halalas' => 10000,
            'vat_total_halalas' => 1500,
            'gross_total_halalas' => 11500,
            'paid_total_halalas' => 0,
            'vat_rate' => 15,
        ];
    }
}
