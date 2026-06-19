<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizedValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_user_receives_arabic_validation_errors(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create([
            'role' => 'operations',
            'locale' => 'ar',
        ]);

        $response = $this
            ->actingAs($manager)
            ->from('/app/services/create')
            ->post('/app/services', [
                'title' => '',
                'category' => 'not_a_real_category',
            ]);

        $response->assertRedirect('/app/services/create');
        $response->assertSessionHasErrors(['title', 'category']);

        $errors = session('errors')->getBag('default');

        $this->assertStringContainsString('مطلوب', $errors->first('title'));
        $this->assertStringContainsString('غير صالح', $errors->first('category'));
        $this->assertStringNotContainsString('required', $errors->first('title'));
        $this->assertStringNotContainsString('selected', $errors->first('category'));
    }

    public function test_session_locale_controls_validation_redirects_for_authenticated_users(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create([
            'role' => 'operations',
            'locale' => 'en',
        ]);

        $response = $this
            ->actingAs($manager)
            ->withSession(['locale' => 'ar'])
            ->from('/app/customers/create')
            ->post('/app/customers', [
                'customer_type' => 'company',
                'name' => '',
                'preferred_channel' => 'whatsapp',
                'preferred_locale' => 'ar',
                'status' => 'active',
                'sites' => [],
            ]);

        $response->assertRedirect('/app/customers/create');
        $response->assertSessionHasErrors(['name']);

        $this->assertStringContainsString('مطلوب', session('errors')->getBag('default')->first('name'));
    }
}
