<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_type' => 'company',
            'name' => $this->faker->company(),
            'phone' => '+9665'.$this->faker->numerify('########'),
            'email' => $this->faker->companyEmail(),
            'preferred_channel' => 'whatsapp',
            'preferred_locale' => 'ar',
            'vat_number' => '3'.$this->faker->numerify('##############'),
            'status' => 'active',
        ];
    }
}
