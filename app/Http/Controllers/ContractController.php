<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSarMoney;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Services\Support\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    use HandlesSarMoney;

    public function __construct(private readonly NumberGenerator $numbers) {}

    public function index(): Response
    {
        return Inertia::render('Contracts/Index', [
            'contracts' => Contract::query()
                ->with([
                    'customer',
                    'site',
                    'service',
                    'servicePackage',
                    'addendums' => fn ($query) => $query->orderBy('number'),
                    'assignments.worker',
                    'assignments.service',
                    'invoices',
                ])
                ->withCount(['assignments', 'invoices'])
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
                ->orderBy('ends_on')
                ->get()
                ->map(fn (Contract $contract): array => $this->serializeContract($contract)),
            'customers' => Customer::query()
                ->with(['sites' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'status' => $customer->status,
                    'sites' => $customer->sites->map(fn (CustomerSite $site): array => [
                        'id' => $site->id,
                        'name' => $site->name,
                        'city' => $site->city,
                        'district' => $site->district,
                    ])->values(),
                ]),
            'servicePackages' => ServicePackage::query()
                ->with('service')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (ServicePackage $package): array => [
                    'id' => $package->id,
                    'service_id' => $package->service_id,
                    'service_title' => $package->service?->title,
                    'name' => $package->name,
                    'billing_cycle' => $package->billing_cycle,
                    'visit_frequency' => $package->visit_frequency,
                    'worker_count' => $package->worker_count,
                    'visits_per_week' => $package->visits_per_week,
                    'hours_per_visit' => $package->hours_per_visit,
                    'expected_labor_minutes' => $package->expected_labor_minutes,
                    'material_cost_halalas' => $package->material_cost_halalas,
                    'price_halalas' => $package->price_halalas,
                    'vat_rate' => $package->vat_rate,
                    'prices_include_vat' => $package->prices_include_vat,
                ]),
            'catalog' => $this->catalog(),
            'metrics' => [
                'total' => Contract::query()->count(),
                'active' => Contract::query()->where('status', 'active')->count(),
                'draft' => Contract::query()->where('status', 'draft')->count(),
                'monthlyValue' => Contract::query()->sum('monthly_fee_halalas'),
            ],
            'createUrl' => '/app/contracts/create',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Contracts/Form', [
            'mode' => 'create',
            'contract' => null,
            'customers' => $this->customerOptions(),
            'serviceOptions' => $this->serviceOptions(),
            'servicePackages' => $this->servicePackageOptions(),
            'catalog' => $this->catalog(),
            'steps' => $this->formSteps(),
            'submitUrl' => '/app/contracts',
            'backUrl' => '/app/contracts',
        ]);
    }

    public function edit(Contract $contract): Response
    {
        $contract->load([
            'customer',
            'site',
            'service',
            'servicePackage',
            'addendums' => fn ($query) => $query->orderBy('number'),
            'assignments.worker',
            'assignments.service',
            'invoices',
        ])->loadCount(['assignments', 'invoices']);

        return Inertia::render('Contracts/Form', [
            'mode' => 'edit',
            'contract' => $this->serializeContract($contract),
            'customers' => $this->customerOptions(),
            'serviceOptions' => $this->serviceOptions(),
            'servicePackages' => $this->servicePackageOptions(),
            'catalog' => $this->catalog(),
            'steps' => $this->formSteps(),
            'submitUrl' => "/app/contracts/{$contract->id}",
            'backUrl' => '/app/contracts',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateContract($request);
        $addendums = Arr::pull($validated, 'addendums', []);

        if (blank($validated['reference'] ?? null)) {
            $validated['reference'] = $this->numbers->next('CON', Contract::class, 'reference');
        }

        $contract = DB::transaction(function () use ($request, $validated, $addendums): Contract {
            $contract = Contract::create($validated);
            $this->syncAddendums($contract, $addendums);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'contract.created',
                'auditable_type' => Contract::class,
                'auditable_id' => $contract->id,
                'changes' => [
                    'created' => [
                        'old' => null,
                        'new' => $this->serializeContract($contract->fresh(['customer', 'site', 'service', 'servicePackage', 'addendums', 'assignments', 'invoices'])),
                    ],
                ],
            ]);

            return $contract;
        });

        return redirect('/app/contracts')->with('success', __('Contract :contract created.', ['contract' => $contract->reference]));
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $validated = $this->validateContract($request, $contract);
        $addendums = Arr::pull($validated, 'addendums', []);
        $oldValues = $contract->only($this->contractFields());
        $oldAddendumCount = $contract->addendums()->count();

        DB::transaction(function () use ($request, $contract, $validated, $addendums, $oldValues, $oldAddendumCount): void {
            $contract->fill($validated)->save();
            $this->syncAddendums($contract, $addendums);

            $contract->refresh();
            $changes = $this->changes($oldValues, $contract->only($this->contractFields()));
            $newAddendumCount = $contract->addendums()->count();

            if ($oldAddendumCount !== $newAddendumCount) {
                $changes['addendums_count'] = [
                    'old' => $oldAddendumCount,
                    'new' => $newAddendumCount,
                ];
            }

            if ($changes !== []) {
                AuditLog::create([
                    'user_id' => $request->user()?->id,
                    'action' => 'contract.updated',
                    'auditable_type' => Contract::class,
                    'auditable_id' => $contract->id,
                    'changes' => $changes,
                ]);
            }
        });

        return redirect('/app/contracts')->with('success', __('Contract :contract updated.', ['contract' => $contract->reference]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateContract(Request $request, ?Contract $contract = null): array
    {
        $catalog = $this->catalog();

        $this->mergeSarFromHalalas($request, 'monthly_fee_sar', 'monthly_fee_halalas');
        $this->mergeSarFromHalalas($request, 'estimated_material_cost_sar', 'estimated_material_cost_halalas');
        $this->mergeSarFromHalalas($request, 'extra_hour_rate_sar', 'extra_hour_rate_halalas');

        $request->merge([
            'pricing_model' => $request->input('pricing_model', 'package'),
            'agreed_workers' => $request->input('agreed_workers', 1),
            'planned_weekly_minutes' => $request->input('planned_weekly_minutes', $this->plannedWeeklyMinutesFromRequest($request)),
            'included_materials' => $request->input('included_materials', true),
            'material_policy' => $request->input('material_policy', 'company_supplied_included'),
            'estimated_material_cost_sar' => $request->input('estimated_material_cost_sar', '0.00'),
            'extra_hour_rate_sar' => $request->input('extra_hour_rate_sar', '0.00'),
            'overtime_policy' => $request->input('overtime_policy', 'none'),
            'service_scope' => $request->input('service_scope', []),
            'terms_and_conditions' => $request->input('terms_and_conditions'),
        ]);

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'customer_site_id' => ['required', 'integer', 'exists:customer_sites,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'service_package_id' => ['nullable', 'integer', 'exists:service_packages,id'],
            'reference' => ['nullable', 'string', 'max:80', Rule::unique('contracts', 'reference')->ignore($contract?->id)],
            'status' => ['required', Rule::in($this->catalogKeys($catalog['statuses']))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'monthly_fee_sar' => $this->sarMoneyRules(),
            'vat_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'prices_include_vat' => ['required', 'boolean'],
            'pricing_model' => ['required', Rule::in($this->catalogKeys($catalog['pricingModels']))],
            'agreed_workers' => ['required', 'integer', 'min:1', 'max:200'],
            'visits_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'hours_per_visit' => ['nullable', 'numeric', 'min:0.25', 'max:24', 'regex:/^\d+(\.\d{1,2})?$/'],
            'planned_weekly_minutes' => ['required', 'integer', 'min:0', 'max:288000'],
            'included_materials' => ['required', 'boolean'],
            'material_policy' => ['required', Rule::in($this->catalogKeys($catalog['materialPolicies']))],
            'estimated_material_cost_sar' => $this->sarMoneyRules(),
            'extra_hour_rate_sar' => $this->sarMoneyRules(),
            'overtime_policy' => ['required', Rule::in($this->catalogKeys($catalog['overtimePolicies']))],
            'service_scope' => ['array', 'max:30'],
            'service_scope.*.area' => ['required', 'string', 'max:160'],
            'service_scope.*.tasks' => ['nullable', 'string', 'max:1000'],
            'terms_and_conditions' => ['nullable', 'string', 'max:5000'],
            'payment_plan' => ['array', 'min:1', 'max:12'],
            'payment_plan.*.label' => ['nullable', 'string', 'max:80'],
            'payment_plan.*.day' => ['required', 'integer', 'min:1', 'max:31'],
            'payment_plan.*.percent' => ['required', 'integer', 'min:1', 'max:100'],
            'notice_days' => ['required', 'integer', 'min:0', 'max:365'],
            'auto_renews' => ['required', 'boolean'],
            'billing_cycle' => ['required', Rule::in($this->catalogKeys($catalog['billingCycles']))],
            'special_terms' => ['nullable', 'string', 'max:2000'],
            'addendums' => ['array', 'max:20'],
            'addendums.*.id' => ['nullable', 'integer', 'exists:contract_addendums,id'],
            'addendums.*.number' => ['required', 'integer', 'min:1', 'max:999'],
            'addendums.*.title' => ['required', 'string', 'max:180'],
            'addendums.*.summary' => ['required', 'string', 'max:2000'],
            'addendums.*.effective_on' => ['required', 'date'],
        ]);

        $siteBelongsToCustomer = CustomerSite::query()
            ->whereKey($validated['customer_site_id'])
            ->where('customer_id', $validated['customer_id'])
            ->exists();

        if (! $siteBelongsToCustomer) {
            throw ValidationException::withMessages([
                'customer_site_id' => __('The selected site does not belong to this customer.'),
                ...$this->paymentPlanErrors($validated),
            ]);
        }

        if (filled($validated['service_package_id'])) {
            $packageBelongsToService = ServicePackage::query()
                ->whereKey($validated['service_package_id'])
                ->where('service_id', $validated['service_id'])
                ->exists();

            if (! $packageBelongsToService) {
                throw ValidationException::withMessages([
                    'service_package_id' => __('The selected package does not belong to this service.'),
                    ...$this->paymentPlanErrors($validated),
                ]);
            }
        } elseif ($validated['pricing_model'] === 'package') {
            throw ValidationException::withMessages([
                'pricing_model' => __('Choose a custom pricing model when no package is selected.'),
                ...$this->paymentPlanErrors($validated),
            ]);
        }

        $paymentPlanErrors = $this->paymentPlanErrors($validated);

        if ($paymentPlanErrors !== []) {
            throw ValidationException::withMessages($paymentPlanErrors);
        }

        if ($contract) {
            $addendumIds = collect($validated['addendums'] ?? [])
                ->pluck('id')
                ->filter()
                ->values()
                ->all();

            if ($addendumIds !== [] && $contract->addendums()->whereKey($addendumIds)->count() !== count($addendumIds)) {
                throw ValidationException::withMessages([
                    'addendums' => __('One or more addendums do not belong to this contract.'),
                ]);
            }
        }

        $validated['payment_plan'] = $this->normalizePaymentPlan($validated['payment_plan'] ?? []);
        $validated['monthly_fee_halalas'] = $this->sarToHalalas($validated['monthly_fee_sar']);
        $validated['estimated_material_cost_halalas'] = $this->sarToHalalas($validated['estimated_material_cost_sar']);
        $validated['extra_hour_rate_halalas'] = $this->sarToHalalas($validated['extra_hour_rate_sar']);
        $validated['service_scope'] = $this->normalizeServiceScope($validated['service_scope'] ?? []);
        unset($validated['monthly_fee_sar'], $validated['estimated_material_cost_sar'], $validated['extra_hour_rate_sar']);

        return $validated;
    }

    private function plannedWeeklyMinutesFromRequest(Request $request): int
    {
        $workers = (int) $request->input('agreed_workers', 1);
        $visits = (int) $request->input('visits_per_week', 0);
        $hours = (float) $request->input('hours_per_visit', 0);

        return (int) round($workers * $visits * $hours * 60);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function paymentPlanErrors(array $validated): array
    {
        $total = collect($validated['payment_plan'] ?? [])->sum(fn (array $item): int => (int) ($item['percent'] ?? 0));

        if ($total === 100) {
            return [];
        }

        return [
            'payment_plan' => __('Payment plan percentages must total 100%.'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $paymentPlan
     * @return array<int, array{label: string, day: int, percent: int}>
     */
    private function normalizePaymentPlan(array $paymentPlan): array
    {
        return collect($paymentPlan)
            ->map(fn (array $item): array => [
                'label' => filled($item['label'] ?? null) ? (string) $item['label'] : __('Installment'),
                'day' => (int) $item['day'],
                'percent' => (int) $item['percent'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $scope
     * @return array<int, array{area: string, tasks: string}>
     */
    private function normalizeServiceScope(array $scope): array
    {
        return collect($scope)
            ->map(fn (array $item): array => [
                'area' => trim((string) ($item['area'] ?? '')),
                'tasks' => trim((string) ($item['tasks'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['area'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $addendums
     */
    private function syncAddendums(Contract $contract, array $addendums): void
    {
        $keptIds = collect();

        foreach ($addendums as $addendum) {
            $addendumId = $addendum['id'] ?? null;
            unset($addendum['id']);

            if ($addendumId) {
                $contract->addendums()->whereKey($addendumId)->update($addendum);
                $keptIds->push((int) $addendumId);

                continue;
            }

            $created = $contract->addendums()->create($addendum);
            $keptIds->push($created->id);
        }

        $contract->addendums()
            ->when($keptIds->isNotEmpty(), fn ($query) => $query->whereKeyNot($keptIds->all()))
            ->delete();
    }

    /**
     * @return list<string>
     */
    private function contractFields(): array
    {
        return [
            'customer_id',
            'customer_site_id',
            'service_id',
            'service_package_id',
            'reference',
            'status',
            'starts_on',
            'ends_on',
            'monthly_fee_halalas',
            'vat_rate',
            'prices_include_vat',
            'pricing_model',
            'agreed_workers',
            'visits_per_week',
            'hours_per_visit',
            'planned_weekly_minutes',
            'included_materials',
            'material_policy',
            'estimated_material_cost_halalas',
            'extra_hour_rate_halalas',
            'overtime_policy',
            'service_scope',
            'terms_and_conditions',
            'payment_plan',
            'billing_cycle',
            'notice_days',
            'auto_renews',
            'special_terms',
        ];
    }

    /**
     * @return array<string, list<array{key: string, label: string}>>
     */
    private function catalog(): array
    {
        return [
            'statuses' => [
                ['key' => 'draft', 'label' => 'Draft'],
                ['key' => 'active', 'label' => 'Active'],
                ['key' => 'paused', 'label' => 'Paused'],
                ['key' => 'expired', 'label' => 'Expired'],
                ['key' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'billingCycles' => [
                ['key' => 'monthly', 'label' => 'Monthly'],
                ['key' => 'quarterly', 'label' => 'Quarterly'],
                ['key' => 'annual', 'label' => 'Annual'],
                ['key' => 'one_time', 'label' => 'One-time'],
            ],
            'pricingModels' => [
                ['key' => 'package', 'label' => 'Package'],
                ['key' => 'hourly', 'label' => 'Hourly'],
                ['key' => 'daily', 'label' => 'Daily'],
                ['key' => 'weekly', 'label' => 'Weekly'],
                ['key' => 'monthly', 'label' => 'Monthly'],
                ['key' => 'custom_package', 'label' => 'Custom package'],
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
     * @return list<array<string, mixed>>
     */
    private function customerOptions(): array
    {
        return Customer::query()
            ->with(['sites' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'status' => $customer->status,
                'sites' => $customer->sites->map(fn (CustomerSite $site): array => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'city' => $site->city,
                    'district' => $site->district,
                ])->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviceOptions(): array
    {
        return Service::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get()
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'title' => $service->title,
                'slug' => $service->slug,
                'category' => $service->category,
                'description' => $service->description,
                'pricing_type' => $service->pricing_type,
                'base_price_halalas' => $service->base_price_halalas,
                'base_price_sar' => $this->halalasToSarString($service->base_price_halalas),
                'vat_rate' => $service->vat_rate,
                'prices_include_vat' => $service->prices_include_vat,
                'materials_included' => $service->materials_included,
                'minimum_billable_minutes' => $service->minimum_billable_minutes,
                'default_workers' => $service->default_workers,
                'default_duration_minutes' => $service->default_duration_minutes,
                'default_material_cost_halalas' => $service->default_material_cost_halalas,
                'default_material_cost_sar' => $this->halalasToSarString($service->default_material_cost_halalas),
                'material_policy' => $service->material_policy,
                'included_materials' => $service->included_materials ?? [],
                'extra_hour_rate_halalas' => $service->extra_hour_rate_halalas,
                'extra_hour_rate_sar' => $this->halalasToSarString($service->extra_hour_rate_halalas),
                'overtime_policy' => $service->overtime_policy,
                'allowed_frequencies' => $service->allowed_frequencies ?? [],
                'required_certificates' => $service->required_certificates ?? [],
                'checklist_template' => $service->checklist_template ?? [],
                'is_active' => $service->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function servicePackageOptions(): array
    {
        return ServicePackage::query()
            ->with('service')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (ServicePackage $package): array => [
                'id' => $package->id,
                'service_id' => $package->service_id,
                'service_title' => $package->service?->title,
                'name' => $package->name,
                'billing_cycle' => $package->billing_cycle,
                'visit_frequency' => $package->visit_frequency,
                'worker_count' => $package->worker_count,
                'visits_per_week' => $package->visits_per_week,
                'hours_per_visit' => $package->hours_per_visit,
                'expected_labor_minutes' => $package->expected_labor_minutes,
                'material_cost_halalas' => $package->material_cost_halalas,
                'material_cost_sar' => $this->halalasToSarString($package->material_cost_halalas),
                'price_halalas' => $package->price_halalas,
                'price_sar' => $this->halalasToSarString($package->price_halalas),
                'vat_rate' => $package->vat_rate,
                'prices_include_vat' => $package->prices_include_vat,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function formSteps(): array
    {
        return [
            ['key' => 'customer_service', 'label' => 'Customer & service'],
            ['key' => 'terms', 'label' => 'Terms'],
            ['key' => 'pricing_scope', 'label' => 'Pricing & scope'],
            ['key' => 'payment_plan', 'label' => 'Payment plan'],
            ['key' => 'addendums', 'label' => 'Addendums'],
            ['key' => 'review', 'label' => 'Review'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContract(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'customer_id' => $contract->customer_id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $contract->service_id,
            'service_package_id' => $contract->service_package_id,
            'reference' => $contract->reference,
            'status' => $contract->status,
            'starts_on' => $contract->starts_on?->toDateString(),
            'ends_on' => $contract->ends_on?->toDateString(),
            'monthly_fee_halalas' => $contract->monthly_fee_halalas,
            'monthly_fee_sar' => $this->halalasToSarString($contract->monthly_fee_halalas),
            'vat_rate' => $contract->vat_rate,
            'prices_include_vat' => $contract->prices_include_vat,
            'pricing_model' => $contract->pricing_model,
            'agreed_workers' => $contract->agreed_workers,
            'visits_per_week' => $contract->visits_per_week,
            'hours_per_visit' => $contract->hours_per_visit,
            'planned_weekly_minutes' => $contract->planned_weekly_minutes,
            'included_materials' => $contract->included_materials,
            'material_policy' => $contract->material_policy,
            'estimated_material_cost_halalas' => $contract->estimated_material_cost_halalas,
            'estimated_material_cost_sar' => $this->halalasToSarString($contract->estimated_material_cost_halalas),
            'extra_hour_rate_halalas' => $contract->extra_hour_rate_halalas,
            'extra_hour_rate_sar' => $this->halalasToSarString($contract->extra_hour_rate_halalas),
            'overtime_policy' => $contract->overtime_policy,
            'service_scope' => $contract->service_scope ?? [],
            'terms_and_conditions' => $contract->terms_and_conditions,
            'payment_plan' => $this->paymentPlanWithAmounts($contract),
            'billing_cycle' => $contract->billing_cycle,
            'notice_days' => $contract->notice_days,
            'auto_renews' => $contract->auto_renews,
            'special_terms' => $contract->special_terms,
            'edit_url' => "/app/contracts/{$contract->id}/edit",
            'assignments_count' => $contract->assignments_count ?? $contract->assignments()->count(),
            'invoices_count' => $contract->invoices_count ?? $contract->invoices()->count(),
            'customer' => [
                'id' => $contract->customer?->id,
                'name' => $contract->customer?->name,
            ],
            'site' => [
                'id' => $contract->site?->id,
                'name' => $contract->site?->name,
                'city' => $contract->site?->city,
                'district' => $contract->site?->district,
            ],
            'service' => $contract->service ? [
                'id' => $contract->service->id,
                'title' => $contract->service->title,
            ] : null,
            'service_package' => $contract->servicePackage ? [
                'id' => $contract->servicePackage->id,
                'name' => $contract->servicePackage->name,
            ] : null,
            'addendums' => $contract->addendums->map(fn (ContractAddendum $addendum): array => [
                'id' => $addendum->id,
                'number' => $addendum->number,
                'title' => $addendum->title,
                'summary' => $addendum->summary,
                'effective_on' => $addendum->effective_on?->toDateString(),
            ])->values(),
        ];
    }

    /**
     * @return array<int, array{label: string, day: int, percent: int, amount_halalas: int, due_on: string|null}>
     */
    private function paymentPlanWithAmounts(Contract $contract): array
    {
        return collect($contract->payment_plan ?? [])
            ->map(function (array $item) use ($contract): array {
                $day = (int) ($item['day'] ?? 1);
                $percent = (int) ($item['percent'] ?? 0);

                return [
                    'label' => (string) ($item['label'] ?? __('Installment')),
                    'day' => $day,
                    'percent' => $percent,
                    'amount_halalas' => (int) round($contract->monthly_fee_halalas * ($percent / 100)),
                    'due_on' => $this->dueDateForDay($contract->starts_on, $day),
                ];
            })
            ->values()
            ->all();
    }

    private function dueDateForDay(?Carbon $startsOn, int $day): ?string
    {
        if (! $startsOn) {
            return null;
        }

        $dueOn = $startsOn->copy()->day(1);
        $day = min($day, $dueOn->daysInMonth);

        return $dueOn->day($day)->toDateString();
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
