<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    Banknote,
    BarChart3,
    CalendarCheck,
    CreditCard,
    FileText,
    Target,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed, type Component } from 'vue';
import MoneyText from '../Components/MoneyText.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import { money, percent } from '../lib/format';
import { useI18n } from '../lib/i18n';
import type { InvoiceRow, Visit, WorkerPerformance } from '../types';

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
    collectionTrend: Array<{
        label: string;
        billed: number;
        collected: number;
    }>;
    visits: Visit[];
    invoices: InvoiceRow[];
    workerPerformance: WorkerPerformance[];
    managementLinks?: {
        reports: string;
        finance: string;
        operations: string;
    };
}>();

const { t } = useI18n();

type MetricCard = {
    label: string;
    value: string | number;
    trend: string;
    trendTone: 'up' | 'down' | 'neutral';
    icon: Component;
};

const metricCards = computed<MetricCard[]>(() => [
    {
        label: t('dashboard.monthRevenue', 'Month revenue'),
        value: money(props.metrics.revenue),
        trend: t('dashboard.billed', 'Billed'),
        trendTone: 'up',
        icon: TrendingUp,
    },
    {
        label: t('dashboard.collected', 'Collected'),
        value: money(props.metrics.monthlyCollected),
        trend: t('dashboard.received', 'Received'),
        trendTone: 'up',
        icon: Banknote,
    },
    {
        label: t('dashboard.openReceivables', 'Open receivables'),
        value: money(props.metrics.openReceivables),
        trend: t('dashboard.outstanding', 'Outstanding'),
        trendTone: 'neutral',
        icon: FileText,
    },
    {
        label: t('dashboard.overdueBalance', 'Overdue balance'),
        value: money(props.metrics.overdue),
        trend: t('dashboard.needsAction', 'Needs action'),
        trendTone: 'down',
        icon: AlertTriangle,
    },
    {
        label: t('dashboard.grossProfit', 'Gross profit'),
        value: money(props.metrics.grossProfit),
        trend: t('dashboard.margin', ':value margin', { value: percent(props.metrics.grossMarginPercent) }),
        trendTone: props.metrics.grossProfit >= 0 ? 'up' : 'down',
        icon: TrendingUp,
    },
    {
        label: t('dashboard.materialCost', 'Material cost'),
        value: money(props.metrics.materialCost),
        trend: t('dashboard.usedInJobs', 'Used in jobs'),
        trendTone: 'neutral',
        icon: FileText,
    },
    {
        label: t('dashboard.overtime', 'Overtime'),
        value: t('dashboard.minutesShort', ':countm', { count: props.metrics.overtimeMinutes }),
        trend: t('dashboard.thisMonth', 'This month'),
        trendTone: props.metrics.overtimeMinutes > 0 ? 'down' : 'up',
        icon: AlertTriangle,
    },
    {
        label: t('dashboard.heldCheques', 'Held cheques'),
        value: money(props.metrics.heldCheques),
        trend: t('dashboard.pendingBank', 'Pending bank'),
        trendTone: 'neutral',
        icon: CreditCard,
    },
    {
        label: t('dashboard.activeContracts', 'Active contracts'),
        value: props.metrics.activeContracts,
        trend: t('dashboard.recurringBase', 'Recurring base'),
        trendTone: 'neutral',
        icon: FileText,
    },
    {
        label: t('dashboard.todayVisits', 'Today visits'),
        value: props.metrics.todayVisits,
        trend: t('dashboard.completeCount', ':count complete', { count: props.metrics.completedToday }),
        trendTone: 'up',
        icon: CalendarCheck,
    },
    {
        label: t('dashboard.workerUtilization', 'Worker utilization'),
        value: percent(props.metrics.workerUtilization),
        trend: t('dashboard.missedCount', ':count missed', { count: props.metrics.missedToday }),
        trendTone: props.metrics.missedToday > 0 ? 'down' : 'up',
        icon: BarChart3,
    },
]);

const maxCollectionTrend = computed(() => Math.max(1, ...props.collectionTrend.flatMap((item) => [item.billed, item.collected])));
const operationSplit = computed(() => [
    { label: t('dashboard.scheduled', 'Scheduled'), value: props.visits.filter((visit) => visit.status === 'scheduled').length, class: 'bg-brand-500' },
    { label: t('dashboard.inProgress', 'In progress'), value: props.visits.filter((visit) => visit.status === 'in_progress').length, class: 'bg-warning-500' },
    { label: t('dashboard.completed', 'Completed'), value: props.visits.filter((visit) => visit.status === 'completed').length, class: 'bg-success-500' },
    { label: t('dashboard.missed', 'Missed'), value: props.visits.filter((visit) => visit.status === 'missed').length, class: 'bg-error-500' },
]);

function trendHeight(value: number): string {
    if (value <= 0) {
        return '0%';
    }

    return `${Math.max(4, Math.round((value / maxCollectionTrend.value) * 100))}%`;
}

