<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Banknote,
    CalendarCheck,
    CalendarDays,
    CheckCircle2,
    Clock3,
    FileText,
    LayoutDashboard,
    MapPin,
    ReceiptText,
    TrendingUp,
    UserCheck,
    Users,
    WalletCards,
} from '@lucide/vue';
import { computed, type Component } from 'vue';
import MoneyText from '../Components/MoneyText.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import { money, percent } from '../lib/format';
import { useI18n } from '../lib/i18n';
import type { InvoiceRow, Visit, WorkerPerformance } from '../types';

type TopStat = {
    key: string;
    label: string;
    value: number;
    format: 'money' | 'number' | 'percent';
    tone: 'success' | 'danger' | 'warning' | 'info' | 'neutral';
    helper?: string;
    helper_value?: number | string;
};

type VisitTimelineRow = {
    id: number;
    status: string;
    starts_at: string;
    ends_at: string;
    timeline_start_percent: number;
    timeline_width_percent: number;
    worker?: string | null;
    worker_count: number;
    site: string;
    customer: string;
    city?: string | null;
    district?: string | null;
    service: string;
};

type InvoiceSummary = {
    total_halalas: number;
    paid_halalas: number;
    open_halalas: number;
    pending_halalas: number;
    overdue_halalas: number;
    paid_percent: number;
    pending_percent: number;
    overdue_percent: number;
};

type OverdueInvoice = {
    id: number;
    number: string;
    customer: string;
    due_date: string;
    days_overdue: number;
    balance: number;
};

type OnlineWorker = {
    id: number;
    name: string;
    status: string;
    job_role?: string | null;
    current_visit: {
        id: number;
        site: string;
        customer: string;
        service: string;
        starts_at: string;
        ends_at: string;
        status: string;
        progress_percent: number;
    };
};

const props = defineProps<{
    metrics: {
        revenue: number;
        monthlyCollected: number;
        openReceivables: number;
        overdue: number;
        heldCheques: number;
        workerUtilization: number;
        grossProfit: number;
        grossMarginPercent: number;
        materialCost: number;
        overtimeMinutes: number;
        activeContracts: number;
        todayVisits: number;
        completedToday: number;
        missedToday: number;
        newLeads: number;
    };
    dashboardDate: string;
    topStats: TopStat[];
    collectionTrend: Array<{
        label: string;
        billed: number;
        collected: number;
    }>;
    visits: Visit[];
    visitTimeline: VisitTimelineRow[];
    invoiceSummary: InvoiceSummary;
    invoices: InvoiceRow[];
    overdueInvoices: OverdueInvoice[];
    onlineWorker?: OnlineWorker | null;
    workerPerformance: WorkerPerformance[];
    managementLinks?: {
        reports: string;
        finance: string;
        operations: string;
    };
}>();

const { locale, t } = useI18n();

const statIcons: Record<string, Component> = {
    revenue: TrendingUp,
    overdue: WalletCards,
    active_contracts: FileText,
    attendance_rate: Users,
    today_visits: CalendarCheck,
};

const statToneClasses: Record<TopStat['tone'], string> = {
    success: 'bg-success-50 text-success-600',
    danger: 'bg-error-50 text-error-600',
    warning: 'bg-warning-50 text-warning-600',
    info: 'bg-brand-50 text-brand-500',
    neutral: 'bg-gray-100 text-gray-600',
};

const statHelperToneClasses: Record<TopStat['tone'], string> = {
    success: 'text-success-600',
    danger: 'text-error-600',
    warning: 'text-warning-600',
    info: 'text-brand-500',
    neutral: 'text-gray-600',
};

const scheduleHours = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'];

const maxCollectionTrend = computed(() => Math.max(1, ...props.collectionTrend.flatMap((item) => [item.billed, item.collected])));
const latestInvoices = computed(() => props.invoices.slice(0, 4));
const topWorkers = computed(() => props.workerPerformance.slice(0, 3));

