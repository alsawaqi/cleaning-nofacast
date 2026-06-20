<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle, ArrowRight, BellRing, CalendarDays, CheckCircle2, Clock, ClipboardList, Eye, Languages, Loader2, LogOut, MapPin, PackageCheck, ReceiptText, Sparkles, UserRound, WalletCards } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from '../../lib/i18n';
import type { AppSharedProps } from '../../types';

type PortalSite = {
    id: number;
    country_code?: string | null;
    name: string;
    city: string;
    district?: string | null;
    address?: string | null;
    is_default: boolean;
    contact_name?: string | null;
    contact_phone?: string | null;
};

type PortalCustomer = {
    id: number;
    name: string;
    customer_type: string;
    phone?: string | null;
    email?: string | null;
    status: string;
    sites: PortalSite[];
};

type PortalPackage = {
    id: number;
    service_id: number;
    name: string;
    description?: string | null;
    billing_cycle: string;
    visit_frequency: string;
    visits_per_week?: number | null;
    hours_per_visit?: string | number | null;
    worker_count: number;
    duration_minutes: number;
    price_sar: string;
};

type PortalService = {
    id: number;
    title: string;
    category: string;
    description?: string | null;
    base_price_sar: string;
    pricing_type: string;
    packages: PortalPackage[];
};

type PortalContract = {
    id: number;
    reference: string;
    status: string;
    starts_on?: string | null;
    ends_on?: string | null;
    monthly_fee_sar: string;
    service?: string | null;
    package?: string | null;
    site?: string | null;
    detail_url: string;
};

type PortalInvoice = {
    id: number;
    number: string;
    status: string;
    issue_date?: string | null;
    due_date?: string | null;
    gross_total_sar: string;
    paid_total_sar: string;
    balance_sar: string;
    detail_url: string;
};

type PortalVisit = {
    id: number;
    scheduled_for?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    status: string;
    service?: string | null;
    site?: string | null;
    worker?: {
        id: number;
        name: string;
        job_role?: string | null;
    } | null;
    contract?: {
        id: number;
        reference: string;
        detail_url: string;
    } | null;
};

type AvailabilitySlot = {
    starts_at: string;
    ends_at: string;
    label: string;
    duration_minutes: number;
    required_workers: number;
    available_workers: number;
    available: boolean;
};

type AvailabilityDay = {
    date: string;
    weekday: string;
    slots: AvailabilitySlot[];
};

type PortalBookingRequest = {
    id: number;
    status: string;
    requested_for?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    worker_count: number;
    duration_minutes: number;
    customer_note?: string | null;
    admin_note?: string | null;
    service?: string | null;
    package?: string | null;
    site?: string | null;
    contract_reference?: string | null;
    detail_url: string;
};

type DashboardSummary = {
    contracts_count: number;
    active_contracts_count: number;
    upcoming_visits_count: number;
    open_invoices_count: number;
    unpaid_balance_halalas: number;
    unpaid_balance_sar: string;
    pending_requests_count: number;
    saved_sites_count: number;
};

type DashboardAction = {
    key: string;
    title: string;
    body: string;
    status: string;
    tone: string;
    due_on?: string | null;
    starts_at?: string | null;
    href: string;
    meta?: Record<string, unknown>;
};

const props = defineProps<{
    customer: PortalCustomer;
    services: PortalService[];
    availability: AvailabilityDay[];
    bookingRequests: PortalBookingRequest[];
    dashboardSummary: DashboardSummary;
    dashboardActions: DashboardAction[];
    contracts: PortalContract[];
    upcomingVisits: PortalVisit[];
    invoices: PortalInvoice[];
}>();

const page = usePage<AppSharedProps>();
const success = computed(() => page.props.flash?.success);
const { locale, t } = useI18n();
const activeTab = ref<'overview' | 'booking' | 'visits' | 'records'>('overview');

const firstService = props.services[0];
const firstSite = props.customer.sites.find((site) => site.is_default) ?? props.customer.sites[0];

const form = useForm({
    service_id: firstService ? String(firstService.id) : '',
    service_package_id: '',
    customer_site_id: firstSite ? String(firstSite.id) : '',
    site_name: firstSite?.name ?? 'Primary site',
    city: firstSite?.city ?? 'Riyadh',
    district: firstSite?.district ?? '',
    address: firstSite?.address ?? '',
    requested_for: '',
    starts_at: '',
    notes: '',
});
const availabilityDays = ref<AvailabilityDay[]>(props.availability ?? []);
const availabilityLoading = ref(false);

