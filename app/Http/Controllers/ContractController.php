<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSarMoney;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\BookingRequest;
use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\ContractDecision;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Visit;
use App\Models\Worker;
use App\Services\Operations\ScheduleGenerator;
use App\Services\Support\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
                    'sla_kpi_template' => $package->sla_kpi_template ?? [],
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

    public function create(Request $request): Response
    {
        $bookingRequest = $this->approvedBookingRequestFromRequest($request);

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
            'initialForm' => $bookingRequest ? $this->bookingRequestInitialForm($bookingRequest) : null,
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

    public function show(Request $request, Contract $contract): Response
    {
        $initialPanel = in_array($request->query('panel'), ['overview', 'assignments', 'sla', 'finance', 'acceptance', 'scope'], true)
            ? (string) $request->query('panel')
            : 'overview';

        $contract->load([
            'customer',
            'site',
            'service',
            'servicePackage',
            'addendums' => fn ($query) => $query->orderBy('number'),
            'assignments' => fn ($query) => $query
                ->with(['worker', 'site.customer', 'service'])
                ->orderBy('weekday')
                ->orderBy('starts_at'),
            'invoices' => fn ($query) => $query
                ->with([
                    'payments' => fn ($paymentQuery) => $paymentQuery->orderByDesc('received_at'),
                    'creditNotes',
                ])
                ->orderByDesc('due_date'),
            'contractDecisions' => fn ($query) => $query
                ->with(['customer', 'reviewedBy'])
                ->latest(),
        ])->loadCount(['assignments', 'invoices']);

        return Inertia::render('Contracts/Show', [
            'contract' => $this->serializeContract($contract),
            'assignments' => $contract->assignments->map(fn (Assignment $assignment): array => $this->serializeAssignment($assignment))->values(),
            'assignmentAssessment' => $this->assignmentAssessment($contract),
            'slaReports' => $this->slaReports($request, $contract),
            'invoices' => $contract->invoices->map(fn (Invoice $invoice): array => $this->serializeInvoice($invoice))->values(),
            'paymentPlan' => $this->paymentPlanSettlement($contract),
            'finance' => $this->financeSummary($contract),
            'contractDecisions' => $contract->contractDecisions->map(fn (ContractDecision $decision): array => $this->serializeContractDecision($decision))->values(),
            'workerOptions' => $this->workerOptions(),
            'weekdayOptions' => $this->weekdayOptions(),
            'assignmentStatuses' => $this->assignmentStatuses(),
            'assignmentStoreUrl' => "/app/contracts/{$contract->id}/assignments",
            'canManageAssignments' => (bool) $request->user()?->hasPermission('manage_operations'),
            'backUrl' => '/app/contracts',
            'initialPanel' => $initialPanel,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $bookingRequestId = $request->integer('booking_request_id') ?: null;
        $validated = $this->validateContract($request);
        $addendums = Arr::pull($validated, 'addendums', []);
        $bookingRequest = $bookingRequestId
            ? BookingRequest::query()->whereKey($bookingRequestId)->where('status', 'approved')->firstOrFail()
            : null;

        if ($bookingRequest) {
            $this->validateBookingRequestMatchesContract($bookingRequest, $validated);
        }

        if (blank($validated['reference'] ?? null)) {
            $validated['reference'] = $this->numbers->next('CON', Contract::class, 'reference');
        }

        $contract = DB::transaction(function () use ($request, $validated, $addendums, $bookingRequest): Contract {
            $contract = Contract::create($validated);
            $this->syncAddendums($contract, $addendums);

            if ($bookingRequest) {
                $bookingRequest->forceFill([
                    'status' => 'converted',
                    'contract_id' => $contract->id,
                ])->save();
            }

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

    private function approvedBookingRequestFromRequest(Request $request): ?BookingRequest
    {
        $bookingRequestId = $request->integer('booking_request_id');

        if (! $bookingRequestId) {
            return null;
        }

        $bookingRequest = BookingRequest::query()
            ->with(['customer', 'site', 'service', 'servicePackage'])
            ->findOrFail($bookingRequestId);

        abort_unless($bookingRequest->status === 'approved', 404);

        return $bookingRequest;
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingRequestInitialForm(BookingRequest $bookingRequest): array
    {
        $service = $bookingRequest->service;
        $package = $bookingRequest->servicePackage;
        $visitsPerWeek = max(1, (int) ($package?->visits_per_week ?? 1));
        $hoursPerVisit = $this->bookingHoursPerVisit($bookingRequest, $package);
        $plannedWeeklyMinutes = (int) ($package?->expected_labor_minutes ?: round($bookingRequest->worker_count * $visitsPerWeek * (float) $hoursPerVisit * 60));

        return [
            'booking_request_id' => $bookingRequest->id,
            'customer_id' => $bookingRequest->customer_id,
            'customer_site_id' => $bookingRequest->customer_site_id,
            'service_id' => $bookingRequest->service_id,
            'service_package_id' => $bookingRequest->service_package_id,
            'pricing_source' => $package ? 'package' : 'custom',
            'status' => 'draft',
            'starts_on' => $bookingRequest->requested_for?->toDateString(),
            'monthly_fee_sar' => $this->halalasToSarString($package?->price_halalas ?? $service->base_price_halalas),
            'vat_rate' => $package?->vat_rate ?? $service->vat_rate,
            'prices_include_vat' => $package?->prices_include_vat ?? $service->prices_include_vat,
            'pricing_model' => $package ? 'package' : $this->pricingModelFromService($service),
            'agreed_workers' => max(1, (int) $bookingRequest->worker_count),
            'visits_per_week' => $visitsPerWeek,
            'hours_per_visit' => $hoursPerVisit,
            'planned_weekly_minutes' => $plannedWeeklyMinutes,
            'included_materials' => $service->materials_included,
            'material_policy' => $service->material_policy,
            'estimated_material_cost_sar' => $this->halalasToSarString($package?->material_cost_halalas ?? $service->default_material_cost_halalas ?? 0),
            'extra_hour_rate_sar' => $this->halalasToSarString($service->extra_hour_rate_halalas ?? 0),
            'overtime_policy' => $service->overtime_policy,
            'service_scope' => $this->initialScopeFromService($service),
            'sla_kpi_template' => $package?->sla_kpi_template ?: ($service->sla_kpi_template ?? []),
            'billing_cycle' => $package?->billing_cycle ?? 'one_time',
            'special_terms' => collect([
                "Created from customer booking request #{$bookingRequest->id}.",
                "Requested slot: {$bookingRequest->requested_for?->toDateString()} {$this->time($bookingRequest->starts_at)} - {$this->time($bookingRequest->ends_at)}.",
                $bookingRequest->customer_note ? "Customer note: {$bookingRequest->customer_note}" : null,
            ])->filter()->implode(PHP_EOL),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateBookingRequestMatchesContract(BookingRequest $bookingRequest, array $validated): void
    {
        $matches = (int) $validated['customer_id'] === $bookingRequest->customer_id
            && (int) $validated['customer_site_id'] === $bookingRequest->customer_site_id
            && (int) $validated['service_id'] === $bookingRequest->service_id
            && (int) ($validated['service_package_id'] ?? 0) === (int) ($bookingRequest->service_package_id ?? 0);

        if (! $matches) {
            throw ValidationException::withMessages([
                'booking_request_id' => __('The contract details no longer match the approved booking request.'),
            ]);
        }
    }

    private function bookingHoursPerVisit(BookingRequest $bookingRequest, ?ServicePackage $package): string
    {
        if ($package?->hours_per_visit !== null) {
            return number_format((float) $package->hours_per_visit, 2, '.', '');
        }

        return number_format(max(0.5, $bookingRequest->duration_minutes / 60), 2, '.', '');
    }

    private function pricingModelFromService(Service $service): string
    {
        return [
            'per_hour' => 'hourly',
            'per_day' => 'daily',
            'per_week' => 'weekly',
            'per_month' => 'monthly',
            'per_worker_month' => 'monthly',
        ][$service->pricing_type] ?? 'custom_quote';
    }

    /**
     * @return list<array{area: string, tasks: string}>
     */
    private function initialScopeFromService(Service $service): array
    {
        $tasks = collect($service->checklist_template ?? [])
            ->pluck('label')
            ->filter(fn (mixed $label): bool => filled($label))
            ->implode(', ');

        return [[
            'area' => __('General service'),
            'tasks' => $tasks,
        ]];
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

    public function storeAssignment(Request $request, Contract $contract, ScheduleGenerator $schedule): RedirectResponse
    {
        $validated = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'share_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::in(array_column($this->assignmentStatuses(), 'key'))],
            'team_role' => ['nullable', Rule::in(['main', 'support'])],
            'task_instructions' => ['array', 'max:30'],
            'task_instructions.*.label' => ['required', 'string', 'max:180'],
            'task_instructions.*.is_required' => ['nullable', 'boolean'],
        ]);
        $validated['team_role'] = $validated['team_role'] ?? 'main';
        $validated['task_instructions'] = $this->normalizeTaskInstructions($validated['task_instructions'] ?? []);

        $assignment = $schedule->createAssignment([
            ...$validated,
            'contract_id' => $contract->id,
            'customer_site_id' => $contract->customer_site_id,
            'service_id' => $contract->service_id,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'assignment.created_from_contract',
            'auditable_type' => Assignment::class,
            'auditable_id' => $assignment->id,
            'changes' => [
                'created' => [
                    'old' => null,
                    'new' => $this->serializeAssignment($assignment->load(['worker', 'site.customer', 'service', 'contract.customer'])),
                ],
            ],
        ]);

        return back()->with('success', __('Assignment created.'));
    }

    public function reviewContractDecision(Request $request, ContractDecision $contractDecision): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $contractDecision->forceFill([
            'status' => 'reviewed',
            'admin_note' => $validated['admin_note'] ?? $contractDecision->admin_note,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ])->save();

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'contract_decision.reviewed',
            'auditable_type' => ContractDecision::class,
            'auditable_id' => $contractDecision->id,
            'changes' => [
                'reviewed' => [
                    'old' => null,
                    'new' => [
                        'status' => 'reviewed',
                        'contract_id' => $contractDecision->contract_id,
                    ],
                ],
            ],
        ]);

        return redirect("/app/contracts/{$contractDecision->contract_id}?panel=acceptance")
            ->with('success', __('Contract customer decision reviewed.'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     * @return list<array{label: string, is_required: bool}>
     */
    private function normalizeTaskInstructions(array $tasks): array
    {
        return collect($tasks)
            ->map(function (array $task): ?array {
                $label = trim((string) ($task['label'] ?? ''));

                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'is_required' => (bool) ($task['is_required'] ?? true),
                ];
            })
            ->filter()
            ->values()
            ->all();
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
            'sla_kpi_template' => $request->input('sla_kpi_template', []),
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
            'sla_kpi_template' => ['array', 'max:20'],
            'sla_kpi_template.*.code' => ['nullable', 'string', 'max:80'],
            'sla_kpi_template.*.label' => ['required', 'string', 'max:160'],
            'sla_kpi_template.*.target' => ['required', 'numeric', 'min:0', 'max:100000'],
            'sla_kpi_template.*.unit' => ['nullable', 'string', 'max:40'],
            'sla_kpi_template.*.weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sla_kpi_template.*.direction' => ['nullable', Rule::in(['at_least', 'at_most'])],
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
        $validated['sla_kpi_template'] = $this->normalizeSlaKpiTemplate($validated['sla_kpi_template'] ?? []);

        if ($validated['sla_kpi_template'] === []) {
            $validated['sla_kpi_template'] = $this->inheritedSlaKpiTemplate(
                (int) $validated['service_id'],
                filled($validated['service_package_id'] ?? null) ? (int) $validated['service_package_id'] : null
            );
        }

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
     * @param  array<int, array<string, mixed>>  $template
     * @return array<int, array{code: string, label: string, target: int|float, unit: string, weight: int, direction: string}>
     */
    private function normalizeSlaKpiTemplate(array $template): array
    {
        return collect($template)
            ->map(function (array $item): ?array {
                $label = trim((string) ($item['label'] ?? ''));

                if ($label === '') {
                    return null;
                }

                $code = trim((string) ($item['code'] ?? ''));
                $target = (float) ($item['target'] ?? 0);

                return [
                    'code' => $code !== '' ? str($code)->snake()->toString() : str($label)->snake()->limit(80, '')->toString(),
                    'label' => $label,
                    'target' => floor($target) === $target ? (int) $target : round($target, 2),
                    'unit' => trim((string) ($item['unit'] ?? 'percent')) ?: 'percent',
                    'weight' => (int) ($item['weight'] ?? 0),
                    'direction' => in_array($item['direction'] ?? null, ['at_least', 'at_most'], true)
                        ? (string) $item['direction']
                        : $this->defaultKpiDirection($code),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function defaultKpiDirection(string $code): string
    {
        return str($code)->contains(['issue', 'missed', 'complaint', 'late'])
            ? 'at_most'
            : 'at_least';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function inheritedSlaKpiTemplate(int $serviceId, ?int $packageId): array
    {
        if ($packageId) {
            $packageTemplate = ServicePackage::query()
                ->whereKey($packageId)
                ->value('sla_kpi_template');

            $normalizedPackage = $this->normalizeStoredSlaKpiTemplate($packageTemplate);

            if ($normalizedPackage !== []) {
                return $normalizedPackage;
            }
        }

        return $this->normalizeStoredSlaKpiTemplate(
            Service::query()->whereKey($serviceId)->value('sla_kpi_template')
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStoredSlaKpiTemplate(mixed $template): array
    {
        if (is_string($template)) {
            $template = json_decode($template, true);
        }

        return is_array($template) ? $this->normalizeSlaKpiTemplate($template) : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function slaReports(Request $request, Contract $contract): array
    {
        $reportDate = Carbon::parse($request->query('report_date', now()->toDateString()));

        return [
            'weekly' => $this->slaReportForPeriod(
                $contract,
                'weekly',
                $reportDate->copy()->startOfWeek(),
                $reportDate->copy()->endOfWeek()
            ),
            'monthly' => $this->slaReportForPeriod(
                $contract,
                'monthly',
                $reportDate->copy()->startOfMonth(),
                $reportDate->copy()->endOfMonth()
            ),
        ];
    }

    private function slaReportForPeriod(Contract $contract, string $periodKey, Carbon $startsOn, Carbon $endsOn): array
    {
        $visits = Visit::query()
            ->with(['checklistItems', 'feedback'])
            ->where('contract_id', $contract->id)
            ->whereBetween('scheduled_for', [$startsOn->toDateString(), $endsOn->toDateString()])
            ->orderBy('scheduled_for')
            ->orderBy('starts_at')
            ->get();

        $scheduled = $visits->count();
        $completed = $visits->where('status', 'completed')->count();
        $missed = $visits->where('status', 'missed')->count();
        $checkedIn = $visits->filter(fn (Visit $visit): bool => $visit->checked_in_at !== null);
        $onTime = $checkedIn->filter(fn (Visit $visit): bool => $this->visitStartedOnTime($visit))->count();
        $issueCount = $visits
            ->filter(fn (Visit $visit): bool => filled($visit->issue_note) || in_array($visit->status, ['issue_reported', 'missed'], true))
            ->count();
        $photoCount = $visits->sum(fn (Visit $visit): int => count($visit->photos ?? []));
        $visitsWithPhotos = $visits->filter(fn (Visit $visit): bool => count($visit->photos ?? []) > 0)->count();
        $supervisorReviews = $visits->filter(fn (Visit $visit): bool => $visit->supervisor_acknowledged_at !== null)->count();
        $checklistTotal = $visits->sum(fn (Visit $visit): int => $visit->checklistItems->count());
        $checklistDone = $visits->sum(fn (Visit $visit): int => $visit->checklistItems->where('status', 'done')->count());
        $qualityReviews = $visits->filter(fn (Visit $visit): bool => $visit->quality_reviewed_at !== null || filled($visit->quality_status));
        $qualityPass = $qualityReviews->filter(fn (Visit $visit): bool => $this->visitQualityStatus($visit) === 'passed')->count();
        $qualityFailures = $qualityReviews
            ->filter(fn (Visit $visit): bool => in_array($this->visitQualityStatus($visit), ['failed', 'needs_correction', 'customer_follow_up'], true))
            ->count();
        $qualityFollowUps = $qualityReviews
            ->filter(fn (Visit $visit): bool => (bool) $visit->quality_follow_up_required || $this->visitQualityStatus($visit) === 'customer_follow_up')
            ->count();
        $feedback = $visits
            ->map(fn (Visit $visit) => $visit->feedback)
            ->filter();
        $customerFeedbackCount = $feedback->count();
        $averageRating = $customerFeedbackCount > 0 ? round((float) $feedback->avg('rating'), 2) : null;
        $lowRatings = $feedback->where('rating', '<=', 2)->count();
        $satisfiedRatings = $feedback->where('rating', '>=', 4)->count();

        $metrics = [
            'scheduled_visits' => $scheduled,
            'completed_visits' => $completed,
            'missed_visits' => $missed,
            'checked_in_visits' => $checkedIn->count(),
            'on_time_visits' => $onTime,
            'issue_count' => $issueCount,
            'photo_count' => $photoCount,
            'visits_with_photos' => $visitsWithPhotos,
            'supervisor_review_count' => $supervisorReviews,
            'checklist_items' => $checklistTotal,
            'checklist_done' => $checklistDone,
            'attendance_rate' => $this->percentage($checkedIn->count(), $scheduled),
            'completion_rate' => $this->percentage($completed, $scheduled),
            'on_time_rate' => $this->percentage($onTime, $scheduled),
            'checklist_completion_rate' => $this->percentage($checklistDone, $checklistTotal),
            'issue_free_rate' => $this->percentage(max(0, $scheduled - $issueCount), $scheduled),
            'photo_evidence_rate' => $this->percentage($visitsWithPhotos, $scheduled),
            'supervisor_review_rate' => $this->percentage($supervisorReviews, $scheduled),
            'quality_review_count' => $qualityReviews->count(),
            'quality_pass_count' => $qualityPass,
            'quality_failure_count' => $qualityFailures,
            'quality_follow_up_count' => $qualityFollowUps,
            'quality_pass_rate' => $this->percentage($qualityPass, $qualityReviews->count()),
            'customer_feedback_count' => $customerFeedbackCount,
            'average_customer_rating' => $averageRating,
            'low_rating_count' => $lowRatings,
            'customer_satisfaction_rate' => $this->percentage($satisfiedRatings, $customerFeedbackCount),
        ];

        $kpiResults = collect($contract->sla_kpi_template ?? [])
            ->map(fn (array $kpi): array => $this->slaKpiResult($kpi, $metrics))
            ->values()
            ->all();

        return [
            'period_key' => $periodKey,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'score' => $this->slaScore($kpiResults),
            'status' => collect($kpiResults)->contains(fn (array $item): bool => $item['passed'] === false) ? 'attention' : 'healthy',
            'metrics' => $metrics,
            'kpi_results' => $kpiResults,
        ];
    }

    private function slaKpiResult(array $kpi, array $metrics): array
    {
        $code = (string) ($kpi['code'] ?? '');
        $direction = (string) ($kpi['direction'] ?? $this->defaultKpiDirection($code));
        $target = (float) ($kpi['target'] ?? 0);
        $actual = $this->slaMetricActual($code, $metrics);
        $passed = $actual === null
            ? false
            : ($direction === 'at_most' ? $actual <= $target : $actual >= $target);

        return [
            'code' => $code,
            'label' => (string) ($kpi['label'] ?? str($code)->headline()->toString()),
            'target' => floor($target) === $target ? (int) $target : round($target, 2),
            'actual' => $actual === null ? null : (floor($actual) === $actual ? (int) $actual : round($actual, 2)),
            'unit' => (string) ($kpi['unit'] ?? 'percent'),
            'weight' => (int) ($kpi['weight'] ?? 0),
            'direction' => $direction,
            'passed' => $passed,
            'performance' => $actual === null ? 0 : $this->slaPerformance($actual, $target, $direction),
        ];
    }

    private function slaMetricActual(string $code, array $metrics): ?float
    {
        $aliases = [
            'attendance' => 'attendance_rate',
            'worker_attendance' => 'attendance_rate',
            'checklist_completion' => 'checklist_completion_rate',
            'supervisor_review' => 'supervisor_review_rate',
            'supervisor_inspection' => 'supervisor_review_rate',
            'supervisor_acknowledgement' => 'supervisor_review_rate',
            'supervisor_acknowledgement_rate' => 'supervisor_review_rate',
            'on_time' => 'on_time_rate',
            'completion' => 'completion_rate',
            'photo_evidence' => 'photo_evidence_rate',
            'issue_free' => 'issue_free_rate',
            'quality' => 'quality_pass_rate',
            'quality_pass' => 'quality_pass_rate',
            'quality_pass_rate' => 'quality_pass_rate',
            'quality_failure' => 'quality_failure_count',
            'quality_follow_up' => 'quality_follow_up_count',
            'customer_satisfaction' => 'customer_satisfaction_rate',
            'customer_rating' => 'average_customer_rating',
            'low_rating' => 'low_rating_count',
        ];

        $metric = $aliases[$code] ?? $code;

        return array_key_exists($metric, $metrics) ? (float) $metrics[$metric] : null;
    }

    private function slaPerformance(float $actual, float $target, string $direction): int
    {
        if ($direction === 'at_most') {
            return $actual <= $target ? 100 : (int) round(max(0, min(100, $target > 0 ? ($target / max($actual, 1)) * 100 : 0)));
        }

        if ($target <= 0) {
            return $actual > 0 ? 100 : 0;
        }

        return (int) round(max(0, min(100, ($actual / $target) * 100)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $kpiResults
     */
    private function slaScore(array $kpiResults): int
    {
        if ($kpiResults === []) {
            return 100;
        }

        $totalWeight = collect($kpiResults)->sum(fn (array $item): int => max(0, (int) $item['weight']));

        if ($totalWeight > 0) {
            $weighted = collect($kpiResults)->sum(fn (array $item): int => (int) $item['performance'] * max(0, (int) $item['weight']));

            return (int) round($weighted / $totalWeight);
        }

        return (int) round(collect($kpiResults)->avg('performance'));
    }

    private function visitQualityStatus(Visit $visit): ?string
    {
        if (filled($visit->quality_status)) {
            return $visit->quality_status;
        }

        return $visit->completion_review_status === 'approved' ? 'passed' : null;
    }

    private function percentage(int $part, int $total): int
    {
        if ($total <= 0) {
            return 100;
        }

        return (int) round(($part / $total) * 100);
    }

    private function visitStartedOnTime(Visit $visit): bool
    {
        if (! $visit->checked_in_at || ! $visit->scheduled_for || ! $visit->starts_at) {
            return false;
        }

        $scheduledStart = Carbon::parse($visit->scheduled_for->toDateString().' '.$this->time($visit->starts_at));

        return $visit->checked_in_at->lessThanOrEqualTo($scheduledStart);
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
            'sla_kpi_template',
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
                'sla_kpi_template' => $service->sla_kpi_template ?? [],
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
                'sla_kpi_template' => $package->sla_kpi_template ?? [],
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
            'sla_kpi_template' => $contract->sla_kpi_template ?? [],
            'payment_plan' => $this->paymentPlanWithAmounts($contract),
            'billing_cycle' => $contract->billing_cycle,
            'notice_days' => $contract->notice_days,
            'auto_renews' => $contract->auto_renews,
            'special_terms' => $contract->special_terms,
            'edit_url' => "/app/contracts/{$contract->id}/edit",
            'detail_url' => "/app/contracts/{$contract->id}",
            'print_url' => "/app/contracts/{$contract->id}/print",
            'download_url' => "/app/contracts/{$contract->id}/download",
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

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentPlanSettlement(Contract $contract): array
    {
        $remainingPaid = (int) $contract->invoices->sum('paid_total_halalas');

        return collect($this->paymentPlanWithAmounts($contract))
            ->map(function (array $item) use (&$remainingPaid): array {
                $amount = (int) $item['amount_halalas'];
                $paid = min($remainingPaid, $amount);
                $remainingPaid = max(0, $remainingPaid - $paid);
                $balance = max(0, $amount - $paid);

                return [
                    ...$item,
                    'paid_halalas' => $paid,
                    'paid_sar' => $this->halalasToSarString($paid),
                    'balance_halalas' => $balance,
                    'balance_sar' => $this->halalasToSarString($balance),
                    'status' => $balance === 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, int|string>
     */
    private function financeSummary(Contract $contract): array
    {
        $gross = (int) $contract->invoices->sum('gross_total_halalas');
        $paid = (int) $contract->invoices->sum('paid_total_halalas');
        $balance = (int) $contract->invoices->sum('balance_halalas');

        return [
            'gross_total_halalas' => $gross,
            'gross_total_sar' => $this->halalasToSarString($gross),
            'paid_total_halalas' => $paid,
            'paid_total_sar' => $this->halalasToSarString($paid),
            'balance_halalas' => $balance,
            'balance_sar' => $this->halalasToSarString($balance),
            'invoices_count' => $contract->invoices->count(),
            'payments_count' => $contract->invoices->sum(fn (Invoice $invoice): int => $invoice->payments->count()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workerOptions(): array
    {
        return Worker::query()
            ->whereIn('status', ['available', 'assigned'])
            ->orderBy('name')
            ->get()
            ->map(fn (Worker $worker): array => $this->serializeWorker($worker))
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: int, label: string}>
     */
    private function weekdayOptions(): array
    {
        return [
            ['key' => 1, 'label' => 'Monday'],
            ['key' => 2, 'label' => 'Tuesday'],
            ['key' => 3, 'label' => 'Wednesday'],
            ['key' => 4, 'label' => 'Thursday'],
            ['key' => 5, 'label' => 'Friday'],
            ['key' => 6, 'label' => 'Saturday'],
            ['key' => 7, 'label' => 'Sunday'],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function assignmentStatuses(): array
    {
        return [
            ['key' => 'active', 'label' => 'Active'],
            ['key' => 'paused', 'label' => 'Paused'],
        ];
    }

    private function serializeAssignment(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'contract_id' => $assignment->contract_id,
            'customer_site_id' => $assignment->customer_site_id,
            'worker_id' => $assignment->worker_id,
            'service_id' => $assignment->service_id,
            'weekday' => $assignment->weekday,
            'starts_at' => $this->time($assignment->starts_at),
            'ends_at' => $this->time($assignment->ends_at),
            'share_percent' => $assignment->share_percent,
            'status' => $assignment->status,
            'team_role' => $assignment->team_role ?? 'main',
            'task_instructions' => $assignment->task_instructions ?? [],
            'worker' => $assignment->worker ? $this->serializeWorker($assignment->worker) : null,
            'site' => $assignment->site ? [
                'id' => $assignment->site->id,
                'name' => $assignment->site->name,
                'city' => $assignment->site->city,
                'district' => $assignment->site->district,
                'customer' => $assignment->site->customer ? [
                    'id' => $assignment->site->customer->id,
                    'name' => $assignment->site->customer->name,
                ] : null,
            ] : null,
            'service' => $assignment->service ? [
                'id' => $assignment->service->id,
                'title' => $assignment->service->title,
                'category' => $assignment->service->category,
            ] : null,
        ];
    }

    private function assignmentAssessment(Contract $contract): array
    {
        $requiredVisits = max(0, (int) ($contract->visits_per_week ?? 0));
        $requiredWorkers = max(1, (int) ($contract->agreed_workers ?? 1));
        $activeAssignments = $contract->assignments
            ->where('status', 'active')
            ->values();

        $slots = $activeAssignments
            ->groupBy(fn (Assignment $assignment): string => $this->assignmentSlotKey($assignment))
            ->map(fn (Collection $assignments): array => $this->assignmentSlotAssessment($assignments, $requiredWorkers))
            ->sortBy([
                ['weekday', 'asc'],
                ['starts_at', 'asc'],
                ['ends_at', 'asc'],
            ])
            ->values();

        $missingVisits = max(0, $requiredVisits - $slots->count());
        $slotsMissingMain = $slots->where('has_main_worker', false)->count();
        $understaffedSlots = $slots->where('has_required_workers', false)->count();

        $followUps = collect();

        if ($missingVisits > 0) {
            $followUps->push([
                'type' => 'missing_visit_slots',
                'severity' => 'warning',
                'count' => $missingVisits,
            ]);
        }

        if ($slotsMissingMain > 0) {
            $followUps->push([
                'type' => 'missing_main_worker',
                'severity' => 'danger',
                'count' => $slotsMissingMain,
            ]);
        }

        if ($understaffedSlots > 0) {
            $followUps->push([
                'type' => 'team_understaffed',
                'severity' => 'warning',
                'count' => $understaffedSlots,
            ]);
        }

        return [
            'required_visits_per_week' => $requiredVisits,
            'covered_visits_per_week' => $slots->count(),
            'missing_visits_per_week' => $missingVisits,
            'required_workers_per_visit' => $requiredWorkers,
            'ready_slots_count' => $slots->where('ready', true)->count(),
            'slots' => $slots->all(),
            'follow_ups' => $followUps->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     */
    private function assignmentSlotAssessment(Collection $assignments, int $requiredWorkers): array
    {
        $mainAssignment = $assignments->firstWhere('team_role', 'main');
        $workersCount = $assignments->pluck('worker_id')->unique()->count();
        $hasMainWorker = $mainAssignment instanceof Assignment;
        $hasRequiredWorkers = $workersCount >= $requiredWorkers;

        return [
            'key' => $this->assignmentSlotKey($assignments->first()),
            'weekday' => (int) $assignments->first()->weekday,
            'starts_at' => $this->time($assignments->first()->starts_at),
            'ends_at' => $this->time($assignments->first()->ends_at),
            'workers_count' => $workersCount,
            'task_count' => $assignments->sum(fn (Assignment $assignment): int => count($assignment->task_instructions ?? [])),
            'has_main_worker' => $hasMainWorker,
            'has_required_workers' => $hasRequiredWorkers,
            'ready' => $hasMainWorker && $hasRequiredWorkers,
            'main_worker' => $mainAssignment?->worker ? $this->serializeWorker($mainAssignment->worker) : null,
            'assignments' => $assignments->map(fn (Assignment $assignment): array => $this->serializeAssignment($assignment))->values(),
        ];
    }

    private function assignmentSlotKey(Assignment $assignment): string
    {
        return implode('|', [
            $assignment->weekday,
            $this->time($assignment->starts_at),
            $this->time($assignment->ends_at),
            $assignment->service_id ?? 'none',
        ]);
    }

    private function serializeInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'gross_total_halalas' => $invoice->gross_total_halalas,
            'gross_total_sar' => $this->halalasToSarString($invoice->gross_total_halalas),
            'paid_total_halalas' => $invoice->paid_total_halalas,
            'paid_total_sar' => $this->halalasToSarString($invoice->paid_total_halalas),
            'balance_halalas' => $invoice->balance_halalas,
            'balance_sar' => $this->halalasToSarString($invoice->balance_halalas),
            'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount_halalas' => $payment->amount_halalas,
                'amount_sar' => $this->halalasToSarString($payment->amount_halalas),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'received_at' => $payment->received_at?->toDateTimeString(),
            ])->values(),
        ];
    }

    private function serializeContractDecision(ContractDecision $decision): array
    {
        return [
            'id' => $decision->id,
            'decision' => $decision->decision,
            'status' => $decision->status,
            'signer_name' => $decision->signer_name,
            'signer_title' => $decision->signer_title,
            'signature_text' => $decision->signature_text,
            'customer_note' => $decision->customer_note,
            'admin_note' => $decision->admin_note,
            'accepted_at' => $decision->accepted_at?->toDateTimeString(),
            'reviewed_at' => $decision->reviewed_at?->toDateTimeString(),
            'created_at' => $decision->created_at?->toDateTimeString(),
            'review_url' => "/app/contract-decisions/{$decision->id}/review",
            'customer' => $decision->customer ? [
                'id' => $decision->customer->id,
                'name' => $decision->customer->name,
                'email' => $decision->customer->email,
            ] : null,
            'reviewed_by' => $decision->reviewedBy ? [
                'id' => $decision->reviewedBy->id,
                'name' => $decision->reviewedBy->name,
            ] : null,
        ];
    }

    private function serializeWorker(Worker $worker): array
    {
        return [
            'id' => $worker->id,
            'name' => $worker->name,
            'employee_code' => $worker->employee_code,
            'status' => $worker->status,
            'job_role' => $worker->job_role,
        ];
    }

    private function time(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return substr($value, 0, 5);
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