const operationSplit = computed(() => {
    const inProgress = props.visits.filter((visit) => visit.status === 'in_progress').length;
    const scheduled = Math.max(0, props.metrics.todayVisits - props.metrics.completedToday - props.metrics.missedToday - inProgress);

    return [
        { label: t('dashboard.scheduled', 'Scheduled'), value: scheduled, class: 'bg-brand-500' },
        { label: t('dashboard.inProgress', 'In progress'), value: inProgress, class: 'bg-warning-500' },
        { label: t('dashboard.completed', 'Completed'), value: props.metrics.completedToday, class: 'bg-success-500' },
        { label: t('dashboard.missed', 'Missed'), value: props.metrics.missedToday, class: 'bg-error-500' },
    ];
});

const invoiceMix = computed(() => [
    {
        label: t('dashboard.paidInvoices', 'Paid'),
        value: props.invoiceSummary.paid_halalas,
        percent: props.invoiceSummary.paid_percent,
        class: 'bg-success-500',
        textClass: 'text-success-600',
    },
    {
        label: t('dashboard.pendingInvoices', 'Pending'),
        value: props.invoiceSummary.pending_halalas,
        percent: props.invoiceSummary.pending_percent,
        class: 'bg-brand-500',
        textClass: 'text-brand-500',
    },
    {
        label: t('dashboard.overdueInvoices', 'Overdue'),
        value: props.invoiceSummary.overdue_halalas,
        percent: props.invoiceSummary.overdue_percent,
        class: 'bg-error-500',
        textClass: 'text-error-600',
    },
]);

const invoiceDonutStyle = computed(() => {
    const total = props.invoiceSummary.total_halalas;

    if (total <= 0) {
        return { background: '#f3f4f6' };
    }

    const paidEnd = clampPercent(props.invoiceSummary.paid_percent);
    const pendingEnd = clampPercent(paidEnd + props.invoiceSummary.pending_percent);
    const overdueEnd = clampPercent(pendingEnd + props.invoiceSummary.overdue_percent);

    return {
        background: `conic-gradient(#12b76a 0% ${paidEnd}%, #465fff ${paidEnd}% ${pendingEnd}%, #f04438 ${pendingEnd}% ${overdueEnd}%, #f3f4f6 ${overdueEnd}% 100%)`,
    };
});

const currentTimePercent = computed<number | null>(() => {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const localDate = `${now.getFullYear()}-${month}-${day}`;

    if (props.dashboardDate !== localDate) {
        return null;
    }

    const minutes = now.getHours() * 60 + now.getMinutes();
    const start = 8 * 60;
    const end = 20 * 60;

    if (minutes < start || minutes > end) {
        return null;
    }

    return Math.round(((minutes - start) / (end - start)) * 100);
});

function statIcon(key: string): Component {
    return statIcons[key] ?? LayoutDashboard;
}

function statValue(stat: TopStat): string {
    if (stat.format === 'money') {
        return money(stat.value);
    }

    if (stat.format === 'percent') {
        return percent(stat.value);
    }

    return new Intl.NumberFormat(locale.value === 'ar' ? 'ar-SA' : 'en-SA').format(stat.value);
}

function statLabel(stat: TopStat): string {
    return t(stat.label, fallbackStatLabel(stat.key));
}

function statHelper(stat: TopStat): string {
    if (! stat.helper) {
        return '';
    }

    const helper = t(stat.helper, fallbackStatHelper(stat.helper));

    if (stat.helper_value === undefined || stat.helper_value === null || stat.helper_value === '') {
        return helper;
    }

    return `${stat.helper_value} ${helper}`;
}

function fallbackStatLabel(key: string): string {
    const labels: Record<string, string> = {
        revenue: 'Revenue this month',
        overdue: 'Overdue receivables',
        active_contracts: 'Active contracts',
        attendance_rate: 'Attendance rate',
        today_visits: 'Today visits',
    };

    return labels[key] ?? key.replaceAll('_', ' ');
}

