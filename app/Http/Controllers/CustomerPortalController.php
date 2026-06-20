<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSarMoney;
use App\Models\BookingRequest;
use App\Models\Contract;
use App\Models\ContractDecision;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitFeedback;
use App\Services\Operations\BookingAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPortalController extends Controller
{
    use HandlesSarMoney;

    public function dashboard(Request $request, BookingAvailability $availability): Response
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());

        $customer->load([
            'sites' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name'),
            'contracts' => fn ($query) => $query
                ->with(['site', 'service', 'servicePackage'])
                ->latest()
                ->limit(8),
            'invoices' => fn ($query) => $query
                ->with('creditNotes')
                ->latest('due_date')
                ->limit(8),
            'bookingRequests' => fn ($query) => $query
                ->with(['site', 'service', 'servicePackage', 'contract'])
                ->latest('requested_for')
                ->latest()
                ->limit(8),
        ]);
        $services = $this->servicePayload();
        $firstService = Service::query()->where('is_active', true)->orderBy('title')->first();
        $upcomingVisits = $this->customerVisitsQuery($customer)
            ->whereDate('scheduled_for', '>=', now()->toDateString())
            ->orderBy('scheduled_for')
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        return Inertia::render('Customer/Dashboard', [
            'customer' => $this->customerPayload($customer),
            'services' => $services,
            'availability' => $firstService
                ? $availability->calendar($firstService, null, now(), 14)
                : [],
            'bookingRequests' => $customer->bookingRequests->map(fn (BookingRequest $bookingRequest): array => $this->bookingRequestPayload($bookingRequest))->values(),
            'dashboardSummary' => $this->dashboardSummary($customer, $upcomingVisits),
            'dashboardActions' => $this->dashboardActions($customer, $upcomingVisits),
            'contracts' => $customer->contracts->map(fn ($contract): array => [
                'id' => $contract->id,
                'reference' => $contract->reference,
                'status' => $contract->status,
                'starts_on' => $contract->starts_on?->toDateString(),
                'ends_on' => $contract->ends_on?->toDateString(),
                'monthly_fee_halalas' => $contract->monthly_fee_halalas,
                'monthly_fee_sar' => $this->money($contract->monthly_fee_halalas),
                'service' => $contract->service?->title,
                'package' => $contract->servicePackage?->name,
                'site' => $contract->site?->name,
                'detail_url' => "/app/customer/contracts/{$contract->id}",
            ])->values(),
            'upcomingVisits' => $upcomingVisits->map(fn (Visit $visit): array => $this->visitPayload($visit))->values(),
            'invoices' => $customer->invoices->map(fn ($invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'gross_total_halalas' => $invoice->gross_total_halalas,
                'gross_total_sar' => $this->money($invoice->gross_total_halalas),
                'paid_total_halalas' => $invoice->paid_total_halalas,
                'paid_total_sar' => $this->money($invoice->paid_total_halalas),
                'balance_halalas' => $invoice->balance_halalas,
                'balance_sar' => $this->money($invoice->balance_halalas),
                'detail_url' => "/app/customer/invoices/{$invoice->id}",
            ])->values(),
        ]);
    }

    public function notifications(Request $request): Response
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        $customer->load(['sites' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name')]);

        $groups = $this->customerNotificationGroups($customer);

        return Inertia::render('Customer/Notifications', [
            'customer' => $this->customerPayload($customer),
            'summary' => $this->customerNotificationSummary($groups),
            'groups' => $groups,
            'backUrl' => '/app/customer',
        ]);
    }

    public function profile(Request $request): Response
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        $customer->load(['sites' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name')]);

        return Inertia::render('Customer/Profile', [
            'customer' => $this->customerPayload($customer),
            'catalog' => $this->customerPortalCatalog(),
            'backUrl' => '/app/customer',
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $this->authorizeCustomerPortal($request);

        $user = $request->user();
        $customer = $this->resolveCustomer($user);
        $validated = $request->validate([
            'customer_type' => ['required', Rule::in(['individual', 'company', 'government'])],
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user?->id)],
            'preferred_channel' => ['required', Rule::in(['whatsapp', 'phone', 'email', 'sms'])],
            'preferred_locale' => ['required', Rule::in(config('cleanops.supported_locales', ['ar', 'en']))],
            'vat_number' => ['nullable', 'string', 'max:32'],
        ]);

        $customer->forceFill([
            ...$validated,
            'status' => 'active',
        ])->save();

        $user?->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'] ?: $user->email,
            'phone' => $validated['phone'] ?? null,
            'locale' => $validated['preferred_locale'],
        ])->save();

        return redirect('/app/customer/profile')->with('success', __('Profile updated.'));
    }

    public function storeSite(Request $request): RedirectResponse
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        $validated = $this->validateCustomerSite($request);
        $makeDefault = (bool) ($validated['is_default'] ?? false) || ! $customer->sites()->exists();
        unset($validated['is_default']);

        $site = $customer->sites()->create([
            ...$validated,
            'is_default' => $makeDefault,
        ]);

        if ($makeDefault) {
            $this->makeDefaultSite($customer, $site);
        }

        return redirect('/app/customer/profile?tab=sites')->with('success', __('Site saved.'));
    }

    public function updateSite(Request $request, CustomerSite $site): RedirectResponse
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        abort_unless((int) $site->customer_id === (int) $customer->id, 404);

        $validated = $this->validateCustomerSite($request);
        $makeDefault = (bool) ($validated['is_default'] ?? false);
        unset($validated['is_default']);

        $site->forceFill([
            ...$validated,
            'is_default' => $makeDefault,
        ])->save();

        if ($makeDefault) {
            $this->makeDefaultSite($customer, $site);
        }

        return redirect('/app/customer/profile?tab=sites')->with('success', __('Site updated.'));
    }

    public function setDefaultSite(Request $request, CustomerSite $site): RedirectResponse
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        abort_unless((int) $site->customer_id === (int) $customer->id, 404);

        $this->makeDefaultSite($customer, $site);

        return redirect('/app/customer/profile?tab=sites')->with('success', __('Default site updated.'));
    }

    public function showBookingRequest(Request $request, BookingRequest $bookingRequest): Response
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        abort_unless((int) $bookingRequest->customer_id === (int) $customer->id, 404);

        $customer->load(['sites' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name')]);
        $bookingRequest->load(['site', 'service', 'servicePackage', 'contract']);

        return Inertia::render('Customer/BookingRequestShow', [
            'customer' => $this->customerPayload($customer),
            'bookingRequest' => $this->bookingRequestDetailPayload($bookingRequest),
            'timeline' => $this->bookingRequestTimeline($bookingRequest),
            'backUrl' => '/app/customer',
        ]);
    }

    public function showContract(Request $request, Contract $contract): Response
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        abort_unless((int) $contract->customer_id === (int) $customer->id, 404);

        $contract->load([
            'site',
            'service',
            'servicePackage',
            'addendums',
            'assignments.worker',
            'invoices.creditNotes',
            'invoices.payments',
            'contractDecisions.reviewedBy',
            'serviceRequests.visit.worker',
        ]);

        $upcomingVisits = $contract->visits()
            ->with(['worker', 'service', 'site', 'checklistItems', 'feedback'])
            ->whereDate('scheduled_for', '>=', now()->toDateString())
            ->orderBy('scheduled_for')
            ->orderBy('starts_at')
            ->limit(12)
            ->get();
        $recentVisits = $contract->visits()
            ->with(['worker', 'service', 'site', 'checklistItems', 'feedback'])
            ->whereDate('scheduled_for', '<', now()->toDateString())
            ->orderByDesc('scheduled_for')
            ->orderByDesc('starts_at')
            ->limit(8)
            ->get();

        return Inertia::render('Customer/ContractShow', [
            'customer' => $this->customerPayload($customer),
            'contract' => $this->contractDetailPayload($contract),
            'upcomingVisits' => $upcomingVisits->map(fn (Visit $visit): array => $this->visitPayload($visit, true))->values(),
            'recentVisits' => $recentVisits->map(fn (Visit $visit): array => $this->visitPayload($visit, true))->values(),
            'invoices' => $contract->invoices
                ->sortByDesc(fn (Invoice $invoice): string => $invoice->due_date?->toDateString() ?? '')
                ->map(fn (Invoice $invoice): array => $this->invoiceSummaryPayload($invoice))
                ->values(),
            'serviceRequests' => $contract->serviceRequests
                ->sortByDesc('id')
                ->take(20)
                ->map(fn (ServiceRequest $serviceRequest): array => $this->serviceRequestPayload($serviceRequest))
                ->values(),
            'contractDecisions' => $contract->contractDecisions
                ->sortByDesc('id')
                ->take(20)
                ->map(fn (ContractDecision $decision): array => $this->contractDecisionPayload($decision))
                ->values(),
            'backUrl' => '/app/customer',
        ]);
    }

    public function storeServiceRequest(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        abort_unless((int) $contract->customer_id === (int) $customer->id, 404);

        $type = (string) $request->input('type');
        $validated = $request->validate([
            'type' => ['required', Rule::in(['reschedule', 'support_ticket'])],
            'visit_id' => [$type === 'reschedule' ? 'required' : 'nullable', 'integer', Rule::exists('visits', 'id')],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'subject' => [$type === 'support_ticket' ? 'required' : 'nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:3000'],
            'requested_for' => [$type === 'reschedule' ? 'required' : 'nullable', 'date', 'after_or_equal:today'],
            'starts_at' => [$type === 'reschedule' ? 'required' : 'nullable', 'date_format:H:i'],
            'ends_at' => [$type === 'reschedule' ? 'required' : 'nullable', 'date_format:H:i', 'after:starts_at'],
        ]);

        $visit = null;

        if (! empty($validated['visit_id'])) {
            $visit = $contract->visits()->findOrFail($validated['visit_id']);
        }

        ServiceRequest::create([
            'type' => $validated['type'],
            'status' => 'pending',
            'priority' => $validated['priority'] ?? 'normal',
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'visit_id' => $visit?->id,
            'subject' => $validated['subject'] ?? $this->defaultServiceRequestSubject($validated['type'], $visit),
            'description' => $validated['description'],
            'requested_for' => $validated['requested_for'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return back()->with('success', __('Your request was sent to operations.'));
    }

    public function storeVisitFeedback(Request $request, Contract $contract, Visit $visit): RedirectResponse
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        abort_unless((int) $contract->customer_id === (int) $customer->id, 404);
        abort_unless((int) $visit->contract_id === (int) $contract->id, 404);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($visit->status !== 'completed') {
            throw ValidationException::withMessages([
                'visit' => __('Only completed visits can be rated.'),
            ]);
        }

        $feedback = VisitFeedback::updateOrCreate(
            ['visit_id' => $visit->id],
            [
                'customer_id' => $customer->id,
                'contract_id' => $contract->id,
                'rating' => (int) $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'submitted_at' => now(),
            ],
        );

        if ($feedback->rating <= 2) {
            $this->createLowRatingFollowUp($feedback);
        }

        return back()->with('success', __('Thank you for your feedback.'));
    }

    public function storeContractDecision(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        abort_unless((int) $contract->customer_id === (int) $customer->id, 404);

        $decision = (string) $request->input('decision');
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'change_requested'])],
            'signer_name' => [$decision === 'accepted' ? 'required' : 'nullable', 'string', 'max:160'],
            'signer_title' => ['nullable', 'string', 'max:120'],
            'signature_text' => [$decision === 'accepted' ? 'required' : 'nullable', 'string', 'max:160'],
            'accepted_terms' => [$decision === 'accepted' ? 'accepted' : 'nullable'],
            'customer_note' => [$decision === 'change_requested' ? 'required' : 'nullable', 'string', 'max:3000'],
        ]);

        ContractDecision::create([
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'decision' => $validated['decision'],
            'status' => 'pending_review',
            'signer_name' => $validated['decision'] === 'accepted' ? $validated['signer_name'] : null,
            'signer_title' => $validated['decision'] === 'accepted' ? ($validated['signer_title'] ?? null) : null,
            'signature_text' => $validated['decision'] === 'accepted' ? $validated['signature_text'] : null,
            'customer_note' => $validated['customer_note'] ?? null,
            'accepted_at' => $validated['decision'] === 'accepted' ? now() : null,
        ]);

        if ($validated['decision'] === 'accepted' && in_array($contract->status, ['draft', 'pending_acceptance', 'pending_customer_acceptance'], true)) {
            $contract->forceFill(['status' => 'active'])->save();
        }

        $message = $validated['decision'] === 'accepted'
            ? __('Contract accepted and signed. Our team will review it.')
            : __('Your requested changes were sent to the contract team.');

        return back()->with('success', $message);
    }

    public function showInvoice(Request $request, Invoice $invoice): Response
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        abort_unless((int) $invoice->customer_id === (int) $customer->id, 404);

        $invoice->load([
            'contract.service',
            'contract.site',
            'payments',
            'paymentProofs',
            'creditNotes',
            'lineItems.visit',
        ]);

        return Inertia::render('Customer/InvoiceShow', [
            'customer' => $this->customerPayload($customer),
            'invoice' => $this->invoiceDetailPayload($invoice),
            'backUrl' => '/app/customer',
        ]);
    }

    public function storePaymentProof(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        abort_unless((int) $invoice->customer_id === (int) $customer->id, 404);

        $validated = $request->validate([
            'amount_sar' => $this->positiveSarMoneyRules(),
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'card', 'cheque', 'online'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'proof_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $amount = $this->sarToHalalas($validated['amount_sar']);

        if ($amount > $invoice->balance_halalas) {
            throw ValidationException::withMessages([
                'amount_sar' => __('Payment proof amount cannot exceed the invoice balance.'),
            ]);
        }

        $path = $request->file('proof_file')->store('payment-proofs', 'public');

        PaymentProof::create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount_halalas' => $amount,
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'paid_on' => $validated['paid_on'],
            'proof_path' => $path,
            'customer_note' => $validated['customer_note'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', __('Payment proof submitted for finance review.'));
    }

    public function printInvoice(Request $request, Invoice $invoice): View
    {
        $this->authorizeCustomerPortal($request);

        $customer = $this->resolveCustomer($request->user());
        abort_unless((int) $invoice->customer_id === (int) $customer->id, 404);

        return view('invoices.print', [
            'invoice' => $invoice->load(['customer', 'contract.site', 'lineItems']),
        ]);
    }

    public function availability(Request $request, BookingAvailability $availability): JsonResponse
    {
        $this->authorizeCustomerPortal($request);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'service_package_id' => ['nullable', 'integer', Rule::exists('service_packages', 'id')],
        ]);

        $service = Service::query()->where('is_active', true)->findOrFail($validated['service_id']);
        $package = $this->activePackageForService($service, $validated['service_package_id'] ?? null);

        return response()->json([
            'availability' => $availability->calendar($service, $package, now(), 14),
        ]);
    }

    public function storeBooking(Request $request, BookingAvailability $availability): RedirectResponse
    {
        $this->authorizeCustomerPortal($request);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'service_package_id' => ['nullable', 'integer', Rule::exists('service_packages', 'id')],
            'customer_site_id' => ['nullable', 'integer', Rule::exists('customer_sites', 'id')],
            'site_name' => ['required_without:customer_site_id', 'nullable', 'string', 'max:120'],
            'city' => ['required_without:customer_site_id', 'nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'requested_for' => ['required', 'date', 'after_or_equal:today'],
            'starts_at' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = $this->resolveCustomer($request->user());
        $service = Service::query()->where('is_active', true)->findOrFail($validated['service_id']);
        $package = $this->activePackageForService($service, $validated['service_package_id'] ?? null);
        $requestedFor = Carbon::parse($validated['requested_for']);

        if (! $availability->isSlotAvailable($service, $package, $requestedFor, $validated['starts_at'])) {
            throw ValidationException::withMessages([
                'starts_at' => __('This time is no longer available. Please choose another slot.'),
            ]);
        }

        if (! empty($validated['customer_site_id'])) {
            $site = $customer->sites()->findOrFail($validated['customer_site_id']);
        } else {
            $site = $customer->sites()->updateOrCreate(
                ['name' => $validated['site_name']],
                [
                    'country_code' => 'SA',
                    'city' => $validated['city'],
                    'district' => $validated['district'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'contact_name' => $customer->name,
                    'contact_phone' => $customer->phone,
                    'is_default' => ! $customer->sites()->where('is_default', true)->exists(),
                ],
            );
        }

        [$endsAt, $durationMinutes, $workerCount] = $availability->window($service, $package, $validated['starts_at']);

        BookingRequest::create([
            'customer_id' => $customer->id,
            'customer_site_id' => $site->id,
            'service_id' => $service->id,
            'service_package_id' => $package?->id,
            'requested_for' => $requestedFor->toDateString(),
            'starts_at' => $validated['starts_at'],
            'ends_at' => $endsAt,
            'worker_count' => $workerCount,
            'duration_minutes' => $durationMinutes,
            'status' => 'pending',
            'customer_note' => $validated['notes'] ?? null,
        ]);

        $locale = app()->getLocale();
        $message = config("cleanops_extra_translations.{$locale}.customerPortal.bookingSuccess", 'Booking request received. The operations team will review and approve it shortly.');

        return back()->with('success', $message);
    }

    private function resolveCustomer(?User $user): Customer
    {
        abort_unless($user, 403);

        $customer = Customer::query()
            ->where('user_id', $user->id)
            ->orWhere(fn ($query) => $query
                ->whereNull('user_id')
                ->where('email', $user->email))
            ->first();

        if ($customer) {
            if (! $customer->user_id) {
                $customer->forceFill(['user_id' => $user->id])->save();
            }

            return $customer;
        }

        return Customer::create([
            'user_id' => $user->id,
            'customer_type' => 'individual',
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'preferred_channel' => 'whatsapp',
            'preferred_locale' => $user->locale ?? app()->getLocale(),
            'status' => 'active',
        ]);
    }

    private function authorizeCustomerPortal(Request $request): void
    {
        abort_unless($request->user()?->role === 'customer', 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'customer_type' => $customer->customer_type,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'preferred_channel' => $customer->preferred_channel,
            'preferred_locale' => $customer->preferred_locale,
            'vat_number' => $customer->vat_number,
            'status' => $customer->status,
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
                'is_default' => $site->is_default,
                'contact_name' => $site->contact_name,
                'contact_phone' => $site->contact_phone,
            ])->values(),
        ];
    }

    /**
     * @param  Collection<int, Visit>  $upcomingVisits
     * @return array<string, int|string>
     */
    private function dashboardSummary(Customer $customer, Collection $upcomingVisits): array
    {
        $openInvoices = $this->openCustomerInvoices($customer);
        $pendingRequests = BookingRequest::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'approved'])
            ->count()
            + ServiceRequest::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['pending', 'in_review'])
                ->count()
            + ContractDecision::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'pending_review')
                ->count();
        $unpaidBalance = $openInvoices->sum(fn (Invoice $invoice): int => $invoice->balance_halalas);

        return [
            'contracts_count' => Contract::query()->where('customer_id', $customer->id)->count(),
            'active_contracts_count' => Contract::query()->where('customer_id', $customer->id)->where('status', 'active')->count(),
            'upcoming_visits_count' => $upcomingVisits->count(),
            'open_invoices_count' => $openInvoices->count(),
            'unpaid_balance_halalas' => $unpaidBalance,
            'unpaid_balance_sar' => $this->money($unpaidBalance),
            'pending_requests_count' => $pendingRequests,
            'saved_sites_count' => CustomerSite::query()->where('customer_id', $customer->id)->count(),
        ];
    }

    /**
     * @param  Collection<int, Visit>  $upcomingVisits
     * @return list<array<string, mixed>>
     */
    private function dashboardActions(Customer $customer, Collection $upcomingVisits): array
    {
        return collect([
            $this->paymentDueAction($customer),
            $this->nextVisitAction($upcomingVisits),
            $this->pendingBookingAction($customer),
            $this->openServiceRequestAction($customer),
            $this->contractDecisionAction($customer),
        ])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function openCustomerInvoices(Customer $customer): Collection
    {
        return Invoice::query()
            ->with('creditNotes')
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['paid', 'void'])
            ->orderBy('due_date')
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->balance_halalas > 0)
            ->values();
    }

    private function paymentDueAction(Customer $customer): ?array
    {
        $invoice = $this->openCustomerInvoices($customer)->first();

        if (! $invoice) {
            return null;
        }

        return [
            'key' => 'payment_due',
            'title' => __('Payment due'),
            'body' => __('Invoice :number has an open balance.', ['number' => $invoice->number]),
            'status' => $invoice->status,
            'tone' => $invoice->due_date && $invoice->due_date->isPast() ? 'danger' : 'warning',
            'due_on' => $invoice->due_date?->toDateString(),
            'href' => "/app/customer/invoices/{$invoice->id}",
            'meta' => [
                'invoice_id' => $invoice->id,
                'number' => $invoice->number,
                'balance_halalas' => $invoice->balance_halalas,
                'balance_sar' => $this->money($invoice->balance_halalas),
            ],
        ];
    }

    /**
     * @param  Collection<int, Visit>  $upcomingVisits
     */
    private function nextVisitAction(Collection $upcomingVisits): ?array
    {
        $visit = $upcomingVisits->first();

        if (! $visit) {
            return null;
        }

        return [
            'key' => 'next_visit',
            'title' => __('Next visit'),
            'body' => __('Your next scheduled service visit is coming up.'),
            'status' => $visit->status,
            'tone' => 'info',
            'due_on' => $visit->scheduled_for?->toDateString(),
            'starts_at' => $this->time($visit->starts_at),
            'href' => $visit->contract_id ? "/app/customer/contracts/{$visit->contract_id}" : '/app/customer',
            'meta' => [
                'visit_id' => $visit->id,
                'contract' => $visit->contract?->reference,
                'service' => $visit->service?->title,
                'site' => $visit->site?->name,
                'worker' => $visit->worker?->name,
            ],
        ];
    }

    private function pendingBookingAction(Customer $customer): ?array
    {
        $bookingRequest = BookingRequest::query()
            ->with(['service', 'site'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'approved'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderBy('requested_for')
            ->first();

        if (! $bookingRequest) {
            return null;
        }

        return [
            'key' => 'booking_pending',
            'title' => __('Booking request'),
            'body' => __('Your request is waiting for the next operation step.'),
            'status' => $bookingRequest->status,
            'tone' => 'warning',
            'due_on' => $bookingRequest->requested_for?->toDateString(),
            'starts_at' => $this->time($bookingRequest->starts_at),
            'href' => "/app/customer/bookings/{$bookingRequest->id}",
            'meta' => [
                'booking_request_id' => $bookingRequest->id,
                'service' => $bookingRequest->service?->title,
                'site' => $bookingRequest->site?->name,
            ],
        ];
    }

    private function openServiceRequestAction(Customer $customer): ?array
    {
        $serviceRequest = ServiceRequest::query()
            ->with(['contract', 'visit'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'in_review'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->first();

        if (! $serviceRequest) {
            return null;
        }

        return [
            'key' => 'service_request',
            'title' => __('Support request'),
            'body' => $serviceRequest->subject ?: __('Your request is being reviewed.'),
            'status' => $serviceRequest->status,
            'tone' => $serviceRequest->priority === 'urgent' ? 'danger' : 'info',
            'due_on' => $serviceRequest->requested_for?->toDateString(),
            'href' => $serviceRequest->contract_id ? "/app/customer/contracts/{$serviceRequest->contract_id}" : '/app/customer',
            'meta' => [
                'service_request_id' => $serviceRequest->id,
                'type' => $serviceRequest->type,
                'priority' => $serviceRequest->priority,
                'contract' => $serviceRequest->contract?->reference,
                'visit_id' => $serviceRequest->visit_id,
            ],
        ];
    }

    private function contractDecisionAction(Customer $customer): ?array
    {
        $decision = ContractDecision::query()
            ->with('contract')
            ->where('customer_id', $customer->id)
            ->where('status', 'pending_review')
            ->latest()
            ->first();

        if (! $decision) {
            return null;
        }

        return [
            'key' => 'contract_decision',
            'title' => __('Contract review'),
            'body' => __('Your contract response is waiting for admin review.'),
            'status' => $decision->decision,
            'tone' => 'info',
            'due_on' => $decision->created_at?->toDateString(),
            'href' => $decision->contract_id ? "/app/customer/contracts/{$decision->contract_id}" : '/app/customer',
            'meta' => [
                'contract_decision_id' => $decision->id,
                'contract' => $decision->contract?->reference,
            ],
        ];
    }

    /**
     * @return array<string, list<array{key: string, label: string}>>
     */
    private function customerPortalCatalog(): array
    {
        return [
            'customerTypes' => [
                ['key' => 'individual', 'label' => 'Individual'],
                ['key' => 'company', 'label' => 'Company'],
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCustomerSite(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'country_code' => ['nullable', Rule::in(['SA'])],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'formatted_address' => ['nullable', 'string', 'max:1000'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function makeDefaultSite(Customer $customer, CustomerSite $site): void
    {
        $customer->sites()->whereKeyNot($site->id)->update(['is_default' => false]);
        $site->forceFill(['is_default' => true])->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function servicePayload(): array
    {
        return Service::query()
            ->where('is_active', true)
            ->with(['packages' => fn ($query) => $query->where('is_active', true)->orderBy('price_halalas')])
            ->orderBy('title')
            ->get()
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'title' => $service->title,
                'category' => $service->category,
                'description' => $service->description,
                'base_price_halalas' => $service->base_price_halalas,
                'base_price_sar' => $this->money($service->base_price_halalas),
                'pricing_type' => $service->pricing_type,
                'packages' => $service->packages->map(fn (ServicePackage $package): array => [
                    'id' => $package->id,
                    'service_id' => $package->service_id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'billing_cycle' => $package->billing_cycle,
                    'visit_frequency' => $package->visit_frequency,
                    'visits_per_week' => $package->visits_per_week,
                    'hours_per_visit' => $package->hours_per_visit,
                    'worker_count' => $package->worker_count,
                    'duration_minutes' => $package->duration_minutes,
                    'price_halalas' => $package->price_halalas,
                    'price_sar' => $this->money($package->price_halalas),
                ])->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function bookingNotes(Customer $customer, CustomerSite $site, Service $service, ?ServicePackage $package, array $validated): string
    {
        return collect([
            "Customer ID: {$customer->id}",
            "Site ID: {$site->id}",
            "Site: {$site->name}, {$site->city}",
            "Service: {$service->title}",
            $package ? "Package: {$package->name}" : null,
            ! empty($validated['requested_for']) ? "Requested date: {$validated['requested_for']}" : null,
            ! empty($validated['starts_at']) ? "Requested time: {$validated['starts_at']}" : null,
            ! empty($validated['notes']) ? "Customer notes: {$validated['notes']}" : null,
        ])->filter()->implode(PHP_EOL);
    }

    private function activePackageForService(Service $service, mixed $packageId): ?ServicePackage
    {
        if (blank($packageId)) {
            return null;
        }

        return ServicePackage::query()
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->findOrFail($packageId);
    }

    private function bookingRequestPayload(BookingRequest $bookingRequest): array
    {
        return [
            'id' => $bookingRequest->id,
            'status' => $bookingRequest->status,
            'requested_for' => $bookingRequest->requested_for?->toDateString(),
            'starts_at' => $this->time($bookingRequest->starts_at),
            'ends_at' => $this->time($bookingRequest->ends_at),
            'worker_count' => $bookingRequest->worker_count,
            'duration_minutes' => $bookingRequest->duration_minutes,
            'customer_note' => $bookingRequest->customer_note,
            'admin_note' => $bookingRequest->admin_note,
            'service' => $bookingRequest->service?->title,
            'package' => $bookingRequest->servicePackage?->name,
            'site' => $bookingRequest->site?->name,
            'contract_reference' => $bookingRequest->contract?->reference,
            'detail_url' => "/app/customer/bookings/{$bookingRequest->id}",
        ];
    }

    private function bookingRequestDetailPayload(BookingRequest $bookingRequest): array
    {
        return [
            ...$this->bookingRequestPayload($bookingRequest),
            'created_at' => $bookingRequest->created_at?->toDateTimeString(),
            'updated_at' => $bookingRequest->updated_at?->toDateTimeString(),
            'approved_at' => $bookingRequest->approved_at?->toDateTimeString(),
            'rejected_at' => $bookingRequest->rejected_at?->toDateTimeString(),
            'service' => $bookingRequest->service ? [
                'id' => $bookingRequest->service->id,
                'title' => $bookingRequest->service->title,
                'category' => $bookingRequest->service->category,
                'description' => $bookingRequest->service->description,
            ] : null,
            'package' => $bookingRequest->servicePackage ? [
                'id' => $bookingRequest->servicePackage->id,
                'name' => $bookingRequest->servicePackage->name,
                'description' => $bookingRequest->servicePackage->description,
                'billing_cycle' => $bookingRequest->servicePackage->billing_cycle,
                'visit_frequency' => $bookingRequest->servicePackage->visit_frequency,
                'visits_per_week' => $bookingRequest->servicePackage->visits_per_week,
                'hours_per_visit' => $bookingRequest->servicePackage->hours_per_visit,
                'worker_count' => $bookingRequest->servicePackage->worker_count,
                'duration_minutes' => $bookingRequest->servicePackage->duration_minutes,
                'price_sar' => $this->money($bookingRequest->servicePackage->price_halalas),
            ] : null,
            'site' => $bookingRequest->site ? [
                'id' => $bookingRequest->site->id,
                'name' => $bookingRequest->site->name,
                'city' => $bookingRequest->site->city,
                'district' => $bookingRequest->site->district,
                'address' => $bookingRequest->site->address,
                'contact_name' => $bookingRequest->site->contact_name,
                'contact_phone' => $bookingRequest->site->contact_phone,
            ] : null,
            'contract' => $bookingRequest->contract ? [
                'id' => $bookingRequest->contract->id,
                'reference' => $bookingRequest->contract->reference,
                'status' => $bookingRequest->contract->status,
                'detail_url' => "/app/customer/contracts/{$bookingRequest->contract->id}",
            ] : null,
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function bookingRequestTimeline(BookingRequest $bookingRequest): array
    {
        $status = $bookingRequest->status;
        $isApproved = in_array($status, ['approved', 'converted'], true) || $bookingRequest->approved_at !== null;
        $isConverted = $status === 'converted' || $bookingRequest->contract_id !== null;
        $isRejected = $status === 'rejected';
        $reviewedAt = $bookingRequest->approved_at ?? $bookingRequest->rejected_at ?? ($status === 'pending' ? null : $bookingRequest->updated_at);

        if ($isRejected) {
            return [
                $this->bookingTimelineStep('submitted', 'complete', $bookingRequest->created_at?->toDateTimeString()),
                $this->bookingTimelineStep('operations_review', 'complete', $reviewedAt?->toDateTimeString()),
                $this->bookingTimelineStep('rejected', 'complete', $bookingRequest->rejected_at?->toDateTimeString() ?? $reviewedAt?->toDateTimeString(), $bookingRequest->admin_note),
                $this->bookingTimelineStep('contract_created', 'blocked', null),
            ];
        }

        return [
            $this->bookingTimelineStep('submitted', 'complete', $bookingRequest->created_at?->toDateTimeString()),
            $this->bookingTimelineStep('operations_review', $status === 'pending' ? 'current' : 'complete', $reviewedAt?->toDateTimeString()),
            $this->bookingTimelineStep('approved', $isApproved ? 'complete' : 'pending', $bookingRequest->approved_at?->toDateTimeString(), $bookingRequest->admin_note),
            $this->bookingTimelineStep('contract_created', $isConverted ? 'complete' : ($isApproved ? 'current' : 'pending'), $bookingRequest->contract?->created_at?->toDateTimeString()),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function bookingTimelineStep(string $key, string $status, ?string $date = null, ?string $note = null): array
    {
        return [
            'key' => $key,
            'status' => $status,
            'date' => $date,
            'note' => $note,
        ];
    }

    private function defaultServiceRequestSubject(string $type, ?Visit $visit): string
    {
        if ($type === 'reschedule') {
            $date = $visit?->scheduled_for?->toDateString();

            return $date ? __('Reschedule visit for :date', ['date' => $date]) : __('Reschedule visit');
        }

        return __('Support ticket');
    }

    private function serviceRequestPayload(ServiceRequest $serviceRequest): array
    {
        return [
            'id' => $serviceRequest->id,
            'type' => $serviceRequest->type,
            'status' => $serviceRequest->status,
            'priority' => $serviceRequest->priority,
            'subject' => $serviceRequest->subject,
            'description' => $serviceRequest->description,
            'requested_for' => $serviceRequest->requested_for?->toDateString(),
            'starts_at' => $this->time($serviceRequest->starts_at),
            'ends_at' => $this->time($serviceRequest->ends_at),
            'admin_note' => $serviceRequest->admin_note,
            'created_at' => $serviceRequest->created_at?->toDateTimeString(),
            'resolved_at' => $serviceRequest->resolved_at?->toDateTimeString(),
            'visit' => $serviceRequest->visit ? [
                'id' => $serviceRequest->visit->id,
                'scheduled_for' => $serviceRequest->visit->scheduled_for?->toDateString(),
                'starts_at' => $this->time($serviceRequest->visit->starts_at),
                'ends_at' => $this->time($serviceRequest->visit->ends_at),
                'status' => $serviceRequest->visit->status,
                'worker' => $serviceRequest->visit->worker ? [
                    'id' => $serviceRequest->visit->worker->id,
                    'name' => $serviceRequest->visit->worker->name,
                ] : null,
            ] : null,
        ];
    }

    private function contractDecisionPayload(ContractDecision $decision): array
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
            'reviewed_by' => $decision->reviewedBy ? [
                'id' => $decision->reviewedBy->id,
                'name' => $decision->reviewedBy->name,
            ] : null,
        ];
    }

    private function contractDetailPayload(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'reference' => $contract->reference,
            'status' => $contract->status,
            'starts_on' => $contract->starts_on?->toDateString(),
            'ends_on' => $contract->ends_on?->toDateString(),
            'monthly_fee_halalas' => $contract->monthly_fee_halalas,
            'monthly_fee_sar' => $this->money($contract->monthly_fee_halalas),
            'vat_rate' => $contract->vat_rate,
            'prices_include_vat' => $contract->prices_include_vat,
            'billing_cycle' => $contract->billing_cycle,
            'notice_days' => $contract->notice_days,
            'auto_renews' => $contract->auto_renews,
            'agreed_workers' => $contract->agreed_workers,
            'visits_per_week' => $contract->visits_per_week,
            'hours_per_visit' => $contract->hours_per_visit,
            'planned_weekly_minutes' => $contract->planned_weekly_minutes,
            'included_materials' => $contract->included_materials,
            'material_policy' => $contract->material_policy,
            'estimated_material_cost_sar' => $this->money($contract->estimated_material_cost_halalas),
            'extra_hour_rate_sar' => $this->money($contract->extra_hour_rate_halalas),
            'overtime_policy' => $contract->overtime_policy,
            'service_scope' => $contract->service_scope ?? [],
            'terms_and_conditions' => $contract->terms_and_conditions,
            'special_terms' => $contract->special_terms,
            'payment_plan' => $contract->payment_plan ?? [],
            'print_url' => "/app/customer/contracts/{$contract->id}/print",
            'download_url' => "/app/customer/contracts/{$contract->id}/download",
            'site' => $contract->site ? [
                'id' => $contract->site->id,
                'name' => $contract->site->name,
                'city' => $contract->site->city,
                'district' => $contract->site->district,
                'address' => $contract->site->address,
            ] : null,
            'service' => $contract->service ? [
                'id' => $contract->service->id,
                'title' => $contract->service->title,
                'category' => $contract->service->category,
                'description' => $contract->service->description,
            ] : null,
            'service_package' => $contract->servicePackage ? [
                'id' => $contract->servicePackage->id,
                'name' => $contract->servicePackage->name,
                'description' => $contract->servicePackage->description,
            ] : null,
            'assignments' => $contract->assignments->map(fn ($assignment): array => [
                'id' => $assignment->id,
                'weekday' => $assignment->weekday,
                'starts_at' => $this->time($assignment->starts_at),
                'ends_at' => $this->time($assignment->ends_at),
                'team_role' => $assignment->team_role,
                'worker' => $assignment->worker ? [
                    'id' => $assignment->worker->id,
                    'name' => $assignment->worker->name,
                    'job_role' => $assignment->worker->job_role,
                ] : null,
                'task_instructions' => $assignment->task_instructions ?? [],
            ])->values(),
            'addendums' => $contract->addendums->map(fn ($addendum): array => [
                'id' => $addendum->id,
                'number' => $addendum->number,
                'title' => $addendum->title,
                'summary' => $addendum->summary,
                'effective_on' => $addendum->effective_on?->toDateString(),
            ])->values(),
        ];
    }

    private function invoiceSummaryPayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'gross_total_sar' => $this->money($invoice->gross_total_halalas),
            'paid_total_sar' => $this->money($invoice->paid_total_halalas),
            'balance_sar' => $this->money($invoice->balance_halalas),
            'detail_url' => "/app/customer/invoices/{$invoice->id}",
        ];
    }

    private function invoiceDetailPayload(Invoice $invoice): array
    {
        return [
            ...$this->invoiceSummaryPayload($invoice),
            'net_total_sar' => $this->money($invoice->net_total_halalas),
            'vat_total_sar' => $this->money($invoice->vat_total_halalas),
            'vat_rate' => $invoice->vat_rate,
            'contract' => $invoice->contract ? [
                'id' => $invoice->contract->id,
                'reference' => $invoice->contract->reference,
                'detail_url' => "/app/customer/contracts/{$invoice->contract->id}",
                'service' => $invoice->contract->service?->title,
                'site' => $invoice->contract->site?->name,
            ] : null,
            'line_items' => $invoice->lineItems->map(fn (InvoiceLineItem $lineItem): array => [
                'id' => $lineItem->id,
                'line_type' => $lineItem->line_type,
                'description' => $lineItem->description,
                'quantity' => $lineItem->quantity,
                'unit_label' => $lineItem->unit_label,
                'unit_price_sar' => $this->money($lineItem->unit_price_halalas),
                'net_total_sar' => $this->money($lineItem->net_total_halalas),
                'vat_total_sar' => $this->money($lineItem->vat_total_halalas),
                'gross_total_sar' => $this->money($lineItem->gross_total_halalas),
                'visit_date' => $lineItem->visit?->scheduled_for?->toDateString(),
            ])->values(),
            'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount_sar' => $this->money($payment->amount_halalas),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'received_at' => $payment->received_at?->toDateTimeString(),
            ])->values(),
            'payment_proofs' => $invoice->paymentProofs
                ->sortByDesc('id')
                ->map(fn (PaymentProof $proof): array => $this->paymentProofPayload($proof))
                ->values(),
            'credit_notes' => $invoice->creditNotes->map(fn ($creditNote): array => [
                'id' => $creditNote->id,
                'number' => $creditNote->number,
                'amount_sar' => $this->money($creditNote->amount_halalas),
                'status' => $creditNote->status,
                'reason' => $creditNote->reason,
            ])->values(),
            'print_url' => "/app/customer/invoices/{$invoice->id}/print",
        ];
    }

    private function paymentProofPayload(PaymentProof $proof): array
    {
        return [
            'id' => $proof->id,
            'amount_sar' => $this->money($proof->amount_halalas),
            'method' => $proof->method,
            'reference' => $proof->reference,
            'paid_on' => $proof->paid_on?->toDateString(),
            'proof_url' => Storage::disk('public')->url($proof->proof_path),
            'customer_note' => $proof->customer_note,
            'status' => $proof->status,
            'admin_note' => $proof->admin_note,
            'reviewed_at' => $proof->reviewed_at?->toDateTimeString(),
        ];
    }

    private function visitPayload(Visit $visit, bool $includeChecklist = false): array
    {
        $feedback = $visit->feedback;

        return [
            'id' => $visit->id,
            'scheduled_for' => $visit->scheduled_for?->toDateString(),
            'starts_at' => $this->time($visit->starts_at),
            'ends_at' => $this->time($visit->ends_at),
            'status' => $visit->status,
            'checked_in_at' => $visit->checked_in_at?->toDateTimeString(),
            'checked_out_at' => $visit->checked_out_at?->toDateTimeString(),
            'issue_note' => $visit->issue_note,
            'photos' => $visit->photos ?? [],
            'planned_minutes' => $visit->planned_minutes,
            'actual_minutes' => $visit->actual_minutes,
            'can_submit_feedback' => $visit->status === 'completed' && $feedback === null,
            'feedback' => $feedback ? [
                'id' => $feedback->id,
                'rating' => $feedback->rating,
                'comment' => $feedback->comment,
                'submitted_at' => $feedback->submitted_at?->toDateTimeString(),
            ] : null,
            'contract' => $visit->contract ? [
                'id' => $visit->contract->id,
                'reference' => $visit->contract->reference,
                'detail_url' => "/app/customer/contracts/{$visit->contract->id}",
            ] : null,
            'service' => $visit->service?->title ?? $visit->contract?->service?->title,
            'site' => $visit->site?->name ?? $visit->contract?->site?->name,
            'worker' => $visit->worker ? [
                'id' => $visit->worker->id,
                'name' => $visit->worker->name,
                'job_role' => $visit->worker->job_role,
            ] : null,
            'checklist' => $includeChecklist
                ? $visit->checklistItems->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'status' => $item->status,
                    'is_required' => $item->is_required,
                    'completed_at' => $item->completed_at?->toDateTimeString(),
                    'notes' => $item->notes,
                    'photo_path' => $item->photo_path,
                ])->values()
                : [],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function customerNotificationGroups(Customer $customer): array
    {
        $groups = [
            'contracts' => $this->contractNotificationItems($customer),
            'finance' => $this->financeNotificationItems($customer),
            'schedule' => $this->scheduleNotificationItems($customer),
            'requests' => $this->requestNotificationItems($customer),
        ];

        return [
            'action_required' => $this->customerActionNotificationItems($groups),
            ...$groups,
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $groups
     * @return array<string, int>
     */
    private function customerNotificationSummary(array $groups): array
    {
        $categoryKeys = ['contracts', 'finance', 'schedule', 'requests'];
        $items = collect($categoryKeys)->flatMap(fn (string $key): array => $groups[$key] ?? []);

        return [
            'total_count' => $items->count(),
            'action_count' => $items->where('requires_action', true)->count(),
            'contract_count' => count($groups['contracts'] ?? []),
            'finance_count' => count($groups['finance'] ?? []),
            'schedule_count' => count($groups['schedule'] ?? []),
            'request_count' => count($groups['requests'] ?? []),
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private function customerActionNotificationItems(array $groups): array
    {
        return collect(['contracts', 'finance', 'schedule', 'requests'])
            ->flatMap(fn (string $key): array => $groups[$key] ?? [])
            ->filter(fn (array $item): bool => (bool) ($item['requires_action'] ?? false))
            ->sortByDesc(fn (array $item): string => (string) ($item['occurred_at'] ?? $item['due_on'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contractNotificationItems(Customer $customer): array
    {
        $pendingContracts = Contract::query()
            ->with(['site', 'service', 'contractDecisions'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['draft', 'pending_acceptance', 'pending_customer_acceptance'])
            ->latest()
            ->limit(10)
            ->get()
            ->filter(function (Contract $contract): bool {
                $hasAcceptedDecision = $contract->contractDecisions
                    ->contains(fn (ContractDecision $decision): bool => $decision->decision === 'accepted');
                $hasPendingDecision = $contract->contractDecisions
                    ->contains(fn (ContractDecision $decision): bool => $decision->status === 'pending_review');

                return ! $hasAcceptedDecision && ! $hasPendingDecision;
            })
            ->map(fn (Contract $contract): array => [
                'id' => "contract-{$contract->id}-acceptance",
                'category' => 'contracts',
                'type' => 'contract_acceptance',
                'title' => __('Contract ready for review'),
                'body' => __('Review and sign :reference to continue scheduling and invoicing.', [
                    'reference' => $contract->reference,
                ]),
                'status' => $contract->status,
                'tone' => 'amber',
                'occurred_at' => $contract->updated_at?->toDateTimeString(),
                'due_on' => null,
                'href' => "/app/customer/contracts/{$contract->id}",
                'requires_action' => true,
                'meta' => [
                    'reference' => $contract->reference,
                    'service' => $contract->service?->title,
                    'site' => $contract->site?->name,
                ],
            ])
            ->values()
            ->all();

        $decisionUpdates = ContractDecision::query()
            ->with(['contract.service', 'contract.site'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (ContractDecision $decision): array {
                $contract = $decision->contract;

                return [
                    'id' => "contract-decision-{$decision->id}",
                    'category' => 'contracts',
                    'type' => 'contract_decision',
                    'title' => __('Contract decision update'),
                    'body' => __('Your :decision decision is currently :status.', [
                        'decision' => str_replace('_', ' ', $decision->decision),
                        'status' => str_replace('_', ' ', $decision->status),
                    ]),
                    'status' => $decision->status,
                    'tone' => $decision->status === 'reviewed' ? 'emerald' : 'blue',
                    'occurred_at' => $decision->updated_at?->toDateTimeString(),
                    'due_on' => null,
                    'href' => $contract ? "/app/customer/contracts/{$contract->id}" : '/app/customer',
                    'requires_action' => false,
                    'meta' => [
                        'reference' => $contract?->reference,
                        'service' => $contract?->service?->title,
                        'site' => $contract?->site?->name,
                    ],
                ];
            })
            ->values()
            ->all();

        return [...$pendingContracts, ...$decisionUpdates];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function financeNotificationItems(Customer $customer): array
    {
        $invoiceItems = Invoice::query()
            ->with(['contract.service', 'contract.site', 'creditNotes'])
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->orderBy('due_date')
            ->limit(12)
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->balance_halalas > 0)
            ->map(function (Invoice $invoice): array {
                $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date)->startOfDay() : null;
                $today = now()->startOfDay();
                $isOverdue = $dueDate?->isBefore($today) ?? false;
                $isDueSoon = $dueDate?->betweenIncluded($today, $today->copy()->addDays(7)) ?? false;
                $tone = $isOverdue ? 'red' : ($isDueSoon ? 'amber' : 'blue');

                return [
                    'id' => "invoice-{$invoice->id}",
                    'category' => 'finance',
                    'type' => 'invoice_due',
                    'title' => $isOverdue ? __('Invoice overdue') : __('Invoice payment due'),
                    'body' => __('Invoice :number has a balance of SAR :amount.', [
                        'number' => $invoice->number,
                        'amount' => $this->money($invoice->balance_halalas),
                    ]),
                    'status' => $isOverdue ? 'overdue' : $invoice->status,
                    'tone' => $tone,
                    'occurred_at' => $invoice->updated_at?->toDateTimeString(),
                    'due_on' => $invoice->due_date?->toDateString(),
                    'href' => "/app/customer/invoices/{$invoice->id}",
                    'requires_action' => true,
                    'meta' => [
                        'invoice' => $invoice->number,
                        'balance_sar' => $this->money($invoice->balance_halalas),
                        'service' => $invoice->contract?->service?->title,
                        'site' => $invoice->contract?->site?->name,
                    ],
                ];
            })
            ->values()
            ->all();

        $proofItems = PaymentProof::query()
            ->with(['invoice.contract.service', 'invoice.contract.site'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (PaymentProof $proof): array {
                $invoice = $proof->invoice;

                return [
                    'id' => "payment-proof-{$proof->id}",
                    'category' => 'finance',
                    'type' => 'payment_proof',
                    'title' => __('Payment proof update'),
                    'body' => __('Your SAR :amount payment proof is :status.', [
                        'amount' => $this->money($proof->amount_halalas),
                        'status' => str_replace('_', ' ', $proof->status),
                    ]),
                    'status' => $proof->status,
                    'tone' => match ($proof->status) {
                        'approved' => 'emerald',
                        'rejected' => 'red',
                        default => 'blue',
                    },
                    'occurred_at' => $proof->updated_at?->toDateTimeString(),
                    'due_on' => $proof->paid_on?->toDateString(),
                    'href' => $invoice ? "/app/customer/invoices/{$invoice->id}" : '/app/customer',
                    'requires_action' => $proof->status === 'rejected',
                    'meta' => [
                        'invoice' => $invoice?->number,
                        'amount_sar' => $this->money($proof->amount_halalas),
                        'service' => $invoice?->contract?->service?->title,
                        'site' => $invoice?->contract?->site?->name,
                    ],
                ];
            })
            ->values()
            ->all();

        return [...$invoiceItems, ...$proofItems];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scheduleNotificationItems(Customer $customer): array
    {
        return $this->customerVisitsQuery($customer)
            ->whereDate('scheduled_for', '>=', now()->toDateString())
            ->whereDate('scheduled_for', '<=', now()->addDays(14)->toDateString())
            ->orderBy('scheduled_for')
            ->orderBy('starts_at')
            ->limit(12)
            ->get()
            ->map(function (Visit $visit): array {
                $isToday = $visit->scheduled_for?->isSameDay(now()) ?? false;
                $isTomorrow = $visit->scheduled_for?->isSameDay(now()->addDay()) ?? false;

                return [
                    'id' => "visit-{$visit->id}",
                    'category' => 'schedule',
                    'type' => 'visit_upcoming',
                    'title' => $isToday ? __('Visit scheduled today') : __('Upcoming visit'),
                    'body' => __('Your visit is scheduled for :date from :start to :end.', [
                        'date' => $visit->scheduled_for?->toDateString() ?? '-',
                        'start' => $this->time($visit->starts_at) ?? '--:--',
                        'end' => $this->time($visit->ends_at) ?? '--:--',
                    ]),
                    'status' => $visit->status,
                    'tone' => $isToday || $isTomorrow ? 'amber' : 'blue',
                    'occurred_at' => $visit->updated_at?->toDateTimeString(),
                    'due_on' => $visit->scheduled_for?->toDateString(),
                    'href' => $visit->contract ? "/app/customer/contracts/{$visit->contract->id}" : '/app/customer',
                    'requires_action' => false,
                    'meta' => [
                        'contract' => $visit->contract?->reference,
                        'service' => $visit->service?->title ?? $visit->contract?->service?->title,
                        'site' => $visit->site?->name ?? $visit->contract?->site?->name,
                        'worker' => $visit->worker?->name,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function requestNotificationItems(Customer $customer): array
    {
        $bookingItems = BookingRequest::query()
            ->with(['site', 'service', 'servicePackage', 'contract'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (BookingRequest $bookingRequest): array => [
                'id' => "booking-request-{$bookingRequest->id}",
                'category' => 'requests',
                'type' => 'booking_request',
                'title' => __('Booking request update'),
                'body' => __('Your :service request is :status.', [
                    'service' => $bookingRequest->service?->title ?? __('service'),
                    'status' => str_replace('_', ' ', $bookingRequest->status),
                ]),
                'status' => $bookingRequest->status,
                'tone' => match ($bookingRequest->status) {
                    'approved', 'converted' => 'emerald',
                    'rejected' => 'red',
                    default => 'amber',
                },
                'occurred_at' => $bookingRequest->updated_at?->toDateTimeString(),
                'due_on' => $bookingRequest->requested_for?->toDateString(),
                'href' => "/app/customer/bookings/{$bookingRequest->id}",
                'requires_action' => false,
                'meta' => [
                    'service' => $bookingRequest->service?->title,
                    'package' => $bookingRequest->servicePackage?->name,
                    'site' => $bookingRequest->site?->name,
                    'contract' => $bookingRequest->contract?->reference,
                ],
            ])
            ->values()
            ->all();

        $serviceRequestItems = ServiceRequest::query()
            ->with(['contract.service', 'contract.site', 'visit'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (ServiceRequest $serviceRequest): array {
                $contract = $serviceRequest->contract;

                return [
                    'id' => "service-request-{$serviceRequest->id}",
                    'category' => 'requests',
                    'type' => $serviceRequest->type,
                    'title' => $serviceRequest->type === 'reschedule' ? __('Reschedule request update') : __('Support ticket update'),
                    'body' => __('Your request ":subject" is :status.', [
                        'subject' => $serviceRequest->subject,
                        'status' => str_replace('_', ' ', $serviceRequest->status),
                    ]),
                    'status' => $serviceRequest->status,
                    'tone' => match ($serviceRequest->status) {
                        'approved', 'resolved' => 'emerald',
                        'rejected' => 'red',
                        default => 'blue',
                    },
                    'occurred_at' => $serviceRequest->updated_at?->toDateTimeString(),
                    'due_on' => $serviceRequest->requested_for?->toDateString(),
                    'href' => $contract ? "/app/customer/contracts/{$contract->id}" : '/app/customer',
                    'requires_action' => $serviceRequest->status === 'rejected',
                    'meta' => [
                        'contract' => $contract?->reference,
                        'service' => $contract?->service?->title,
                        'site' => $contract?->site?->name,
                        'priority' => $serviceRequest->priority,
                    ],
                ];
            })
            ->values()
            ->all();

        return [...$bookingItems, ...$serviceRequestItems];
    }

    private function customerVisitsQuery(Customer $customer)
    {
        return Visit::query()
            ->with(['contract.service', 'contract.site', 'worker', 'site', 'service', 'checklistItems', 'feedback'])
            ->whereHas('contract', fn ($query) => $query->where('customer_id', $customer->id));
    }

    private function createLowRatingFollowUp(VisitFeedback $feedback): ServiceRequest
    {
        $feedback->loadMissing('visit', 'contract');
        $visit = $feedback->visit;

        return ServiceRequest::query()->firstOrCreate(
            [
                'type' => 'quality_follow_up',
                'visit_id' => $feedback->visit_id,
            ],
            [
                'status' => 'pending',
                'priority' => 'urgent',
                'customer_id' => $feedback->customer_id,
                'contract_id' => $feedback->contract_id,
                'subject' => 'Low customer rating follow-up',
                'description' => $feedback->comment ?: __('Customer submitted a low visit rating.'),
                'requested_for' => $visit?->scheduled_for?->toDateString(),
                'starts_at' => $this->time($visit?->starts_at),
                'ends_at' => $this->time($visit?->ends_at),
            ],
        );
    }

    private function money(int $halalas): string
    {
        return number_format($halalas / 100, 2);
    }

    private function time(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }
}
