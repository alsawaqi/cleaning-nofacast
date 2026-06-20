<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\User;
use App\Models\Visit;
use App\Models\Worker;
use App\Models\WorkerRevenueTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WorkerComplianceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_workers_with_compliance_metrics_and_catalog(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $worker = Worker::factory()->create([
            'employee_code' => 'W-2001',
            'name' => 'Sara Ali',
            'job_role' => 'cleaner',
            'status' => 'available',
            'skills' => ['general_cleaning', 'deep_cleaning'],
            'certifications' => ['infection_control'],
        ]);

        DB::table('worker_documents')->insert([
            'worker_id' => $worker->id,
            'document_type' => 'iqama',
            'document_number' => 'IQ-2001',
            'expires_on' => now()->addDays(20)->toDateString(),
            'file_path' => 'worker-documents/iqama.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('training_records')->insert([
            'worker_id' => $worker->id,
            'course_name' => 'Infection Control',
            'certificate_code' => 'IC-2001',
            'completed_on' => now()->subMonth()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)->get('/app/workers')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workers/Index')
                ->where('catalog.statuses.0.key', 'available')
                ->where('catalog.documentTypes.0.key', 'iqama')
                ->where('catalog.jobRoles.0.key', 'cleaner')
                ->where('metrics.total', 1)
                ->where('metrics.available', 1)
                ->where('metrics.expiringDocuments', 1)
                ->where('workers.0.name', 'Sara Ali')
                ->where('workers.0.edit_url', "/app/workers/{$worker->id}/edit")
                ->where('workers.0.detail_url', "/app/workers/{$worker->id}")
                ->where('createUrl', '/app/workers/create')
                ->where('statusUrl', '/app/workers/status')
                ->where('workers.0.documents.0.document_type', 'iqama')
                ->where('workers.0.training_records.0.course_name', 'Infection Control')
                ->where('workers.0.compliance.expiring_documents', 1)
            );
    }

    public function test_manager_can_view_worker_status_report_with_targets_and_comparison(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-07-15 10:00:00');

        $manager = User::factory()->create(['role' => 'operations']);
        $customer = Customer::factory()->create(['name' => 'Riyadh Clinic Group']);
        $site = CustomerSite::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Olaya Medical Center',
            'city' => 'Riyadh',
        ]);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'reference' => 'CON-WRK-001',
        ]);
        $ahmed = Worker::factory()->create(['name' => 'Ahmed Hassan', 'employee_code' => 'WRK-901']);
        $saeed = Worker::factory()->create(['name' => 'Saeed Ali', 'employee_code' => 'WRK-902']);

        WorkerRevenueTarget::create([
            'worker_id' => $ahmed->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'target_halalas' => 100000,
        ]);
        WorkerRevenueTarget::create([
            'worker_id' => $saeed->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'target_halalas' => 100000,
        ]);

        Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $ahmed->id,
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-04',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'completed',
            'actual_minutes' => 120,
            'overtime_minutes' => 20,
            'billable_overtime_minutes' => 20,
            'planned_revenue_halalas' => 70000,
            'billable_overtime_halalas' => 10000,
            'gross_profit_halalas' => 55000,
        ]);
        Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $ahmed->id,
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-12',
            'starts_at' => '08:00',
            'ends_at' => '11:00',
            'status' => 'completed',
            'actual_minutes' => 180,
            'planned_revenue_halalas' => 40000,
            'billable_overtime_halalas' => 0,
            'gross_profit_halalas' => 30000,
        ]);
        Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $saeed->id,
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-15',
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'status' => 'completed',
            'actual_minutes' => 120,
            'planned_revenue_halalas' => 45000,
            'gross_profit_halalas' => 25000,
        ]);

        $this->actingAs($manager)->get("/app/workers/status?from=2026-06-01&to=2026-06-30&workers[]={$ahmed->id}&workers[]={$saeed->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workers/Status')
                ->where('filters.from', '2026-06-01')
                ->where('filters.to', '2026-06-30')
                ->where('filters.selected_worker_ids.0', $ahmed->id)
                ->where('summary.workers_count', 2)
                ->where('summary.target_total_halalas', 200000)
                ->where('summary.actual_revenue_total_halalas', 165000)
                ->where('summary.gross_profit_total_halalas', 110000)
                ->where('summary.target_attainment_percent', 83)
                ->where('workers.0.worker.name', 'Ahmed Hassan')
                ->where('workers.0.target_halalas', 100000)
                ->where('workers.0.actual_revenue_halalas', 120000)
                ->where('workers.0.gross_profit_halalas', 85000)
                ->where('workers.0.worked_minutes', 300)
                ->where('workers.0.completed_visits_count', 2)
                ->where('workers.0.target_attainment_percent', 120)
                ->where('workers.0.target_status', 'achieved')
                ->where('workers.1.worker.name', 'Saeed Ali')
                ->where('workers.1.target_status', 'behind')
                ->where('comparison.0.worker_id', $ahmed->id)
                ->where('trend.0.label', 'Jun 2026')
                ->has('workerOptions.0')
            );
    }

    public function test_manager_can_view_worker_attendance_and_availability_calendar(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-06-16 10:00:00');

        $manager = User::factory()->create(['role' => 'operations']);
        $customer = Customer::factory()->create(['name' => 'Calendar Customer']);
        $site = CustomerSite::factory()->for($customer)->create(['name' => 'Calendar Site']);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
        ]);
        $worker = Worker::factory()->create([
            'name' => 'Calendar Worker',
            'status' => 'available',
        ]);

        Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-16',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'status' => 'completed',
            'checked_in_at' => '2026-06-16 08:05:00',
            'checked_out_at' => '2026-06-16 10:15:00',
            'actual_minutes' => 130,
        ]);
        Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-06-17',
            'starts_at' => '12:00',
            'ends_at' => '14:00',
            'status' => 'missed',
        ]);

        $this->actingAs($manager)
            ->post('/app/workers/availability-blocks', [
                'worker_id' => $worker->id,
                'date' => '2026-06-18',
                'starts_at' => '09:00',
                'ends_at' => '17:00',
                'reason' => 'Medical appointment',
                'status' => 'unavailable',
            ])->assertRedirect();

        $this->assertDatabaseHas('worker_availability_blocks', [
            'worker_id' => $worker->id,
            'date' => '2026-06-18',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'reason' => 'Medical appointment',
            'status' => 'unavailable',
        ]);

        $this->actingAs($manager)
            ->get("/app/workers/status?from=2026-06-16&to=2026-06-18&workers[]={$worker->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attendanceCalendar.summary.scheduled_visits', 2)
                ->where('attendanceCalendar.summary.completed_visits', 1)
                ->where('attendanceCalendar.summary.missed_visits', 1)
                ->where('attendanceCalendar.summary.unavailable_blocks', 1)
                ->where('attendanceCalendar.days.0.date', '2026-06-16')
                ->where('attendanceCalendar.days.0.workers.0.worker.name', 'Calendar Worker')
                ->where('attendanceCalendar.days.0.workers.0.status', 'worked')
                ->where('attendanceCalendar.days.1.workers.0.status', 'missed')
                ->where('attendanceCalendar.days.2.workers.0.status', 'unavailable')
                ->where('attendanceCalendar.days.2.workers.0.blocks.0.reason', 'Medical appointment'));
    }

    public function test_manager_can_view_worker_detail_with_performance_contracts_visits_and_targets(): void
    {
        $this->withoutVite();
        Carbon::setTestNow('2026-07-15 10:00:00');

        $manager = User::factory()->create(['role' => 'operations']);
        $customer = Customer::factory()->create(['name' => 'Al Noor Facility Group']);
        $site = CustomerSite::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Olaya Tower',
            'city' => 'Riyadh',
        ]);
        $contract = Contract::factory()->create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'reference' => 'CON-WORKER-DETAIL',
        ]);
        $worker = Worker::factory()->create([
            'name' => 'Sara Ali',
            'employee_code' => 'WRK-950',
            'status' => 'assigned',
            'skills' => ['general_cleaning', 'site_supervision'],
        ]);
        Assignment::factory()->for($contract)->create([
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'worker_id' => $worker->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '11:00',
            'status' => 'active',
        ]);
        WorkerRevenueTarget::create([
            'worker_id' => $worker->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'target_halalas' => 150000,
        ]);
        Visit::create([
            'contract_id' => $contract->id,
            'worker_id' => $worker->id,
            'customer_site_id' => $site->id,
            'service_id' => $contract->service_id,
            'scheduled_for' => '2026-07-07',
            'starts_at' => '08:00',
            'ends_at' => '11:00',
            'status' => 'completed',
            'actual_minutes' => 180,
            'planned_revenue_halalas' => 90000,
            'billable_overtime_halalas' => 5000,
            'gross_profit_halalas' => 70000,
        ]);

        $this->actingAs($manager)->get("/app/workers/{$worker->id}?from=2026-07-01&to=2026-07-31")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workers/Show')
                ->where('worker.name', 'Sara Ali')
                ->where('summary.target_halalas', 150000)
                ->where('summary.actual_revenue_halalas', 95000)
                ->where('summary.gross_profit_halalas', 70000)
                ->where('summary.worked_minutes', 180)
                ->where('summary.completed_visits_count', 1)
                ->where('summary.target_attainment_percent', 63)
                ->where('contracts.0.reference', 'CON-WORKER-DETAIL')
                ->where('contracts.0.customer.name', 'Al Noor Facility Group')
                ->where('contracts.0.worked_minutes', 180)
                ->where('visits.0.site.name', 'Olaya Tower')
                ->where('visits.0.actual_revenue_halalas', 95000)
                ->where('targets.0.target_sar', '1500.00')
                ->where('targetStoreUrl', "/app/workers/{$worker->id}/targets")
                ->where('statusUrl', '/app/workers/status')
            );
    }

    public function test_manager_can_set_worker_revenue_target(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $worker = Worker::factory()->create(['name' => 'Target Worker']);

        $this->actingAs($manager)->post("/app/workers/{$worker->id}/targets", [
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'target_sar' => '3500.50',
        ])->assertRedirect("/app/workers/{$worker->id}");

        $this->assertDatabaseHas('worker_revenue_targets', [
            'worker_id' => $worker->id,
            'period_start' => '2026-07-01 00:00:00',
            'period_end' => '2026-07-31 00:00:00',
            'target_halalas' => 350050,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'worker.target.created',
            'auditable_type' => WorkerRevenueTarget::class,
        ]);
    }

    public function test_manager_uses_dedicated_worker_wizard_pages_for_create_and_edit(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);
        $worker = Worker::factory()->create([
            'employee_code' => 'W-2100',
            'name' => 'Dedicated Wizard Worker',
            'cost_rate_halalas' => 225075,
        ]);

        $this->actingAs($manager)->get('/app/workers/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workers/Form')
                ->where('mode', 'create')
                ->where('worker', null)
                ->where('submitUrl', '/app/workers')
                ->where('backUrl', '/app/workers')
                ->where('steps.0.key', 'profile')
                ->where('steps.1.key', 'skills')
                ->where('steps.2.key', 'documents')
                ->where('steps.3.key', 'training')
                ->where('catalog.statuses.0.key', 'available')
            );

        $this->actingAs($manager)->get("/app/workers/{$worker->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workers/Form')
                ->where('mode', 'edit')
                ->where('worker.id', $worker->id)
                ->where('worker.name', 'Dedicated Wizard Worker')
                ->where('worker.cost_rate_sar', '2250.75')
                ->where('submitUrl', "/app/workers/{$worker->id}")
                ->where('backUrl', '/app/workers')
                ->where('steps.0.key', 'profile')
            );
    }

    public function test_manager_can_create_worker_with_documents_training_and_audit_log(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'operations']);

        $this->actingAs($manager)->post('/app/workers', $this->workerPayload([
            'employee_code' => 'W-3001',
            'name' => 'Mohammed Saleh',
            'phone' => '+966500003001',
            'job_role' => 'maintenance_technician',
            'status' => 'available',
            'cost_rate_sar' => '1800.50',
            'skills' => ['ac_maintenance'],
            'certifications' => ['electrical_safety'],
            'documents' => [
                [
                    'document_type' => 'iqama',
                    'document_number' => 'IQ-3001',
                    'expires_on' => now()->addYear()->toDateString(),
                    'file_path' => 'worker-documents/iqama-3001.pdf',
                ],
            ],
            'training_records' => [
                [
                    'course_name' => 'Electrical Safety',
                    'certificate_code' => 'ES-3001',
                    'completed_on' => now()->subWeek()->toDateString(),
                    'expires_on' => now()->addYear()->toDateString(),
                ],
            ],
        ]))->assertRedirect('/app/workers');

        $worker = Worker::query()->where('employee_code', 'W-3001')->firstOrFail();

        $this->assertDatabaseHas('workers', [
            'id' => $worker->id,
            'name' => 'Mohammed Saleh',
            'job_role' => 'maintenance_technician',
            'status' => 'available',
            'cost_rate_halalas' => 180050,
        ]);
        $this->assertDatabaseHas('worker_documents', [
            'worker_id' => $worker->id,
            'document_type' => 'iqama',
            'document_number' => 'IQ-3001',
        ]);
        $this->assertDatabaseHas('training_records', [
            'worker_id' => $worker->id,
            'course_name' => 'Electrical Safety',
            'certificate_code' => 'ES-3001',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'worker.created',
            'auditable_type' => Worker::class,
            'auditable_id' => $worker->id,
        ]);
    }

    public function test_manager_can_update_worker_compliance_details(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => 'supervisor']);
        $worker = Worker::factory()->create([
            'employee_code' => 'W-4001',
            'name' => 'Old Worker Name',
            'job_role' => 'cleaner',
            'status' => 'available',
            'skills' => ['general_cleaning'],
            'certifications' => [],
        ]);

        $documentId = DB::table('worker_documents')->insertGetId([
            'worker_id' => $worker->id,
            'document_type' => 'iqama',
            'document_number' => 'OLD-IQ',
            'expires_on' => now()->addMonth()->toDateString(),
            'file_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trainingId = DB::table('training_records')->insertGetId([
            'worker_id' => $worker->id,
            'course_name' => 'Old Course',
            'certificate_code' => null,
            'completed_on' => now()->subYear()->toDateString(),
            'expires_on' => now()->subDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)->patch("/app/workers/{$worker->id}", $this->workerPayload([
            'name' => 'Updated Worker Name',
            'job_role' => 'team_lead',
            'status' => 'on_leave',
            'cost_rate_sar' => '2250.25',
            'skills' => ['general_cleaning', 'site_supervision'],
            'certifications' => ['infection_control'],
            'documents' => [
                [
                    'id' => $documentId,
                    'document_type' => 'iqama',
                    'document_number' => 'NEW-IQ',
                    'expires_on' => now()->addMonths(10)->toDateString(),
                    'file_path' => 'worker-documents/new-iqama.pdf',
                ],
                [
                    'document_type' => 'passport',
                    'document_number' => 'P-4001',
                    'expires_on' => now()->addYears(3)->toDateString(),
                    'file_path' => null,
                ],
            ],
            'training_records' => [
                [
                    'id' => $trainingId,
                    'course_name' => 'Infection Control',
                    'certificate_code' => 'IC-4001',
                    'completed_on' => now()->subMonth()->toDateString(),
                    'expires_on' => now()->addYear()->toDateString(),
                ],
            ],
        ]))->assertRedirect('/app/workers');

        $worker->refresh();

        $this->assertSame('Updated Worker Name', $worker->name);
        $this->assertSame('team_lead', $worker->job_role);
        $this->assertSame('on_leave', $worker->status);
        $this->assertSame(225025, $worker->cost_rate_halalas);
        $this->assertSame(['general_cleaning', 'site_supervision'], $worker->skills);

        $this->assertDatabaseHas('worker_documents', [
            'id' => $documentId,
            'document_number' => 'NEW-IQ',
            'file_path' => 'worker-documents/new-iqama.pdf',
        ]);
        $this->assertDatabaseHas('worker_documents', [
            'worker_id' => $worker->id,
            'document_type' => 'passport',
            'document_number' => 'P-4001',
        ]);
        $this->assertDatabaseHas('training_records', [
            'id' => $trainingId,
            'course_name' => 'Infection Control',
            'certificate_code' => 'IC-4001',
        ]);

        $audit = AuditLog::query()
            ->where('action', 'worker.updated')
            ->where('auditable_id', $worker->id)
            ->firstOrFail();

        $this->assertSame('Old Worker Name', $audit->changes['name']['old']);
        $this->assertSame('Updated Worker Name', $audit->changes['name']['new']);
        $this->assertSame('available', $audit->changes['status']['old']);
        $this->assertSame('on_leave', $audit->changes['status']['new']);
        $this->assertSame(150000, $audit->changes['cost_rate_halalas']['old']);
        $this->assertSame(225025, $audit->changes['cost_rate_halalas']['new']);
    }

    public function test_users_without_worker_permission_cannot_manage_workers(): void
    {
        $this->withoutVite();

        $accountant = User::factory()->create(['role' => 'accountant']);
        $worker = Worker::factory()->create();

        $this->actingAs($accountant)->get('/app/workers')->assertForbidden();
        $this->actingAs($accountant)->get('/app/workers/status')->assertForbidden();
        $this->actingAs($accountant)->get("/app/workers/{$worker->id}")->assertForbidden();
        $this->actingAs($accountant)->get('/app/workers/create')->assertForbidden();
        $this->actingAs($accountant)->get("/app/workers/{$worker->id}/edit")->assertForbidden();
        $this->actingAs($accountant)->post("/app/workers/{$worker->id}/targets", [
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'target_sar' => '1000.00',
        ])->assertForbidden();
        $this->actingAs($accountant)->post('/app/workers', $this->workerPayload())->assertForbidden();
        $this->actingAs($accountant)->patch("/app/workers/{$worker->id}", $this->workerPayload())->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function workerPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'employee_code' => 'W-1001',
            'name' => 'Ahmed Hassan',
            'phone' => '+966500000303',
            'role_language' => 'ar',
            'job_role' => 'cleaner',
            'status' => 'available',
            'cost_rate_sar' => '1800.00',
            'skills' => ['general_cleaning'],
            'certifications' => ['infection_control'],
            'availability_notes' => 'Available for morning shifts.',
            'documents' => [
                [
                    'document_type' => 'iqama',
                    'document_number' => 'IQ-1001',
                    'expires_on' => now()->addYear()->toDateString(),
                    'file_path' => null,
                ],
            ],
            'training_records' => [
                [
                    'course_name' => 'Infection Control',
                    'certificate_code' => 'IC-1001',
                    'completed_on' => now()->subMonth()->toDateString(),
                    'expires_on' => now()->addYear()->toDateString(),
                ],
            ],
        ], $overrides);
    }
}
