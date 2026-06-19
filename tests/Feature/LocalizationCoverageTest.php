<?php

namespace Tests\Feature;

use App\Http\Controllers\ContractController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\WorkerController;
use ReflectionMethod;
use Tests\TestCase;

class LocalizationCoverageTest extends TestCase
{
    public function test_vue_pages_use_the_i18n_helper_and_translated_head_titles(): void
    {
        $pages = $this->vuePages();

        foreach ($pages as $page) {
            $contents = file_get_contents(resource_path("js/Pages/{$page}"));

            $this->assertStringContainsString('useI18n', $contents, "{$page} must use the i18n helper.");
            $this->assertStringNotContainsString('<Head title="', $contents, "{$page} must use a translated Head title.");
        }
    }

    public function test_recent_translation_namespaces_are_available_in_both_locales(): void
    {
        $requiredKeys = [
            'auth.headTitle',
            'auth.staffLogin',
            'dashboard.title',
            'dashboard.grossProfit',
            'finance.title',
            'finance.openBalance',
            'publicLead.headTitle',
            'publicLead.requestQuotation',
            'reports.title',
            'reports.profitabilityEngine',
            'settings.title',
            'settings.companyProfile',
            'workerPortal.headTitle',
            'workerPortal.startWork',
        ];

        foreach (['en', 'ar'] as $locale) {
            $messages = $this->localeMessages($locale);

            foreach ($requiredKeys as $key) {
                $value = data_get($messages, $key);

                $this->assertIsString($value, "{$locale}.{$key} is missing.");
                $this->assertNotSame('', trim($value), "{$locale}.{$key} is empty.");
            }
        }
    }