function fallbackStatHelper(key: string): string {
    const helpers: Record<string, string> = {
        'dashboard.topStats.thisMonth': 'this month',
        'dashboard.topStats.overdueCustomers': 'overdue invoices',
        'dashboard.topStats.expiringSoon': 'ending within 30 days',
        'dashboard.topStats.present': 'present',
        'dashboard.topStats.completed': 'completed',
    };

    return helpers[key] ?? key;
}

function formatDashboardDate(date: string): string {
    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA' : 'en-SA', {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
}

function trendHeight(value: number): string {
    if (value <= 0) {
        return '0%';
    }

    return `${Math.max(4, Math.round((value / maxCollectionTrend.value) * 100))}%`;
}

function invoiceBalance(invoice: InvoiceRow): number {
    return invoice.balance_halalas ?? invoice.balance ?? 0;
}

function timelineStyle(visit: VisitTimelineRow): Record<string, string> {
    const width = Math.max(6, Math.min(100, visit.timeline_width_percent));
    const left = Math.max(0, Math.min(100 - width, visit.timeline_start_percent));

    return {
        left: `${left}%`,
        width: `${width}%`,
    };
}

function timelineBarClass(status: string): string {
    const classes: Record<string, string> = {
        completed: 'bg-success-50 text-success-600 border-success-500/30',
        in_progress: 'bg-warning-50 text-warning-600 border-warning-500/30',
        missed: 'bg-error-50 text-error-600 border-error-500/30',
        scheduled: 'bg-brand-100 text-brand-700 border-brand-500/30',
    };

    return classes[status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
}

function timelineDotClass(status: string): string {
    const classes: Record<string, string> = {
        completed: 'bg-success-500',
        in_progress: 'bg-warning-500',
        missed: 'bg-error-500',
        scheduled: 'bg-brand-500',
    };

    return classes[status] ?? 'bg-gray-400';
}

function visitPlace(visit: VisitTimelineRow): string {
    return [visit.district, visit.city].filter(Boolean).join(' - ') || t('dashboard.locationFallback', 'Location pending');
}

function workerInitials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();
}

function progressWidth(value?: number | null): string {
    return `${clampPercent(value ?? 0)}%`;
}

function workerProgressClass(value: number): string {
    if (value >= 85) {
        return 'bg-success-500';
    }

    if (value >= 65) {
        return 'bg-brand-500';
    }

    return 'bg-warning-500';
}

function clampPercent(value: number): number {
    return Math.max(0, Math.min(100, Math.round(value)));
}
</script>