const selectedService = computed(() => props.services.find((service) => String(service.id) === String(form.service_id)));
const selectedPackages = computed(() => selectedService.value?.packages ?? []);
const selectedPackage = computed(() => selectedPackages.value.find((item) => String(item.id) === String(form.service_package_id)));
const selectedBookingSite = computed(() => props.customer.sites.find((site) => String(site.id) === String(form.customer_site_id)));
const availableSlotCount = computed(() => availabilityDays.value.reduce((total, day) => total + day.slots.filter((slot) => slot.available).length, 0));
const selectedSlotLabel = computed(() => form.requested_for && form.starts_at
    ? `${form.requested_for} / ${form.starts_at}`
    : t('customerPortal.noSlotSelected', 'No slot selected'));
const primaryAction = computed(() => props.dashboardActions[0]);
const secondaryActions = computed(() => props.dashboardActions.slice(1, 4));

function choosePackage(packageId: number): void {
    form.service_package_id = String(packageId);
}

function selectSlot(day: AvailabilityDay, slot: AvailabilitySlot): void {
    if (! slot.available) {
        return;
    }

    form.requested_for = day.date;
    form.starts_at = slot.starts_at;
}

function clearSelectedSlot(): void {
    form.requested_for = '';
    form.starts_at = '';
}

