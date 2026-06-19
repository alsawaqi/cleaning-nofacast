<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Service;
use App\Models\ServicePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    public function definition(): array
    {
        $customer = Customer::factory()->create();
        $site = CustomerSite::factory()->create(['customer_id' => $customer->id]);
        $service = Service::factory()->create();
        $package = ServicePackage::factory()->create(['service_id' => $service->id]);

        return [
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-'.$this->faker->unique()->numerify('######'),
            'status' => 'active',
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => now()->addMonths(6)->endOfMonth()->toDateString(),
            'monthly_fee_halalas' => 920000,
            'vat_rate' => 15,
            'prices_include_vat' => false,
            'pricing_model' => 'package',
            'agreed_workers' => 2,
            'visits_per_week' => 1,
            'hours_per_visit' => '3.00',
            'planned_weekly_minutes' => 360,
            'included_materials' => true,
            'material_policy' => 'company_supplied_included',
            'estimated_material_cost_halalas' => 0,
            'extra_hour_rate_halalas' => 0,
            'overtime_policy' => 'none',
            'service_scope' => [],
            'terms_and_conditions' => null,
            'payment_plan' => [
                ['day' => 1, 'percent' => 50],
                ['day' => 15, 'percent' => 50],
            ],
            'billing_cycle' => 'monthly',
            'notice_days' => 30,
            'auto_renews' => true,
        ];
    }
}
