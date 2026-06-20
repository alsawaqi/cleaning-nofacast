<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\CustomerSite;
use App\Models\Service;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        $contract = Contract::factory()->create();

        return [
            'contract_id' => $contract->id,
            'customer_site_id' => $contract->customer_site_id,
            'worker_id' => Worker::factory(),
            'service_id' => Service::factory(),
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'share_percent' => 100,
            'status' => 'active',
            'team_role' => 'main',
        ];
    }

    public function forSite(CustomerSite $site): static
    {
        return $this->state(fn () => ['customer_site_id' => $site->id]);
    }
}
