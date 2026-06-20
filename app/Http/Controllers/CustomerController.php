<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSarMoney;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Visit;
use App\Models\Worker;
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

class CustomerController extends Controller
{
    use HandlesSarMoney;

    public function index(): Response
    {
        $customers = Customer::query()
            ->with(['sites' => fn ($query) => $query->orderBy('city')->orderBy('name'), 'contracts'])
            ->withCount(['sites', 'contracts', 'invoices'])
            ->orderByRaw("case when status = 'active' then 0 when status = 'prospect' then 1 else 2 end")
            ->orderBy('name')
            ->get();

        return Inertia::render('Customers/Index', [
            'customers' => $customers->map(fn (Customer $customer): array => $this->serializeCustomer($customer)),
            'catalog' => $this->catalog(),
            'metrics' => [
                'total' => Customer::query()->count(),
                'active' => Customer::query()->where('status', 'active')->count(),
                'prospects' => Customer::query()->where('status', 'prospect')->count(),
                'sites' => CustomerSite::query()->count(),
            ],
            'createUrl' => '/app/customers/create',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Form', [
            'mode' => 'create',
            'customer' => null,
            'catalog' => $this->catalog(),
            'locationCatalog' => $this->locationCatalog(),
            'steps' => $this->formSteps(),
            'submitUrl' => '/app/customers',
            'backUrl' => '/app/customers',
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $customer->load(['sites' => fn ($query) => $query->orderBy('city')->orderBy('name')])
            ->loadCount(['sites', 'contracts', 'invoices']);

        return Inertia::render('Customers/Form', [
            'mode' => 'edit',
            'customer' => $this->serializeCustomer($customer),
            'catalog' => $this->catalog(),
            'locationCatalog' => $this->locationCatalog(),
            'steps' => $this->formSteps(),
            'submitUrl' => "/app/customers/{$customer->id}",
            'backUrl' => '/app/customers',
        ]);
    }

    public function show(Customer $customer): Response
    {
        $customer->load([
            'sites' => fn ($query) => $query->orderBy('city')->orderBy('name'),
            'contracts' => fn ($query) => $query
                ->with(['site', 'service', 'servicePackage'])
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'paused' THEN 2 ELSE 3 END")
                ->orderBy('reference'),
            'invoices' => fn ($query) => $query
                ->with([
                    'payments' => fn ($paymentQuery) => $paymentQuery->orderByDesc('received_at'),
                    'creditNotes',
                    'contract.site',
                ])
                ->orderBy('due_date'),
        ])->loadCount(['sites', 'contracts', 'invoices']);

        $contracts = $customer->contracts;
        $sites = $customer->sites;
        $contractIds = $contracts->pluck('id')->values();
        $siteIds = $sites->pluck('id')->values();
        $assignments = Assignment::query()
            ->with(['worker', 'site', 'contract'])
            ->whereIn('contract_id', $contractIds)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get();
        $visits = Visit::query()
            ->with(['worker', 'site', 'contract', 'service'])
            ->where(function ($query) use ($contractIds, $siteIds): void {
                $query->whereIn('contract_id', $contractIds)
                    ->orWhereIn('customer_site_id', $siteIds);
            })
            ->orderByDesc('scheduled_for')
            ->orderBy('starts_at')
            ->get();
        $invoices = $customer->invoices;

        return Inertia::render('Customers/Show', [
            'customer' => $this->serializeCustomer($customer),
            'summary' => $this->customerSummary($customer, $contracts, $assignments, $visits),
            'finance' => $this->financeSummary($invoices),
            'sites' => $this->siteRows($sites, $contracts, $assignments, $visits, $invoices),
            'contracts' => $this->contractRows($contracts, $assignments, $visits, $invoices),
            'workers' => $this->workerRows($assignments, $visits),
            'performanceTrend' => $this->performanceTrend($visits, $invoices),
            'invoices' => $invoices->map(fn (Invoice $invoice): array => $this->serializeInvoice($invoice))->values(),
            'catalog' => $this->catalog(),
            'backUrl' => '/app/customers',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCustomer($request);
        $sites = Arr::pull($validated, 'sites', []);

        $customer = DB::transaction(function () use ($request, $validated, $sites): Customer {
            $customer = Customer::create($validated);
            $this->syncSites($customer, $sites);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'customer.created',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'changes' => [
                    'created' => [
                        'old' => null,
                        'new' => $this->serializeCustomer($customer->fresh(['sites'])),
                    ],
                ],
            ]);

            return $customer;
        });

        return redirect('/app/customers')->with('success', __('Customer :customer created.', ['customer' => $customer->name]));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $this->validateCustomer($request, $customer);
        $sites = Arr::pull($validated, 'sites', []);
        $oldValues = $customer->only($this->customerFields());
        $oldSiteCount = $customer->sites()->count();

        DB::transaction(function () use ($request, $customer, $validated, $sites, $oldValues, $oldSiteCount): void {
            $customer->fill($validated)->save();
            $this->syncSites($customer, $sites);

            $changes = $this->changes($oldValues, $customer->fresh()->only($this->customerFields()));
            $newSiteCount = $customer->sites()->count();

            if ($oldSiteCount !== $newSiteCount) {
                $changes['sites_count'] = [
                    'old' => $oldSiteCount,
                    'new' => $newSiteCount,
                ];
            }

            if ($changes !== []) {
                AuditLog::create([
                    'user_id' => $request->user()?->id,
                    'action' => 'customer.updated',
                    'auditable_type' => Customer::class,
                    'auditable_id' => $customer->id,
                    'changes' => $changes,
                ]);
            }
        });

        return redirect('/app/customers')->with('success', __('Customer :customer updated.', ['customer' => $customer->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        $catalog = $this->catalog();
        $locationCatalog = $this->locationCatalog();

        $validated = $request->validate([
            'customer_type' => ['required', Rule::in($this->catalogKeys($catalog['customerTypes']))],
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:180'],
            'preferred_channel' => ['required', Rule::in($this->catalogKeys($catalog['channels']))],
            'preferred_locale' => ['required', Rule::in(config('cleanops.supported_locales', ['ar', 'en']))],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'status' => ['required', Rule::in($this->catalogKeys($catalog['statuses']))],
            'sites' => ['array', 'max:30'],
            'sites.*.id' => ['nullable', 'integer', 'exists:customer_sites,id'],
            'sites.*.country_code' => ['nullable', Rule::in($this->catalogKeys($locationCatalog['countries']))],
            'sites.*.name' => ['required', 'string', 'max:180'],
            'sites.*.city' => ['required', 'string', 'max:120'],
            'sites.*.district' => ['nullable', 'string', 'max:120'],
            'sites.*.address' => ['nullable', 'string', 'max:1000'],
            'sites.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'sites.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sites.*.google_place_id' => ['nullable', 'string', 'max:255'],
            'sites.*.formatted_address' => ['nullable', 'string', 'max:1000'],
            'sites.*.contact_name' => ['nullable', 'string', 'max:160'],
            'sites.*.contact_phone' => ['nullable', 'string', 'max:32'],
        ]);

        if ($customer) {
            $siteIds = collect($validated['sites'] ?? [])
                ->pluck('id')
                ->filter()
                ->values();

            if ($siteIds->isNotEmpty()) {
                $ownedCount = $customer->sites()->whereKey($siteIds)->count();

                if ($ownedCount !== $siteIds->count()) {
                    throw ValidationException::withMessages([
                        'sites' => __('One or more sites do not belong to this customer.'),
                    ]);
                }
            }
        }

        return $validated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sites
     */
    private function syncSites(Customer $customer, array $sites): void
    {
        $keptIds = collect();

        foreach ($sites as $site) {
            $siteId = $site['id'] ?? null;
            unset($site['id']);
            $site['country_code'] = $site['country_code'] ?? 'SA';

            if ($siteId) {
                $customer->sites()->whereKey($siteId)->update($site);
                $keptIds->push((int) $siteId);

                continue;
            }

            $created = $customer->sites()->create($site);
            $keptIds->push($created->id);
        }

        $customer->sites()
            ->when($keptIds->isNotEmpty(), fn ($query) => $query->whereKeyNot($keptIds->all()))
            ->doesntHave('contracts')
            ->delete();
    }

    /**
     * @return list<string>
     */
    private function customerFields(): array
    {
        return [
            'customer_type',
            'name',
            'phone',
            'email',
            'preferred_channel',
            'preferred_locale',
            'vat_number',
            'status',
        ];
    }

    /**
     * @return array<string, list<array{key: string, label: string}>>
     */
    private function catalog(): array
    {
        return [
            'customerTypes' => [
                ['key' => 'company', 'label' => 'Company'],
                ['key' => 'individual', 'label' => 'Individual'],
                ['key' => 'government', 'label' => 'Government'],
            ],
            'channels' => [
                ['key' => 'whatsapp', 'label' => 'WhatsApp'],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'sms', 'label' => 'SMS'],
            ],
            'locales' => [
                ['key' => 'ar', 'label' => 'Arabic'],
                ['key' => 'en', 'label' => 'English'],
            ],
            'statuses' => [
                ['key' => 'active', 'label' => 'Active'],
                ['key' => 'prospect', 'label' => 'Prospect'],
                ['key' => 'inactive', 'label' => 'Inactive'],
                ['key' => 'blocked', 'label' => 'Blocked'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function locationCatalog(): array
    {
        return [
            'countries' => [
                ['key' => 'SA', 'label' => 'Saudi Arabia'],
            ],
            'cities' => [
                [
                    'key' => 'Riyadh',
                    'label' => 'Riyadh',
                    'country_code' => 'SA',
                    'center' => ['lat' => 24.7136, 'lng' => 46.6753],
                    'districts' => [
                        ['key' => 'Al Malaz', 'label' => 'Al Malaz'],
                        ['key' => 'Al Olaya', 'label' => 'Al Olaya'],
                        ['key' => 'Al Murabba', 'label' => 'Al Murabba'],
                        ['key' => 'Al Sulimaniyah', 'label' => 'Al Sulimaniyah'],
                        ['key' => 'Al Wurud', 'label' => 'Al Wurud'],
                        ['key' => 'Al Nakheel', 'label' => 'Al Nakheel'],
                        ['key' => 'King Fahd', 'label' => 'King Fahd'],
                        ['key' => 'King Abdullah', 'label' => 'King Abdullah'],
                        ['key' => 'Al Mohammadiyah', 'label' => 'Al Mohammadiyah'],
                        ['key' => 'Al Muruj', 'label' => 'Al Muruj'],
                        ['key' => 'Al Nuzhah', 'label' => 'Al Nuzhah'],
                        ['key' => 'Al Rawdah', 'label' => 'Al Rawdah'],
                        ['key' => 'Al Hamra', 'label' => 'Al Hamra'],
                        ['key' => 'Al Khaleej', 'label' => 'Al Khaleej'],
                        ['key' => 'Al Nahdah', 'label' => 'Al Nahdah'],
                        ['key' => 'Al Rayyan', 'label' => 'Al Rayyan'],
                        ['key' => 'Al Rabwah', 'label' => 'Al Rabwah'],
                        ['key' => 'Al Shifa', 'label' => 'Al Shifa'],
                        ['key' => 'Al Aziziyah', 'label' => 'Al Aziziyah'],
                        ['key' => 'Manfuhah', 'label' => 'Manfuhah'],
                        ['key' => 'Al Dirah', 'label' => 'Al Dirah'],
                        ['key' => 'Al Batha', 'label' => 'Al Batha'],
                        ['key' => 'Al Yasmin', 'label' => 'Al Yasmin'],
                        ['key' => 'Al Narjis', 'label' => 'Al Narjis'],
                        ['key' => 'Al Aqiq', 'label' => 'Al Aqiq'],
                        ['key' => 'Hittin', 'label' => 'Hittin'],
                        ['key' => 'Qurtubah', 'label' => 'Qurtubah'],
                        ['key' => 'Al Munsiyah', 'label' => 'Al Munsiyah'],
                        ['key' => 'Al Sahafah', 'label' => 'Al Sahafah'],
                        ['key' => 'Al Rabi', 'label' => 'Al Rabi'],
                    ],
                ],
                [
                    'key' => 'Jeddah',
                    'label' => 'Jeddah',
                    'country_code' => 'SA',
                    'center' => ['lat' => 21.5433, 'lng' => 39.1728],
                    'districts' => [
                        ['key' => 'Al Hamra', 'label' => 'Al Hamra'],
                        ['key' => 'Al Rawdah', 'label' => 'Al Rawdah'],
                        ['key' => 'Al Salamah', 'label' => 'Al Salamah'],
                        ['key' => 'Al Zahra', 'label' => 'Al Zahra'],
                        ['key' => 'Al Andalus', 'label' => 'Al Andalus'],
                        ['key' => 'Al Corniche', 'label' => 'Al Corniche'],
                    ],
                ],
                [
                    'key' => 'Dammam',
                    'label' => 'Dammam',
                    'country_code' => 'SA',
                    'center' => ['lat' => 26.4207, 'lng' => 50.0888],
                    'districts' => [
                        ['key' => 'Al Faisaliyah', 'label' => 'Al Faisaliyah'],
                        ['key' => 'Al Shati', 'label' => 'Al Shati'],
                        ['key' => 'Al Rakah', 'label' => 'Al Rakah'],
                        ['key' => 'Al Mazruiyah', 'label' => 'Al Mazruiyah'],
                    ],
                ],
                [
                    'key' => 'Makkah',
                    'label' => 'Makkah',
                    'country_code' => 'SA',
                    'center' => ['lat' => 21.3891, 'lng' => 39.8579],
                    'districts' => [
                        ['key' => 'Al Aziziyah', 'label' => 'Al Aziziyah'],
                        ['key' => 'Al Awali', 'label' => 'Al Awali'],
                        ['key' => 'Al Shoqiyah', 'label' => 'Al Shoqiyah'],
                    ],
                ],
                [
                    'key' => 'Madinah',
                    'label' => 'Madinah',
                    'country_code' => 'SA',
                    'center' => ['lat' => 24.5247, 'lng' => 39.5692],
                    'districts' => [
                        ['key' => 'Quba', 'label' => 'Quba'],
                        ['key' => 'Al Awali', 'label' => 'Al Awali'],
                        ['key' => 'Al Aridh', 'label' => 'Al Aridh'],
                    ],
                ],
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
            ['key' => 'profile', 'label' => 'Profile'],
            ['key' => 'sites', 'label' => 'Sites'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_type' => $customer->customer_type,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'preferred_channel' => $customer->preferred_channel,
            'preferred_locale' => $customer->preferred_locale,
            'vat_number' => $customer->vat_number,
            'status' => $customer->status,
            'edit_url' => "/app/customers/{$customer->id}/edit",
            'detail_url' => "/app/customers/{$customer->id}",
            'sites_count' => $customer->sites_count ?? $customer->sites()->count(),
            'contracts_count' => $customer->contracts_count ?? $customer->contracts()->count(),
            'invoices_count' => $customer->invoices_count ?? $customer->invoices()->count(),
            'sites' => $customer->sites->map(fn (CustomerSite $site): array => [
                'id' => $site->id,
                'country_code' => $site->country_code ?? 'SA',
                'name' => $site->name,
                'city' => $site->city,
                'district' => $site->district,
                'address' => $site->address,
                'latitude' => $site->latitude,
                'longitude' => $site->longitude,
                'google_place_id' => $site->google_place_id,
                'formatted_address' => $site->formatted_address,
                'contact_name' => $site->contact_name,
                'contact_phone' => $site->contact_phone,
            ])->values(),
        ];
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @param  Collection<int, Assignment>  $assignments
     * @param  Collection<int, Visit>  $visits
     * @return array<string, int>
     */
    private function customerSummary(Customer $customer, Collection $contracts, Collection $assignments, Collection $visits): array
    {
        return [
            'sites_count' => $customer->sites_count ?? $customer->sites()->count(),
            'contracts_count' => $contracts->count(),
            'active_contracts_count' => $contracts->where('status', 'active')->count(),
            'assigned_workers_count' => $this->distinctWorkerCount($assignments, $visits),
            'visits_count' => $visits->count(),
            'completed_visits_count' => $visits->where('status', 'completed')->count(),
            'worked_minutes' => $this->workedMinutes($visits),
        ];
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return array<string, int|string>
     */
    private function financeSummary(Collection $invoices): array
    {
        $gross = (int) $invoices->sum('gross_total_halalas');
        $paid = (int) $invoices->sum('paid_total_halalas');
        $balance = (int) $invoices->sum('balance_halalas');
        $overdue = (int) $invoices
            ->filter(fn (Invoice $invoice): bool => $invoice->status !== 'paid' && $invoice->due_date?->isPast())
            ->sum('balance_halalas');

        return [
            'gross_total_halalas' => $gross,
            'gross_total_sar' => $this->halalasToSarString($gross),
            'paid_total_halalas' => $paid,
            'paid_total_sar' => $this->halalasToSarString($paid),
            'balance_halalas' => $balance,
            'balance_sar' => $this->halalasToSarString($balance),
            'overdue_halalas' => $overdue,
            'overdue_sar' => $this->halalasToSarString($overdue),
            'invoices_count' => $invoices->count(),
            'payments_count' => $invoices->sum(fn (Invoice $invoice): int => $invoice->payments->count()),
        ];
    }

    /**
     * @param  Collection<int, CustomerSite>  $sites
     * @param  Collection<int, Contract>  $contracts
     * @param  Collection<int, Assignment>  $assignments
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, Invoice>  $invoices
     * @return list<array<string, mixed>>
     */
    private function siteRows(Collection $sites, Collection $contracts, Collection $assignments, Collection $visits, Collection $invoices): array
    {
        return $sites
            ->map(function (CustomerSite $site) use ($contracts, $assignments, $visits, $invoices): array {
                $siteContracts = $contracts->where('customer_site_id', $site->id);
                $siteContractIds = $siteContracts->pluck('id');
                $siteAssignments = $assignments->filter(fn (Assignment $assignment): bool => (int) $assignment->customer_site_id === (int) $site->id || $siteContractIds->contains($assignment->contract_id));
                $siteVisits = $visits->filter(fn (Visit $visit): bool => (int) $visit->customer_site_id === (int) $site->id || $siteContractIds->contains($visit->contract_id));
                $siteInvoices = $invoices->where('customer_site_id', $site->id);

                return [
                    'id' => $site->id,
                    'name' => $site->name,
                    'city' => $site->city,
                    'district' => $site->district,
                    'address' => $site->address,
                    'contracts_count' => $siteContracts->count(),
                    'workers_count' => $this->distinctWorkerCount($siteAssignments, $siteVisits),
                    'visits_count' => $siteVisits->count(),
                    'completed_visits_count' => $siteVisits->where('status', 'completed')->count(),
                    'worked_minutes' => $this->workedMinutes($siteVisits),
                    'gross_total_halalas' => (int) $siteInvoices->sum('gross_total_halalas'),
                    'balance_halalas' => (int) $siteInvoices->sum('balance_halalas'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @param  Collection<int, Assignment>  $assignments
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, Invoice>  $invoices
     * @return list<array<string, mixed>>
     */
    private function contractRows(Collection $contracts, Collection $assignments, Collection $visits, Collection $invoices): array
    {
        return $contracts
            ->map(function (Contract $contract) use ($assignments, $visits, $invoices): array {
                $contractAssignments = $assignments->where('contract_id', $contract->id);
                $contractVisits = $visits->where('contract_id', $contract->id);
                $contractInvoices = $invoices->where('contract_id', $contract->id);

                return [
                    'id' => $contract->id,
                    'reference' => $contract->reference,
                    'status' => $contract->status,
                    'site' => $contract->site ? [
                        'id' => $contract->site->id,
                        'name' => $contract->site->name,
                    ] : null,
                    'service' => $contract->service ? [
                        'id' => $contract->service->id,
                        'title' => $contract->service->title,
                    ] : null,
                    'monthly_fee_halalas' => $contract->monthly_fee_halalas,
                    'workers_count' => $this->distinctWorkerCount($contractAssignments, $contractVisits),
                    'assignments_count' => $contractAssignments->count(),
                    'visits_count' => $contractVisits->count(),
                    'completed_visits_count' => $contractVisits->where('status', 'completed')->count(),
                    'worked_minutes' => $this->workedMinutes($contractVisits),
                    'gross_total_halalas' => (int) $contractInvoices->sum('gross_total_halalas'),
                    'paid_total_halalas' => (int) $contractInvoices->sum('paid_total_halalas'),
                    'balance_halalas' => (int) $contractInvoices->sum('balance_halalas'),
                    'detail_url' => "/app/contracts/{$contract->id}",
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, mixed>>
     */
    private function workerRows(Collection $assignments, Collection $visits): array
    {
        $workerIds = $assignments->pluck('worker_id')
            ->merge($visits->pluck('worker_id'))
            ->filter()
            ->unique()
            ->values();

        return Worker::query()
            ->whereIn('id', $workerIds)
            ->orderBy('name')
            ->get()
            ->map(function (Worker $worker) use ($assignments, $visits): array {
                $workerAssignments = $assignments->where('worker_id', $worker->id);
                $workerVisits = $visits->where('worker_id', $worker->id);

                return [
                    'id' => $worker->id,
                    'name' => $worker->name,
                    'employee_code' => $worker->employee_code,
                    'status' => $worker->status,
                    'job_role' => $worker->job_role,
                    'assignments_count' => $workerAssignments->count(),
                    'contracts_count' => $workerAssignments->pluck('contract_id')->merge($workerVisits->pluck('contract_id'))->filter()->unique()->count(),
                    'sites_count' => $workerAssignments->pluck('customer_site_id')->merge($workerVisits->pluck('customer_site_id'))->filter()->unique()->count(),
                    'visits_count' => $workerVisits->count(),
                    'completed_visits_count' => $workerVisits->where('status', 'completed')->count(),
                    'worked_minutes' => $this->workedMinutes($workerVisits),
                    'latest_visit' => $workerVisits->max(fn (Visit $visit): ?string => $visit->scheduled_for?->toDateString()),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, Invoice>  $invoices
     * @return list<array<string, mixed>>
     */
    private function performanceTrend(Collection $visits, Collection $invoices): array
    {
        $months = $visits
            ->map(fn (Visit $visit): ?string => $visit->scheduled_for?->format('Y-m'))
            ->merge($invoices->map(fn (Invoice $invoice): ?string => $invoice->issue_date?->format('Y-m')))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $months
            ->map(function (string $month) use ($visits, $invoices): array {
                $monthVisits = $visits->filter(fn (Visit $visit): bool => $visit->scheduled_for?->format('Y-m') === $month);
                $monthInvoices = $invoices->filter(fn (Invoice $invoice): bool => $invoice->issue_date?->format('Y-m') === $month);

                return [
                    'month' => $month,
                    'label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                    'visits_count' => $monthVisits->count(),
                    'completed_visits_count' => $monthVisits->where('status', 'completed')->count(),
                    'worked_minutes' => $this->workedMinutes($monthVisits),
                    'billed_halalas' => (int) $monthInvoices->sum('gross_total_halalas'),
                    'paid_halalas' => (int) $monthInvoices->sum('paid_total_halalas'),
                    'balance_halalas' => (int) $monthInvoices->sum('balance_halalas'),
                ];
            })
            ->values()
            ->all();
    }

    private function serializeInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'contract' => $invoice->contract?->reference,
            'site' => $invoice->contract?->site?->name,
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

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @param  Collection<int, Visit>  $visits
     */
    private function distinctWorkerCount(Collection $assignments, Collection $visits): int
    {
        return $assignments->pluck('worker_id')
            ->merge($visits->pluck('worker_id'))
            ->filter()
            ->unique()
            ->count();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     */
    private function workedMinutes(Collection $visits): int
    {
        return (int) $visits
            ->filter(fn (Visit $visit): bool => $visit->status === 'completed')
            ->sum(fn (Visit $visit): int => $this->visitWorkedMinutes($visit));
    }

    private function visitWorkedMinutes(Visit $visit): int
    {
        if ((int) $visit->actual_minutes > 0) {
            return (int) $visit->actual_minutes;
        }

        if ($visit->checked_in_at && $visit->checked_out_at) {
            return max(0, $visit->checked_in_at->diffInMinutes($visit->checked_out_at));
        }

        return $this->durationMinutes($visit->starts_at, $visit->ends_at);
    }

    private function durationMinutes(?string $startsAt, ?string $endsAt): int
    {
        if (! $startsAt || ! $endsAt) {
            return 0;
        }

        $start = Carbon::createFromFormat('H:i', substr($startsAt, 0, 5));
        $end = Carbon::createFromFormat('H:i', substr($endsAt, 0, 5));

        return max(0, $start->diffInMinutes($end));
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
