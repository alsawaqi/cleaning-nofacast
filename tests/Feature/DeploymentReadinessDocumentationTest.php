<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentReadinessDocumentationTest extends TestCase
{
    public function test_nofaclean_production_environment_template_is_safe_for_real_domain(): void
    {
        $template = file_get_contents(base_path('.env.nofaclean.production.example'));

        $this->assertIsString($template);
        $this->assertStringContainsString('APP_NAME="Nofa Clean"', $template);
        $this->assertStringContainsString('APP_ENV=production', $template);
        $this->assertStringContainsString('APP_DEBUG=false', $template);
        $this->assertStringContainsString('APP_URL=https://nofaclean.com', $template);
        $this->assertStringContainsString('APP_TIMEZONE=Asia/Riyadh', $template);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $template);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $template);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $template);
        $this->assertStringContainsString('VITE_GOOGLE_MAPS_API_KEY=', $template);
        $this->assertStringNotContainsString('APP_DEBUG=true', $template);
    }

    public function test_deployment_pack_is_production_only_without_staging_domain(): void
    {
        $runbook = file_get_contents(base_path('docs/deployment/nofaclean-com.md'));

        $this->assertIsString($runbook);
        $this->assertFileDoesNotExist(base_path('.env.nofaclean.staging.example'));
        $this->assertStringContainsString('Production UAT on `https://nofaclean.com`', $runbook);
        $this->assertStringContainsString('protect the live domain during UAT', $runbook);
        $this->assertStringNotContainsString('staging', strtolower($runbook));
        $this->assertStringNotContainsString('staging.nofaclean.com', $runbook);
        $this->assertStringNotContainsString('.env.nofaclean.staging.example', $runbook);
    }

    public function test_nofaclean_deployment_runbook_covers_cutover_and_operations(): void
    {
        $runbook = file_get_contents(base_path('docs/deployment/nofaclean-com.md'));

        $this->assertIsString($runbook);
        $this->assertStringContainsString('https://nofaclean.com', $runbook);
        $this->assertStringContainsString('88.222.210.73', $runbook);
        $this->assertStringContainsString('Hostinger default page', $runbook);
        $this->assertStringContainsString('Hostinger Cloud', $runbook);
        $this->assertStringContainsString('hPanel Cron Jobs', $runbook);
        $this->assertStringContainsString('public/ document root', $runbook);
        $this->assertStringContainsString('php artisan migrate --force', $runbook);
        $this->assertStringContainsString('php artisan storage:link', $runbook);
        $this->assertStringContainsString('php artisan queue:work database --sleep=3 --tries=3 --timeout=90 --stop-when-empty', $runbook);
        $this->assertStringContainsString('php artisan schedule:run', $runbook);
        $this->assertStringContainsString('APP_DEBUG=false', $runbook);
        $this->assertStringNotContainsString('Suggested Supervisor command', $runbook);
        $this->assertStringContainsString('rollback', strtolower($runbook));
    }
}