    public function test_literal_vue_translation_keys_exist_in_both_locales(): void
    {
        $keys = [];
        $basePath = resource_path('js');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['ts', 'vue'], true)) {
                continue;
            }

            preg_match_all('/\bt\(\s*[\'"]([A-Za-z0-9_.-]+)[\'"]/', file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }

        foreach (['en', 'ar'] as $locale) {
            $messages = $this->localeMessages($locale);
            $missing = array_values(array_filter(array_keys($keys), fn (string $key) => ! is_string(data_get($messages, $key))));
            sort($missing);

            $this->assertSame([], $missing, "{$locale} is missing literal translation keys.");
        }
    }

    public function test_select_options_do_not_render_backend_label_fallbacks_directly(): void
    {
        $forbiddenPatterns = [
            '/<option[^>]+v-for="option in catalog[^"]+"[^>]*>\s*\{\{\s*option\.label\s*\}\}/s',
            '/<option[^>]+v-for="status in [^"]+"[^>]*>\s*\{\{\s*status\.label\s*\}\}/s',
            '/<option[^>]+v-for="weekday in [^"]+"[^>]*>\s*\{\{\s*weekday\.label\s*\}\}/s',
            '/<option[^>]+v-for="country in [^"]+"[^>]*>\s*\{\{\s*country\.label\s*\}\}/s',
            '/<option[^>]+v-for="city in [^"]+"[^>]*>\s*\{\{\s*city\.label\s*\}\}/s',
            '/<option[^>]+v-for="district in [^"]+"[^>]*>\s*\{\{\s*district\.label\s*\}\}/s',
        ];

        foreach ($this->vuePages() as $page) {
            $contents = file_get_contents(resource_path("js/Pages/{$page}"));

            foreach ($forbiddenPatterns as $pattern) {
                $this->assertDoesNotMatchRegularExpression($pattern, $contents, "{$page} renders a backend option label directly.");
            }
        }
    }

    public function test_dynamic_select_translation_keys_exist_in_both_locales(): void
    {
        $requiredKeys = [
            'contractsAdmin.catalog.materialPolicies.company_supplied_billable',
            'contractsAdmin.catalog.overtimePolicies.requires_approval',
            'contractsAdmin.catalog.pricingModels.custom_quote',
            'customersAdmin.location.cities.riyadh',
            'customersAdmin.location.countries.sa',
            'customersAdmin.location.districts.al_malaz',
            'operations.overtimeStatuses.pending_approval',
            'operations.overtimeStatuses.rejected',
            'operations.weekdays.mon',
            'servicesAdmin.catalog.materialPolicies.company_supplied_included',
            'servicesAdmin.catalog.overtimePolicies.charge_after_planned_time',
            'servicesAdmin.catalog.pricingTypes.per_day',
        ];

        foreach (['en', 'ar'] as $locale) {
            $messages = $this->localeMessages($locale);

            foreach ($requiredKeys as $key) {
                $value = data_get($messages, $key);

                $this->assertIsString($value, "{$locale}.{$key} is missing.");
                $this->assertNotSame('', trim($value), "{$locale}.{$key} is empty.");
            }
        }
    }

    public function test_controller_catalog_select_keys_exist_in_both_locales(): void
    {
        $requiredKeys = [];
        $catalogControllers = [
            [ServiceController::class, 'servicesAdmin.catalog'],
            [ContractController::class, 'contractsAdmin.catalog'],
            [CustomerController::class, 'customersAdmin.catalog'],
            [WorkerController::class, 'workersAdmin.catalog'],
        ];

        foreach ($catalogControllers as [$controller, $namespace]) {
            foreach ($this->invokePrivate(app($controller), 'catalog') as $group => $items) {
                foreach ($items as $item) {
                    $requiredKeys[] = "{$namespace}.{$group}.{$item['key']}";
                }
            }
        }

        $operations = new OperationsController;

        foreach ($this->invokePrivate($operations, 'statusOptions') as $item) {
            $requiredKeys[] = "statuses.{$item['key']}";
        }

        foreach ($this->invokePrivate($operations, 'assignmentStatuses') as $item) {
            $requiredKeys[] = "statuses.{$item['key']}";
        }

        foreach ($this->invokePrivate($operations, 'overtimeStatuses') as $item) {
            $requiredKeys[] = "operations.overtimeStatuses.{$item['key']}";
        }

        foreach ($this->invokePrivate($operations, 'weekdayOptions') as $item) {
            $requiredKeys[] = 'operations.weekdays.'.$this->weekdayKey((int) $item['key']);
        }

        $locations = $this->invokePrivate(new CustomerController, 'locationCatalog');

        foreach ($locations['countries'] as $country) {
            $requiredKeys[] = 'customersAdmin.location.countries.'.$this->translationKey($country['key']);
        }

        foreach ($locations['cities'] as $city) {
            $requiredKeys[] = 'customersAdmin.location.cities.'.$this->translationKey($city['key']);

            foreach ($city['districts'] as $district) {
                $requiredKeys[] = 'customersAdmin.location.districts.'.$this->translationKey($district['key']);
            }
        }

        $requiredKeys = array_values(array_unique($requiredKeys));
        sort($requiredKeys);

        foreach (['en', 'ar'] as $locale) {
            $messages = $this->localeMessages($locale);
            $missing = array_values(array_filter($requiredKeys, fn (string $key) => ! is_string(data_get($messages, $key))));

            $this->assertSame([], $missing, "{$locale} is missing controller catalog translation keys.");
        }
    }

    public function test_controller_form_step_keys_exist_in_both_locales(): void
    {
        $requiredKeys = [];
        $stepControllers = [
            [ServiceController::class, 'servicesAdmin.steps'],
            [ContractController::class, 'contractsAdmin.steps'],
            [CustomerController::class, 'customersAdmin.steps'],
            [WorkerController::class, 'workersAdmin.steps'],
        ];

        foreach ($stepControllers as [$controller, $namespace]) {
            foreach ($this->invokePrivate(app($controller), 'formSteps') as $step) {
                $requiredKeys[] = "{$namespace}.{$step['key']}";
            }
        }

        foreach (['en', 'ar'] as $locale) {
            $messages = $this->localeMessages($locale);
            $missing = array_values(array_filter($requiredKeys, fn (string $key) => ! is_string(data_get($messages, $key))));

            $this->assertSame([], $missing, "{$locale} is missing controller form step translation keys.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedMessages(string $locale): array
    {
        return array_replace_recursive(
            config('cleanops.translations.en', []),
            config('cleanops_extra_translations.en', []),
            config("cleanops.translations.{$locale}", []),
            config("cleanops_extra_translations.{$locale}", []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function localeMessages(string $locale): array
    {
        return array_replace_recursive(
            config("cleanops.translations.{$locale}", []),
            config("cleanops_extra_translations.{$locale}", []),
        );
    }

    private function invokePrivate(object $object, string $method): mixed
    {
        $reflection = new ReflectionMethod($object, $method);

        return $reflection->invoke($object);
    }

    private function translationKey(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($value)), '_');
    }

    private function weekdayKey(int $weekday): string
    {
        return [
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
        ][$weekday];
    }

    /**
     * @return array<int, string>
     */
    private function vuePages(): array
    {
        $basePath = resource_path('js/Pages');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath));
        $pages = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'vue') {
                continue;
            }

            $pages[] = str_replace('\\', '/', substr($file->getPathname(), strlen($basePath) + 1));
        }

        sort($pages);

        return $pages;
    }
}
