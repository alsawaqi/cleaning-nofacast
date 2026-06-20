<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerRevenueTarget;
use App\Services\Finance\VatCalculator;
use App\Services\Integrations\EInvoiceIssuer;
use App\Services\Operations\ScheduleGenerator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = collect([
            ['name' => 'Nofacast Owner', 'email' => 'owner@nofacast.test', 'role' => 'owner'],
            ['name' => 'Operations Lead', 'email' => 'operations@nofacast.test', 'role' => 'operations'],
            ['name' => 'Finance Lead', 'email' => 'finance@nofacast.test', 'role' => 'accountant'],
            ['name' => 'Quality Supervisor', 'email' => 'supervisor@nofacast.test', 'role' => 'supervisor'],
            ['name' => 'Sales Lead', 'email' => 'sales@nofacast.test', 'role' => 'sales'],
            ['name' => 'Field Worker', 'email' => 'worker@nofacast.test', 'role' => 'worker'],
            ['name' => 'Customer Contact', 'email' => 'customer@nofacast.test', 'role' => 'customer'],
        ])->mapWithKeys(fn (array $user) => [
            $user['role'] => User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                    'locale' => 'ar',
                    'is_active' => true,
                ],
            ),
        ]);

        foreach (SystemSetting::defaults() as $key => $definition) {
            SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'value' => SystemSetting::encodeValue($definition['value'], $definition['type']),
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                    'is_public' => $definition['is_public'],
                ],
            );
        }

        $services = collect([
            [
                'title' => 'Office Contract Cleaning',
                'pricing_type' => 'per_worker_month',
                'base_price_halalas' => 920000,
                'checklist_template' => [
                    ['label' => 'Sweep and mop floors'],
                    ['label' => 'Clean reception glass'],
                    ['label' => 'Sanitize bathrooms'],
                    ['label' => 'Remove waste bags'],
                ],
            ],
            [
                'title' => 'Home Deep Cleaning',
                'pricing_type' => 'fixed',
                'base_price_halalas' => 55000,
                'checklist_template' => [
                    ['label' => 'Kitchen deep clean'],
                    ['label' => 'Bathroom scale removal'],
                    ['label' => 'Dust furniture and fixtures'],
                ],
            ],
            [
                'title' => 'Glass Facade Cleaning',
                'pricing_type' => 'per_area',
                'base_price_halalas' => 120000,
                'checklist_template' => [
                    ['label' => 'Clean internal glass'],
                    ['label' => 'Clean external facade'],
                    ['label' => 'Supervisor safety check'],
                ],
            ],
        ])->map(fn (array $data) => Service::updateOrCreate(
            ['slug' => Str::slug($data['title'])],
            [
                ...$data,
                'slug' => Str::slug($data['title']),
                'category' => 'cleaning',
                'description' => 'Phase 1 seeded service for operations planning.',
                'vat_rate' => 15,
                'prices_include_vat' => false,
                'materials_included' => true,
                'allowed_frequencies' => ['once', 'weekly', 'six_days_weekly', 'monthly'],
                'required_certificates' => [],
                'is_active' => true,
            ],
        ));

        $services->each(function (Service $service): void {
            $service->packages()->updateOrCreate(
                ['name' => "{$service->title} Standard"],
                [
                    'description' => 'Default package for quotations and contract setup.',
                    'billing_cycle' => $service->pricing_type === 'fixed' ? 'one_time' : 'monthly',
                    'visit_frequency' => in_array('monthly', $service->allowed_frequencies ?? [], true) ? 'monthly' : 'once',
                    'worker_count' => $service->pricing_type === 'per_worker_month' ? 1 : 2,
                    'duration_minutes' => $service->pricing_type === 'per_worker_month' ? 360 : 180,
                    'price_halalas' => $service->base_price_halalas,
                    'vat_rate' => $service->vat_rate,
                    'prices_include_vat' => $service->prices_include_vat,
                    'checklist_template' => $service->checklist_template,
                    'is_active' => true,
                ],
            );

            $service->pricingRules()->updateOrCreate(
                ['name' => "{$service->title} add-on"],
                [
                    'pricing_type' => $service->pricing_type,
                    'unit_label' => $service->pricing_type === 'per_area' ? 'm2' : 'unit',
                    'unit_price_halalas' => max(1000, (int) round($service->base_price_halalas * 0.2)),
                    'minimum_quantity' => 1,
                    'maximum_quantity' => null,
                    'vat_rate' => $service->vat_rate,
                    'prices_include_vat' => $service->prices_include_vat,
                    'applies_to' => [$service->category],
                    'is_active' => true,
                ],
            );
        });

        $customerData = [
            'customer_type' => 'company',
            'user_id' => $users['customer']->id,
            'name' => 'Riyadh Clinic Group',
            'email' => 'facilities@riyadhclinic.test',
            'phone' => '+966500000101',
            'preferred_channel' => 'whatsapp',
            'preferred_locale' => 'ar',
            'vat_number' => '300000000000003',
            'status' => 'active',
        ];

        $customer = Customer::query()
            ->where('user_id', $users['customer']->id)
            ->first();

        $existingDemoCustomer = Customer::query()
            ->where('email', $customerData['email'])
            ->first();

        if ($customer && $existingDemoCustomer && (int) $existingDemoCustomer->id !== (int) $customer->id) {
            $existingDemoCustomer->forceFill([
                'user_id' => null,
                'email' => "facilities+legacy-{$existingDemoCustomer->id}@riyadhclinic.test",
            ])->save();
        }

        if ($customer) {
            $customer->forceFill($customerData)->save();
        } else {
            $customer = Customer::updateOrCreate(
                ['email' => $customerData['email']],
                $customerData,
            );
        }

        $site = CustomerSite::updateOrCreate(
            ['customer_id' => $customer->id, 'name' => 'Olaya Medical Center'],
            [
                'city' => 'Riyadh',
                'district' => 'Olaya',
                'address' => 'King Fahd Road',
                'contact_name' => 'Branch Manager',
                'contact_phone' => '+966500000202',
            ],
        );

        $worker = Worker::updateOrCreate(
            ['employee_code' => 'W-1001'],
            [
                'user_id' => $users['worker']->id,
                'name' => 'Ahmed Hassan',
                'phone' => '+966500000303',
                'hired_on' => now()->subYear()->toDateString(),
                'nationality' => 'Saudi Arabia',
                'role_language' => 'ar',
                'job_role' => 'cleaner',
                'status' => 'assigned',
                'cost_rate_halalas' => 180000,
                'skills' => ['general_cleaning', 'clinic_cleaning'],
                'certifications' => ['infection_control'],
                'availability_notes' => 'Morning shift preferred; clinic cleaning certified.',
            ],
        );

        $worker->documents()->updateOrCreate(
            ['document_type' => 'iqama'],
            [
                'document_number' => 'IQ-1001',
                'expires_on' => now()->addMonths(10)->toDateString(),
                'file_path' => null,
            ],
        );

        $worker->trainingRecords()->updateOrCreate(
            ['course_name' => 'Infection Control'],
            [
                'certificate_code' => 'IC-1001',
                'completed_on' => now()->subMonths(3)->toDateString(),
                'expires_on' => now()->addMonths(9)->toDateString(),
            ],
        );

        $contractService = $services->first();
        $contractPackage = $contractService->packages()->first();

        $contract = Contract::updateOrCreate(
            ['reference' => 'CON-2026-00001'],
            [
                'customer_id' => $customer->id,
                'customer_site_id' => $site->id,
                'service_id' => $contractService->id,
                'service_package_id' => $contractPackage?->id,
                'status' => 'active',
                'starts_on' => now()->startOfMonth()->toDateString(),
                'ends_on' => now()->addMonths(6)->endOfMonth()->toDateString(),
                'monthly_fee_halalas' => 920000,
                'vat_rate' => 15,
                'prices_include_vat' => false,
                'payment_plan' => [
                    ['label' => 'Advance', 'day' => 1, 'percent' => 50],
                    ['label' => 'Mid-month balance', 'day' => 15, 'percent' => 50],
                ],
                'billing_cycle' => 'monthly',
                'notice_days' => 30,
                'auto_renews' => true,
                'special_terms' => 'Customer may request worker replacement according to contract SLA.',
            ],
        );

        $contract->addendums()->updateOrCreate(
            ['number' => 1],
            [
                'title' => 'Clinic infection-control checklist',
                'summary' => 'Adds clinic-specific sanitation points and daily supervisor acknowledgement.',
                'effective_on' => now()->startOfMonth()->addDays(7)->toDateString(),
            ],
        );

        $weekday = now()->dayOfWeekIso;
        Assignment::updateOrCreate(
            ['contract_id' => $contract->id, 'worker_id' => $worker->id, 'weekday' => $weekday],
            [
                'customer_site_id' => $site->id,
                'service_id' => $services->first()->id,
                'starts_at' => '08:00',
                'ends_at' => '14:00',
                'share_percent' => 80,
                'status' => 'active',
            ],
        );

        app(ScheduleGenerator::class)->generateVisits(
            $contract,
            Carbon::parse(now()->subDays(1)->toDateString()),
            Carbon::parse(now()->addDays(13)->toDateString()),
        );

        WorkerRevenueTarget::updateOrCreate(
            ['worker_id' => $worker->id, 'period_start' => now()->startOfMonth()->toDateString()],
            [
                'period_end' => now()->endOfMonth()->toDateString(),
                'target_halalas' => 850000,
            ],
        );

        $vat = app(VatCalculator::class)->fromExclusive($contract->monthly_fee_halalas, 15);
        $invoice = Invoice::updateOrCreate(
            ['number' => 'INV-2026-00001'],
            [
                'contract_id' => $contract->id,
                'customer_id' => $customer->id,
                'customer_site_id' => $site->id,
                'status' => 'issued',
                'issue_date' => now()->startOfMonth()->toDateString(),
                'due_date' => now()->subDay()->toDateString(),
                'net_total_halalas' => $vat->netHalalas,
                'vat_total_halalas' => $vat->vatHalalas,
                'gross_total_halalas' => $vat->grossHalalas,
                'paid_total_halalas' => 250000,
                'vat_rate' => 15,
            ],
        );
        app(EInvoiceIssuer::class)->issue($invoice);

        Lead::updateOrCreate(
            ['phone' => '+966500000404'],
            [
                'name' => 'Al Noor Pharmacy',
                'email' => 'ops@alnoor-pharmacy.test',
                'service_interest' => 'Monthly branch cleaning',
                'city' => 'Riyadh',
                'notes' => 'Interested in cleaning for three branches.',
                'status' => 'new',
            ],
        );
    }
}