function visitTime(visit: Visit): string {
    return visit.time ?? `${visit.starts_at?.slice(0, 5) ?? '--:--'} - ${visit.ends_at?.slice(0, 5) ?? '--:--'}`;
}

function visitWorker(visit: Visit): string {
    return typeof visit.worker === 'string' ? visit.worker : visit.worker?.name ?? t('dashboard.unassigned', 'Unassigned');
}

function visitCustomer(visit: Visit): string {
    if (typeof visit.site !== 'string') {
        return visit.site?.customer?.name ?? visit.customer ?? t('dashboard.customerFallback', 'Customer');
    }

    return visit.customer ?? t('dashboard.customerFallback', 'Customer');
}

function visitSite(visit: Visit): string {
    return typeof visit.site === 'string' ? visit.site : visit.site?.name ?? t('dashboard.siteFallback', 'Site');
}

function visitService(visit: Visit): string {
    return typeof visit.service === 'string' ? visit.service : visit.service?.title ?? t('dashboard.contractService', 'Contract service');
}

function invoiceBalance(invoice: InvoiceRow): number {
    return invoice.balance_halalas ?? invoice.balance ?? 0;
}
</script>

<template>
    <Head :title="t('dashboard.headTitle', 'Dashboard')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('dashboard.title', 'Operations Command Center') }}</h1>
                <p class="mt-1 text-sm leading-6 text-gray-500">
                    {{ t('dashboard.subtitle', 'Daily visits, attendance risks, worker targets, and collections in one admin view.') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <Link :href="managementLinks?.operations ?? '/app/operations'" class="ta-btn ta-btn-primary">{{ t('dashboard.openOperations', 'Open operations') }}</Link>
                <Link :href="managementLinks?.reports ?? '/app/reports'" class="ta-btn ta-btn-secondary">{{ t('dashboard.reports', 'Reports') }}</Link>
                <Link :href="managementLinks?.finance ?? '/app/finance'" class="ta-btn ta-btn-secondary">{{ t('dashboard.reviewFinance', 'Review finance') }}</Link>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-4 md:gap-6">
            <div class="col-span-12 space-y-6 xl:col-span-7">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
                    <article v-for="card in metricCards" :key="card.label" class="ta-card p-5 md:p-6">
                        <div class="ta-icon-box">
                            <component :is="card.icon" class="h-6 w-6" />
                        </div>

                        <div class="mt-5 flex items-end justify-between gap-4">
                            <div>
                                <span class="text-sm text-gray-500">{{ card.label }}</span>
                                <h4 class="mt-2 text-3xl font-bold text-gray-800">{{ card.value }}</h4>
                            </div>

                            <span
                                class="flex items-center gap-1 rounded-full px-2 py-0.5 text-sm font-medium"
                                :class="{
                                    'bg-success-50 text-success-600': card.trendTone === 'up',
                                    'bg-error-50 text-error-600': card.trendTone === 'down',
                                    'bg-brand-50 text-brand-500': card.trendTone === 'neutral',
                                }"
                            >
                                <ArrowUp v-if="card.trendTone === 'up'" class="h-3 w-3" />
                                <ArrowDown v-if="card.trendTone === 'down'" class="h-3 w-3" />
                                {{ card.trend }}
                            </span>
                        </div>
                    </article>
                </div>

                <article class="ta-card p-5 sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">{{ t('dashboard.collectionsPace', 'Collections Pace') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.collectionsPaceBody', 'Expected cash visibility for the current operating month.') }}</p>
                        </div>
                        <span class="rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-500">{{ t('dashboard.sarDashboard', 'SAR dashboard') }}</span>
                    </div>

                    <div class="mt-8 flex h-48 items-end gap-2">
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

                    <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-brand-500" /> {{ t('dashboard.billed', 'Billed') }}</span>
                        <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-success-500" /> {{ t('dashboard.collected', 'Collected') }}</span>
                    </div>

                    <div class="mt-5 grid gap-3 border-t border-gray-100 pt-5 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-medium uppercase text-gray-400">{{ t('dashboard.revenue', 'Revenue') }}</p>
                            <p class="mt-1 text-base font-semibold text-gray-800">{{ money(metrics.revenue) }}</p>
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

            <div class="col-span-12 xl:col-span-5">
                <article class="ta-card h-full p-5 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">{{ t('dashboard.todayOperations', 'Today Operations') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.todayOperationsBody', 'Visit status mix and field actions.') }}</p>
                        </div>
                        <Users class="h-5 w-5 text-brand-500" />
                    </div>

                    <div class="mt-8 flex justify-center">
                        <div class="relative flex h-56 w-56 items-center justify-center rounded-full bg-gray-50">
                            <div class="absolute inset-5 rounded-full border-[28px] border-brand-500" />
                            <div class="absolute inset-10 rounded-full border-[18px] border-success-500/80" />
                            <div class="relative text-center">
                                <p class="text-4xl font-bold text-gray-800">{{ metrics.todayVisits }}</p>
                                <p class="mt-1 text-sm font-medium text-gray-500">{{ t('dashboard.visits', 'visits') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 space-y-4">
                        <div v-for="item in operationSplit" :key="item.label" class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full" :class="item.class" />
                                <span class="text-sm font-medium text-gray-700">{{ item.label }}</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ item.value }}</span>
                        </div>
                    </div>

                    <Link href="/app/worker/today" class="ta-btn ta-btn-secondary mt-8 w-full">
                        {{ t('dashboard.workerMobileView', 'Worker mobile view') }}
                    </Link>
                </article>
            </div>

            <div class="col-span-12">
                <article class="ta-card overflow-hidden px-4 pb-3 pt-4 sm:px-6">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">{{ t('dashboard.todayServiceBoard', 'Today Service Board') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.todayServiceBoardBody', 'Attendance, assigned worker, site, and completion status.') }}</p>
                        </div>
                        <Link href="/app/operations" class="ta-btn ta-btn-secondary">{{ t('dashboard.seeAll', 'See all') }}</Link>
                    </div>

                    <div class="w-full overflow-x-auto">
                        <table class="ta-table min-w-[760px]">
                            <thead>
                                <tr>
                                    <th>{{ t('dashboard.time', 'Time') }}</th>
                                    <th>{{ t('dashboard.worker', 'Worker') }}</th>
                                    <th>{{ t('dashboard.customerSite', 'Customer / Site') }}</th>
                                    <th>{{ t('dashboard.service', 'Service') }}</th>
                                    <th>{{ t('dashboard.status', 'Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="visit in visits" :key="visit.id">
                                    <td class="font-semibold text-gray-800">{{ visitTime(visit) }}</td>
                                    <td>{{ visitWorker(visit) }}</td>
                                    <td>
                                        <div class="font-medium text-gray-800">{{ visitCustomer(visit) }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ visitSite(visit) }}</div>
                                    </td>
                                    <td>{{ visitService(visit) }}</td>
                                    <td><StatusBadge :status="visit.status" /></td>
                                </tr>
                                <tr v-if="visits.length === 0">
                                    <td colspan="5" class="py-8 text-center text-gray-500">{{ t('dashboard.noVisits', 'No visits scheduled for today.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>

            <div class="col-span-12 xl:col-span-5">
                <article class="ta-card p-5 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">{{ t('dashboard.workerTargetPace', 'Worker Target Pace') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.workerTargetPaceBody', 'Revenue actuals against active worker targets.') }}</p>
                        </div>
                        <Target class="h-5 w-5 text-brand-500" />
                    </div>

                    <div class="mt-6 space-y-5">
                        <div v-for="worker in workerPerformance" :key="worker.id">
                            <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                <div>
                                    <div class="font-semibold text-gray-800">{{ worker.name }}</div>
                                    <div class="text-xs text-gray-500">{{ t('dashboard.completedVisits', ':count completed visits', { count: worker.completed_visits }) }}</div>
                                </div>
                                <div class="text-sm font-semibold text-gray-700">{{ percent(worker.pace) }}</div>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-brand-500" :style="{ width: `${Math.min(worker.pace, 100)}%` }" />
                            </div>
                            <div class="mt-2 flex justify-between text-xs text-gray-500">
                                <span><MoneyText :value="worker.actual" /></span>
                                <span>{{ t('dashboard.target', 'Target') }} <MoneyText :value="worker.target" /></span>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div class="col-span-12 xl:col-span-7">
                <article class="ta-card overflow-hidden px-4 pb-3 pt-4 sm:px-6">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">{{ t('dashboard.receivablesFollowUp', 'Receivables Follow-up') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.receivablesFollowUpBody', 'Open invoices ordered by due date.') }}</p>
                        </div>
                        <Link :href="managementLinks?.finance ?? '/app/finance'" class="ta-btn ta-btn-secondary">{{ t('dashboard.openFinance', 'Open finance') }}</Link>
                    </div>

                    <div class="w-full overflow-x-auto">
                        <table class="ta-table min-w-[700px]">
                            <thead>
                                <tr>
                                    <th>{{ t('dashboard.invoice', 'Invoice') }}</th>
                                    <th>{{ t('dashboard.customer', 'Customer') }}</th>
                                    <th>{{ t('dashboard.due', 'Due') }}</th>
                                    <th>{{ t('dashboard.balance', 'Balance') }}</th>
                                    <th>{{ t('dashboard.status', 'Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="invoice in invoices" :key="invoice.id">
                                    <td class="font-semibold text-gray-800">{{ invoice.number }}</td>
                                    <td>{{ invoice.customer }}</td>
                                    <td>{{ invoice.due_date }}</td>
                                    <td class="font-semibold text-gray-800"><MoneyText :value="invoiceBalance(invoice)" /></td>
                                    <td><StatusBadge :status="invoice.status" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
