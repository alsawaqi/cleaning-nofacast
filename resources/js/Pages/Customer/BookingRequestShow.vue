<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BellRing, CalendarDays, CheckCircle2, Circle, ClipboardList, Eye, Languages, LogOut, MapPin, MessageSquareText, PackageCheck, Sparkles, UsersRound, XCircle } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '../../lib/i18n';

type PortalCustomer = {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
};

type BookingTimelineStep = {
    key: string;
    status: 'complete' | 'current' | 'pending' | 'blocked';
    date?: string | null;
    note?: string | null;
};

type BookingRequestDetail = {
    id: number;
    status: string;
    requested_for?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    worker_count: number;
    duration_minutes: number;
    customer_note?: string | null;
    admin_note?: string | null;
    created_at?: string | null;
    approved_at?: string | null;
    rejected_at?: string | null;
    detail_url: string;
    service?: {
        id: number;
        title: string;
        category?: string | null;
        description?: string | null;
    } | null;
    package?: {
        id: number;
        name: string;
        description?: string | null;
        billing_cycle?: string | null;
        visit_frequency?: string | null;
        visits_per_week?: number | null;
        hours_per_visit?: string | number | null;
        worker_count?: number | null;
        duration_minutes?: number | null;
        price_sar?: string | null;
    } | null;
    site?: {
        id: number;
        name: string;
        city?: string | null;
        district?: string | null;
        address?: string | null;
        contact_name?: string | null;
        contact_phone?: string | null;
    } | null;
    contract?: {
        id: number;
        reference: string;
        status: string;
        detail_url: string;
    } | null;
};

const props = defineProps<{
    customer: PortalCustomer;
    bookingRequest: BookingRequestDetail;
    timeline: BookingTimelineStep[];
    backUrl: string;
}>();

const { locale, t } = useI18n();

const requestedWindow = computed(() => {
    const date = props.bookingRequest.requested_for ?? '-';
    const start = props.bookingRequest.starts_at ?? '--:--';
    const end = props.bookingRequest.ends_at ?? '--:--';

    return `${date} / ${start} - ${end}`;
});

function changeLocale(): void {
    router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveScroll: true });
}

function logout(): void {
    router.post('/logout');
}

function statusLabel(status: string): string {
    return t(`customerPortal.bookingStatuses.${status}`, status.replaceAll('_', ' '));
}

function statusTone(status: string): string {
    if (status === 'approved' || status === 'converted') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (status === 'rejected') {
        return 'bg-red-50 text-red-700';
    }

    return 'bg-amber-50 text-amber-700';
}

function timelineLabel(step: BookingTimelineStep): string {
    return t(`customerPortal.bookingTimeline.${step.key}.label`, step.key.replaceAll('_', ' '));
}

function timelineBody(step: BookingTimelineStep): string {
    return t(`customerPortal.bookingTimeline.${step.key}.body`, '');
}

function timelineStatusLabel(status: BookingTimelineStep['status']): string {
    return t(`customerPortal.timelineStatuses.${status}`, status.replaceAll('_', ' '));
}