async function loadAvailability(): Promise<void> {
    if (! form.service_id) {
        availabilityDays.value = [];
        return;
    }

    const params = new URLSearchParams({ service_id: String(form.service_id) });

    if (form.service_package_id) {
        params.set('service_package_id', String(form.service_package_id));
    }

    availabilityLoading.value = true;

    try {
        const response = await fetch(`/app/customer/availability?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            const payload = await response.json() as { availability: AvailabilityDay[] };
            availabilityDays.value = payload.availability ?? [];
        }
    } finally {
        availabilityLoading.value = false;
    }
}

watch(() => [form.service_id, form.service_package_id], () => {
    clearSelectedSlot();
    void loadAvailability();
});

function submitBooking(): void {
    form.post('/app/customer/bookings', {
        preserveScroll: true,
        onSuccess: () => {
            activeTab.value = 'overview';
            form.reset('requested_for', 'starts_at', 'notes');
        },
    });
}

function changeLocale(): void {
    router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveScroll: true });
}

function logout(): void {
    router.post('/logout');
}

function bookingStatusTone(status: string): string {
    if (status === 'approved' || status === 'converted') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (status === 'rejected') {
        return 'bg-red-50 text-red-700';
    }

    return 'bg-amber-50 text-amber-700';
}

function bookingStatusLabel(status: string): string {
    return t(`customerPortal.bookingStatuses.${status}`, status.replaceAll('_', ' '));
}

function statusTone(status: string): string {
    if (['completed', 'paid', 'converted', 'approved', 'active'].includes(status)) {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (['overdue', 'missed', 'cancelled', 'rejected'].includes(status)) {
        return 'bg-red-50 text-red-700';
    }

    return 'bg-slate-100 text-slate-700';
}

function statusLabel(status: string): string {
    return t(`statuses.${status}`, status.replaceAll('_', ' '));
}

function actionTitle(action: DashboardAction): string {
    return t(`customerPortal.dashboardActions.${action.key}.title`, action.title);
}

function actionBody(action: DashboardAction): string {
    return t(`customerPortal.dashboardActions.${action.key}.body`, action.body);
}

function actionTone(tone: string): string {
    if (tone === 'danger') {
        return 'border-red-200 bg-red-50 text-red-700';
    }

    if (tone === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    return 'border-blue-200 bg-blue-50 text-[#1040b6]';
}

function actionIconTone(tone: string): string {
    if (tone === 'danger') {
        return 'bg-red-600 text-white';
    }

    if (tone === 'warning') {
        return 'bg-[#ffb703] text-[#071b3d]';
    }

    return 'bg-[#1040b6] text-white';
}
</script>

<template>
    <Head :title="t('customerPortal.headTitle', 'Customer dashboard')" />

    <main class="min-h-screen bg-[#f7f9fd] text-[#071b3d]">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4 lg:px-8">
                <Link href="/" class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-lg bg-[#1040b6] text-[#ffb703] shadow-lg shadow-blue-900/15">
                        <Sparkles class="h-6 w-6" />
                    </span>
                    <span>
                        <span class="block text-xl font-extrabold leading-none">{{ t('publicLead.brand', 'Nofa Clean') }}</span>
                        <span class="mt-1 block text-xs font-bold uppercase text-slate-500">{{ t('customerPortal.portal', 'Customer portal') }}</span>
                    </span>
                </Link>
                <div class="flex items-center gap-2">
                    <Link href="/app/customer/profile" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        <UserRound class="h-4 w-4" />
                        {{ t('customerPortal.profileSitesLink', 'Profile & sites') }}
                    </Link>
                    <Link href="/app/customer/notifications" :aria-label="t('customerPortal.notifications.link', 'Notifications')" :title="t('customerPortal.notifications.link', 'Notifications')" class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50">
                        <BellRing class="h-4 w-4" />
                    </Link>
                    <button type="button" class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50" @click="changeLocale">
                        <Languages class="h-4 w-4" />
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50" @click="logout">
                        <LogOut class="h-4 w-4" />
                        {{ t('customerPortal.logout', 'Logout') }}
                    </button>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-7xl px-5 py-8 lg:px-8">
            <div class="rounded-lg bg-[#071b3d] p-6 text-white lg:p-8">
                <p class="text-sm font-extrabold uppercase text-[#ffdd72]">{{ t('customerPortal.welcomeKicker', 'Welcome') }}</p>
                <h1 class="mt-3 text-3xl font-extrabold md:text-4xl">{{ t('customerPortal.welcomeTitle', 'Hello, :name', { name: customer.name }) }}</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">
                    {{ t('customerPortal.welcomeBody', 'Choose a service package, select an available slot, and follow your Nofa Clean requests, contracts, and invoices.') }}
                </p>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <ClipboardList class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ dashboardSummary.active_contracts_count }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.activeContracts', 'Active contracts') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <PackageCheck class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ dashboardSummary.upcoming_visits_count }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.upcomingVisits', 'Upcoming visits') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <ReceiptText class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ dashboardSummary.open_invoices_count }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.openInvoices', 'Open invoices') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <WalletCards class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ dashboardSummary.unpaid_balance_sar }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.unpaidBalance', 'Unpaid balance') }}</div>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                <button type="button" class="rounded-md px-4 py-2 text-sm font-extrabold" :class="activeTab === 'overview' ? 'bg-[#1040b6] text-white' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'overview'">
                    {{ t('customerPortal.overviewTab', 'Overview') }}
                </button>
                <button type="button" class="rounded-md px-4 py-2 text-sm font-extrabold" :class="activeTab === 'booking' ? 'bg-[#1040b6] text-white' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'booking'">
                    {{ t('customerPortal.bookingTab', 'Book service') }}
                </button>
                <button type="button" class="rounded-md px-4 py-2 text-sm font-extrabold" :class="activeTab === 'visits' ? 'bg-[#1040b6] text-white' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'visits'">
                    {{ t('customerPortal.visitsTab', 'Upcoming visits') }}
                </button>
                <button type="button" class="rounded-md px-4 py-2 text-sm font-extrabold" :class="activeTab === 'records' ? 'bg-[#1040b6] text-white' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'records'">
                    {{ t('customerPortal.recordsTab', 'Contracts & invoices') }}
                </button>
            </div>

            <section v-if="activeTab === 'overview'" class="mt-6 space-y-6">
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
                    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-extrabold uppercase text-[#1040b6]">{{ t('customerPortal.nextStep', 'Next step') }}</p>
                                <h2 class="mt-2 text-2xl font-extrabold">{{ t('customerPortal.actionCenter', 'Action center') }}</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('customerPortal.actionCenterIntro', 'See what needs attention first across visits, payments, bookings, and support requests.') }}</p>
                            </div>
                            <button type="button" class="rounded-md bg-[#ffb703] px-4 py-2 text-sm font-extrabold text-[#071b3d] hover:bg-[#ffcc38]" @click="activeTab = 'booking'">
                                {{ t('customerPortal.startBooking', 'Start booking') }}
                            </button>
                        </div>

                        <div v-if="primaryAction" class="mt-5 rounded-lg border p-5" :class="actionTone(primaryAction.tone)">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex gap-3">
                                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md" :class="actionIconTone(primaryAction.tone)">
                                        <AlertCircle class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-extrabold">{{ actionTitle(primaryAction) }}</h3>
                                            <span class="rounded-md bg-white/70 px-2 py-1 text-xs font-extrabold">{{ statusLabel(primaryAction.status) }}</span>
                                        </div>
                                        <p class="mt-2 text-sm leading-6">{{ actionBody(primaryAction) }}</p>
                                        <p v-if="primaryAction.due_on" class="mt-2 text-xs font-extrabold">
                                            {{ t('customerPortal.date', 'Date') }}: {{ primaryAction.due_on }}<span v-if="primaryAction.starts_at"> / {{ primaryAction.starts_at }}</span>
                                        </p>
                                    </div>
                                </div>
                                <Link :href="primaryAction.href" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-extrabold text-[#071b3d] shadow-sm hover:bg-slate-50">
                                    {{ t('customerPortal.openAction', 'Open') }}
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>

                        <div v-else class="mt-5 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                            <CheckCircle2 class="mx-auto h-9 w-9 text-emerald-600" />
                            <h3 class="mt-3 font-extrabold">{{ t('customerPortal.noDashboardActions', 'No pending customer actions') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('customerPortal.noDashboardActionsBody', 'New visit, invoice, booking, and support updates will appear here.') }}</p>
                        </div>

                        <div v-if="secondaryActions.length > 0" class="mt-4 grid gap-3 md:grid-cols-3">
                            <Link v-for="action in secondaryActions" :key="action.key" :href="action.href" class="rounded-lg border border-slate-200 p-4 text-sm hover:border-[#1040b6] hover:bg-blue-50">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-extrabold text-slate-900">{{ actionTitle(action) }}</h3>
                                    <ArrowRight class="h-4 w-4 text-[#1040b6]" />
                                </div>
                                <p class="mt-2 line-clamp-2 leading-6 text-slate-600">{{ actionBody(action) }}</p>
                                <p v-if="action.due_on" class="mt-2 text-xs font-extrabold text-[#1040b6]">{{ action.due_on }}</p>
                            </Link>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="text-xl font-extrabold">{{ t('customerPortal.accountSnapshot', 'Account snapshot') }}</h2>
                            <Link href="/app/customer/profile" class="rounded-md border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">
                                {{ t('customerPortal.manageProfileSites', 'Manage') }}
                            </Link>
                        </div>
                        <dl class="mt-5 grid gap-3 text-sm">
                            <div class="rounded-lg bg-slate-50 px-4 py-3">
                                <dt class="font-bold text-slate-500">{{ t('customerPortal.defaultSite', 'Default site') }}</dt>
                                <dd class="mt-1 font-extrabold">{{ firstSite?.name ?? '-' }}</dd>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-lg bg-slate-50 px-4 py-3">
                                    <dt class="font-bold text-slate-500">{{ t('customerPortal.pendingRequests', 'Pending requests') }}</dt>
                                    <dd class="mt-1 text-xl font-extrabold">{{ dashboardSummary.pending_requests_count }}</dd>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-4 py-3">
                                    <dt class="font-bold text-slate-500">{{ t('customerPortal.savedSites', 'Saved sites') }}</dt>
                                    <dd class="mt-1 text-xl font-extrabold">{{ dashboardSummary.saved_sites_count }}</dd>
                                </div>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3">
                                <dt class="font-bold text-slate-500">{{ t('customerPortal.email', 'Email') }}</dt>
                                <dd class="mt-1 break-words font-extrabold">{{ customer.email ?? '-' }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3">
                                <dt class="font-bold text-slate-500">{{ t('customerPortal.phone', 'Phone') }}</dt>
                                <dd class="mt-1 font-extrabold">{{ customer.phone ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-extrabold">{{ t('customerPortal.quickBookingTitle', 'Ready to book?') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('customerPortal.quickBookingIntro', 'Choose from active services or start a custom booking request.') }}</p>
                        </div>
                        <button type="button" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-extrabold text-slate-700 hover:bg-slate-50" @click="activeTab = 'booking'">
                            {{ t('customerPortal.viewAllServices', 'View services') }}
                        </button>
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article v-for="service in services.slice(0, 4)" :key="service.id" class="rounded-lg border border-slate-200 p-4">
                            <h3 class="font-extrabold">{{ service.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ service.description ?? t('customerPortal.serviceFallback', 'Package and custom booking options are available.') }}</p>
                            <p class="mt-3 text-sm font-extrabold text-[#1040b6]">{{ t('customerPortal.fromSar', 'From SAR :amount', { amount: service.base_price_sar }) }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'booking'" class="mt-6 grid items-start gap-6 lg:grid-cols-[1.08fr_0.92fr]">
                <form class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submitBooking">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.bookingTitle', 'Book a service') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('customerPortal.bookingIntro', 'Choose the service, package, site, and one available slot. The operations team will approve it before the contract is created.') }}</p>

                    <div v-if="success" class="mt-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                        {{ success }}
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('customerPortal.service', 'Service') }}
                            <select v-model="form.service_id" class="mt-2 w-full rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" @change="form.service_package_id = ''">
                                <option v-for="service in services" :key="service.id" :value="String(service.id)">{{ service.title }}</option>
                            </select>
                            <span v-if="form.errors.service_id" class="mt-1 block text-xs font-bold text-red-600">{{ form.errors.service_id }}</span>
                        </label>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                            <span class="block font-bold text-slate-500">{{ t('customerPortal.selectedSlot', 'Selected slot') }}</span>
                            <span class="mt-1 flex items-center gap-2 font-extrabold text-[#1040b6]">
                                <Clock class="h-4 w-4" />
                                {{ selectedSlotLabel }}
                            </span>
                            <span v-if="form.errors.requested_for" class="mt-1 block text-xs font-bold text-red-600">{{ form.errors.requested_for }}</span>
                            <span v-if="form.errors.starts_at" class="mt-1 block text-xs font-bold text-red-600">{{ form.errors.starts_at }}</span>
                        </div>
                        <label class="block text-sm font-bold text-slate-700 md:col-span-2">
                            {{ t('customerPortal.savedBookingSite', 'Saved site') }}
                            <select v-model="form.customer_site_id" class="mt-2 w-full rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100">
                                <option value="">{{ t('customerPortal.enterNewSite', 'Enter a new site') }}</option>
                                <option v-for="site in customer.sites" :key="site.id" :value="String(site.id)">
                                    {{ site.name }}{{ site.is_default ? ` - ${t('customerPortal.defaultSite', 'Default')}` : '' }}
                                </option>
                            </select>
                        </label>
                        <div v-if="selectedBookingSite" class="rounded-lg border border-slate-200 bg-blue-50 px-4 py-3 text-sm md:col-span-2">
                            <span class="flex items-start gap-2 font-extrabold text-[#1040b6]">
                                <MapPin class="mt-0.5 h-4 w-4" />
                                {{ selectedBookingSite.name }}
                            </span>
                            <p class="mt-1 text-slate-600">{{ selectedBookingSite.city }}<span v-if="selectedBookingSite.district"> / {{ selectedBookingSite.district }}</span></p>
                            <p class="mt-1 text-slate-600">{{ selectedBookingSite.address ?? '-' }}</p>
                        </div>
                        <template v-if="!form.customer_site_id">
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('customerPortal.siteName', 'Site name') }}
                            <input v-model="form.site_name" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="text">
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('customerPortal.city', 'City') }}
                            <input v-model="form.city" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="text">
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('customerPortal.district', 'District') }}
                            <input v-model="form.district" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="text">
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('customerPortal.address', 'Address') }}
                            <input v-model="form.address" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="text">
                        </label>
                        </template>
                    </div>

                    <section class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="font-extrabold text-slate-900">{{ t('customerPortal.availableSlots', 'Available slots') }}</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ t('customerPortal.availableSlotsIntro', 'Slots are based on current worker capacity and existing schedules.') }}</p>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-1.5 text-xs font-extrabold text-slate-600">
                                <Loader2 v-if="availabilityLoading" class="h-4 w-4 animate-spin" />
                                {{ availableSlotCount }} {{ t('customerPortal.slots', 'slots') }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <article v-for="day in availabilityDays" :key="day.date" class="rounded-lg border border-slate-200 bg-white p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="font-extrabold text-slate-900">{{ day.weekday }}</h4>
                                    <span class="text-xs font-bold text-slate-500">{{ day.date }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        v-for="slot in day.slots"
                                        :key="`${day.date}-${slot.starts_at}`"
                                        type="button"
                                        class="rounded-md border px-3 py-2 text-xs font-extrabold transition disabled:cursor-not-allowed disabled:opacity-45"
                                        :class="form.requested_for === day.date && form.starts_at === slot.starts_at ? 'border-[#1040b6] bg-[#1040b6] text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-[#1040b6] hover:text-[#1040b6]'"
                                        :disabled="!slot.available"
                                        @click="selectSlot(day, slot)"
                                    >
                                        {{ slot.label }}
                                    </button>
                                </div>
                            </article>
                        </div>

                        <p v-if="availabilityDays.length === 0 || availableSlotCount === 0" class="mt-4 rounded-md border border-dashed border-slate-300 bg-white px-4 py-5 text-center text-sm font-bold text-slate-500">
                            {{ t('customerPortal.noAvailableSlots', 'No available slots for this selection. Try another service or package.') }}
                        </p>
                    </section>

                    <label class="mt-4 block text-sm font-bold text-slate-700">
                        {{ t('customerPortal.notes', 'Notes') }}
                        <textarea v-model="form.notes" class="mt-2 min-h-28 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" />
                    </label>

                    <button type="submit" class="mt-6 inline-flex items-center gap-2 rounded-md bg-[#1040b6] px-5 py-3 text-sm font-extrabold text-white hover:bg-[#0a2f95] disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing || !form.service_id || !form.requested_for || !form.starts_at">
                        <CalendarDays class="h-4 w-4" />
                        {{ t('customerPortal.submitBooking', 'Send booking request') }}
                    </button>
                </form>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.packageTitle', 'Available packages') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('customerPortal.packageIntro', 'Pick a fixed package or leave it empty for a custom quotation.') }}</p>
                    <button type="button" class="mt-5 w-full rounded-md border border-dashed border-slate-300 px-4 py-3 text-sm font-extrabold text-slate-600 hover:bg-slate-50" :class="!form.service_package_id ? 'border-[#1040b6] bg-blue-50 text-[#1040b6]' : ''" @click="form.service_package_id = ''">
                        {{ t('customerPortal.customPackage', 'Custom quotation') }}
                    </button>
                    <div class="mt-4 grid gap-4">
                        <button
                            v-for="item in selectedPackages"
                            :key="item.id"
                            type="button"
                            class="rounded-lg border p-4 text-start hover:bg-slate-50"
                            :class="selectedPackage?.id === item.id ? 'border-[#1040b6] bg-blue-50' : 'border-slate-200 bg-white'"
                            @click="choosePackage(item.id)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-extrabold">{{ item.name }}</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ item.description ?? t('customerPortal.packageFallback', 'Prepared package for this service.') }}</p>
                                </div>
                                <CheckCircle2 v-if="selectedPackage?.id === item.id" class="h-5 w-5 text-[#1040b6]" />
                            </div>
                            <div class="mt-3 text-sm font-extrabold text-[#1040b6]">{{ t('customerPortal.sarAmount', 'SAR :amount', { amount: item.price_sar }) }}</div>
                        </button>
                        <div v-if="selectedPackages.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500">
                            {{ t('customerPortal.noPackages', 'No active packages for this service yet.') }}
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'visits'" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.upcomingVisitsTitle', 'Upcoming visits') }}</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('customerPortal.upcomingVisitsIntro', 'Scheduled visits from your active contracts.') }}</p>
                    </div>
                    <span class="rounded-md bg-blue-50 px-3 py-1.5 text-sm font-extrabold text-[#1040b6]">{{ upcomingVisits.length }}</span>
                </div>

                <div class="mt-5 grid gap-3 lg:grid-cols-2">
                    <article v-for="visit in upcomingVisits" :key="visit.id" class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(visit.status)">{{ statusLabel(visit.status) }}</span>
                                    <h3 class="font-extrabold">{{ visit.scheduled_for ?? '-' }}</h3>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">{{ visit.starts_at ?? '--:--' }} - {{ visit.ends_at ?? '--:--' }} / {{ visit.site ?? '-' }}</p>
                                <p class="mt-1 text-sm font-bold text-slate-700">{{ visit.service ?? '-' }}</p>
                            </div>
                            <Link v-if="visit.contract" :href="visit.contract.detail_url" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">
                                <Eye class="h-4 w-4" />
                                {{ visit.contract.reference }}
                            </Link>
                        </div>
                        <div class="mt-4 flex items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm font-bold text-slate-600">
                            <UserRound class="h-4 w-4 text-[#1040b6]" />
                            {{ visit.worker?.name ?? t('customerPortal.workerPending', 'Worker will be assigned') }}
                        </div>
                    </article>
                    <div v-if="upcomingVisits.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500 lg:col-span-2">{{ t('customerPortal.noUpcomingVisits', 'No upcoming visits scheduled yet.') }}</div>
                </div>
            </section>

            <section v-if="activeTab === 'records'" class="mt-6 grid gap-6 lg:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.bookingRequestsTitle', 'Booking requests') }}</h2>
                    <div class="mt-5 grid gap-3">
                        <article v-for="request in bookingRequests" :key="request.id" class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-extrabold">{{ request.service ?? '-' }}</h3>
                                <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="bookingStatusTone(request.status)">
                                    {{ bookingStatusLabel(request.status) }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">{{ request.site ?? '-' }} / {{ request.requested_for ?? '-' }}</p>
                            <p class="mt-2 text-sm font-extrabold text-[#1040b6]">{{ request.starts_at ?? '--:--' }} - {{ request.ends_at ?? '--:--' }}</p>
                            <p v-if="request.contract_reference" class="mt-2 text-xs font-bold text-emerald-700">
                                {{ t('customerPortal.contractCreated', 'Contract created') }}: {{ request.contract_reference }}
                            </p>
                            <Link :href="request.detail_url" class="mt-4 inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">
                                <Eye class="h-4 w-4" />
                                {{ t('customerPortal.viewRequest', 'View request') }}
                            </Link>
                        </article>
                        <div v-if="bookingRequests.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500">{{ t('customerPortal.noBookingRequests', 'No booking requests yet.') }}</div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.contractsTitle', 'Contracts') }}</h2>
                    <div class="mt-5 grid gap-3">
                        <article v-for="contract in contracts" :key="contract.id" class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-extrabold">{{ contract.reference }}</h3>
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-extrabold text-slate-600">{{ contract.status }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">{{ contract.service ?? '-' }} - {{ contract.site ?? '-' }}</p>
                            <p class="mt-3 text-sm font-extrabold text-[#1040b6]">{{ t('customerPortal.sarAmount', 'SAR :amount', { amount: contract.monthly_fee_sar }) }}</p>
                            <Link :href="contract.detail_url" class="mt-4 inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">
                                <Eye class="h-4 w-4" />
                                {{ t('customerPortal.viewDetails', 'View details') }}
                            </Link>
                        </article>
                        <div v-if="contracts.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500">{{ t('customerPortal.noContracts', 'No contracts yet.') }}</div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.invoicesTitle', 'Invoices') }}</h2>
                    <div class="mt-5 grid gap-3">
                        <article v-for="invoice in invoices" :key="invoice.id" class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-extrabold">{{ invoice.number }}</h3>
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-extrabold text-slate-600">{{ invoice.status }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">{{ t('customerPortal.dueDate', 'Due date') }}: {{ invoice.due_date ?? '-' }}</p>
                            <p class="mt-3 text-sm font-extrabold text-[#1040b6]">{{ t('customerPortal.balanceAmount', 'Balance SAR :amount', { amount: invoice.balance_sar }) }}</p>
                            <Link :href="invoice.detail_url" class="mt-4 inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">
                                <Eye class="h-4 w-4" />
                                {{ t('customerPortal.viewInvoice', 'View invoice') }}
                            </Link>
                        </article>
                        <div v-if="invoices.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500">{{ t('customerPortal.noInvoices', 'No invoices yet.') }}</div>
                    </div>
                </div>
            </section>
        </section>
    </main>
</template>
