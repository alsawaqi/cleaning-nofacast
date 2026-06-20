<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BellRing, CalendarDays, CheckCircle2, CreditCard, Download, Languages, LifeBuoy, LogOut, MapPin, Printer, ReceiptText, Send, Signature, Sparkles, Star, TimerReset, UserRound } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { useI18n } from '../../lib/i18n';

type PortalCustomer = {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
};

type PortalContract = {
    id: number;
    reference: string;
    status: string;
    starts_on?: string | null;
    ends_on?: string | null;
    monthly_fee_sar: string;
    vat_rate: number;
    prices_include_vat: boolean;
    billing_cycle: string;
    notice_days: number;
    auto_renews: boolean;
    agreed_workers: number;
    visits_per_week?: number | null;
    hours_per_visit?: string | number | null;
    planned_weekly_minutes: number;
    included_materials: boolean;
    material_policy?: string | null;
    estimated_material_cost_sar: string;
    extra_hour_rate_sar: string;
    overtime_policy?: string | null;
    service_scope: Array<{ area: string; tasks?: string | null }>;
    terms_and_conditions?: string | null;
    special_terms?: string | null;
    print_url?: string;
    download_url?: string;
    payment_plan: Array<{ label?: string | null; day: number; percent: number }>;
    site?: {
        id: number;
        name: string;
        city?: string | null;
        district?: string | null;
        address?: string | null;
    } | null;
    service?: {
        id: number;
        title: string;
        category?: string | null;
        description?: string | null;
    } | null;
    service_package?: {
        id: number;
        name: string;
        description?: string | null;
    } | null;
    assignments: Array<{
        id: number;
        weekday: number;
        starts_at?: string | null;
        ends_at?: string | null;
        team_role?: string | null;
        worker?: {
            id: number;
            name: string;
            job_role?: string | null;
        } | null;
        task_instructions: Array<{ label: string; is_required?: boolean }>;
    }>;
    addendums: Array<{
        id: number;
        number: number;
        title: string;
        summary: string;
        effective_on?: string | null;
    }>;
};

type PortalVisit = {
    id: number;
    scheduled_for?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    status: string;
    service?: string | null;
    site?: string | null;
    can_submit_feedback?: boolean;
    feedback?: {
        id: number;
        rating: number;
        comment?: string | null;
        submitted_at?: string | null;
    } | null;
    worker?: {
        id: number;
        name: string;
        job_role?: string | null;
    } | null;
    checklist: Array<{
        id: number;
        label: string;
        status: string;
        is_required: boolean;
        notes?: string | null;
        photo_path?: string | null;
    }>;
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

type PortalServiceRequest = {
    id: number;
    type: string;
    status: string;
    priority: string;
    subject?: string | null;
    description?: string | null;
    requested_for?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    admin_note?: string | null;
    created_at?: string | null;
    resolved_at?: string | null;
    visit?: {
        id: number;
        scheduled_for?: string | null;
        starts_at?: string | null;
        ends_at?: string | null;
        status: string;
        worker?: {
            id: number;
            name: string;
        } | null;
    } | null;
};

type PortalContractDecision = {
    id: number;
    decision: string;
    status: string;
    signer_name?: string | null;
    signer_title?: string | null;
    signature_text?: string | null;
    customer_note?: string | null;
    admin_note?: string | null;
    accepted_at?: string | null;
    reviewed_at?: string | null;
    created_at?: string | null;
    reviewed_by?: {
        id: number;
        name: string;
    } | null;
};

type RescheduleForm = {
    requested_for: string;
    starts_at: string;
    ends_at: string;
    description: string;
    processing: boolean;
};

type FeedbackForm = {
    rating: number;
    comment: string;
    processing: boolean;
};

const props = defineProps<{
    customer: PortalCustomer;
    contract: PortalContract;
    upcomingVisits: PortalVisit[];
    recentVisits: PortalVisit[];
    invoices: PortalInvoice[];
    serviceRequests: PortalServiceRequest[];
    contractDecisions: PortalContractDecision[];
    backUrl: string;
}>();

const { locale, t } = useI18n();
const contractTabs = ['overview', 'visits', 'invoices', 'acceptance', 'support', 'scope'] as const;
type ContractTab = typeof contractTabs[number];
const activeTab = ref<ContractTab>('overview');
const allVisits = computed(() => [...props.upcomingVisits, ...props.recentVisits]);
const latestContractDecision = computed(() => props.contractDecisions[0]);
const rescheduleForms = reactive<Record<number, RescheduleForm>>({});
const feedbackForms = reactive<Record<number, FeedbackForm>>({});
const acceptanceForm = reactive({
    signer_name: props.customer.name,
    signer_title: '',
    signature_text: props.customer.name,
    accepted_terms: false,
    customer_note: '',
    processing: false,
});
const changeRequestForm = reactive({
    customer_note: '',
    processing: false,
});
const supportForm = reactive({
    priority: 'normal',
    subject: '',
    description: '',
    visit_id: '',
    processing: false,
});

function changeLocale(): void {
    router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveScroll: true });
}

