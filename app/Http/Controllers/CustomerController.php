<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
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
