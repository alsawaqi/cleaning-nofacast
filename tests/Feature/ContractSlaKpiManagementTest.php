<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitFeedback;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ContractSlaKpiManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_save_service_and_package_sla_kpi_templates(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);

        $this->actingAs($manager)->post('/app/services', [
            'title' => 'Retail SLA Cleaning',
            'category' => 'cleaning',
            'description' => 'Managed cleaning with KPI reporting.',
            'pricing_type' => 'per_worker_month',
            'base_price_sar' => '2000.00',
            'vat_rate' => 15,
            'prices_include_vat' => false,
            'materials_included' => true,
            'minimum_billable_minutes' => 60,
            'default_workers' => 1,
            'default_duration_minutes' => 120,
            'default_material_cost_sar' => '0.00',
            'material_policy' => 'company_supplied_included',
            'included_materials_text' => '',
            'extra_hour_rate_sar' => '0.00',
            'overtime_policy' => 'none',
            'allowed_frequencies' => ['weekly'],
            'required_certificates' => [],
            'checklist_template' => [
                ['label' => 'Clean floors', 'is_required' => true],
            ],
            'sla_kpi_template' => [
                [
                    'code' => 'attendance',
                    'label' => 'Worker attendance',
                    'target' => 95,
                    'unit' => 'percent',
                    'weight' => 40,
                ],
                [
                    'code' => 'checklist_completion',
                    'label' => 'Checklist completion',
                    'target' => 90,
                    'unit' => 'percent',
                    'weight' => 60,
                ],
            ],
            'is_active' => true,
            'packages' => [
                [
                    'name' => 'KPI Monthly',
                    'description' => 'Monthly package with stricter KPI.',
                    'billing_cycle' => 'monthly',
                    'visit_frequency' => 'weekly',
                    'visits_per_week' => 3,
                    'hours_per_visit' => '2.00',
                    'worker_count' => 1,
                    'duration_minutes' => 120,
                    'expected_labor_minutes' => 120,
                    'material_cost_sar' => '0.00',
                    'price_sar' => '2500.00',
                    'vat_rate' => 15,
                    'prices_include_vat' => false,
                    'checklist_template' => [
                        ['label' => 'Clean glass', 'is_required' => true],
                    ],
                    'sla_kpi_template' => [
                        [
                            'code' => 'supervisor_review',
                            'label' => 'Supervisor review',
                            'target' => 80,
                            'unit' => 'percent',
                            'weight' => 100,
                        ],
                    ],
                    'is_active' => true,
                ],
            ],
            'pricing_rules' => [],
        ])->assertRedirect('/app/services');

        $service = Service::query()->where('title', 'Retail SLA Cleaning')->firstOrFail();
        $package = $service->packages()->firstOrFail();

        $this->assertSame('attendance', $service->sla_kpi_template[0]['code']);
        $this->assertSame(95, $service->sla_kpi_template[0]['target']);
        $this->assertSame('supervisor_review', $package->sla_kpi_template[0]['code']);
        $this->assertSame(80, $package->sla_kpi_template[0]['target']);
    }

    public function test_contract_inherits_package_or_service_sla_kpi_template(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, $site, $service, $package] = $this->contractDependencies();

        $this->actingAs($manager)->post('/app/contracts', $this->contractPayload([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-SLA-PACKAGE',
        ]))->assertRedirect('/app/contracts');

        $packageContract = Contract::query()->where('reference', 'CON-SLA-PACKAGE')->firstOrFail();

        $this->assertSame('package_attendance', $packageContract->sla_kpi_template[0]['code']);
        $this->assertSame(97, $packageContract->sla_kpi_template[0]['target']);

        $this->actingAs($manager)->post('/app/contracts', $this->contractPayload([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => null,
            'reference' => 'CON-SLA-SERVICE',
            'pricing_model' => 'custom_quote',
            'sla_kpi_template' => [],
        ]))->assertRedirect('/app/contracts');

        $serviceContract = Contract::query()->where('reference', 'CON-SLA-SERVICE')->firstOrFail();

        $this->assertSame('service_attendance', $serviceContract->sla_kpi_template[0]['code']);
        $this->assertSame(95, $serviceContract->sla_kpi_template[0]['target']);
    }

    public function test_contract_detail_generates_weekly_and_monthly_sla_reports_from_visits(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-06-20 12:00:00');

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $worker = Worker::factory()->create();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-SLA-REPORT',
            'sla_kpi_template' => [
                ['code' => 'attendance', 'label' => 'Attendance', 'target' => 90, 'unit' => 'percent', 'weight' => 40],
                ['code' => 'checklist_completion', 'label' => 'Checklist completion', 'target' => 90, 'unit' => 'percent', 'weight' => 30],
                ['code' => 'supervisor_review', 'label' => 'Supervisor review', 'target' => 80, 'unit' => 'percent', 'weight' => 30],
            ],
        ]);

        $completedWeekly = $this->visit($contract, $site, $service, $worker, [
            'scheduled_for' => '2026-06-16',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'completed',
            'checked_in_at' => '2026-06-16 08:00:00',
            'checked_out_at' => '2026-06-16 10:00:00',
            'photos' => ['visits/lobby-before.jpg', 'visits/lobby-after.jpg'],
            'supervisor_acknowledged_at' => '2026-06-16 11:00:00',
            'supervisor_acknowledged_by' => $manager->id,
        ]);

        ChecklistItem::query()->create(['visit_id' => $completedWeekly->id, 'label' => 'Floors', 'status' => 'done', 'completed_at' => now()]);
        ChecklistItem::query()->create(['visit_id' => $completedWeekly->id, 'label' => 'Glass', 'status' => 'done', 'completed_at' => now()]);
        ChecklistItem::query()->create(['visit_id' => $completedWeekly->id, 'label' => 'Bathrooms', 'status' => 'pending']);

        $this->visit($contract, $site, $service, $worker, [
            'scheduled_for' => '2026-06-18',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'missed',
            'issue_note' => 'Worker unavailable.',
        ]);

        $this->visit($contract, $site, $service, $worker, [
            'scheduled_for' => '2026-06-03',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'completed',
            'checked_in_at' => '2026-06-03 08:20:00',
            'checked_out_at' => '2026-06-03 10:00:00',
        ]);

        $this->actingAs($manager)->get("/app/contracts/{$contract->id}?report_date=2026-06-20")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Show')
                ->where('contract.sla_kpi_template.0.code', 'attendance')
                ->where('slaReports.weekly.period_key', 'weekly')
                ->where('slaReports.weekly.starts_on', '2026-06-15')
                ->where('slaReports.weekly.ends_on', '2026-06-21')
                ->where('slaReports.weekly.metrics.scheduled_visits', 2)
                ->where('slaReports.weekly.metrics.completed_visits', 1)
                ->where('slaReports.weekly.metrics.missed_visits', 1)
                ->where('slaReports.weekly.metrics.attendance_rate', 50)
                ->where('slaReports.weekly.metrics.checklist_completion_rate', 67)
                ->where('slaReports.weekly.metrics.issue_count', 1)
                ->where('slaReports.weekly.metrics.photo_count', 2)
                ->where('slaReports.weekly.metrics.supervisor_review_rate', 50)
                ->where('slaReports.weekly.kpi_results.0.code', 'attendance')
                ->where('slaReports.weekly.kpi_results.0.actual', 50)
                ->where('slaReports.monthly.period_key', 'monthly')
                ->where('slaReports.monthly.starts_on', '2026-06-01')
                ->where('slaReports.monthly.ends_on', '2026-06-30')
                ->where('slaReports.monthly.metrics.scheduled_visits', 3)
                ->where('slaReports.monthly.metrics.completed_visits', 2)
            );
    }

    public function test_sla_reports_include_quality_reviews_and_customer_satisfaction(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        [$customer, $site, $service, $package] = $this->contractDependencies();
        $worker = Worker::factory()->create();
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package->id,
            'reference' => 'CON-SLA-QUALITY',
            'sla_kpi_template' => [
                ['code' => 'quality_pass', 'label' => 'Quality pass rate', 'target' => 90, 'unit' => 'percent', 'weight' => 50],
                ['code' => 'customer_satisfaction', 'label' => 'Customer satisfaction', 'target' => 80, 'unit' => 'percent', 'weight' => 50],
            ],
        ]);

        $passedVisit = $this->visit($contract, $site, $service, $worker, [
            'scheduled_for' => '2026-06-16',
            'status' => 'completed',
            'checked_in_at' => '2026-06-16 08:00:00',
            'checked_out_at' => '2026-06-16 10:00:00',
            'quality_status' => 'passed',
            'quality_score' => 96,
            'quality_reviewed_at' => '2026-06-16 11:00:00',
            'quality_reviewed_by' => $manager->id,
        ]);
        $failedVisit = $this->visit($contract, $site, $service, $worker, [
            'scheduled_for' => '2026-06-18',
            'status' => 'completed',
            'checked_in_at' => '2026-06-18 08:00:00',
            'checked_out_at' => '2026-06-18 10:00:00',
            'quality_status' => 'failed',
            'quality_score' => 40,
            'quality_reviewed_at' => '2026-06-18 11:00:00',
            'quality_reviewed_by' => $manager->id,
        ]);

        VisitFeedback::query()->create([
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'visit_id' => $passedVisit->id,
            'rating' => 5,
            'comment' => 'Excellent visit.',
            'submitted_at' => '2026-06-16 12:00:00',
        ]);
        VisitFeedback::query()->create([
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'visit_id' => $failedVisit->id,
            'rating' => 2,
            'comment' => 'Needs follow-up.',
            'submitted_at' => '2026-06-18 12:00:00',
        ]);

        $this->actingAs($manager)->get("/app/contracts/{$contract->id}?report_date=2026-06-20")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contracts/Show')
                ->where('slaReports.weekly.metrics.quality_review_count', 2)
                ->where('slaReports.weekly.metrics.quality_pass_count', 1)
                ->where('slaReports.weekly.metrics.quality_failure_count', 1)
                ->where('slaReports.weekly.metrics.quality_pass_rate', 50)
                ->where('slaReports.weekly.metrics.customer_feedback_count', 2)
                ->where('slaReports.weekly.metrics.average_customer_rating', 3.5)
                ->where('slaReports.weekly.metrics.customer_satisfaction_rate', 50)
                ->where('slaReports.weekly.metrics.low_rating_count', 1)
                ->where('slaReports.weekly.kpi_results.0.code', 'quality_pass')
                ->where('slaReports.weekly.kpi_results.0.actual', 50)
                ->where('slaReports.weekly.kpi_results.1.code', 'customer_satisfaction')
                ->where('slaReports.weekly.kpi_results.1.actual', 50));
    }

    /**
     * @return array{0: Customer, 1: CustomerSite, 2: Service, 3: ServicePackage}
     */
    private function contractDependencies(): array
    {
        $customer = Customer::factory()->create(['name' => 'Riyadh Clinic Group']);
        $site = CustomerSite::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Olaya Medical Center',
        ]);
        $service = Service::factory()->create([
            'title' => 'Office Contract Cleaning',
            'slug' => 'office-contract-cleaning',
            'sla_kpi_template' => [
                ['code' => 'service_attendance', 'label' => 'Service attendance', 'target' => 95, 'unit' => 'percent', 'weight' => 100],
            ],
        ]);
        $package = ServicePackage::factory()->create([
            'service_id' => $service->id,
            'name' => 'Standard Monthly',
            'sla_kpi_template' => [
                ['code' => 'package_attendance', 'label' => 'Package attendance', 'target' => 97, 'unit' => 'percent', 'weight' => 100],
            ],
        ]);

        return [$customer, $site, $service, $package];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function contractPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_id' => 1,
            'customer_site_id' => 1,
            'service_id' => 1,
            'service_package_id' => 1,
            'reference' => 'CON-2026-00001',
            'status' => 'active',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-12-31',
            'monthly_fee_sar' => '12000.00',
            'vat_rate' => 15,
            'prices_include_vat' => false,
            'payment_plan' => [
                ['label' => 'Advance', 'day' => 1, 'percent' => 50],
                ['label' => 'Completion', 'day' => 20, 'percent' => 50],
            ],
            'notice_days' => 30,
            'auto_renews' => true,
            'billing_cycle' => 'monthly',
            'special_terms' => 'Customer may request scope changes through addendums.',
            'addendums' => [],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function visit(Contract $contract, CustomerSite $site, Service $service, Worker $worker, array $overrides = []): Visit
    {
        return Visit::query()->create(array_replace([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'scheduled_for' => '2026-06-16',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'scheduled',
        ], $overrides));
    }
}