function logout(): void {
    router.post('/logout');
}

function statusTone(status: string): string {
    if (['active', 'completed', 'paid', 'approved', 'resolved', 'reviewed'].includes(status)) {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (['missed', 'cancelled', 'overdue', 'rejected'].includes(status)) {
        return 'bg-red-50 text-red-700';
    }

    if (['pending', 'in_review', 'pending_review'].includes(status)) {
        return 'bg-amber-50 text-amber-700';
    }

    return 'bg-slate-100 text-slate-700';
}

function statusLabel(status: string): string {
    return t(`statuses.${status}`, status.replaceAll('_', ' '));
}

function weekdayLabel(weekday: number): string {
    const labels: Record<number, string> = {
        1: t('operations.weekdays.mon', 'Monday'),
        2: t('operations.weekdays.tue', 'Tuesday'),
        3: t('operations.weekdays.wed', 'Wednesday'),
        4: t('operations.weekdays.thu', 'Thursday'),
        5: t('operations.weekdays.fri', 'Friday'),
        6: t('operations.weekdays.sat', 'Saturday'),
        7: t('operations.weekdays.sun', 'Sunday'),
    };

    return labels[weekday] ?? String(weekday);
}

function setActiveTab(tab: ContractTab): void {
    activeTab.value = tab;
}

function canRequestReschedule(visit: PortalVisit): boolean {
    return props.upcomingVisits.some((item) => item.id === visit.id)
        && ! ['completed', 'cancelled', 'missed'].includes(visit.status);
}

function rescheduleForm(visit: PortalVisit): RescheduleForm {
    rescheduleForms[visit.id] ??= {
        requested_for: visit.scheduled_for ?? '',
        starts_at: visit.starts_at ?? '',
        ends_at: visit.ends_at ?? '',
        description: '',
        processing: false,
    };

    return rescheduleForms[visit.id];
}

function feedbackForm(visit: PortalVisit): FeedbackForm {
    feedbackForms[visit.id] ??= {
        rating: 5,
        comment: '',
        processing: false,
    };

    return feedbackForms[visit.id];
}

function setVisitRating(visit: PortalVisit, rating: number): void {
    feedbackForm(visit).rating = rating;
}

function pendingRescheduleForVisit(visit: PortalVisit): PortalServiceRequest | undefined {
    return props.serviceRequests.find((request) => request.type === 'reschedule'
        && request.visit?.id === visit.id
        && ['pending', 'in_review'].includes(request.status));
}

function submitReschedule(visit: PortalVisit): void {
    const form = rescheduleForm(visit);
    form.processing = true;

    router.post(`/app/customer/contracts/${props.contract.id}/service-requests`, {
        type: 'reschedule',
        visit_id: visit.id,
        requested_for: form.requested_for,
        starts_at: form.starts_at,
        ends_at: form.ends_at,
        description: form.description,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            form.description = '';
        },
        onFinish: () => {
            form.processing = false;
        },
    });
}

function submitVisitFeedback(visit: PortalVisit): void {
    const form = feedbackForm(visit);
    form.processing = true;

    router.post(`/app/customer/contracts/${props.contract.id}/visits/${visit.id}/feedback`, {
        rating: form.rating,
        comment: form.comment,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            form.comment = '';
        },
        onFinish: () => {
            form.processing = false;
        },
    });
}

function submitSupportTicket(): void {
    supportForm.processing = true;

    router.post(`/app/customer/contracts/${props.contract.id}/service-requests`, {
        type: 'support_ticket',
        priority: supportForm.priority,
        subject: supportForm.subject,
        description: supportForm.description,
        visit_id: supportForm.visit_id || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            supportForm.priority = 'normal';
            supportForm.subject = '';
            supportForm.description = '';
            supportForm.visit_id = '';
        },
        onFinish: () => {
            supportForm.processing = false;
        },
    });
}

function serviceRequestTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        reschedule: t('customerPortal.requestTypes.reschedule', 'Reschedule'),
        support_ticket: t('customerPortal.requestTypes.supportTicket', 'Support ticket'),
    };

    return labels[type] ?? type.replaceAll('_', ' ');
}

function priorityLabel(priority: string): string {
    const labels: Record<string, string> = {
        low: t('customerPortal.priorities.low', 'Low'),
        normal: t('customerPortal.priorities.normal', 'Normal'),
        high: t('customerPortal.priorities.high', 'High'),
        urgent: t('customerPortal.priorities.urgent', 'Urgent'),
    };

    return labels[priority] ?? priority.replaceAll('_', ' ');
}

function decisionLabel(decision: string): string {
    const labels: Record<string, string> = {
        accepted: t('customerPortal.decisionAccepted', 'Accepted and signed'),
        change_requested: t('customerPortal.decisionChangeRequested', 'Changes requested'),
    };

    return labels[decision] ?? decision.replaceAll('_', ' ');
}

function submitContractDecision(decision: 'accepted' | 'change_requested'): void {
    const isAccepted = decision === 'accepted';

    if (isAccepted) {
        acceptanceForm.processing = true;
    } else {
        changeRequestForm.processing = true;
    }

    router.post(`/app/customer/contracts/${props.contract.id}/decisions`, isAccepted ? {
        decision,
        signer_name: acceptanceForm.signer_name,
        signer_title: acceptanceForm.signer_title,
        signature_text: acceptanceForm.signature_text,
        accepted_terms: acceptanceForm.accepted_terms,
        customer_note: acceptanceForm.customer_note,
    } : {
        decision,
        customer_note: changeRequestForm.customer_note,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            if (isAccepted) {
                acceptanceForm.accepted_terms = false;
                acceptanceForm.customer_note = '';
            } else {
                changeRequestForm.customer_note = '';
            }
        },
        onFinish: () => {
            if (isAccepted) {
                acceptanceForm.processing = false;
            } else {
                changeRequestForm.processing = false;
            }
        },
    });
}
</script>

