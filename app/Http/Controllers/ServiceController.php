<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSarMoney;
use App\Models\AuditLog;
use App\Models\Service;
use App\Models\ServiceCostItem;
use App\Models\ServicePackage;
use App\Models\ServicePricingRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    use HandlesSarMoney;

    public function index(Request $request): Response
    {
        return Inertia::render('Services/Index', [
            'services' => Service::query()
                ->with([
                    'packages' => fn ($query) => $query->orderByDesc('is_active')->orderBy('name'),
                    'pricingRules' => fn ($query) => $query->orderByDesc('is_active')->orderBy('name'),
                    'serviceCostItems.costItem',
                ])
                ->orderByDesc('is_active')
                ->orderBy('title')
                ->get()
                ->map(fn (Service $service): array => $this->serializeService($service)),
            'catalog' => $this->catalog(),
            'createUrl' => '/app/services/create',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Services/Form', [
            'mode' => 'create',
            'service' => null,
            'catalog' => $this->catalog(),
            'steps' => $this->formSteps(),
            'submitUrl' => '/app/services',
            'backUrl' => '/app/services',
        ]);
    }

    public function edit(Service $service): Response
    {
        $service->load([
            'packages' => fn ($query) => $query->orderByDesc('is_active')->orderBy('name'),
            'pricingRules' => fn ($query) => $query->orderByDesc('is_active')->orderBy('name'),
            'serviceCostItems.costItem',
        ]);

        return Inertia::render('Services/Form', [
            'mode' => 'edit',
            'service' => $this->serializeService($service),
            'catalog' => $this->catalog(),
            'steps' => $this->formSteps(),
            'submitUrl' => "/app/services/{$service->id}",
            'backUrl' => '/app/services',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateService($request);
        $packages = Arr::pull($validated, 'packages', []);
        $pricingRules = Arr::pull($validated, 'pricing_rules', []);

        $service = DB::transaction(function () use ($request, $validated, $packages, $pricingRules): Service {
            $service = Service::create([
                ...$validated,
                'slug' => $this->uniqueSlug($validated['title']),
            ]);

            $this->syncPackages($service, $packages);
            $this->syncPricingRules($service, $pricingRules);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'service.created',
                'auditable_type' => Service::class,
                'auditable_id' => $service->id,
                'changes' => [
                    'created' => [
                        'old' => null,
                        'new' => $this->serializeService($service->fresh(['packages', 'pricingRules', 'serviceCostItems.costItem'])),
                    ],
                ],
            ]);

            return $service;
        });

        return redirect('/app/services')->with('success', __('Service :service created.', ['service' => $service->title]));
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $this->validateService($request);
        $packages = Arr::pull($validated, 'packages', []);
        $pricingRules = Arr::pull($validated, 'pricing_rules', []);
        $oldValues = $service->only($this->serviceFields());

        DB::transaction(function () use ($request, $service, $validated, $packages, $pricingRules, $oldValues): void {
            $validated['slug'] = $this->uniqueSlug($validated['title'], $service);

            $service->fill($validated)->save();
            $this->syncPackages($service, $packages);
            $this->syncPricingRules($service, $pricingRules);

            $changes = $this->changes($oldValues, $service->fresh()->only($this->serviceFields()));

            if ($changes !== [] || $packages !== [] || $pricingRules !== []) {
                if ($packages !== []) {
                    $changes['packages_count'] = ['old' => null, 'new' => count($packages)];
                }

                if ($pricingRules !== []) {
                    $changes['pricing_rules_count'] = ['old' => null, 'new' => count($pricingRules)];
                }

                AuditLog::create([
                    'user_id' => $request->user()?->id,
                    'action' => 'service.updated',
                    'auditable_type' => Service::class,
                    'auditable_id' => $service->id,
                    'changes' => $changes,
                ]);
            }
        });

        return redirect('/app/services')->with('success', __('Service :service updated.', ['service' => $service->title]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateService(Request $request): array
    {
        $catalog = $this->catalog();

        $this->mergeSarFromHalalas($request, 'base_price_sar', 'base_price_halalas');
        $this->mergeSarFromHalalas($request, 'default_material_cost_sar', 'default_material_cost_halalas');
        $this->mergeSarFromHalalas($request, 'extra_hour_rate_sar', 'extra_hour_rate_halalas');

        $request->merge([
            'minimum_billable_minutes' => $request->input('minimum_billable_minutes', 0),
            'default_workers' => $request->input('default_workers', 1),
            'default_duration_minutes' => $request->input('default_duration_minutes', 60),
            'default_material_cost_sar' => $request->input('default_material_cost_sar', '0.00'),
            'material_policy' => $request->input('material_policy', $request->boolean('materials_included') ? 'company_supplied_included' : 'customer_supplied'),
            'included_materials_text' => $request->input('included_materials_text', ''),
            'extra_hour_rate_sar' => $request->input('extra_hour_rate_sar', '0.00'),
            'overtime_policy' => $request->input('overtime_policy', 'none'),
        ]);

        if (is_array($request->input('packages'))) {
            $request->merge([
                'packages' => collect($request->input('packages', []))
                    ->map(function (array $package): array {
                        if (! array_key_exists('price_sar', $package) && array_key_exists('price_halalas', $package)) {
                            $package['price_sar'] = $this->halalasToSarString((int) $package['price_halalas']);
                        }

                        if (! array_key_exists('material_cost_sar', $package)) {
                            $package['material_cost_sar'] = array_key_exists('material_cost_halalas', $package)
                                ? $this->halalasToSarString((int) $package['material_cost_halalas'])
                                : '0.00';
                        }

                        $durationMinutes = (int) ($package['duration_minutes'] ?? 60);
                        $workerCount = (int) ($package['worker_count'] ?? 1);
                        $package['hours_per_visit'] = $package['hours_per_visit'] ?? number_format($durationMinutes / 60, 2, '.', '');
                        $package['expected_labor_minutes'] = $package['expected_labor_minutes'] ?? ($durationMinutes * $workerCount);

                        return $package;
                    })
                    ->all(),
            ]);
        }

        if (is_array($request->input('pricing_rules'))) {
            $request->merge([
                'pricing_rules' => collect($request->input('pricing_rules', []))
                    ->map(function (array $rule): array {
                        if (! array_key_exists('unit_price_sar', $rule) && array_key_exists('unit_price_halalas', $rule)) {
                            $rule['unit_price_sar'] = $this->halalasToSarString((int) $rule['unit_price_halalas']);
                        }

                        return $rule;
                    })
                    ->all(),
            ]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in($this->catalogKeys($catalog['categories']))],
            'description' => ['nullable', 'string', 'max:2000'],
            'pricing_type' => ['required', Rule::in($this->catalogKeys($catalog['pricingTypes']))],
            'base_price_sar' => $this->sarMoneyRules(),
            'vat_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'prices_include_vat' => ['required', 'boolean'],
            'materials_included' => ['required', 'boolean'],
            'minimum_billable_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'default_workers' => ['required', 'integer', 'min:1', 'max:200'],
            'default_duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'default_material_cost_sar' => $this->sarMoneyRules(),
            'material_policy' => ['required', Rule::in($this->catalogKeys($catalog['materialPolicies']))],
            'included_materials_text' => ['nullable', 'string', 'max:2000'],
            'extra_hour_rate_sar' => $this->sarMoneyRules(),
            'overtime_policy' => ['required', Rule::in($this->catalogKeys($catalog['overtimePolicies']))],
            'allowed_frequencies' => ['array', 'max:8'],
            'allowed_frequencies.*' => ['required', Rule::in($this->catalogKeys($catalog['frequencies']))],
            'required_certificates' => ['array', 'max:12'],
            'required_certificates.*' => ['required', Rule::in($this->catalogKeys($catalog['certificates']))],
            'checklist_template' => ['array', 'max:30'],
            'checklist_template.*.label' => ['required', 'string', 'max:160'],
            'checklist_template.*.is_required' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'packages' => ['array', 'max:12'],
            'packages.*.name' => ['required', 'string', 'max:160'],
            'packages.*.description' => ['nullable', 'string', 'max:1000'],
            'packages.*.billing_cycle' => ['required', Rule::in($this->catalogKeys($catalog['billingCycles']))],
            'packages.*.visit_frequency' => ['required', Rule::in($this->catalogKeys($catalog['frequencies']))],
            'packages.*.visits_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'packages.*.hours_per_visit' => ['nullable', 'numeric', 'min:0.25', 'max:24', 'regex:/^\d+(\.\d{1,2})?$/'],
            'packages.*.worker_count' => ['required', 'integer', 'min:1', 'max:200'],
            'packages.*.duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'packages.*.expected_labor_minutes' => ['required', 'integer', 'min:15', 'max:288000'],
            'packages.*.material_cost_sar' => $this->sarMoneyRules(),
            'packages.*.price_sar' => $this->sarMoneyRules(),
            'packages.*.vat_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'packages.*.prices_include_vat' => ['required', 'boolean'],
            'packages.*.checklist_template' => ['array', 'max:30'],
            'packages.*.checklist_template.*.label' => ['required', 'string', 'max:160'],
            'packages.*.checklist_template.*.is_required' => ['nullable', 'boolean'],
            'packages.*.is_active' => ['required', 'boolean'],
            'pricing_rules' => ['array', 'max:20'],
            'pricing_rules.*.name' => ['required', 'string', 'max:160'],
            'pricing_rules.*.pricing_type' => ['required', Rule::in($this->catalogKeys($catalog['pricingTypes']))],
            'pricing_rules.*.unit_label' => ['required', 'string', 'max:80'],
            'pricing_rules.*.unit_price_sar' => $this->sarMoneyRules(),
            'pricing_rules.*.minimum_quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'pricing_rules.*.maximum_quantity' => ['nullable', 'integer', 'min:1', 'max:999999', 'gte:pricing_rules.*.minimum_quantity'],
            'pricing_rules.*.vat_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'pricing_rules.*.prices_include_vat' => ['required', 'boolean'],
            'pricing_rules.*.applies_to' => ['array', 'max:12'],
            'pricing_rules.*.applies_to.*' => ['required', 'string', 'max:80'],
            'pricing_rules.*.is_active' => ['required', 'boolean'],
        ]);

        $validated['base_price_halalas'] = $this->sarToHalalas($validated['base_price_sar']);
        $validated['default_material_cost_halalas'] = $this->sarToHalalas($validated['default_material_cost_sar']);
        $validated['extra_hour_rate_halalas'] = $this->sarToHalalas($validated['extra_hour_rate_sar']);
        $validated['included_materials'] = $this->splitList($validated['included_materials_text'] ?? '');
        unset($validated['base_price_sar']);
        unset($validated['default_material_cost_sar'], $validated['extra_hour_rate_sar'], $validated['included_materials_text']);

        foreach ($validated['packages'] ?? [] as $index => $package) {
            $validated['packages'][$index]['price_halalas'] = $this->sarToHalalas($package['price_sar']);
            $validated['packages'][$index]['material_cost_halalas'] = $this->sarToHalalas($package['material_cost_sar']);
            unset($validated['packages'][$index]['price_sar'], $validated['packages'][$index]['material_cost_sar']);
        }

        foreach ($validated['pricing_rules'] ?? [] as $index => $rule) {
            $validated['pricing_rules'][$index]['unit_price_halalas'] = $this->sarToHalalas($rule['unit_price_sar']);
            unset($validated['pricing_rules'][$index]['unit_price_sar']);
        }

        return $validated;
    }

    /**
     * @return list<string>
     */
    private function serviceFields(): array
    {
        return [
            'title',
            'slug',
            'category',
            'description',
            'pricing_type',
            'base_price_halalas',
            'vat_rate',
            'prices_include_vat',
            'materials_included',
            'minimum_billable_minutes',
            'default_workers',
            'default_duration_minutes',
            'default_material_cost_halalas',
            'material_policy',
            'included_materials',
            'extra_hour_rate_halalas',
            'overtime_policy',
            'allowed_frequencies',
            'required_certificates',
            'checklist_template',
            'is_active',
        ];
    }

    private function syncPackages(Service $service, array $packages): void
    {
        $service->packages()->delete();

        collect($packages)
            ->map(fn (array $package): array => [
                ...$package,
                'checklist_template' => $package['checklist_template'] ?? [],
            ])
            ->each(fn (array $package): ServicePackage => $service->packages()->create($package));
    }

    private function syncPricingRules(Service $service, array $pricingRules): void
    {
        $service->pricingRules()->delete();

        collect($pricingRules)
            ->map(fn (array $rule): array => [
                ...$rule,
                'applies_to' => $rule['applies_to'] ?? [],
            ])
            ->each(fn (array $rule): ServicePricingRule => $service->pricingRules()->create($rule));
    }

    private function uniqueSlug(string $title, ?Service $ignore = null): string
    {
        $base = Str::slug($title) ?: 'service';
        $slug = $base;
        $counter = 2;

        while (Service::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, list<array{key: string, label: string}>>
     */
    private function catalog(): array
    {
        return [
            'categories' => [
                ['key' => 'cleaning', 'label' => 'Cleaning'],
                ['key' => 'maintenance', 'label' => 'Maintenance'],
                ['key' => 'car_cleaning', 'label' => 'Car cleaning'],
                ['key' => 'pest_control', 'label' => 'Pest control'],
                ['key' => 'landscaping', 'label' => 'Landscaping'],
                ['key' => 'ac_service', 'label' => 'AC service'],
                ['key' => 'water_tank', 'label' => 'Water tank cleaning'],
                ['key' => 'glass_facade', 'label' => 'Glass and facade'],
                ['key' => 'move_in_out', 'label' => 'Move-in/out'],
                ['key' => 'disinfection', 'label' => 'Disinfection'],
            ],
            'pricingTypes' => [
                ['key' => 'fixed', 'label' => 'Fixed price'],
                ['key' => 'per_worker_month', 'label' => 'Per worker / month'],
                ['key' => 'per_visit', 'label' => 'Per visit'],
                ['key' => 'per_hour', 'label' => 'Per hour'],
                ['key' => 'per_day', 'label' => 'Per day'],
                ['key' => 'per_week', 'label' => 'Per week'],
                ['key' => 'per_month', 'label' => 'Per month'],
                ['key' => 'per_area', 'label' => 'Per area'],
                ['key' => 'per_unit', 'label' => 'Per unit'],
                ['key' => 'extra_hour', 'label' => 'Extra hour'],
                ['key' => 'material_charge', 'label' => 'Material charge'],
                ['key' => 'custom_quote', 'label' => 'Custom quote'],
            ],
            'materialPolicies' => [
                ['key' => 'customer_supplied', 'label' => 'Customer supplied'],
                ['key' => 'company_supplied_included', 'label' => 'Company supplied / included'],
                ['key' => 'company_supplied_billable', 'label' => 'Company supplied / billable'],
                ['key' => 'company_supplied_internal_cost', 'label' => 'Company supplied / internal cost only'],
            ],
            'overtimePolicies' => [
                ['key' => 'none', 'label' => 'No overtime'],
                ['key' => 'included', 'label' => 'Included in agreement'],
                ['key' => 'charge_after_planned_time', 'label' => 'Charge after planned time'],
                ['key' => 'requires_approval', 'label' => 'Requires approval'],
            ],
            'frequencies' => [
                ['key' => 'once', 'label' => 'Once'],
                ['key' => 'daily', 'label' => 'Daily'],
                ['key' => 'weekly', 'label' => 'Weekly'],
                ['key' => 'six_days_weekly', 'label' => 'Six days weekly'],
                ['key' => 'monthly', 'label' => 'Monthly'],
                ['key' => 'quarterly', 'label' => 'Quarterly'],
            ],
            'billingCycles' => [
                ['key' => 'one_time', 'label' => 'One-time'],
                ['key' => 'weekly', 'label' => 'Weekly'],
                ['key' => 'monthly', 'label' => 'Monthly'],
                ['key' => 'quarterly', 'label' => 'Quarterly'],
                ['key' => 'annual', 'label' => 'Annual'],
            ],
            'certificates' => [
                ['key' => 'infection_control', 'label' => 'Infection control'],
                ['key' => 'hvac_basic', 'label' => 'HVAC basic'],
                ['key' => 'height_safety', 'label' => 'Height safety'],
                ['key' => 'pest_control', 'label' => 'Pest control'],
                ['key' => 'driver_license', 'label' => 'Driver license'],
                ['key' => 'electrical_basic', 'label' => 'Electrical basic'],
            ],
        ];
    }

    /**
     * @param  list<array{key: string, label: string}>  $items
     * @return list<string>
     */
    private function catalogKeys(array $items): array
    {
        return array_column($items, 'key');
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function formSteps(): array
    {
        return [
            ['key' => 'basics', 'label' => 'Basics'],
            ['key' => 'pricing', 'label' => 'Pricing'],
            ['key' => 'packages', 'label' => 'Packages'],
            ['key' => 'pricing_rules', 'label' => 'Pricing rules'],
        ];
    }

    /**
     * @return list<string>
     */
    private function splitList(string $value): array
    {
        return collect(preg_split('/[,\n]/', $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeService(Service $service): array
    {
        return [
            ...$service->only($this->serviceFields()),
            'id' => $service->id,
            'edit_url' => "/app/services/{$service->id}/edit",
            'base_price_sar' => $this->halalasToSarString($service->base_price_halalas),
            'default_material_cost_sar' => $this->halalasToSarString($service->default_material_cost_halalas),
            'extra_hour_rate_sar' => $this->halalasToSarString($service->extra_hour_rate_halalas),
            'packages' => $service->packages->map(fn (ServicePackage $package): array => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'billing_cycle' => $package->billing_cycle,
                'visit_frequency' => $package->visit_frequency,
                'visits_per_week' => $package->visits_per_week,
                'hours_per_visit' => $package->hours_per_visit,
                'worker_count' => $package->worker_count,
                'duration_minutes' => $package->duration_minutes,
                'expected_labor_minutes' => $package->expected_labor_minutes,
                'material_cost_halalas' => $package->material_cost_halalas,
                'material_cost_sar' => $this->halalasToSarString($package->material_cost_halalas),
                'price_halalas' => $package->price_halalas,
                'price_sar' => $this->halalasToSarString($package->price_halalas),
                'vat_rate' => $package->vat_rate,
                'prices_include_vat' => $package->prices_include_vat,
                'checklist_template' => $package->checklist_template ?? [],
                'is_active' => $package->is_active,
            ])->values(),
            'pricing_rules' => $service->pricingRules->map(fn (ServicePricingRule $rule): array => [
                'id' => $rule->id,
                'name' => $rule->name,
                'pricing_type' => $rule->pricing_type,
                'unit_label' => $rule->unit_label,
                'unit_price_halalas' => $rule->unit_price_halalas,
                'unit_price_sar' => $this->halalasToSarString($rule->unit_price_halalas),
                'minimum_quantity' => $rule->minimum_quantity,
                'maximum_quantity' => $rule->maximum_quantity,
                'vat_rate' => $rule->vat_rate,
                'prices_include_vat' => $rule->prices_include_vat,
                'applies_to' => $rule->applies_to ?? [],
                'is_active' => $rule->is_active,
            ])->values(),
            'cost_items' => $service->serviceCostItems->map(fn (ServiceCostItem $row): array => $this->serializeServiceCostItem($row))->values(),
            'cost_breakdown' => $this->serviceCostBreakdown($service),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeServiceCostItem(ServiceCostItem $row): array
    {
        return [
            'id' => $row->id,
            'cost_item_id' => $row->cost_item_id,
            'name' => $row->costItem?->name,
            'item_type' => $row->costItem?->item_type,
            'unit' => $row->costItem?->unit,
            'quantity' => $row->quantity,
            'cost_per_use_halalas' => $row->cost_per_use_halalas,
            'cost_per_use_sar' => $this->halalasToSarString($row->cost_per_use_halalas),
            'line_total_halalas' => $row->line_total_halalas,
            'line_total_sar' => $this->halalasToSarString($row->line_total_halalas),
            'charge_customer' => $row->charge_customer,
            'notes' => $row->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceCostBreakdown(Service $service): array
    {
        $service->loadMissing('serviceCostItems.costItem');
        $total = (int) $service->serviceCostItems->sum('line_total_halalas');

        return [
            'total_cost_halalas' => $total,
            'total_cost_sar' => $this->halalasToSarString($total),
            'gross_profit_estimate_halalas' => $service->base_price_halalas - $total,
            'gross_profit_estimate_sar' => $this->halalasToSarString($service->base_price_halalas - $total),
            'items_count' => $service->serviceCostItems->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function changes(array $oldValues, array $newValues): array
    {
        $changes = [];

        foreach ($newValues as $key => $newValue) {
            if (($oldValues[$key] ?? null) !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValues[$key] ?? null,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }
}
