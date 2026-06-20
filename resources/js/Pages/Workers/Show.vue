<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart3,
    BriefcaseBusiness,
    CalendarDays,
    CheckCircle2,
    Clock3,
    FileText,
    GraduationCap,
    Pencil,
    Target,
    TrendingUp,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type { Money, Worker } from '../../types';

type CatalogOption = {
    key: string;
    label: string;
};

type Catalog = {
    statuses: CatalogOption[];
    jobRoles: CatalogOption[];
    skills: CatalogOption[];
    certifications: CatalogOption[];
    documentTypes: CatalogOption[];
    languages: CatalogOption[];
};

type Summary = {
    target_halalas: Money;
    actual_revenue_halalas: Money;
    gross_profit_halalas: Money;
    worked_minutes: number;
    scheduled_visits_count: number;
    completed_visits_count: number;
    missed_visits_count: number;
    overtime_minutes: number;
    billable_overtime_minutes: number;
    billable_overtime_halalas: Money;
    target_attainment_percent: number;
    target_status: 'achieved' | 'behind' | 'no_target' | string;
    profit_per_hour_halalas: Money;
    contracts_count: number;
    sites_count: number;
};

type ContractRow = {
    id: number;
    reference: string;
    status: string;
    customer?: { id: number; name: string } | null;
    site?: { id: number; name: string; city?: string | null; district?: string | null } | null;
    service?: { id: number; title: string } | null;
    assignments_count: number;
    visits_count: number;
    completed_visits_count: number;
    worked_minutes: number;
    actual_revenue_halalas: Money;
    gross_profit_halalas: Money;
    detail_url: string;
};

type AssignmentRow = {
    id: number;
    weekday: number;
    starts_at: string;
    ends_at: string;
    share_percent: number;
    status: string;
    contract?: { id: number; reference: string; customer?: { id: number; name: string } | null } | null;
    site?: { id: number; name: string; city?: string | null; district?: string | null } | null;
    service?: { id: number; title: string } | null;
};

type VisitRow = {
    id: number;
    scheduled_for?: string | null;
    starts_at: string;
    ends_at: string;
    status: string;
    actual_minutes: number;
    planned_minutes: number;
    overtime_minutes: number;
    billable_overtime_minutes: number;
    actual_revenue_halalas: Money;
    gross_profit_halalas: Money;
    contract?: { id: number; reference: string; customer?: { id: number; name: string } | null } | null;
    site?: { id: number; name: string; city?: string | null; district?: string | null } | null;
    service?: { id: number; title: string } | null;
};

type TargetRow = {
    id: number;
    period_start?: string | null;
    period_end?: string | null;
    target_halalas: Money;
    target_sar: string;
};

type TrendRow = {
    month: string;
    label: string;
    actual_revenue_halalas: Money;
    gross_profit_halalas: Money;
    worked_minutes: number;
    completed_visits_count: number;
};

type Panel = 'overview' | 'performance' | 'contracts' | 'visits' | 'targets' | 'compliance';

const props = defineProps<{
    worker: Worker;
    filters: {
        from: string;
        to: string;
    };
    summary: Summary;
    contracts: ContractRow[];
    assignments: AssignmentRow[];
    visits: VisitRow[];
    targets: TargetRow[];
    trend: TrendRow[];
    catalog: Catalog;
    backUrl: string;
    statusUrl: string;
    targetStoreUrl: string;
}>();

const { t } = useI18n();
const activePanel = ref<Panel>('overview');

const rangeForm = reactive({
    from: props.filters.from,
    to: props.filters.to,
});

const targetForm = useForm({
    period_start: props.filters.from,
    period_end: props.filters.to,
    target_sar: '',
});

const panels = computed(() => [
    { key: 'overview' as Panel, label: t('workersAdmin.overviewTab', 'Overview'), icon: BarChart3 },
    { key: 'performance' as Panel, label: t('workersAdmin.performanceTab', 'Performance'), icon: TrendingUp },
    { key: 'contracts' as Panel, label: t('workersAdmin.contractsTab', 'Contracts'), icon: BriefcaseBusiness },
    { key: 'visits' as Panel, label: t('workersAdmin.visitsTab', 'Visits'), icon: CalendarDays },
    { key: 'targets' as Panel, label: t('workersAdmin.targetsTab', 'Targets'), icon: Target },
    { key: 'compliance' as Panel, label: t('workersAdmin.complianceTab', 'Compliance'), icon: FileText },
]);

