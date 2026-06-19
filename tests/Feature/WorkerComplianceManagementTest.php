<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                ->where('createUrl', '/app/workers/create')
                ->where('workers.0.documents.0.document_type', 'iqama')
                ->where('workers.0.training_records.0.course_name', 'Infection Control')
                ->where('workers.0.compliance.expiring_documents', 1)
            );
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
        $this->actingAs($accountant)->get('/app/workers/create')->assertForbidden();
        $this->actingAs($accountant)->get("/app/workers/{$worker->id}/edit")->assertForbidden();
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