<template>
    <Head :title="t('customerPortal.contractDetailTitle', 'Contract details')" />

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
                        <p class="text-sm font-extrabold uppercase text-[#ffdd72]">{{ t('customerPortal.contract', 'Contract') }}</p>
                        <h1 class="mt-3 text-3xl font-extrabold md:text-4xl">{{ contract.reference }}</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">
                            {{ contract.service?.title ?? '-' }} / {{ contract.service_package?.name ?? t('customerPortal.customPackage', 'Custom quotation') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a v-if="contract.print_url" :href="contract.print_url" class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-xs font-extrabold text-[#071b3d] hover:bg-slate-100" target="_blank">
                            <Printer class="h-4 w-4" />
                            {{ t('customerPortal.printContract', 'Print') }}
                        </a>
                        <a v-if="contract.download_url" :href="contract.download_url" class="inline-flex items-center gap-2 rounded-md bg-[#ffb703] px-3 py-2 text-xs font-extrabold text-[#071b3d] hover:bg-[#ffcc38]">
                            <Download class="h-4 w-4" />
                            {{ t('customerPortal.downloadContractPdf', 'Download PDF') }}
                        </a>
                        <span class="rounded-md bg-white/10 px-3 py-1.5 text-sm font-extrabold">{{ statusLabel(contract.status) }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <CreditCard class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ contract.monthly_fee_sar }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.monthlyFee', 'Monthly fee') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <CalendarDays class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ upcomingVisits.length }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.upcomingVisits', 'Upcoming visits') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <UserRound class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ contract.agreed_workers }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.workers', 'Workers') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <ReceiptText class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ invoices.length }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.invoices', 'Invoices') }}</div>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                <button v-for="tab in contractTabs" :key="tab" type="button" class="rounded-md px-4 py-2 text-sm font-extrabold" :class="activeTab === tab ? 'bg-[#1040b6] text-white' : 'text-slate-600 hover:bg-slate-50'" @click="setActiveTab(tab)">
                    {{ t(`customerPortal.contractTabs.${tab}`, tab) }}
                </button>
            </div>

            <section v-if="activeTab === 'overview'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_0.9fr]">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.agreementSummary', 'Agreement summary') }}</h2>
                    <dl class="mt-5 grid gap-4 text-sm md:grid-cols-2">
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.period', 'Period') }}</dt>
                            <dd class="mt-1 font-extrabold">{{ contract.starts_on ?? '-' }} - {{ contract.ends_on ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.billingCycle', 'Billing cycle') }}</dt>
                            <dd class="mt-1 font-extrabold">{{ contract.billing_cycle }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.visitsPerWeek', 'Visits per week') }}</dt>
                            <dd class="mt-1 font-extrabold">{{ contract.visits_per_week ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.hoursPerVisit', 'Hours per visit') }}</dt>
                            <dd class="mt-1 font-extrabold">{{ contract.hours_per_visit ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.materials', 'Materials') }}</dt>
                            <dd class="mt-1 font-extrabold">{{ contract.included_materials ? t('customerPortal.included', 'Included') : t('customerPortal.notIncluded', 'Not included') }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.extraHourRate', 'Extra hour') }}</dt>
                            <dd class="mt-1 font-extrabold">{{ contract.extra_hour_rate_sar }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.serviceSite', 'Service site') }}</h2>
                    <div class="mt-5 rounded-lg bg-slate-50 p-4">
                        <div class="flex items-start gap-3">
                            <MapPin class="mt-1 h-5 w-5 text-[#1040b6]" />
                            <div>
                                <p class="font-extrabold">{{ contract.site?.name ?? '-' }}</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ contract.site?.city ?? '-' }} / {{ contract.site?.district ?? '-' }}</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ contract.site?.address ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="contract.assignments.length" class="mt-5 grid gap-3">
                        <div v-for="assignment in contract.assignments" :key="assignment.id" class="rounded-lg border border-slate-200 p-3">
                            <p class="font-extrabold">{{ weekdayLabel(assignment.weekday) }} / {{ assignment.starts_at }} - {{ assignment.ends_at }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ assignment.worker?.name ?? t('customerPortal.workerPending', 'Worker will be assigned') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'visits'" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-extrabold">{{ t('customerPortal.visits', 'Visits') }}</h2>
                <div class="mt-5 grid gap-3 lg:grid-cols-2">
                    <article v-for="visit in allVisits" :key="visit.id" class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(visit.status)">{{ statusLabel(visit.status) }}</span>
                                <h3 class="mt-2 font-extrabold">{{ visit.scheduled_for }} / {{ visit.starts_at }} - {{ visit.ends_at }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ visit.worker?.name ?? t('customerPortal.workerPending', 'Worker will be assigned') }}</p>
                            </div>
                        </div>
                        <div v-if="visit.checklist.length" class="mt-4 grid gap-2">
                            <div v-for="item in visit.checklist" :key="item.id" class="flex items-center justify-between gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm">
                                <span class="font-bold text-slate-700">{{ item.label }}</span>
                                <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(item.status)">{{ statusLabel(item.status) }}</span>
                            </div>
                        </div>
                        <div v-if="visit.feedback" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="inline-flex items-center gap-1 text-emerald-700">
                                    <Star v-for="rating in 5" :key="rating" class="h-4 w-4" :class="rating <= (visit.feedback?.rating ?? 0) ? 'fill-current' : 'opacity-30'" />
                                </div>
                                <span class="text-xs font-extrabold uppercase text-emerald-700">{{ t('customerPortal.feedbackSubmitted', 'Feedback submitted') }}</span>
                            </div>
                            <p v-if="visit.feedback.comment" class="mt-2 text-sm leading-6 text-emerald-800">{{ visit.feedback.comment }}</p>
                        </div>
                        <form v-else-if="visit.can_submit_feedback" class="mt-4 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3" @submit.prevent="submitVisitFeedback(visit)">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-extrabold text-slate-900">{{ t('customerPortal.rateVisit', 'Rate this visit') }}</p>
                                    <p class="mt-1 text-xs font-bold text-slate-500">{{ t('customerPortal.rateVisitIntro', 'Tell us how the completed service went.') }}</p>
                                </div>
                                <div class="inline-flex gap-1">
                                    <button
                                        v-for="rating in 5"
                                        :key="rating"
                                        type="button"
                                        class="rounded-md p-1 text-amber-500 transition hover:bg-white"
                                        :aria-label="t('customerPortal.ratingValue', ':count stars', { count: rating })"
                                        @click="setVisitRating(visit, rating)"
                                    >
                                        <Star class="h-5 w-5" :class="rating <= feedbackForm(visit).rating ? 'fill-current' : 'opacity-30'" />
                                    </button>
                                </div>
                            </div>
                            <textarea v-model="feedbackForm(visit).comment" rows="2" class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-800" :placeholder="t('customerPortal.feedbackCommentPlaceholder', 'Optional note for the operations team')"></textarea>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-[#1040b6] px-3 py-2 text-sm font-extrabold text-white disabled:opacity-60" :disabled="feedbackForm(visit).processing">
                                <Send class="h-4 w-4" />
                                {{ t('customerPortal.submitFeedback', 'Submit feedback') }}
                            </button>
                        </form>
                        <div v-if="canRequestReschedule(visit)" class="mt-4 space-y-3 rounded-lg bg-slate-50 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="inline-flex items-center gap-2 text-sm font-extrabold text-slate-800">
                                    <TimerReset class="h-4 w-4 text-[#1040b6]" />
                                    {{ t('customerPortal.requestReschedule', 'Request reschedule') }}
                                </div>
                                <span v-if="pendingRescheduleForVisit(visit)" class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(pendingRescheduleForVisit(visit)?.status ?? 'pending')">
                                    {{ statusLabel(pendingRescheduleForVisit(visit)?.status ?? 'pending') }}
                                </span>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <label class="text-xs font-bold uppercase text-slate-500">
                                    {{ t('customerPortal.newDate', 'New date') }}
                                    <input v-model="rescheduleForm(visit).requested_for" type="date" class="mt-1 h-10 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                </label>
                                <label class="text-xs font-bold uppercase text-slate-500">
                                    {{ t('customerPortal.startTime', 'Start') }}
                                    <input v-model="rescheduleForm(visit).starts_at" type="time" class="mt-1 h-10 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                </label>
                                <label class="text-xs font-bold uppercase text-slate-500">
                                    {{ t('customerPortal.endTime', 'End') }}
                                    <input v-model="rescheduleForm(visit).ends_at" type="time" class="mt-1 h-10 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                </label>
                            </div>
                            <textarea v-model="rescheduleForm(visit).description" rows="2" class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-800" :placeholder="t('customerPortal.rescheduleReason', 'Reason')"></textarea>
                            <button type="button" class="inline-flex items-center gap-2 rounded-md bg-[#1040b6] px-3 py-2 text-sm font-extrabold text-white disabled:opacity-60" :disabled="rescheduleForm(visit).processing" @click="submitReschedule(visit)">
                                <Send class="h-4 w-4" />
                                {{ t('customerPortal.sendRequest', 'Send request') }}
                            </button>
                        </div>
                    </article>
                    <div v-if="allVisits.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500 lg:col-span-2">{{ t('customerPortal.noVisits', 'No visits yet.') }}</div>
                </div>
            </section>

            <section v-if="activeTab === 'invoices'" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-extrabold">{{ t('customerPortal.invoicesTitle', 'Invoices') }}</h2>
                <div class="mt-5 grid gap-3 lg:grid-cols-2">
                    <article v-for="invoice in invoices" :key="invoice.id" class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-extrabold">{{ invoice.number }}</h3>
                            <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(invoice.status)">{{ statusLabel(invoice.status) }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ t('customerPortal.dueDate', 'Due date') }}: {{ invoice.due_date ?? '-' }}</p>
                        <p class="mt-3 text-sm font-extrabold text-[#1040b6]">{{ t('customerPortal.balanceAmount', 'Balance SAR :amount', { amount: invoice.balance_sar }) }}</p>
                        <Link :href="invoice.detail_url" class="mt-4 inline-flex rounded-md border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">
                            {{ t('customerPortal.viewInvoice', 'View invoice') }}
                        </Link>
                    </article>
                    <div v-if="invoices.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500 lg:col-span-2">{{ t('customerPortal.noInvoices', 'No invoices yet.') }}</div>
                </div>
            </section>

            <section v-if="activeTab === 'acceptance'" class="mt-6 space-y-6">
                <div v-if="latestContractDecision" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-extrabold uppercase text-[#1040b6]">{{ t('customerPortal.latestContractDecision', 'Latest contract decision') }}</p>
                            <h2 class="mt-2 text-xl font-extrabold">{{ decisionLabel(latestContractDecision.decision) }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ latestContractDecision.customer_note || t('customerPortal.noDecisionNote', 'No note added.') }}</p>
                        </div>
                        <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(latestContractDecision.status)">{{ statusLabel(latestContractDecision.status) }}</span>
                    </div>
                    <p v-if="latestContractDecision.admin_note" class="mt-4 rounded-md bg-slate-50 px-3 py-2 text-sm leading-6 text-slate-600">{{ latestContractDecision.admin_note }}</p>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <form class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submitContractDecision('accepted')">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-lg bg-emerald-50 text-emerald-600">
                                <Signature class="h-5 w-5" />
                            </span>
                            <h2 class="text-xl font-extrabold">{{ t('customerPortal.acceptAndSign', 'Accept and sign') }}</h2>
                        </div>
                        <div class="mt-5 grid gap-4">
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.signerName', 'Signer name') }}
                                <input v-model="acceptanceForm.signer_name" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.signerTitle', 'Signer title') }}
                                <input v-model="acceptanceForm.signer_title" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.typedSignature', 'Typed signature') }}
                                <input v-model="acceptanceForm.signature_text" type="text" class="mt-1 h-12 w-full rounded-md border border-slate-200 px-3 font-serif text-lg text-slate-900">
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.acceptanceNote', 'Note') }}
                                <textarea v-model="acceptanceForm.customer_note" rows="3" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-800"></textarea>
                            </label>
                            <label class="inline-flex items-start gap-3 rounded-lg bg-slate-50 p-3 text-sm font-bold text-slate-700">
                                <input v-model="acceptanceForm.accepted_terms" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-[#1040b6] focus:ring-[#1040b6]">
                                <span>{{ t('customerPortal.acceptTermsConfirm', 'I confirm that I reviewed the scope, price, payment plan, and terms for this contract.') }}</span>
                            </label>
                            <button type="submit" class="inline-flex w-fit items-center gap-2 rounded-md bg-[#1040b6] px-4 py-2 text-sm font-extrabold text-white disabled:opacity-60" :disabled="acceptanceForm.processing">
                                <CheckCircle2 class="h-4 w-4" />
                                {{ t('customerPortal.submitAcceptance', 'Submit acceptance') }}
                            </button>
                        </div>
                    </form>

                    <form class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submitContractDecision('change_requested')">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-lg bg-amber-50 text-amber-600">
                                <LifeBuoy class="h-5 w-5" />
                            </span>
                            <h2 class="text-xl font-extrabold">{{ t('customerPortal.requestContractChanges', 'Request changes') }}</h2>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ t('customerPortal.requestContractChangesBody', 'Send the contract team the exact change you need before signing.') }}</p>
                        <label class="mt-5 block text-sm font-bold text-slate-600">
                            {{ t('customerPortal.changeRequestMessage', 'Change request') }}
                            <textarea v-model="changeRequestForm.customer_note" rows="7" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-800"></textarea>
                        </label>
                        <button type="submit" class="mt-4 inline-flex w-fit items-center gap-2 rounded-md border border-slate-200 px-4 py-2 text-sm font-extrabold text-slate-700 hover:bg-slate-50 disabled:opacity-60" :disabled="changeRequestForm.processing">
                            <Send class="h-4 w-4" />
                            {{ t('customerPortal.sendChangeRequest', 'Send change request') }}
                        </button>
                    </form>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.contractDecisionHistory', 'Acceptance history') }}</h2>
                    <div class="mt-5 grid gap-3">
                        <article v-for="decision in contractDecisions" :key="decision.id" class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(decision.status)">{{ statusLabel(decision.status) }}</span>
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-extrabold text-slate-700">{{ decisionLabel(decision.decision) }}</span>
                            </div>
                            <p v-if="decision.signer_name" class="mt-3 text-sm font-extrabold text-slate-800">{{ decision.signer_name }}<span v-if="decision.signer_title"> / {{ decision.signer_title }}</span></p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ decision.customer_note || '-' }}</p>
                            <p v-if="decision.admin_note" class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-sm leading-6 text-slate-600">{{ decision.admin_note }}</p>
                        </article>
                        <div v-if="contractDecisions.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500">{{ t('customerPortal.noContractDecisions', 'No acceptance activity yet.') }}</div>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'support'" class="mt-6 grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-lg bg-[#1040b6]/10 text-[#1040b6]">
                            <LifeBuoy class="h-5 w-5" />
                        </span>
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.openSupportTicket', 'Open support ticket') }}</h2>
                    </div>
                    <div class="mt-5 grid gap-4">
                        <label class="text-sm font-bold text-slate-600">
                            {{ t('customerPortal.priority', 'Priority') }}
                            <select v-model="supportForm.priority" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                <option value="low">{{ priorityLabel('low') }}</option>
                                <option value="normal">{{ priorityLabel('normal') }}</option>
                                <option value="high">{{ priorityLabel('high') }}</option>
                                <option value="urgent">{{ priorityLabel('urgent') }}</option>
                            </select>
                        </label>
                        <label class="text-sm font-bold text-slate-600">
                            {{ t('customerPortal.relatedVisit', 'Related visit') }}
                            <select v-model="supportForm.visit_id" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                <option value="">{{ t('customerPortal.noSpecificVisit', 'No specific visit') }}</option>
                                <option v-for="visit in allVisits" :key="visit.id" :value="visit.id">{{ visit.scheduled_for }} / {{ visit.starts_at }} - {{ visit.ends_at }}</option>
                            </select>
                        </label>
                        <label class="text-sm font-bold text-slate-600">
                            {{ t('customerPortal.subject', 'Subject') }}
                            <input v-model="supportForm.subject" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                        </label>
                        <label class="text-sm font-bold text-slate-600">
                            {{ t('customerPortal.message', 'Message') }}
                            <textarea v-model="supportForm.description" rows="5" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-800"></textarea>
                        </label>
                        <button type="button" class="inline-flex w-fit items-center gap-2 rounded-md bg-[#1040b6] px-4 py-2 text-sm font-extrabold text-white disabled:opacity-60" :disabled="supportForm.processing" @click="submitSupportTicket">
                            <Send class="h-4 w-4" />
                            {{ t('customerPortal.sendTicket', 'Send ticket') }}
                        </button>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.requestHistory', 'Request history') }}</h2>
                    <div class="mt-5 grid gap-3">
                        <article v-for="request in serviceRequests" :key="request.id" class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(request.status)">{{ statusLabel(request.status) }}</span>
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-extrabold text-slate-700">{{ serviceRequestTypeLabel(request.type) }}</span>
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-extrabold text-slate-700">{{ priorityLabel(request.priority) }}</span>
                            </div>
                            <h3 class="mt-3 font-extrabold">{{ request.subject ?? serviceRequestTypeLabel(request.type) }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ request.description ?? '-' }}</p>
                            <p v-if="request.type === 'reschedule'" class="mt-3 text-sm font-bold text-[#1040b6]">{{ request.requested_for }} / {{ request.starts_at }} - {{ request.ends_at }}</p>
                            <p v-if="request.admin_note" class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-sm leading-6 text-slate-600">{{ request.admin_note }}</p>
                        </article>
                        <div v-if="serviceRequests.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500">{{ t('customerPortal.noServiceRequests', 'No requests yet.') }}</div>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'scope'" class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.scopeTitle', 'Scope') }}</h2>
                    <div class="mt-5 grid gap-3">
                        <article v-for="scope in contract.service_scope" :key="scope.area" class="rounded-lg border border-slate-200 p-4">
                            <h3 class="font-extrabold">{{ scope.area }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ scope.tasks || '-' }}</p>
                        </article>
                        <div v-if="contract.service_scope.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500">{{ t('customerPortal.noScope', 'No scope items listed.') }}</div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.termsTitle', 'Terms') }}</h2>
                    <p class="mt-5 whitespace-pre-line rounded-lg bg-slate-50 p-4 text-sm leading-7 text-slate-700">{{ contract.terms_and_conditions || contract.special_terms || '-' }}</p>
                    <div v-if="contract.addendums.length" class="mt-5 grid gap-3">
                        <article v-for="addendum in contract.addendums" :key="addendum.id" class="rounded-lg border border-slate-200 p-4">
                            <h3 class="font-extrabold">{{ addendum.number }}. {{ addendum.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ addendum.summary }}</p>
                        </article>
                    </div>
                </div>
            </section>
        </section>
    </main>
</template>
