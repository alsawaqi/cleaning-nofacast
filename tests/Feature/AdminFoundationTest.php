<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_users_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.test',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => 'inactive@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_worker_can_open_worker_view_but_not_back_office_dashboard(): void
    {
        $this->withoutVite();

        $worker = User::factory()->create(['role' => 'worker']);

        $this->actingAs($worker)->get('/app/worker/today')->assertOk();
        $this->actingAs($worker)->get('/app/dashboard')->assertForbidden();
    }

    public function test_owner_can_update_company_settings_and_changes_are_audited(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->get('/app/settings')->assertOk();

        $this->actingAs($owner)->post('/app/settings', [
            'company.name' => 'Trust Palace Facility Services',
            'company.vat_number' => '300000000000003',
            'operations.default_city' => 'Riyadh',
            'operations.working_week' => ['sun', 'mon', 'tue', 'wed', 'thu'],
            'finance.vat_rate' => 15,
        ])->assertRedirect('/app/settings');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'company.name',
            'value' => 'Trust Palace Facility Services',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'finance.vat_rate',
            'value' => '15',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'settings.updated',
            'auditable_type' => 'system_settings',
        ]);

        $audit = AuditLog::query()->where('action', 'settings.updated')->first();

        $this->assertSame('Trust Palace Facility Services', $audit?->changes['company.name']['new']);
    }

    public function test_non_owner_cannot_update_company_settings(): void
    {
        $this->withoutVite();

        $operations = User::factory()->create(['role' => 'operations']);

        $this->actingAs($operations)->get('/app/settings')->assertForbidden();
        $this->actingAs($operations)->post('/app/settings', [
            'company.name' => 'Blocked Update',
        ])->assertForbidden();

        $this->assertFalse(DB::table('system_settings')->where('value', 'Blocked Update')->exists());
    }

    public function test_authenticated_user_can_switch_locale_and_inertia_uses_direction(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create(['role' => 'owner', 'locale' => 'ar']);

        $this->actingAs($owner)->post('/app/locale', [
            'locale' => 'en',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'locale' => 'en',
        ]);

        $this->actingAs($owner)->get('/app/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('app.locale', 'en')
                ->where('app.dir', 'ltr')
                ->where('i18n.messages.layout.searchPlaceholder', 'Search contracts, customers, invoices...')
                ->has('auth.user.permissions')
            );
    }

    public function test_arabic_locale_shares_rtl_direction_and_arabic_admin_copy(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create(['role' => 'owner', 'locale' => 'ar']);

        $this->actingAs($owner)->get('/app/operations')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('app.locale', 'ar')
                ->where('app.dir', 'rtl')
                ->where('i18n.messages.layout.searchPlaceholder', 'ابحث في العقود والعملاء والفواتير...')
                ->where('i18n.messages.operations.title', 'لوحة العمليات اليومية')
            );
    }
}
