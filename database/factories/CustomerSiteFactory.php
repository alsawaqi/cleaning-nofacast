<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerSite>
 */
class CustomerSiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'country_code' => 'SA',
            'name' => $this->faker->randomElement(['Head Office', 'Branch 01', 'Warehouse']),
            'city' => 'Riyadh',
            'district' => $this->faker->citySuffix(),
            'address' => $this->faker->streetAddress(),
            'latitude' => null,
            'longitude' => null,
            'google_place_id' => null,
            'formatted_address' => null,
            'contact_name' => $this->faker->name(),
            'contact_phone' => '+9665'.$this->faker->numerify('########'),
        ];
    }
}
