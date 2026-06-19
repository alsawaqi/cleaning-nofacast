<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminUsersAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_admin_users_with_roles_workers_and_audit_logs(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create(['role' => 'owner']);
        User::factory()->create(['name' => 'Operations Lead', 'role' => 'operations']);
        Worker::factory()->create(['name' => 'Unlinked Worker']);
        AuditLog::create([
            'user_id' => $owner->id,
            'action' => 'settings.updated',
            'auditable_type' => 'system_settings',
            'changes' => ['company.name' => ['old' => 'A', 'new' => 'B']],
        ]);

        $this->actingAs($owner)->get('/app/users')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 2)
                ->where('roles.owner.label', 'Owner')
                ->where('roles.worker.permissions.0', 'access_worker_portal')
                ->has('workers', 1)
                ->has('auditLogs', 1)
            );
    }

    public function test_non_owner_cannot_manage_admin_users(): void
    {
        $this->withoutVite();

        $operations = User::factory()->create(['role' => 'operations']);
        $target = User::factory()->create(['role' => 'worker']);

        $this->actingAs($operations)->get('/app/users')->assertForbidden();

        $this->actingAs($operations)->post('/app/users', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.test',
            'phone' => '+966500000000',
            'role' => 'sales',
            'locale' => 'en',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($operations)->patch("/app/users/{$target->id}", [
            'name' => 'Changed',
            'email' => $target->email,
            'phone' => $target->phone,
            'role' => 'operations',
            'locale' => 'en',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_owner_can_create_user_and_link_worker_profile_with_audit_log(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create(['role' => 'owner']);
        $worker = Worker::factory()->create(['name' => 'Ahmed Worker', 'user_id' => null]);

        $this->actingAs($owner)->post('/app/users', [
            'name' => 'Ahmed Worker Login',
            'email' => 'ahmed.worker@example.test',
            'phone' => '+966500000111',
            'role' => 'worker',
            'locale' => 'en',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_active' => true,
            'worker_id' => $worker->id,
        ])->assertRedirect('/app/users');

        $created = User::query()->where('email', 'ahmed.worker@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('password', $created->password));
        $this->assertDatabaseHas('workers', [
            'id' => $worker->id,
            'user_id' => $created->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'user.created',
            'auditable_type' => User::class,
            'auditable_id' => $created->id,
        ]);
    }

    public function test_owner_can_update_role_status_and_worker_link_with_audit_log(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create(['role' => 'owner']);
        $target = User::factory()->create([
            'name' => 'Field User',
            'email' => 'field@example.test',
            'role' => 'worker',
            'is_active' => true,
        ]);
        $oldWorker = Worker::factory()->create(['user_id' => $target->id]);
        $newWorker = Worker::factory()->create(['user_id' => null]);

        $this->actingAs($owner)->patch("/app/users/{$target->id}", [
            'name' => 'Senior Supervisor',
            'email' => 'supervisor.updated@example.test',
            'phone' => '+966500000222',
            'role' => 'supervisor',
            'locale' => 'ar',
            'is_active' => false,
            'worker_id' => $newWorker->id,
        ])->assertRedirect('/app/users');

        $target->refresh();

        $this->assertSame('Senior Supervisor', $target->name);
        $this->assertSame('supervisor', $target->role);
        $this->assertFalse($target->is_active);
        $this->assertDatabaseHas('workers', ['id' => $oldWorker->id, 'user_id' => null]);
        $this->assertDatabaseHas('workers', ['id' => $newWorker->id, 'user_id' => $target->id]);

        $audit = AuditLog::query()
            ->where('action', 'user.updated')
            ->where('auditable_id', $target->id)
            ->firstOrFail();

        $this->assertSame('worker', $audit->changes['role']['old']);
        $this->assertSame('supervisor', $audit->changes['role']['new']);
        $this->assertTrue($audit->changes['is_active']['old']);
        $this->assertFalse($audit->changes['is_active']['new']);
    }
}
