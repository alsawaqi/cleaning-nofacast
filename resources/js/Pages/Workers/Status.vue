<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BarChart3, CalendarDays, CheckCircle2, Clock3, Eye, Filter, Target, TrendingUp, UsersRound, WalletCards, X } from '@lucide/vue';
import { computed, reactive } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import { useI18n } from '../../lib/i18n';
import type { Money } from '../../types';

type WorkerSummary = {
    id: number;
    name: string;
    employee_code?: string | null;
    status: string;
    job_role: string;
    detail_url: string;
    edit_url: string;
};

type WorkerPerformanceRow = {
    worker: WorkerSummary;
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

type TrendRow = {
    month: string;
    label: string;
    actual_revenue_halalas: Money;
    gross_profit_halalas: Money;
    worked_minutes: number;
    completed_visits_count: number;
};

type AvailabilityBlock = {
    id: number;
    worker_id: number;
    date?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    status: string;
    reason?: string | null;
};

type AttendanceCalendarWorkerDay = {
    worker: WorkerSummary;
    status: string;
    scheduled_visits_count: number;
    completed_visits_count: number;
    missed_visits_count: number;
    worked_minutes: number;
    visits: Array<{
        id: number;
        starts_at?: string | null;
        ends_at?: string | null;
        status: string;
        site?: { name: string } | null;
        contract?: { reference: string } | null;
    }>;
    blocks: AvailabilityBlock[];
};

type AttendanceCalendar = {
    summary: {
        scheduled_visits: number;
        completed_visits: number;
        missed_visits: number;
        unavailable_blocks: number;
        workers_count: number;
    };
    days: Array<{
        date: string;
        label: string;
        workers: AttendanceCalendarWorkerDay[];
    }>;
};

const props = defineProps<{
    filters: {
        from: string;
        to: string;
        selected_worker_ids: number[];
    };
    summary: {
        workers_count: number;
        target_total_halalas: Money;
        actual_revenue_total_halalas: Money;
        gross_profit_total_halalas: Money;
        worked_minutes: number;
        completed_visits_count: number;
        overtime_minutes: number;
        achieved_count: number;
        behind_count: number;
        no_target_count: number;
        target_attainment_percent: number;
    };
    workers: WorkerPerformanceRow[];
    comparison: WorkerPerformanceRow[];
    trend: TrendRow[];
    attendanceCalendar: AttendanceCalendar;
    workerOptions: WorkerSummary[];
    backUrl: string;
}>();

const { t } = useI18n();

const form = reactive({
    from: props.filters.from,
    to: props.filters.to,
    workers: props.filters.selected_worker_ids.map(String),
});
const availabilityForm = reactive({
    worker_id: props.filters.selected_worker_ids[0] ? String(props.filters.selected_worker_ids[0]) : '',
    date: props.filters.from,
    starts_at: '09:00',
    ends_at: '17:00',
    status: 'unavailable',
    reason: '',
    processing: false,
});

const metricCards = computed(() => [
    { label: t('workersAdmin.summaryTarget', 'Target'), value: props.summary.target_total_halalas, icon: Target, tone: 'bg-brand-50 text-brand-600', money: true },
    { label: t('workersAdmin.actualRevenue', 'Actual revenue'), value: props.summary.actual_revenue_total_halalas, icon: TrendingUp, tone: 'bg-success-50 text-success-600', money: true },
    { label: t('workersAdmin.grossProfit', 'Gross profit'), value: props.summary.gross_profit_total_halalas, icon: WalletCards, tone: 'bg-warning-50 text-warning-600', money: true },
    { label: t('workersAdmin.targetAttainment', 'Target attainment'), value: props.summary.target_attainment_percent, icon: CheckCircle2, tone: 'bg-gray-100 text-gray-700', money: false },
]);

const maxTrendRevenue = computed(() => Math.max(1, ...props.trend.map((row) => row.actual_revenue_halalas)));
const selectedWorkerCount = computed(() => form.workers.length);

function applyFilters(): void {
    router.get('/app/workers/status', {
        from: form.from,
        to: form.to,
        workers: form.workers.map((id) => Number(id)).filter(Boolean),
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function setMonthRange(offset: number): void {
    const base = form.from ? new Date(`${form.from}T00:00:00`) : new Date();
    const month = new Date(base.getFullYear(), base.getMonth() + offset, 1);
    const firstDay = new Date(month.getFullYear(), month.getMonth(), 1);
    const lastDay = new Date(month.getFullYear(), month.getMonth() + 1, 0);

    form.from = formatDateInput(firstDay);
    form.to = formatDateInput(lastDay);
}

function clearWorkerSelection(): void {
    form.workers = [];
}

function formatDateInput(date: Date): string {
    const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return localDate.toISOString().slice(0, 10);
}

function formatHours(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    if (hours === 0) {
        return `${mins}m`;
    }

    return mins === 0 ? `${hours}h` : `${hours}h ${mins}m`;
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

function progressWidth(value: number): string {
    return `${Math.min(100, Math.max(0, value))}%`;
}

function barHeight(value: number, maxValue: number): string {
    return `${Math.max(10, Math.round((value / maxValue) * 132))}px`;
}

function submitAvailabilityBlock(): void {
    availabilityForm.processing = true;

    router.post('/app/workers/availability-blocks', {
        worker_id: Number(availabilityForm.worker_id),
        date: availabilityForm.date,
        starts_at: availabilityForm.starts_at,
        ends_at: availabilityForm.ends_at,
        status: availabilityForm.status,
        reason: availabilityForm.reason,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            availabilityForm.reason = '';
        },
        onFinish: () => {
            availabilityForm.processing = false;
        },
    });
}

function attendanceTone(status: string): string {
    const tones: Record<string, string> = {
        worked: 'bg-success-50 text-success-700 ring-success-500/20',
        in_progress: 'bg-brand-50 text-brand-700 ring-brand-500/20',
        scheduled: 'bg-blue-50 text-blue-700 ring-blue-500/20',
        missed: 'bg-error-50 text-error-700 ring-error-500/20',
        unavailable: 'bg-warning-50 text-warning-700 ring-warning-500/20',
        available: 'bg-gray-50 text-gray-600 ring-gray-200',
    };

    return tones[status] ?? tones.available;
}

function attendanceLabel(status: string): string {
    return t(`workersAdmin.attendanceStatuses.${status}`, status.replaceAll('_', ' '));
}

function blockStatusLabel(status: string): string {
    return t(`workersAdmin.availabilityBlockStatuses.${status}`, status.replaceAll('_', ' '));
}
</script>

<template>
    <Head :title="t('workersAdmin.statusHeadTitle', 'Worker status')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <Link :href="backUrl" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-brand-600">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('workersAdmin.backToWorkers', 'Back to workers') }}
                </Link>
                <h1 class="ta-page-title">{{ t('workersAdmin.workerStatusTitle', 'Worker status report') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ t('workersAdmin.workerStatusSubtitle', 'Compare worker revenue, targets, visits, overtime, hours, and gross profit for a selected period.') }}
                </p>
            </div>
        </div>

        <form class="ta-card overflow-hidden p-0" @submit.prevent="applyFilters">
            <div class="flex flex-col gap-4 border-b border-gray-200 bg-gradient-to-r from-brand-50 via-white to-success-50 p-5 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 shadow-theme-xs">
                        <CalendarDays class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ t('workersAdmin.dateRange', 'Date range') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('workersAdmin.dateRangeBody', 'Choose the reporting period and workers you want to compare.') }}</p>
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-3">
                    <button type="button" class="ta-btn ta-btn-secondary h-10 px-3 text-xs" @click="setMonthRange(-1)">
                        {{ t('workersAdmin.previousMonth', 'Previous month') }}
                    </button>
                    <button type="button" class="ta-btn ta-btn-secondary h-10 px-3 text-xs" @click="setMonthRange(0)">
                        {{ t('workersAdmin.thisMonth', 'This month') }}
                    </button>
                    <button type="button" class="ta-btn ta-btn-secondary h-10 px-3 text-xs" @click="setMonthRange(1)">
                        {{ t('workersAdmin.nextMonth', 'Next month') }}
                    </button>
                </div>
            </div>

            <div class="grid gap-5 p-5 xl:grid-cols-[minmax(280px,0.75fr)_minmax(0,1.25fr)_auto] xl:items-end">
                <div class="rounded-2xl border border-gray-200 bg-white p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('workersAdmin.from', 'From') }}</span>
                            <input v-model="form.from" type="date" class="h-12 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 text-sm font-semibold text-gray-900 outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-50" />
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('workersAdmin.to', 'To') }}</span>
                            <input v-model="form.to" type="date" class="h-12 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 text-sm font-semibold text-gray-900 outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-50" />
                        </label>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('workersAdmin.workersToCompare', 'Workers to compare') }}</p>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ t('workersAdmin.selectedWorkersCount', ':count selected').replace(':count', String(selectedWorkerCount)) }}
                            </p>
                        </div>
                        <button v-if="selectedWorkerCount" type="button" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-gray-500 hover:bg-gray-50 hover:text-gray-900" @click="clearWorkerSelection">
                            <X class="h-3.5 w-3.5" />
                            {{ t('workersAdmin.clearSelection', 'Clear') }}
                        </button>
                    </div>

                    <div class="max-h-36 overflow-y-auto pr-1">
                        <div class="flex flex-wrap gap-2">
                            <label
                                v-for="worker in workerOptions"
                                :key="worker.id"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                                :class="form.workers.includes(String(worker.id)) ? 'border-brand-300 bg-brand-50 text-brand-700 shadow-theme-xs' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-brand-200 hover:bg-white hover:text-gray-900'"
                            >
                                <input v-model="form.workers" type="checkbox" class="sr-only" :value="String(worker.id)" />
                                <span class="h-2.5 w-2.5 rounded-full" :class="form.workers.includes(String(worker.id)) ? 'bg-brand-500' : 'bg-gray-300'"></span>
                                <span>{{ worker.name }}</span>
                                <span class="text-xs font-medium opacity-70">{{ worker.employee_code }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="ta-btn ta-btn-primary h-12 w-full px-5 xl:w-auto">
                    <Filter class="h-4 w-4" />
                    {{ t('workersAdmin.applyFilters', 'Apply filters') }}
                </button>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in metricCards" :key="card.label" class="ta-card p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900 sm:text-3xl">
                            <MoneyText v-if="card.money" :value="Number(card.value)" />
                            <span v-else>{{ card.value }}%</span>
                        </p>
                    </div>
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" :class="card.tone">
                        <component :is="card.icon" class="h-6 w-6" />
                    </span>
                </div>
            </article>
        </div>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <article class="ta-card p-5">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            <CalendarDays class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.attendanceCalendar', 'Attendance & availability calendar') }}</h2>
                            <p class="text-sm text-gray-500">{{ t('workersAdmin.attendanceCalendarBody', 'Review daily visits, missed work, worked time, and planned unavailable periods.') }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                        <span class="rounded-full bg-success-50 px-3 py-1 text-success-700">{{ attendanceCalendar.summary.completed_visits }} {{ t('workersAdmin.completedVisits', 'Completed visits') }}</span>
                        <span class="rounded-full bg-error-50 px-3 py-1 text-error-700">{{ attendanceCalendar.summary.missed_visits }} {{ t('workersAdmin.missedVisits', 'Missed visits') }}</span>
                        <span class="rounded-full bg-warning-50 px-3 py-1 text-warning-700">{{ attendanceCalendar.summary.unavailable_blocks }} {{ t('workersAdmin.unavailableBlocks', 'Unavailable') }}</span>
                    </div>
                </div>

                <div class="grid gap-3 lg:grid-cols-3">
                    <div v-for="day in attendanceCalendar.days" :key="day.date" class="rounded-2xl border border-gray-200 bg-white p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ day.label }}</p>
                                <h3 class="text-base font-bold text-gray-900">{{ day.date }}</h3>
                            </div>
                            <span class="rounded-full bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-500">{{ day.workers.length }}</span>
                        </div>

                        <div class="mt-3 space-y-2">
                            <div v-for="item in day.workers" :key="`${day.date}-${item.worker.id}`" class="rounded-xl bg-gray-50 px-3 py-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ item.worker.name }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ item.worker.employee_code }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold ring-1" :class="attendanceTone(item.status)">
                                        {{ attendanceLabel(item.status) }}
                                    </span>
                                </div>
                                <div v-if="item.visits.length" class="mt-2 space-y-1 text-xs text-gray-600">
                                    <p v-for="visit in item.visits" :key="visit.id" class="truncate">
                                        {{ visit.starts_at }}-{{ visit.ends_at }} / {{ visit.site?.name ?? visit.contract?.reference ?? t('workersAdmin.visit', 'Visit') }}
                                    </p>
                                </div>
                                <div v-if="item.blocks.length" class="mt-2 space-y-1 text-xs text-warning-700">
                                    <p v-for="block in item.blocks" :key="block.id" class="truncate">
                                        {{ block.starts_at ?? '--:--' }}-{{ block.ends_at ?? '--:--' }} / {{ blockStatusLabel(block.status) }} / {{ block.reason ?? t('workersAdmin.noReason', 'No reason') }}
                                    </p>
                                </div>
                                <p v-if="item.worked_minutes" class="mt-2 text-xs font-semibold text-success-700">{{ t('workersAdmin.workedTime', 'Worked') }}: {{ formatHours(item.worked_minutes) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>

            <form class="ta-card p-5" @submit.prevent="submitAvailabilityBlock">
                <div class="mb-4 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-warning-50 text-warning-600">
                        <Clock3 class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.addAvailabilityBlock', 'Add availability block') }}</h2>
                        <p class="text-sm text-gray-500">{{ t('workersAdmin.addAvailabilityBlockBody', 'Mark leave, training, or unavailable time for planning.') }}</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.worker', 'Worker') }}</span>
                        <select v-model="availabilityForm.worker_id" class="ta-input h-11 w-full px-3 text-sm" required>
                            <option value="">{{ t('workersAdmin.selectWorker', 'Select worker') }}</option>
                            <option v-for="worker in workerOptions" :key="worker.id" :value="String(worker.id)">{{ worker.name }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.date', 'Date') }}</span>
                        <input v-model="availabilityForm.date" type="date" class="ta-input h-11 w-full px-3 text-sm" required>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.startsAt', 'Starts at') }}</span>
                            <input v-model="availabilityForm.starts_at" type="time" class="ta-input h-11 w-full px-3 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.endsAt', 'Ends at') }}</span>
                            <input v-model="availabilityForm.ends_at" type="time" class="ta-input h-11 w-full px-3 text-sm">
                        </label>
                    </div>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.status', 'Status') }}</span>
                        <select v-model="availabilityForm.status" class="ta-input h-11 w-full px-3 text-sm">
                            <option value="unavailable">{{ blockStatusLabel('unavailable') }}</option>
                            <option value="on_leave">{{ blockStatusLabel('on_leave') }}</option>
                            <option value="training">{{ blockStatusLabel('training') }}</option>
                            <option value="available">{{ blockStatusLabel('available') }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.reason', 'Reason') }}</span>
                        <textarea v-model="availabilityForm.reason" class="ta-input min-h-24 w-full px-3 py-2 text-sm" :placeholder="t('workersAdmin.availabilityReasonPlaceholder', 'Example: Sick leave, training, personal appointment')" />
                    </label>
                    <button type="submit" class="ta-btn ta-btn-primary w-full" :disabled="availabilityForm.processing">
                        <CalendarDays class="h-4 w-4" />
                        {{ t('workersAdmin.saveAvailabilityBlock', 'Save availability') }}
                    </button>
                </div>
            </form>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <BarChart3 class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.comparison', 'Worker comparison') }}</h2>
                        <p class="text-sm text-gray-500">{{ t('workersAdmin.comparisonBody', 'Selected workers shown side by side by revenue and target pace.') }}</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div v-for="row in comparison" :key="row.worker.id" class="rounded-xl border border-gray-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900">{{ row.worker.name }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ row.worker.employee_code }} / {{ formatHours(row.worked_minutes) }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="targetTone(row.target_status)">
                                {{ targetLabel(row.target_status) }}
                            </span>
                        </div>
                        <div class="mt-4 h-3 rounded-full bg-gray-100">
                            <div class="h-3 rounded-full bg-brand-500" :style="{ width: progressWidth(row.target_attainment_percent) }"></div>
                        </div>
                        <div class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                            <div>
                                <span class="text-gray-500">{{ t('workersAdmin.target', 'Target') }}</span>
                                <p class="font-semibold text-gray-900"><MoneyText :value="row.target_halalas" /></p>
                            </div>
                            <div>
                                <span class="text-gray-500">{{ t('workersAdmin.actual', 'Actual') }}</span>
                                <p class="font-semibold text-success-700"><MoneyText :value="row.actual_revenue_halalas" /></p>
                            </div>
                            <div>
                                <span class="text-gray-500">{{ t('workersAdmin.profit', 'Profit') }}</span>
                                <p class="font-semibold text-gray-900"><MoneyText :value="row.gross_profit_halalas" /></p>
                            </div>
                        </div>
                    </div>
                    <p v-if="comparison.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('workersAdmin.noWorkerPerformanceData', 'No worker performance data for this range.') }}
                    </p>
                </div>
            </article>

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
                            <div class="w-full rounded-t-lg bg-success-500" :style="{ height: barHeight(row.actual_revenue_halalas, maxTrendRevenue) }"></div>
                        </div>
                        <p class="text-xs font-semibold text-gray-700">{{ row.label }}</p>
                        <p class="text-xs text-gray-500">{{ row.completed_visits_count }} / {{ formatHours(row.worked_minutes) }}</p>
                    </div>
                    <p v-if="trend.length === 0" class="w-full py-8 text-center text-sm text-gray-500">
                        {{ t('workersAdmin.noPerformanceData', 'No performance data yet.') }}
                    </p>
                </div>
            </article>
        </div>

        <article class="ta-card overflow-hidden p-5">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <UsersRound class="h-5 w-5" />
                    </span>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('workersAdmin.workerStatusTable', 'Worker status table') }}</h2>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full bg-success-50 px-3 py-1 text-success-700">{{ summary.achieved_count }} {{ t('workersAdmin.achieved', 'Achieved') }}</span>
                    <span class="rounded-full bg-error-50 px-3 py-1 text-error-700">{{ summary.behind_count }} {{ t('workersAdmin.behind', 'Behind') }}</span>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-600">{{ summary.no_target_count }} {{ t('workersAdmin.noTarget', 'No target') }}</span>
                </div>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="ta-table min-w-[1120px]">
                    <thead>
                        <tr>
                            <th>{{ t('workersAdmin.worker', 'Worker') }}</th>
                            <th>{{ t('workersAdmin.target', 'Target') }}</th>
                            <th>{{ t('workersAdmin.actual', 'Actual') }}</th>
                            <th>{{ t('workersAdmin.profit', 'Profit') }}</th>
                            <th>{{ t('workersAdmin.hoursWorked', 'Hours worked') }}</th>
                            <th>{{ t('workersAdmin.completedVisits', 'Completed visits') }}</th>
                            <th>{{ t('workersAdmin.overtime', 'Overtime') }}</th>
                            <th>{{ t('workersAdmin.contracts', 'Contracts') }}</th>
                            <th>{{ t('workersAdmin.sites', 'Sites') }}</th>
                            <th>{{ t('workersAdmin.status', 'Status') }}</th>
                            <th>{{ t('workersAdmin.details', 'Details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in workers" :key="row.worker.id">
                            <td>
                                <p class="font-semibold text-gray-900">{{ row.worker.name }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ row.worker.employee_code }}</p>
                            </td>
                            <td><MoneyText :value="row.target_halalas" /></td>
                            <td><MoneyText :value="row.actual_revenue_halalas" /></td>
                            <td><MoneyText :value="row.gross_profit_halalas" /></td>
                            <td>{{ formatHours(row.worked_minutes) }}</td>
                            <td>{{ row.completed_visits_count }} / {{ row.scheduled_visits_count }}</td>
                            <td>{{ formatHours(row.overtime_minutes) }}</td>
                            <td>{{ row.contracts_count }}</td>
                            <td>{{ row.sites_count }}</td>
                            <td>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="targetTone(row.target_status)">
                                    {{ targetLabel(row.target_status) }}
                                </span>
                            </td>
                            <td>
                                <Link :href="row.worker.detail_url" class="ta-btn ta-btn-secondary h-10 w-10 p-0" :aria-label="t('workersAdmin.openWorker', 'Open worker')">
                                    <Eye class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="workers.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                {{ t('workersAdmin.noWorkerPerformanceData', 'No worker performance data for this range.') }}
            </p>
        </article>

        <div class="grid gap-4 sm:grid-cols-3">
            <article class="ta-card p-5">
                <div class="flex items-center gap-3">
                    <Clock3 class="h-5 w-5 text-brand-500" />
                    <p class="text-sm font-semibold text-gray-900">{{ t('workersAdmin.hoursWorked', 'Hours worked') }}</p>
                </div>
                <p class="mt-3 text-2xl font-bold text-gray-900">{{ formatHours(summary.worked_minutes) }}</p>
            </article>
            <article class="ta-card p-5">
                <div class="flex items-center gap-3">
                    <CheckCircle2 class="h-5 w-5 text-success-600" />
                    <p class="text-sm font-semibold text-gray-900">{{ t('workersAdmin.completedVisits', 'Completed visits') }}</p>
                </div>
                <p class="mt-3 text-2xl font-bold text-gray-900">{{ summary.completed_visits_count }}</p>
            </article>
            <article class="ta-card p-5">
                <div class="flex items-center gap-3">
                    <Clock3 class="h-5 w-5 text-warning-600" />
                    <p class="text-sm font-semibold text-gray-900">{{ t('workersAdmin.overtime', 'Overtime') }}</p>
                </div>
                <p class="mt-3 text-2xl font-bold text-gray-900">{{ formatHours(summary.overtime_minutes) }}</p>
            </article>
        </div>
    </section>
</template>