<template>
    <Head :title="t('dashboard.headTitle', 'Dashboard')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('dashboard.title', 'Operations Command Center') }}</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ t('dashboard.subtitle', 'Daily visits, attendance risks, worker targets, and collections in one admin view.') }}
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm">
                    <CalendarDays class="h-4 w-4 text-gray-500" />
                    {{ formatDashboardDate(dashboardDate) }}
                </div>
                <Link :href="managementLinks?.operations ?? '/app/operations'" class="ta-btn ta-btn-primary">
                    {{ t('dashboard.newAction', 'New action') }}
                </Link>
                <Link :href="managementLinks?.reports ?? '/app/reports'" class="ta-btn ta-btn-secondary">{{ t('dashboard.reports', 'Reports') }}</Link>
                <Link :href="managementLinks?.finance ?? '/app/finance'" class="ta-btn ta-btn-secondary">{{ t('dashboard.reviewFinance', 'Review finance') }}</Link>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
            <article v-for="stat in topStats" :key="stat.key" class="ta-card p-4 sm:p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500">{{ statLabel(stat) }}</p>
                        <p class="mt-4 break-words text-2xl font-bold leading-tight text-gray-900 2xl:text-3xl">{{ statValue(stat) }}</p>
                    </div>
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg" :class="statToneClasses[stat.tone]">
                        <component :is="statIcon(stat.key)" class="h-5 w-5" />
                    </div>
                </div>
                <p v-if="statHelper(stat)" class="mt-4 text-sm font-semibold" :class="statHelperToneClasses[stat.tone]">
                    {{ statHelper(stat) }}
                </p>
            </article>
        </div>

        <div class="grid grid-cols-12 gap-5">
            <article class="ta-card col-span-12 overflow-hidden p-4 sm:p-5 2xl:col-span-7">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('dashboard.visitSchedule', 'Today visit schedule') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.visitScheduleBody', 'Live workload by site, time window, and assigned worker count.') }}</p>
                    </div>
                    <Link href="/app/operations" class="ta-btn ta-btn-secondary">{{ t('dashboard.viewAllVisits', 'View all visits') }}</Link>
                </div>

                <div v-if="visitTimeline.length > 0" class="overflow-x-auto">
                    <div class="min-w-[640px]" dir="ltr">
                        <div class="grid grid-cols-[11rem_minmax(340px,1fr)_4.5rem] items-center gap-3 border-b border-gray-100 pb-3 text-xs font-medium text-gray-500">
                            <div />
                            <div class="grid grid-cols-7">
                                <span v-for="hour in scheduleHours" :key="hour" class="text-center">{{ hour }}</span>
                            </div>
                            <div class="text-end">{{ t('dashboard.upcoming', 'Upcoming') }}</div>
                        </div>

                        <div class="divide-y divide-gray-100">
                            <div v-for="visit in visitTimeline" :key="visit.id" class="grid grid-cols-[11rem_minmax(340px,1fr)_4.5rem] items-center gap-3 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="timelineDotClass(visit.status)" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ visit.site }}</p>
                                        <p class="truncate text-xs text-gray-500">{{ visitPlace(visit) }}</p>
                                    </div>
                                </div>

                                <div class="relative h-12 rounded-lg bg-gray-50">
                                    <div class="absolute inset-y-0 left-0 right-0 grid grid-cols-6">
                                        <span v-for="hour in scheduleHours.slice(0, -1)" :key="hour" class="border-e border-gray-200/70" />
                                    </div>
                                    <div
                                        v-if="currentTimePercent !== null"
                                        class="absolute bottom-1 top-1 z-10 border-s border-dashed border-error-500"
                                        :style="{ left: `${currentTimePercent}%` }"
                                    />
                                    <div
                                        class="absolute top-1/2 z-20 flex h-8 -translate-y-1/2 items-center justify-center rounded-md border px-3 text-xs font-semibold shadow-sm"
                                        :class="timelineBarClass(visit.status)"
                                        :style="timelineStyle(visit)"
                                    >
                                        {{ t('dashboard.workerCountShort', ':count workers', { count: visit.worker_count }) }}
                                    </div>
                                </div>

                                <div class="text-end text-sm font-semibold text-brand-500">
                                    {{ visit.starts_at }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-gray-200 py-12 text-center text-sm text-gray-500">
                    {{ t('dashboard.noVisits', 'No visits scheduled for today.') }}
                </div>
            </article>

            <article class="ta-card col-span-12 p-4 sm:p-5 md:col-span-6 xl:col-span-5 2xl:col-span-2">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('dashboard.invoiceSummary', 'Invoice summary') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.invoiceSummaryBody', 'Paid, pending, and overdue invoice mix.') }}</p>
                    </div>
                    <ReceiptText class="h-5 w-5 text-brand-500" />
                </div>

                <div class="mt-6 flex justify-center">
                    <div class="relative h-36 w-36 rounded-full sm:h-44 sm:w-44 2xl:h-36 2xl:w-36" :style="invoiceDonutStyle">
                        <div class="absolute inset-7 flex flex-col items-center justify-center rounded-full bg-white text-center shadow-inner sm:inset-9 2xl:inset-7">
                            <span class="text-xs font-medium text-gray-500">{{ t('dashboard.invoiceTotal', 'Invoice total') }}</span>
                            <span class="mt-1 text-base font-bold text-gray-900">{{ money(invoiceSummary.total_halalas) }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 space-y-3">
                    <div v-for="item in invoiceMix" :key="item.label" class="flex items-center justify-between gap-3 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" :class="item.class" />
                            <span class="font-medium text-gray-700">{{ item.label }}</span>
                        </div>
                        <div class="text-end">
                            <p class="font-semibold text-gray-900">{{ money(item.value) }}</p>
                            <p class="text-xs" :class="item.textClass">{{ percent(item.percent) }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 border-t border-gray-100 pt-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">{{ t('dashboard.latestInvoices', 'Latest invoices') }}</h3>
                        <Link :href="managementLinks?.finance ?? '/app/finance'" class="text-sm font-semibold text-brand-500">{{ t('dashboard.viewAll', 'View all') }}</Link>
                    </div>
                    <div class="space-y-3">
                        <div v-for="invoice in latestInvoices" :key="invoice.id" class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-900">{{ invoice.number }}</p>
                                <p class="truncate text-xs text-gray-500">{{ invoice.customer }}</p>
                            </div>
                            <div class="text-end">
                                <MoneyText class="font-semibold text-gray-900" :value="invoiceBalance(invoice)" />
                                <div class="mt-1"><StatusBadge :status="invoice.status" /></div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>

            <aside class="col-span-12 space-y-5 md:col-span-6 xl:col-span-7 2xl:col-span-3">
                <article class="ta-card p-4 sm:p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('dashboard.workerPerformanceMonthly', 'Worker performance') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.workerPerformanceBody', 'Monthly target achievement.') }}</p>
                        </div>
                        <UserCheck class="h-5 w-5 text-brand-500" />
                    </div>

                    <div v-if="topWorkers.length > 0" class="space-y-4">
                        <div v-for="worker in topWorkers" :key="worker.id" class="rounded-lg border border-gray-200 p-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-gray-700">
                                    {{ workerInitials(worker.name) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ worker.name }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ t(`statuses.${worker.status}`, worker.status.replaceAll('_', ' ')) }}</p>
                                </div>
                                <span class="text-sm font-bold" :class="worker.pace >= 80 ? 'text-success-600' : 'text-warning-600'">{{ percent(worker.pace) }}</span>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-gray-500">
                                <div>
                                    <p>{{ t('dashboard.target', 'Target') }}</p>
                                    <MoneyText class="font-semibold text-gray-900" :value="worker.target" />
                                </div>
                                <div>
                                    <p>{{ t('dashboard.achieved', 'Achieved') }}</p>
                                    <MoneyText class="font-semibold text-gray-900" :value="worker.actual" />
                                </div>
                            </div>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full" :class="workerProgressClass(worker.pace)" :style="{ width: progressWidth(worker.pace) }" />
                            </div>
                        </div>
                    </div>
                    <p v-else class="py-8 text-center text-sm text-gray-500">{{ t('dashboard.noWorkerTargets', 'No worker targets recorded yet.') }}</p>

                    <Link href="/app/reports" class="ta-btn ta-btn-secondary mt-5 w-full">{{ t('dashboard.viewAllWorkers', 'View all workers') }}</Link>
                </article>

                <article v-if="onlineWorker" class="ta-card p-4 sm:p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('dashboard.activeWorkerNow', 'Active worker now') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.currentVisit', 'Current visit') }}</p>
                        </div>
                        <span class="h-2.5 w-2.5 rounded-full bg-success-500" />
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-success-50 text-sm font-bold text-success-700">
                            {{ workerInitials(onlineWorker.name) }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-900">{{ onlineWorker.name }}</p>
                            <p class="truncate text-sm text-gray-500">{{ onlineWorker.job_role ?? t('dashboard.worker', 'Worker') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3 text-sm">
                        <div class="flex items-center gap-2 text-gray-600">
                            <MapPin class="h-4 w-4 text-brand-500" />
                            <span class="truncate">{{ onlineWorker.current_visit.site }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 rounded-lg bg-gray-50 p-3">
                            <div>
                                <p class="text-xs text-gray-500">{{ t('dashboard.shiftStart', 'Shift start') }}</p>
                                <p class="font-semibold text-gray-900">{{ onlineWorker.current_visit.starts_at }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">{{ t('dashboard.visitProgress', 'Visit progress') }}</p>
                                <p class="font-semibold text-gray-900">{{ percent(onlineWorker.current_visit.progress_percent) }}</p>
                            </div>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-success-500" :style="{ width: progressWidth(onlineWorker.current_visit.progress_percent) }" />
                        </div>
                    </div>
                </article>
            </aside>

            <article class="ta-card col-span-12 overflow-hidden px-4 pb-4 pt-4 sm:px-5 xl:col-span-7">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('dashboard.overdueReceivables', 'Overdue receivables') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.overdueReceivablesBody', 'Customers requiring collection follow-up.') }}</p>
                    </div>
                    <Link :href="managementLinks?.finance ?? '/app/finance'" class="ta-btn ta-btn-secondary">{{ t('dashboard.openFinance', 'Open finance') }}</Link>
                </div>

                <div class="w-full overflow-x-auto">
                    <table class="ta-table min-w-[720px]">
                        <thead>
                            <tr>
                                <th>{{ t('dashboard.customer', 'Customer') }}</th>
                                <th>{{ t('dashboard.dueDate', 'Due date') }}</th>
                                <th>{{ t('dashboard.amount', 'Amount') }}</th>
                                <th>{{ t('dashboard.overdueFor', 'Overdue for') }}</th>
                                <th>{{ t('dashboard.action', 'Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invoice in overdueInvoices" :key="invoice.id">
                                <td>
                                    <p class="font-semibold text-gray-900">{{ invoice.customer }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ invoice.number }}</p>
                                </td>
                                <td>{{ invoice.due_date }}</td>
                                <td class="font-semibold text-error-600"><MoneyText :value="invoice.balance" /></td>
                                <td class="font-medium text-error-600">{{ t('dashboard.daysOverdue', ':count days', { count: invoice.days_overdue }) }}</td>
                                <td>
                                    <Link :href="managementLinks?.finance ?? '/app/finance'" class="ta-btn ta-btn-secondary px-3 py-2 text-xs">
                                        {{ t('dashboard.sendReminder', 'Send reminder') }}
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="overdueInvoices.length === 0">
                                <td colspan="5" class="py-8 text-center text-gray-500">{{ t('dashboard.noOverdueInvoices', 'No overdue invoices right now.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="ta-card col-span-12 p-4 sm:p-5 xl:col-span-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('dashboard.collectionsPace', 'Collections Pace') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.collectionsPaceBody', 'Expected cash visibility for the current operating month.') }}</p>
                    </div>
                    <Banknote class="h-5 w-5 text-success-600" />
                </div>

                <div class="mt-8 flex h-44 items-end gap-2">
                    <div
                        v-for="day in collectionTrend"
                        :key="day.label"
                        class="flex h-full flex-1 items-end justify-center gap-0.5 rounded-t-lg bg-gray-50 px-0.5"
                        :title="day.label"
                    >
                        <div class="w-1/2 rounded-t-sm bg-brand-500" :style="{ height: trendHeight(day.billed) }" />
                        <div class="w-1/2 rounded-t-sm bg-success-500" :style="{ height: trendHeight(day.collected) }" />
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-500">
                    <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-brand-500" /> {{ t('dashboard.billed', 'Billed') }}</span>
                    <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-success-500" /> {{ t('dashboard.collected', 'Collected') }}</span>
                </div>

                <div class="mt-5 grid gap-3 border-t border-gray-100 pt-5 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-400">{{ t('dashboard.revenue', 'Revenue') }}</p>
                        <p class="mt-1 text-base font-semibold text-gray-900">{{ money(metrics.revenue) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-400">{{ t('dashboard.overdue', 'Overdue') }}</p>
                        <p class="mt-1 text-base font-semibold text-error-600">{{ money(metrics.overdue) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-400">{{ t('dashboard.collected', 'Collected') }}</p>
                        <p class="mt-1 text-base font-semibold text-success-600">{{ money(metrics.monthlyCollected) }}</p>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