function timelineTone(status: BookingTimelineStep['status']): string {
    if (status === 'complete') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (status === 'current') {
        return 'border-blue-200 bg-blue-50 text-[#1040b6]';
    }

    if (status === 'blocked') {
        return 'border-red-200 bg-red-50 text-red-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-500';
}
</script>

<template>
    <Head :title="t('customerPortal.bookingRequestDetailTitle', 'Booking request details')" />

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
            <Link :href="backUrl" class="inline-flex items-center gap-2 text-sm font-extrabold text-[#1040b6]">
                <ArrowLeft class="h-4 w-4 rtl:rotate-180" />
                {{ t('customerPortal.backToPortal', 'Back to portal') }}
            </Link>

            <div class="mt-5 rounded-lg bg-[#071b3d] p-6 text-white lg:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-extrabold uppercase text-[#ffdd72]">{{ t('customerPortal.bookingRequest', 'Booking request') }}</p>
                        <h1 class="mt-3 text-3xl font-extrabold md:text-4xl">
                            {{ t('customerPortal.requestNumber', 'Request #:id', { id: bookingRequest.id }) }}
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">
                            {{ bookingRequest.service?.title ?? '-' }} / {{ bookingRequest.package?.name ?? t('customerPortal.customPackage', 'Custom quotation') }}
                        </p>
                    </div>
                    <span class="rounded-md px-3 py-1.5 text-sm font-extrabold" :class="statusTone(bookingRequest.status)">
                        {{ statusLabel(bookingRequest.status) }}
                    </span>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <CalendarDays class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-base font-extrabold">{{ bookingRequest.requested_for ?? '-' }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.preferredDate', 'Preferred date') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <ClipboardList class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-base font-extrabold">{{ bookingRequest.starts_at ?? '--:--' }} - {{ bookingRequest.ends_at ?? '--:--' }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.requestedWindow', 'Requested window') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <UsersRound class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-base font-extrabold">{{ bookingRequest.worker_count }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.teamSize', 'Team size') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <PackageCheck class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-base font-extrabold">{{ bookingRequest.duration_minutes }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.durationMinutes', 'Duration minutes') }}</div>
                </div>
            </div>

            <section class="mt-6 grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.requestSummary', 'Request summary') }}</h2>
                    <dl class="mt-5 grid gap-4 text-sm">
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.requestedService', 'Requested service') }}</dt>
                            <dd class="mt-1 font-extrabold">{{ bookingRequest.service?.title ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.requestedPackage', 'Requested package') }}</dt>
                            <dd class="mt-1 font-extrabold">{{ bookingRequest.package?.name ?? t('customerPortal.customPackage', 'Custom quotation') }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.requestedSite', 'Requested site') }}</dt>
                            <dd class="mt-1 font-extrabold">{{ bookingRequest.site?.name ?? '-' }}</dd>
                            <dd class="mt-1 text-slate-600">
                                {{ [bookingRequest.site?.district, bookingRequest.site?.city].filter(Boolean).join(', ') || '-' }}
                            </dd>
                            <dd v-if="bookingRequest.site?.address" class="mt-1 text-slate-600">{{ bookingRequest.site.address }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.requestedWindow', 'Requested window') }}</dt>
                            <dd class="mt-1 font-extrabold">{{ requestedWindow }}</dd>
                        </div>
                    </dl>

                    <div v-if="bookingRequest.contract" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-sm font-extrabold text-emerald-800">{{ t('customerPortal.relatedContract', 'Related contract') }}</p>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-extrabold text-emerald-900">{{ bookingRequest.contract.reference }}</p>
                                <p class="text-sm font-bold text-emerald-700">{{ statusLabel(bookingRequest.contract.status) }}</p>
                            </div>
                            <Link :href="bookingRequest.contract.detail_url" class="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-3 py-2 text-xs font-extrabold text-white hover:bg-emerald-800">
                                <Eye class="h-4 w-4" />
                                {{ t('customerPortal.openContract', 'Open contract') }}
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.statusTimeline', 'Status timeline') }}</h2>
                        <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(bookingRequest.status)">
                            {{ statusLabel(bookingRequest.status) }}
                        </span>
                    </div>

                    <ol class="mt-6 space-y-4">
                        <li v-for="step in timeline" :key="step.key" class="flex gap-4">
                            <span class="mt-1 grid h-9 w-9 shrink-0 place-items-center rounded-full border" :class="timelineTone(step.status)">
                                <CheckCircle2 v-if="step.status === 'complete'" class="h-5 w-5" />
                                <XCircle v-else-if="step.status === 'blocked'" class="h-5 w-5" />
                                <Circle v-else class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1 rounded-lg border border-slate-200 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="font-extrabold">{{ timelineLabel(step) }}</h3>
                                    <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="timelineTone(step.status)">
                                        {{ timelineStatusLabel(step.status) }}
                                    </span>
                                </div>
                                <p v-if="timelineBody(step)" class="mt-2 text-sm leading-6 text-slate-600">{{ timelineBody(step) }}</p>
                                <p v-if="step.date" class="mt-2 text-xs font-bold text-slate-500">{{ step.date }}</p>
                                <p v-if="step.note" class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-sm font-bold text-slate-600">{{ step.note }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-2">
                        <MessageSquareText class="h-5 w-5 text-[#1040b6]" />
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.customerNote', 'Customer note') }}</h2>
                    </div>
                    <p class="mt-4 text-sm leading-7 text-slate-600">{{ bookingRequest.customer_note || t('customerPortal.noCustomerNote', 'No customer note added.') }}</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-2">
                        <MapPin class="h-5 w-5 text-[#1040b6]" />
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.adminNote', 'Operations note') }}</h2>
                    </div>
                    <p class="mt-4 text-sm leading-7 text-slate-600">{{ bookingRequest.admin_note || t('customerPortal.noAdminNote', 'No operations note yet.') }}</p>
                </div>
            </section>
        </section>
    </main>
</template>
