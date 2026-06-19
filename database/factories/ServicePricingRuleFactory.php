<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServicePricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePricingRule>
 */
class ServicePricingRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'name' => $this->faker->randomElement(['Extra Worker', 'Additional Visit', 'Extra Square Meter']),
            'pricing_type' => 'per_unit',
            'unit_label' => 'unit',
            'unit_price_halalas' => 15000,
            'minimum_quantity' => 1,
            'maximum_quantity' => null,
            'vat_rate' => 15,
            'prices_include_vat' => false,
            'applies_to' => ['standard'],
            'is_active' => true,
        ];
    }
}
