<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLayoutResponsivenessTest extends TestCase
{
    public function test_desktop_sidebar_is_fixed_and_main_content_is_offset(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));

        $this->assertStringContainsString('fixed inset-y-0 start-0', $layout);
        $this->assertStringContainsString('lg:ms-[290px]', $layout);
        $this->assertStringContainsString('h-dvh', $layout);
        $this->assertStringNotContainsString('lg:static', $layout);
    }

    public function test_sidebar_dropdown_parent_aligns_and_exposes_expanded_state(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('text-align: start;', $css);
        $this->assertStringContainsString('justify-content: flex-start;', $css);
        $this->assertStringContainsString(':aria-expanded="isExpanded(item)"', $layout);
        $this->assertStringContainsString('@click.stop="toggleNavItem(item)"', $layout);
        $this->assertStringContainsString('expandedNav.value = {', $layout);
    }

    public function test_mobile_header_keeps_actions_in_single_top_row(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));

        $this->assertStringContainsString('flex w-full items-center justify-between gap-2 px-3 py-3 lg:gap-4 lg:px-6 lg:py-4', $layout);
        $this->assertStringContainsString('flex shrink-0 items-center justify-end', $layout);
        $this->assertStringNotContainsString('flex grow flex-col items-center justify-between lg:flex-row', $layout);
        $this->assertStringNotContainsString('shadow-theme-md lg:w-auto', $layout);
    }

    public function test_dashboard_kpis_do_not_cramp_money_values_on_standard_desktop(): void
    {
        $dashboard = file_get_contents(resource_path('js/Pages/Dashboard.vue'));

        $this->assertStringContainsString('xl:grid-cols-3 2xl:grid-cols-5', $dashboard);
        $this->assertStringContainsString('class="min-w-0"', $dashboard);
        $this->assertStringContainsString('break-words text-2xl font-bold leading-tight', $dashboard);
        $this->assertStringNotContainsString('sm:grid-cols-2 xl:grid-cols-5', $dashboard);
    }
}
