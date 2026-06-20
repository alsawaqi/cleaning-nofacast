<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_entry_uses_lazy_page_imports_for_production_chunking(): void
    {
        $entrypoint = file_get_contents(base_path('resources/js/app.ts'));

        $this->assertIsString($entrypoint);
        $this->assertStringContainsString(
            "import.meta.glob<PageModule>('./Pages/**/*.vue')",
            $entrypoint,
            'The Inertia page resolver should lazy-load pages so the production build can split route chunks.',
        );
        $this->assertStringNotContainsString(
            'eager: true',
            $entrypoint,
            'Eager page imports pull every admin/customer/worker page into the initial app bundle.',
        );
    }

    public function test_database_seeder_upgrades_blank_demo_customer_account_with_demo_business_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Customer Contact',
            'email' => 'customer@nofacast.test',
            'role' => 'customer',
            'is_active' => true,
        ]);

        Customer::factory()->create([
            'user_id' => $user->id,
            'name' => 'Customer Contact',
            'email' => 'customer@nofacast.test',
            'customer_type' => 'individual',
        ]);

        $this->seed(DatabaseSeeder::class);

        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(1, Customer::query()->where('user_id', $user->id)->count());
        $this->assertSame('Riyadh Clinic Group', $customer->name);
        $this->assertSame('facilities@riyadhclinic.test', $customer->email);
        $this->assertDatabaseHas('customer_sites', [
            'customer_id' => $customer->id,
            'name' => 'Olaya Medical Center',
        ]);
        $this->assertDatabaseHas('contracts', [
            'customer_id' => $customer->id,
            'reference' => 'CON-2026-00001',
        ]);
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'number' => 'INV-2026-00001',
        ]);
    }

    public function test_role_portal_entry_points_are_scoped_by_permission(): void
    {
        $this->withoutVite();

        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $worker = User::factory()->create(['role' => 'worker', 'is_active' => true]);
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $operations = User::factory()->create(['role' => 'operations', 'is_active' => true]);

        $this->get('/app/dashboard')->assertRedirect('/login');
        $this->get('/app/customer')->assertRedirect('/login');
        $this->get('/app/worker/today')->assertRedirect('/login');

        $this->actingAs($customer)->get('/app/customer')->assertOk();
        $this->actingAs($customer)->get('/app/dashboard')->assertForbidden();
        $this->actingAs($customer)->get('/app/finance')->assertForbidden();
        $this->actingAs($customer)->get('/app/worker/today')->assertForbidden();

        $this->actingAs($worker)->get('/app/worker/today')->assertOk();
        $this->actingAs($worker)->get('/app/customer')->assertForbidden();
        $this->actingAs($worker)->get('/app/finance')->assertForbidden();

        $this->actingAs($accountant)->get('/app/finance')->assertOk();
        $this->actingAs($accountant)->get('/app/operations')->assertForbidden();
        $this->actingAs($accountant)->get('/app/customer')->assertForbidden();

        $this->actingAs($operations)->get('/app/operations')->assertOk();
        $this->actingAs($operations)->get('/app/customer')->assertForbidden();
        $this->actingAs($operations)->get('/app/finance')->assertForbidden();
    }

    public function test_authenticated_shell_uses_nofa_clean_brand_consistently(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        $translations = file_get_contents(config_path('cleanops_extra_translations.php'));

        $this->assertIsString($layout);
        $this->assertIsString($translations);
        $this->assertStringContainsString("t('publicLead.brand', 'Nofa Clean')", $layout);
        $this->assertStringNotContainsString('>CleanOps<', $layout);
        $this->assertStringNotContainsString("'productName' => 'CleanOps'", $translations);
    }
}