const maxTrendRevenue = computed(() => Math.max(1, ...props.trend.map((row) => row.actual_revenue_halalas)));

function applyRange(): void {
    router.get(`/app/workers/${props.worker.id}`, {
        from: rangeForm.from,
        to: rangeForm.to,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function submitTarget(): void {
    targetForm.post(props.targetStoreUrl, {
        preserveScroll: true,
        onSuccess: () => targetForm.reset('target_sar'),
    });
}

function catalogLabel(group: keyof Catalog, key?: string | null): string {
    if (! key) {
        return '-';
    }

    const option = props.catalog[group].find((item) => item.key === key);

    return t(`workersAdmin.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
}

function formatHours(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    if (hours === 0) {
        return `${mins}m`;
    }

    return mins === 0 ? `${hours}h` : `${hours}h ${mins}m`;
}

function progressWidth(value: number): string {
    return `${Math.min(100, Math.max(0, value))}%`;
}

function barHeight(value: number): string {
    return `${Math.max(10, Math.round((value / maxTrendRevenue.value) * 132))}px`;
}

function targetTone(status: string): string {
    const tones: Record<string, string> = {
        achieved: 'bg-success-50 text-success-700',
        behind: 'bg-error-50 text-error-700',
        no_target: 'bg-gray-100 text-gray-600',
    };

    return tones[status] ?? tones.no_target;
}

function targetLabel(status: string): string {
    return t(`workersAdmin.targetStatuses.${status}`, status.replaceAll('_', ' '));
}

function weekdayLabel(weekday: number): string {
    const keys: Record<number, string> = {
        1: 'mon',
        2: 'tue',
        3: 'wed',
        4: 'thu',
        5: 'fri',
        6: 'sat',
        7: 'sun',
    };

    return t(`operations.weekdays.${keys[weekday] ?? 'mon'}`, String(weekday));
}

function expiryTone(status: string): string {
    const tones: Record<string, string> = {
        valid: 'bg-success-50 text-success-600',
        expiring: 'bg-warning-50 text-warning-600',
        expired: 'bg-error-50 text-error-600',
        not_tracked: 'bg-gray-100 text-gray-500',
    };

    return tones[status] ?? tones.not_tracked;
}
</script>

<template>
    <Head :title="t('workersAdmin.workerDetailHeadTitle', 'Worker details')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <Link :href="backUrl" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-brand-600">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('workersAdmin.backToWorkers', 'Back to workers') }}
                </Link>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="ta-page-title">{{ worker.name }}</h1>
                    <StatusBadge :status="worker.status" />
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="targetTone(summary.target_status)">
                        {{ targetLabel(summary.target_status) }}
                    </span>
                </div>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ worker.employee_code }} / {{ catalogLabel('jobRoles', worker.job_role) }} / {{ worker.phone || t('workersAdmin.noPhone', 'No phone') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link :href="statusUrl" class="ta-btn ta-btn-secondary">
                    <BarChart3 class="h-4 w-4" />
                    {{ t('workersAdmin.showStatusReport', 'Status report') }}
                </Link>
                <Link :href="worker.edit_url ?? `/app/workers/${worker.id}/edit`" class="ta-btn ta-btn-primary">
                    <Pencil class="h-4 w-4" />
                    {{ t('workersAdmin.editWorker', 'Edit worker') }}
                </Link>
            </div>
        </div>

        <form class="ta-card overflow-hidden p-0" @submit.prevent="applyRange">
            <div class="grid gap-4 p-4 lg:grid-cols-[minmax(220px,0.7fr)_minmax(0,1fr)_auto] lg:items-end">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <CalendarDays class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ t('workersAdmin.dateRange', 'Date range') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('workersAdmin.dateRangeBody', 'Choose the reporting period and workers you want to compare.') }}</p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('workersAdmin.from', 'From') }}</span>
                        <input v-model="rangeForm.from" type="date" class="h-12 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 text-sm font-semibold text-gray-900 outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-50" />
                    </label>
                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('workersAdmin.to', 'To') }}</span>
                        <input v-model="rangeForm.to" type="date" class="h-12 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 text-sm font-semibold text-gray-900 outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-50" />
                    </label>
                </div>

                <button type="submit" class="ta-btn ta-btn-secondary h-12 w-full lg:w-auto">
                    {{ t('workersAdmin.applyFilters', 'Apply filters') }}
                </button>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('workersAdmin.target', 'Target') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="summary.target_halalas" /></p>
            </article>
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('workersAdmin.actualRevenue', 'Actual revenue') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="summary.actual_revenue_halalas" /></p>
            </article>
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('workersAdmin.grossProfit', 'Gross profit') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="summary.gross_profit_halalas" /></p>
            </article>
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('workersAdmin.hoursWorked', 'Hours worked') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ formatHours(summary.worked_minutes) }}</p>
            </article>
        </div>

        <div class="ta-card p-2">
            <div class="grid gap-2 sm:grid-cols-3 xl:grid-cols-6">
                <button
                    v-for="panel in panels"
                    :key="panel.key"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition"
                    :class="activePanel === panel.key ? 'bg-brand-500 text-white shadow-theme-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                    @click="activePanel = panel.key"
                >
                    <component :is="panel.icon" class="h-4 w-4" />
                    {{ panel.label }}
                </button>
            </div>
        </div>

        <section v-if="activePanel === 'overview'" class="grid gap-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <FileText class="h-5 w-5" />
                    </span>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.employeeProfile', 'Employee profile') }}</h2>
                </div>

                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.employeeCode', 'Employee code') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ worker.employee_code }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.roleLanguage', 'Role language') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ catalogLabel('languages', worker.role_language) }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.hiredOn', 'Hired on') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ worker.hired_on ?? '-' }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.costRate', 'Cost rate (SAR)') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900"><MoneyText :value="worker.cost_rate_halalas" /></dd>
                    </div>
                </dl>

                <div class="mt-5">
                    <p class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.skills', 'Skills') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span v-for="skill in worker.skills" :key="skill" class="rounded-lg bg-gray-50 px-3 py-1 text-xs font-semibold text-gray-700">
                            {{ catalogLabel('skills', skill) }}
                        </span>
                        <span v-if="worker.skills.length === 0" class="rounded-lg bg-gray-50 px-3 py-1 text-xs font-semibold text-gray-500">
                            {{ t('workersAdmin.none', 'None') }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-50 text-success-600">
                        <Target class="h-5 w-5" />
                    </span>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.performanceSummary', 'Performance summary') }}</h2>
                </div>

                <div class="rounded-xl bg-gray-50 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-gray-700">{{ t('workersAdmin.targetVsActual', 'Target vs actual') }}</span>
                        <span class="text-sm font-bold text-gray-900">{{ summary.target_attainment_percent }}%</span>
                    </div>
                    <div class="h-3 rounded-full bg-white">
                        <div class="h-3 rounded-full bg-brand-500" :style="{ width: progressWidth(summary.target_attainment_percent) }"></div>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.completedVisits', 'Completed visits') }}</p>
                        <p class="mt-2 text-xl font-bold text-gray-900">{{ summary.completed_visits_count }} / {{ summary.scheduled_visits_count }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.profitPerHour', 'Profit per hour') }}</p>
                        <p class="mt-2 text-xl font-bold text-gray-900"><MoneyText :value="summary.profit_per_hour_halalas" /></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.billableOvertime', 'Billable overtime') }}</p>
                        <p class="mt-2 text-xl font-bold text-gray-900">{{ formatHours(summary.billable_overtime_minutes) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.missedVisits', 'Missed visits') }}</p>
                        <p class="mt-2 text-xl font-bold text-gray-900">{{ summary.missed_visits_count }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section v-else-if="activePanel === 'performance'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.8fr)]">
            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-50 text-success-600">
                        <TrendingUp class="h-5 w-5" />
                    </span>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.performanceTrend', 'Performance trend') }}</h2>
                </div>

                <div class="flex min-h-[190px] items-end gap-3 overflow-x-auto rounded-xl bg-gray-50 p-4">
                    <div v-for="row in trend" :key="row.month" class="flex min-w-20 flex-1 flex-col items-center justify-end gap-2">
                        <div class="flex h-36 w-full items-end justify-center rounded-lg bg-white px-3">
                            <div class="w-full rounded-t-lg bg-success-500" :style="{ height: barHeight(row.actual_revenue_halalas) }"></div>
                        </div>
                        <p class="text-xs font-semibold text-gray-700">{{ row.label }}</p>
                        <p class="text-xs text-gray-500">{{ row.completed_visits_count }} / {{ formatHours(row.worked_minutes) }}</p>
                    </div>
                    <p v-if="trend.length === 0" class="w-full py-8 text-center text-sm text-gray-500">
                        {{ t('workersAdmin.noPerformanceData', 'No performance data yet.') }}
                    </p>
                </div>
            </article>

            <article class="ta-card p-5">
                <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.performanceSummary', 'Performance summary') }}</h2>
                <div class="mt-5 space-y-4">
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <CheckCircle2 class="h-5 w-5 text-success-600" />
                            <span class="text-sm font-semibold text-gray-700">{{ t('workersAdmin.targetAttainment', 'Target attainment') }}</span>
                        </div>
                        <span class="text-xl font-bold text-gray-900">{{ summary.target_attainment_percent }}%</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <Clock3 class="h-5 w-5 text-warning-600" />
                            <span class="text-sm font-semibold text-gray-700">{{ t('workersAdmin.overtime', 'Overtime') }}</span>
                        </div>
                        <span class="text-xl font-bold text-gray-900">{{ formatHours(summary.overtime_minutes) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <BriefcaseBusiness class="h-5 w-5 text-brand-500" />
                            <span class="text-sm font-semibold text-gray-700">{{ t('workersAdmin.contracts', 'Contracts') }}</span>
                        </div>
                        <span class="text-xl font-bold text-gray-900">{{ summary.contracts_count }}</span>
                    </div>
                </div>
            </article>
        </section>

        <section v-else-if="activePanel === 'contracts'" class="ta-card overflow-hidden p-5">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <BriefcaseBusiness class="h-5 w-5" />
                </span>
                <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.contractContribution', 'Contract contribution') }}</h2>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="ta-table min-w-[980px]">
                    <thead>
                        <tr>
                            <th>{{ t('workersAdmin.contract', 'Contract') }}</th>
                            <th>{{ t('workersAdmin.customer', 'Customer') }}</th>
                            <th>{{ t('workersAdmin.site', 'Site') }}</th>
                            <th>{{ t('workersAdmin.service', 'Service') }}</th>
                            <th>{{ t('workersAdmin.visits', 'Visits') }}</th>
                            <th>{{ t('workersAdmin.hoursWorked', 'Hours worked') }}</th>
                            <th>{{ t('workersAdmin.revenue', 'Revenue') }}</th>
                            <th>{{ t('workersAdmin.profit', 'Profit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="contract in contracts" :key="contract.id">
                            <td>
                                <Link :href="contract.detail_url" class="font-semibold text-brand-600 hover:text-brand-700">{{ contract.reference }}</Link>
                                <div class="mt-1"><StatusBadge :status="contract.status" /></div>
                            </td>
                            <td>{{ contract.customer?.name ?? '-' }}</td>
                            <td>{{ contract.site?.name ?? '-' }}</td>
                            <td>{{ contract.service?.title ?? '-' }}</td>
                            <td>{{ contract.completed_visits_count }} / {{ contract.visits_count }}</td>
                            <td>{{ formatHours(contract.worked_minutes) }}</td>
                            <td><MoneyText :value="contract.actual_revenue_halalas" /></td>
                            <td><MoneyText :value="contract.gross_profit_halalas" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else-if="activePanel === 'visits'" class="ta-card overflow-hidden p-5">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <CalendarDays class="h-5 w-5" />
                </span>
                <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.visitHistory', 'Visit history') }}</h2>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="ta-table min-w-[1080px]">
                    <thead>
                        <tr>
                            <th>{{ t('workersAdmin.date', 'Date') }}</th>
                            <th>{{ t('workersAdmin.time', 'Time') }}</th>
                            <th>{{ t('workersAdmin.contract', 'Contract') }}</th>
                            <th>{{ t('workersAdmin.site', 'Site') }}</th>
                            <th>{{ t('workersAdmin.status', 'Status') }}</th>
                            <th>{{ t('workersAdmin.hoursWorked', 'Hours worked') }}</th>
                            <th>{{ t('workersAdmin.overtime', 'Overtime') }}</th>
                            <th>{{ t('workersAdmin.revenue', 'Revenue') }}</th>
                            <th>{{ t('workersAdmin.profit', 'Profit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="visit in visits" :key="visit.id">
                            <td>{{ visit.scheduled_for }}</td>
                            <td>{{ visit.starts_at }} - {{ visit.ends_at }}</td>
                            <td>{{ visit.contract?.reference ?? '-' }}</td>
                            <td>{{ visit.site?.name ?? '-' }}</td>
                            <td><StatusBadge :status="visit.status" /></td>
                            <td>{{ formatHours(visit.actual_minutes) }}</td>
                            <td>{{ formatHours(visit.overtime_minutes) }}</td>
                            <td><MoneyText :value="visit.actual_revenue_halalas" /></td>
                            <td><MoneyText :value="visit.gross_profit_halalas" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="visits.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                {{ t('workersAdmin.noVisitsForWorker', 'No visits recorded for this worker in this range.') }}
            </p>
        </section>

        <section v-else-if="activePanel === 'targets'" class="grid gap-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <Target class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.setTarget', 'Set target') }}</h2>
                        <p class="text-sm text-gray-500">{{ t('workersAdmin.setTargetBody', 'Create a daily, weekly, or monthly target by choosing the matching date range.') }}</p>
                    </div>
                </div>

                <form class="space-y-4" @submit.prevent="submitTarget">
                    <label class="block space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.periodStart', 'Period start') }}</span>
                        <input v-model="targetForm.period_start" type="date" class="ta-input" />
                        <span v-if="targetForm.errors.period_start" class="text-xs font-semibold text-error-600">{{ targetForm.errors.period_start }}</span>
                    </label>
                    <label class="block space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.periodEnd', 'Period end') }}</span>
                        <input v-model="targetForm.period_end" type="date" class="ta-input" />
                        <span v-if="targetForm.errors.period_end" class="text-xs font-semibold text-error-600">{{ targetForm.errors.period_end }}</span>
                    </label>
                    <label class="block space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.targetSar', 'Target SAR') }}</span>
                        <input v-model="targetForm.target_sar" type="number" min="0.01" step="0.01" class="ta-input" />
                        <span v-if="targetForm.errors.target_sar" class="text-xs font-semibold text-error-600">{{ targetForm.errors.target_sar }}</span>
                    </label>
                    <button type="submit" class="ta-btn ta-btn-primary w-full" :disabled="targetForm.processing">
                        {{ t('workersAdmin.saveTarget', 'Save target') }}
                    </button>
                </form>
            </article>

            <article class="ta-card overflow-hidden p-5">
                <h2 class="mb-5 text-lg font-semibold text-gray-900">{{ t('workersAdmin.targetHistory', 'Target history') }}</h2>
                <div class="space-y-3">
                    <div v-for="target in targets" :key="target.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 p-4">
                        <div>
                            <p class="font-semibold text-gray-900">{{ target.period_start }} - {{ target.period_end }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ t('workersAdmin.target', 'Target') }}</p>
                        </div>
                        <MoneyText :value="target.target_halalas" class="text-lg font-bold text-gray-900" />
                    </div>
                    <p v-if="targets.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('workersAdmin.noTargets', 'No targets recorded yet.') }}
                    </p>
                </div>
            </article>
        </section>

        <section v-else class="grid gap-5 xl:grid-cols-2">
            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <FileText class="h-5 w-5" />
                    </span>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.documents', 'Documents') }}</h2>
                </div>

                <div class="space-y-3">
                    <div v-for="document in worker.documents" :key="document.id" class="rounded-xl border border-gray-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900">{{ catalogLabel('documentTypes', document.document_type) }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ document.document_number || t('workersAdmin.noNumber', 'No number') }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="expiryTone(document.status)">
                                {{ t(`workersAdmin.expiry.${document.status}`, document.status) }}
                            </span>
                        </div>
                    </div>
                    <p v-if="worker.documents.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('workersAdmin.noDocuments', 'No documents yet.') }}
                    </p>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-50 text-success-600">
                        <GraduationCap class="h-5 w-5" />
                    </span>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.training', 'Training') }}</h2>
                </div>

                <div class="space-y-3">
                    <div v-for="record in worker.training_records" :key="record.id" class="rounded-xl border border-gray-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900">{{ record.course_name }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ record.certificate_code || t('workersAdmin.noCode', 'No code') }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="expiryTone(record.status)">
                                {{ t(`workersAdmin.expiry.${record.status}`, record.status) }}
                            </span>
                        </div>
                    </div>
                    <p v-if="worker.training_records.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('workersAdmin.noTraining', 'No training yet.') }}
                    </p>
                </div>
            </article>
        </section>
    </section>
</template>
