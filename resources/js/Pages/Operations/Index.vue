<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Camera,
    CalendarDays,
    CheckCheck,
    CheckCircle2,
    CircleDollarSign,
    ClipboardCheck,
    Clock,
    Filter,
    MapPinned,
    MessageSquareWarning,
    Plus,
    RefreshCcw,
    ShieldCheck,
    TimerReset,
    UsersRound,
    XCircle,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type {
    Assignment,
    ChecklistItem,
    OperationsCalendarDay,
    OperationsWorkload,
    ServiceSummary,
    SiteSummary,
    Visit,
    WorkerSummary,
} from '../../types';

type Option<T extends string | number = string> = {
    key: T;
    label: string;
};

type ContractOption = {
    id: number;
    reference: string;
    status: string;
    starts_on?: string | null;
    ends_on?: string | null;
    customer?: {
        id: number;
        name: string;
    } | null;
    site?: SiteSummary | null;
    service?: ServiceSummary | null;
    service_package?: {
        id: number;
        name: string;
    } | null;
};

type CustomerOption = {
    id: number;
    name: string;
    status: string;
};

type Metrics = {
    visitsToday: number;
    weeklyVisits: number;
    activeAssignments: number;
    workersScheduled: number;
    completedThisWeek: number;
    lateVisits: number;
    missedVisits: number;
    openIssues: number;
    awaitingAcknowledgement: number;
    pendingOvertime: number;
};

type Filters = {
    date: string;
    week_start?: string;
    worker_id?: string | number | null;
    customer_id?: string | number | null;
    customer_site_id?: string | number | null;
    status?: string | null;
};

type AssignmentForm = {
    contract_id: string;
    customer_site_id: string;
    service_id: string;
    worker_id: string;
    weekday: number;
    starts_at: string;
    ends_at: string;
    share_percent: number;
    status: string;
};

type GenerateForm = {
    contract_id: string;
    from: string;
    to: string;
};

type DailyAlert = {
    type: string;
    severity: string;
    title: string;
    message: string;
    visit_id: number;
    site?: string | null;
    worker?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
};

type DailyControl = {
    date: string;
    alerts: DailyAlert[];
    awaitingAcknowledgement: Visit[];
    evidence: {
        open_issues: number;
        photo_count: number;
        checklist_done: number;
        checklist_pending: number;
    };
    costControl: {
        planned_revenue_halalas: number;
        labor_cost_halalas: number;
        material_cost_halalas: number;
        billable_overtime_halalas: number;
        gross_profit_halalas: number;
        overtime_minutes: number;
        pending_overtime: number;
    };
};

type AvailabilityEvent = {
    id: string;
    source: string;
    status: string;
    starts_at: string;
    ends_at: string;
    minutes: number;
    worker: WorkerSummary;
    site?: SiteSummary | null;
    service?: ServiceSummary | null;
    contract?: {
        id: number;
        reference: string;
    } | null;
};

type AvailabilityBoard = {
    date: string;
    summary: {
        total_workers: number;
        booked_workers: number;
        available_workers: number;
        capacity_minutes: number;
        booked_minutes: number;
        available_minutes: number;
        utilization_percent: number;
    };
    timeSlots: Array<{
        label: string;
        starts_at: string;
        ends_at: string;
        total_workers: number;
        booked_workers: number;
        available_workers: number;
        capacity_percent: number;
        status: string;
        events_count: number;
    }>;
    workerLanes: Array<{
        worker: WorkerSummary;
        booked_minutes: number;
        available_minutes: number;
        utilization_percent: number;
        events: AvailabilityEvent[];
    }>;
};

type IssueForm = {
    issue_note: string;
    photos: string;
};

type ReasonForm = {
    reason: string;
};

type AcknowledgeForm = {
    supervisor_note: string;
};

type ChecklistEvidenceForm = {
    status: string;
    notes: string;
    photo_path: string;
};

type ExecutionForm = {
    checked_in_at: string;
    checked_out_at: string;
    material_cost_sar: string;
    materials_used_text: string;
    overtime_status: string;
    execution_notes: string;
};

const props = defineProps<{
    date: string;
    weekStart: string;
    filters: Filters;
    visits: Visit[];
    assignments: Assignment[];
    calendarDays: OperationsCalendarDay[];
    availabilityBoard: AvailabilityBoard;
    workload: OperationsWorkload[];
    dailyControl: DailyControl;
    metrics: Metrics;
    contractOptions: ContractOption[];
    workerOptions: WorkerSummary[];
    customerOptions: CustomerOption[];
    siteOptions: SiteSummary[];
    serviceOptions: ServiceSummary[];
    statusOptions: Option[];
    overtimeStatuses: Option[];
    weekdayOptions: Option<number>[];
    assignmentStatuses: Option[];
}>();

const { t } = useI18n();

const filterForm = reactive({
    date: props.filters.date ?? props.date,
    worker_id: props.filters.worker_id ? String(props.filters.worker_id) : '',
    customer_id: props.filters.customer_id ? String(props.filters.customer_id) : '',
    customer_site_id: props.filters.customer_site_id ? String(props.filters.customer_site_id) : '',
    status: props.filters.status ?? '',
});

const firstContract = computed(() => props.contractOptions[0]);
const firstWorker = computed(() => props.workerOptions[0]);

const assignmentForm = useForm<AssignmentForm>({
    contract_id: firstContract.value ? String(firstContract.value.id) : '',
    customer_site_id: firstContract.value?.site ? String(firstContract.value.site.id) : '',
    service_id: firstContract.value?.service ? String(firstContract.value.service.id) : '',
    worker_id: firstWorker.value ? String(firstWorker.value.id) : '',
    weekday: isoWeekday(props.date),
    starts_at: '08:00',
    ends_at: '12:00',
    share_percent: 100,
    status: 'active',
});

const generateForm = useForm<GenerateForm>({
    contract_id: firstContract.value ? String(firstContract.value.id) : '',
    from: props.weekStart,
    to: addDays(props.weekStart, 6),
});

const selectedAssignmentContract = computed(() => props.contractOptions.find((contract) => contract.id === Number(assignmentForm.contract_id)));
const selectedGenerationContract = computed(() => props.contractOptions.find((contract) => contract.id === Number(generateForm.contract_id)));
const maxVisitsInDay = computed(() => Math.max(1, ...props.calendarDays.map((day) => day.visits_count)));
const maxAssignmentMinutes = computed(() => Math.max(60, ...props.workload.map((row) => row.assignment_minutes)));
const issueForms = reactive<Record<number, IssueForm>>({});
const missedForms = reactive<Record<number, ReasonForm>>({});
const acknowledgementForms = reactive<Record<number, AcknowledgeForm>>({});
const checklistEvidenceForms = reactive<Record<number, ChecklistEvidenceForm>>({});
const executionForms = reactive<Record<number, ExecutionForm>>({});

watch(() => assignmentForm.contract_id, () => {
    const contract = selectedAssignmentContract.value;

    assignmentForm.customer_site_id = contract?.site ? String(contract.site.id) : '';
    assignmentForm.service_id = contract?.service ? String(contract.service.id) : '';
});

function applyFilters(): void {
    const query = compact({
        date: filterForm.date,
        worker_id: filterForm.worker_id,
        customer_id: filterForm.customer_id,
        customer_site_id: filterForm.customer_site_id,
        status: filterForm.status,
    });

    router.get('/app/operations', query, {
        preserveScroll: true,
        preserveState: true,
    });
}

function clearFilters(): void {
    filterForm.date = props.date;
    filterForm.worker_id = '';
    filterForm.customer_id = '';
    filterForm.customer_site_id = '';
    filterForm.status = '';

    router.get('/app/operations', { date: props.date }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function createAssignment(): void {
    assignmentForm.post('/app/operations/assignments', {
        preserveScroll: true,
    });
}

function generateVisits(): void {
    generateForm.post('/app/operations/generate-visits', {
        preserveScroll: true,
    });
}

function post(url: string): void {
    router.post(url, {}, { preserveScroll: true });
}

function completeChecklist(visit: Visit): void {
    router.post(`/app/operations/visits/${visit.id}/checklist`, {
        items: checklist(visit).map((item) => item.id),
    }, { preserveScroll: true });
}

function reportIssue(visit: Visit): void {
    const form = issueForm(visit);

    router.post(`/app/operations/visits/${visit.id}/issue`, {
        issue_note: form.issue_note,
        photos: photoList(form.photos),
    }, { preserveScroll: true });
}

function markMissed(visit: Visit): void {
    router.post(`/app/operations/visits/${visit.id}/missed`, missedForm(visit), { preserveScroll: true });
}

function acknowledgeVisit(visit: Visit): void {
    router.post(`/app/operations/visits/${visit.id}/acknowledge`, acknowledgementForm(visit), { preserveScroll: true });
}

function updateChecklistEvidence(item: ChecklistItem): void {
    router.patch(`/app/operations/checklist-items/${item.id}`, checklistEvidenceForm(item), { preserveScroll: true });
}

function updateExecution(visit: Visit): void {
    const form = executionForm(visit);

    router.patch(`/app/operations/visits/${visit.id}/execution`, {
        checked_in_at: serverDateTime(form.checked_in_at),
        checked_out_at: serverDateTime(form.checked_out_at),
        material_cost_sar: form.material_cost_sar || '0.00',
        materials_used: materialRows(form.materials_used_text),
        overtime_status: form.overtime_status,
        execution_notes: form.execution_notes,
    }, { preserveScroll: true });
}

function checklist(visit: Visit): ChecklistItem[] {
    return visit.checklist_items ?? visit.checklistItems ?? [];
}

function issueForm(visit: Visit): IssueForm {
    issueForms[visit.id] ??= {
        issue_note: visit.issue_note ?? '',
        photos: (visit.photos ?? []).join('\n'),
    };

    return issueForms[visit.id];
}

function missedForm(visit: Visit): ReasonForm {
    missedForms[visit.id] ??= {
        reason: visit.issue_note ?? '',
    };

    return missedForms[visit.id];
}

function acknowledgementForm(visit: Visit): AcknowledgeForm {
    acknowledgementForms[visit.id] ??= {
        supervisor_note: visit.supervisor_note ?? '',
    };

    return acknowledgementForms[visit.id];
}

function checklistEvidenceForm(item: ChecklistItem): ChecklistEvidenceForm {
    checklistEvidenceForms[item.id] ??= {
        status: item.status,
        notes: item.notes ?? '',
        photo_path: item.photo_path ?? '',
    };

    return checklistEvidenceForms[item.id];
}

function executionForm(visit: Visit): ExecutionForm {
    executionForms[visit.id] ??= {
        checked_in_at: dateTimeLocal(visit.checked_in_at, visit.scheduled_for, visit.starts_at),
        checked_out_at: dateTimeLocal(visit.checked_out_at, visit.scheduled_for, visit.ends_at),
        material_cost_sar: sarString(visit.material_cost_halalas ?? 0),
        materials_used_text: materialText(visit),
        overtime_status: visit.overtime_status && visit.overtime_status !== 'not_required'
            ? visit.overtime_status
            : 'pending_approval',
        execution_notes: visit.execution_notes ?? '',
    };

    return executionForms[visit.id];
}

function photoList(value: string): string[] {
    return value
        .split(/\r?\n|,/)
        .map((item) => item.trim())
        .filter(Boolean);
}

function materialRows(value: string): Array<{ name: string; quantity: string; cost_sar: string }> {
    return value
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const [name = '', quantity = '', cost = '0.00'] = line.split('|').map((item) => item.trim());

            return {
                name,
                quantity,
                cost_sar: cost || '0.00',
            };
        })
        .filter((item) => item.name.length > 0);
}

function materialText(visit: Visit): string {
    return (visit.materials_used ?? [])
        .map((item) => [item.name, item.quantity ?? '', item.cost_sar ?? sarString(item.cost_halalas)].join(' | '))
        .join('\n');
}

function dateTimeLocal(value?: string | null, date?: string | null, time?: string | null): string {
    if (value) {
        return value.slice(0, 16).replace(' ', 'T');
    }

    if (date && time) {
        return `${date}T${time}`;
    }

    return '';
}

function serverDateTime(value: string): string {
    return value.replace('T', ' ');
}

function sarString(value: number): string {
    const halalas = Math.max(0, Math.round(value || 0));

    return `${Math.trunc(halalas / 100)}.${String(halalas % 100).padStart(2, '0')}`;
}

function workerName(visit: Visit): string {
    return typeof visit.worker === 'string' ? visit.worker : visit.worker?.name ?? t('operations.unassigned');
}

function siteName(visit: Visit): string {
    return typeof visit.site === 'string' ? visit.site : visit.site?.name ?? t('operations.site');
}

function customerName(visit: Visit): string {
    return typeof visit.site === 'string' ? visit.customer ?? '' : visit.site?.customer?.name ?? '';
}

function serviceName(visit: Visit): string {
    return typeof visit.service === 'string' ? visit.service : visit.service?.title ?? t('operations.contractService');
}

function assignmentTitle(assignment: Assignment): string {
    return `${assignment.worker?.name ?? t('operations.unassigned')} / ${assignment.starts_at} - ${assignment.ends_at}`;
}

function workloadWidth(minutes: number): string {
    return `${Math.max(6, Math.round((minutes / maxAssignmentMinutes.value) * 100))}%`;
}

function capacityWidth(percent: number): string {
    return `${Math.max(4, Math.min(100, percent))}%`;
}

function capacityTone(status: string): string {
    if (status === 'full') {
        return 'bg-error-500';
    }

    if (status === 'available') {
        return 'bg-warning-500';
    }

    return 'bg-success-500';
}

function capacityStatusLabel(status: string): string {
    return t(`operations.capacityStatuses.${status}`, status.replaceAll('_', ' '));
}

function calendarHeight(count: number): string {
    return `${Math.max(8, Math.round((count / maxVisitsInDay.value) * 52))}px`;
}

const weekdayTranslationKeys: Record<number, string> = {
    1: 'mon',
    2: 'tue',
    3: 'wed',
    4: 'thu',
    5: 'fri',
    6: 'sat',
    7: 'sun',
};

function weekdayLabel(weekday: number): string {
    const option = props.weekdayOptions.find((item) => item.key === weekday);
    const key = weekdayTranslationKeys[weekday];

    if (key) {
        return t(`operations.weekdays.${key}`, option?.label ?? String(weekday));
    }

    return option?.label ?? String(weekday);
}

function statusLabel(status: Option): string {
    return t(`statuses.${status.key}`, status.label);
}

function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    if (hours === 0) {
        return `${mins}m`;
    }

    return mins === 0 ? `${hours}h` : `${hours}h ${mins}m`;
}

function overtimeLabel(status?: string): string {
    if (! status) {
        return t('operations.notRecorded', 'Not recorded');
    }

    const option = props.overtimeStatuses.find((item) => item.key === status);

    return t(`operations.overtimeStatuses.${status}`, option?.label ?? status.replaceAll('_', ' '));
}

function profitTone(value?: number | null): string {
    return Number(value ?? 0) >= 0 ? 'text-success-700' : 'text-error-700';
}

function compact(values: Record<string, string | number | null | undefined>): Record<string, string | number> {
    return Object.fromEntries(
        Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    ) as Record<string, string | number>;
}

function isoWeekday(date: string): number {
    const [year, month, dayOfMonth] = date.split('-').map(Number);
    const day = new Date(year, month - 1, dayOfMonth).getDay();

    return day === 0 ? 7 : day;
}

function addDays(date: string, days: number): string {
    const [year, month, dayOfMonth] = date.split('-').map(Number);
    const value = new Date(year, month - 1, dayOfMonth);
    value.setDate(value.getDate() + days);

    const nextYear = value.getFullYear();
    const nextMonth = String(value.getMonth() + 1).padStart(2, '0');
    const nextDay = String(value.getDate()).padStart(2, '0');

    return `${nextYear}-${nextMonth}-${nextDay}`;
}
</script>

<template>
    <Head :title="t('layout.operationsBoard', 'Operations Board')" />

    <section class="space-y-6">
        <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full border border-brand-500/15 bg-brand-50 px-3 py-1 text-xs font-semibold uppercase text-brand-600">
                    <CalendarDays class="h-3.5 w-3.5" />
                    {{ t('operations.schedulerEyebrow', 'Admin scheduling') }}
                </p>
                <h1 class="ta-page-title mt-3">{{ t('operations.title') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500">
                    {{ t('operations.subtitle', undefined, { date }) }}
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5 xl:min-w-[760px]">
                <article class="rounded-2xl border border-brand-500/15 bg-brand-50 px-4 py-3">
                    <p class="text-xs font-medium text-brand-600">{{ t('operations.visitsToday', 'Today') }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ metrics.visitsToday }}</p>
                </article>
                <article class="rounded-2xl border border-success-500/15 bg-success-50 px-4 py-3">
                    <p class="text-xs font-medium text-success-600">{{ t('operations.weeklyVisits', 'Weekly visits') }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ metrics.weeklyVisits }}</p>
                </article>
                <article class="rounded-2xl border border-warning-500/15 bg-warning-50 px-4 py-3">
                    <p class="text-xs font-medium text-warning-600">{{ t('operations.assignmentsMetric', 'Assignments') }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ metrics.activeAssignments }}</p>
                </article>
                <article class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                    <p class="text-xs font-medium text-gray-500">{{ t('operations.workersScheduled', 'Workers') }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ metrics.workersScheduled }}</p>
                </article>
                <article class="rounded-2xl border border-error-500/15 bg-error-50 px-4 py-3">
                    <p class="text-xs font-medium text-error-600">{{ t('operations.pendingOvertime', 'Overtime') }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ metrics.pendingOvertime }}</p>
                </article>
            </div>
        </div>

        <section class="ta-card p-4 md:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end">
                <div class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('operations.date', 'Date') }}
                        <input v-model="filterForm.date" type="date" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('operations.worker', 'Worker') }}
                        <select v-model="filterForm.worker_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option value="">{{ t('operations.allWorkers', 'All workers') }}</option>
                            <option v-for="worker in workerOptions" :key="worker.id" :value="worker.id">{{ worker.name }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('operations.customer', 'Customer') }}
                        <select v-model="filterForm.customer_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option value="">{{ t('operations.allCustomers', 'All customers') }}</option>
                            <option v-for="customer in customerOptions" :key="customer.id" :value="customer.id">{{ customer.name }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('operations.siteFilter', 'Site') }}
                        <select v-model="filterForm.customer_site_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option value="">{{ t('operations.allSites', 'All sites') }}</option>
                            <option v-for="site in siteOptions" :key="site.id" :value="site.id">{{ site.name }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('operations.status', 'Status') }}
                        <select v-model="filterForm.status" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option value="">{{ t('operations.allStatuses', 'All statuses') }}</option>
                            <option v-for="status in statusOptions" :key="status.key" :value="status.key">{{ statusLabel(status) }}</option>
                        </select>
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="ta-btn ta-btn-primary" @click="applyFilters">
                        <Filter class="h-4 w-4" />
                        {{ t('operations.applyFilters', 'Apply') }}
                    </button>
                    <button type="button" class="ta-btn ta-btn-secondary" @click="clearFilters">
                        {{ t('operations.clearFilters', 'Clear') }}
                    </button>
                </div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_430px]">
            <article class="ta-card p-4 md:p-5">
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.availabilityCalendar', 'Availability calendar') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('operations.availabilityCalendarSubtitle', 'Manpower capacity by hour for the selected operations date.') }}</p>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1.5 text-sm font-semibold text-brand-600">
                        <ShieldCheck class="h-4 w-4" />
                        {{ availabilityBoard.summary.available_workers }} / {{ availabilityBoard.summary.total_workers }}
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-gray-400">{{ t('operations.totalCapacity', 'Total capacity') }}</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ formatMinutes(availabilityBoard.summary.capacity_minutes) }}</p>
                    </div>
                    <div class="rounded-2xl border border-warning-500/20 bg-warning-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-warning-700">{{ t('operations.bookedCapacity', 'Booked') }}</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ formatMinutes(availabilityBoard.summary.booked_minutes) }}</p>
                    </div>
                    <div class="rounded-2xl border border-success-500/20 bg-success-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-success-700">{{ t('operations.availableWorkers', 'Available workers') }}</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ availabilityBoard.summary.available_workers }}</p>
                    </div>
                    <div class="rounded-2xl border border-brand-500/20 bg-brand-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-brand-700">{{ t('operations.utilization', 'Utilization') }}</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ availabilityBoard.summary.utilization_percent }}%</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <div v-for="slot in availabilityBoard.timeSlots" :key="slot.starts_at" class="grid gap-2 rounded-2xl border border-gray-200 px-3 py-3 sm:grid-cols-[68px_minmax(0,1fr)_120px] sm:items-center">
                        <div class="font-semibold text-gray-900">{{ slot.label }}</div>
                        <div class="min-w-0">
                            <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full" :class="capacityTone(slot.status)" :style="{ width: capacityWidth(slot.capacity_percent) }" />
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ slot.booked_workers }} {{ t('operations.bookedWorkersShort', 'booked') }} / {{ slot.available_workers }} {{ t('operations.availableShort', 'available') }}
                            </p>
                        </div>
                        <span class="justify-self-start rounded-full px-2.5 py-1 text-xs font-semibold sm:justify-self-end" :class="slot.status === 'full' ? 'bg-error-50 text-error-700' : slot.status === 'available' ? 'bg-warning-50 text-warning-700' : 'bg-success-50 text-success-700'">
                            {{ capacityStatusLabel(slot.status) }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="ta-card p-4 md:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.workerLanes', 'Worker lanes') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('operations.workerLanesSubtitle', 'Who is booked, who is still available, and where each worker is assigned.') }}</p>
                    </div>
                    <UsersRound class="h-5 w-5 text-brand-500" />
                </div>

                <div class="mt-5 space-y-4">
                    <div v-for="lane in availabilityBoard.workerLanes" :key="lane.worker.id" class="rounded-2xl border border-gray-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-900">{{ lane.worker.name }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ formatMinutes(lane.booked_minutes) }} / {{ lane.utilization_percent }}%</p>
                            </div>
                            <StatusBadge :status="lane.worker.status ?? 'available'" />
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-brand-500" :style="{ width: capacityWidth(lane.utilization_percent) }" />
                        </div>
                        <div class="mt-3 space-y-2">
                            <div v-for="event in lane.events" :key="event.id" class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="min-w-0 truncate font-semibold text-gray-800">{{ event.service?.title ?? t('operations.contractService', 'Contract service') }}</span>
                                    <span class="shrink-0 text-xs text-gray-500">{{ event.starts_at }} - {{ event.ends_at }}</span>
                                </div>
                                <p class="mt-1 truncate text-xs text-gray-500">{{ event.site?.name ?? t('operations.site', 'Site') }}</p>
                            </div>
                            <p v-if="lane.events.length === 0" class="rounded-xl border border-dashed border-gray-200 px-3 py-4 text-center text-xs text-gray-500">
                                {{ t('operations.workerFullyAvailable', 'Available for booking on this date.') }}
                            </p>
                        </div>
                    </div>

                    <p v-if="availabilityBoard.workerLanes.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('operations.noAvailableWorkers', 'No available or assigned workers are active for capacity planning.') }}
                    </p>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
            <article class="ta-card p-4 md:p-5">
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.dailyControl', 'Daily control') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('operations.dailyControlSubtitle', 'Late visits, missed work, reported issues, and supervisor acknowledgements for the selected date.') }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <span class="rounded-2xl border border-warning-500/15 bg-warning-50 px-3 py-2 text-center">
                            <span class="block text-lg font-bold text-warning-600">{{ metrics.lateVisits }}</span>
                            <span class="text-xs text-warning-700">{{ t('operations.late', 'Late') }}</span>
                        </span>
                        <span class="rounded-2xl border border-error-500/15 bg-error-50 px-3 py-2 text-center">
                            <span class="block text-lg font-bold text-error-600">{{ metrics.missedVisits }}</span>
                            <span class="text-xs text-error-700">{{ t('operations.missed', 'Missed') }}</span>
                        </span>
                        <span class="rounded-2xl border border-error-500/15 bg-error-50 px-3 py-2 text-center">
                            <span class="block text-lg font-bold text-error-600">{{ metrics.openIssues }}</span>
                            <span class="text-xs text-error-700">{{ t('operations.issues', 'Issues') }}</span>
                        </span>
                        <span class="rounded-2xl border border-brand-500/15 bg-brand-50 px-3 py-2 text-center">
                            <span class="block text-lg font-bold text-brand-600">{{ metrics.awaitingAcknowledgement }}</span>
                            <span class="text-xs text-brand-700">{{ t('operations.ackQueue', 'Ack') }}</span>
                        </span>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 lg:grid-cols-2">
                    <div
                        v-for="alert in dailyControl.alerts"
                        :key="`${alert.type}-${alert.visit_id}`"
                        class="rounded-2xl border px-4 py-3"
                        :class="alert.severity === 'error' ? 'border-error-500/20 bg-error-50' : 'border-warning-500/20 bg-warning-50'"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                :class="alert.severity === 'error' ? 'bg-error-500 text-white' : 'bg-warning-500 text-white'"
                            >
                                <AlertTriangle v-if="alert.type !== 'missed'" class="h-4 w-4" />
                                <XCircle v-else class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">{{ alert.title }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ alert.site }} / {{ alert.worker }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ alert.starts_at }} - {{ alert.ends_at }} / {{ alert.message }}</p>
                            </div>
                        </div>
                    </div>

                    <p v-if="dailyControl.alerts.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 lg:col-span-2">
                        {{ t('operations.noAlerts', 'No late, missed, or issue alerts for this date.') }}
                    </p>
                </div>
            </article>

            <aside class="space-y-5">
                <article class="ta-card p-4 md:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.awaitingAcknowledgement', 'Awaiting acknowledgement') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.awaitingAcknowledgementSubtitle', 'Completed visits needing supervisor review.') }}</p>
                        </div>
                        <ShieldCheck class="h-5 w-5 text-brand-500" />
                    </div>

                    <div class="mt-4 space-y-3">
                        <div v-for="visit in dailyControl.awaitingAcknowledgement" :key="visit.id" class="rounded-2xl border border-gray-200 p-3">
                            <p class="font-semibold text-gray-900">{{ siteName(visit) }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ workerName(visit) }} / {{ visit.starts_at }} - {{ visit.ends_at }}</p>
                            <div class="mt-3 flex flex-col gap-2">
                                <input
                                    v-model="acknowledgementForm(visit).supervisor_note"
                                    type="text"
                                    class="ta-input h-10 w-full px-3 text-sm"
                                    :placeholder="t('operations.supervisorNote', 'Supervisor note')"
                                >
                                <button type="button" class="ta-btn ta-btn-primary w-full" @click="acknowledgeVisit(visit)">
                                    <CheckCheck class="h-4 w-4" />
                                    {{ t('operations.acknowledge', 'Acknowledge') }}
                                </button>
                            </div>
                        </div>

                        <p v-if="dailyControl.awaitingAcknowledgement.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                            {{ t('operations.noAcknowledgements', 'No completed visits waiting for review.') }}
                        </p>
                    </div>
                </article>

                <article class="ta-card p-4 md:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.evidenceSummary', 'Evidence summary') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.evidenceSummarySubtitle', 'Photos and checklist completion captured today.') }}</p>
                        </div>
                        <Camera class="h-5 w-5 text-success-600" />
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-center">
                        <span class="rounded-2xl bg-gray-50 px-3 py-3">
                            <span class="block text-xl font-bold text-gray-900">{{ dailyControl.evidence.photo_count }}</span>
                            <span class="text-xs text-gray-500">{{ t('operations.photos', 'Photos') }}</span>
                        </span>
                        <span class="rounded-2xl bg-gray-50 px-3 py-3">
                            <span class="block text-xl font-bold text-gray-900">{{ dailyControl.evidence.checklist_done }}</span>
                            <span class="text-xs text-gray-500">{{ t('operations.doneItems', 'Done items') }}</span>
                        </span>
                        <span class="rounded-2xl bg-gray-50 px-3 py-3">
                            <span class="block text-xl font-bold text-gray-900">{{ dailyControl.evidence.checklist_pending }}</span>
                            <span class="text-xs text-gray-500">{{ t('operations.pendingItems', 'Pending') }}</span>
                        </span>
                        <span class="rounded-2xl bg-gray-50 px-3 py-3">
                            <span class="block text-xl font-bold text-gray-900">{{ dailyControl.evidence.open_issues }}</span>
                            <span class="text-xs text-gray-500">{{ t('operations.openIssues', 'Open issues') }}</span>
                        </span>
                    </div>
                </article>

                <article class="ta-card p-4 md:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.costControl', 'Cost control') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.costControlSubtitle', 'Revenue, labor, materials, overtime, and profit for the selected date.') }}</p>
                        </div>
                        <CircleDollarSign class="h-5 w-5 text-success-600" />
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <span class="rounded-2xl bg-gray-50 px-3 py-3">
                            <span class="block text-sm font-semibold text-gray-500">{{ t('operations.revenue', 'Revenue') }}</span>
                            <MoneyText :value="dailyControl.costControl.planned_revenue_halalas" class="mt-1 block text-base font-bold text-gray-900" />
                        </span>
                        <span class="rounded-2xl bg-gray-50 px-3 py-3">
                            <span class="block text-sm font-semibold text-gray-500">{{ t('operations.laborCost', 'Labor') }}</span>
                            <MoneyText :value="dailyControl.costControl.labor_cost_halalas" class="mt-1 block text-base font-bold text-gray-900" />
                        </span>
                        <span class="rounded-2xl bg-gray-50 px-3 py-3">
                            <span class="block text-sm font-semibold text-gray-500">{{ t('operations.materialCost', 'Materials') }}</span>
                            <MoneyText :value="dailyControl.costControl.material_cost_halalas" class="mt-1 block text-base font-bold text-gray-900" />
                        </span>
                        <span class="rounded-2xl bg-gray-50 px-3 py-3">
                            <span class="block text-sm font-semibold text-gray-500">{{ t('operations.overtimeBillable', 'Overtime') }}</span>
                            <MoneyText :value="dailyControl.costControl.billable_overtime_halalas" class="mt-1 block text-base font-bold text-gray-900" />
                        </span>
                        <span class="rounded-2xl bg-success-50 px-3 py-3">
                            <span class="block text-sm font-semibold text-success-700">{{ t('operations.grossProfit', 'Profit') }}</span>
                            <MoneyText :value="dailyControl.costControl.gross_profit_halalas" class="mt-1 block text-base font-bold" :class="profitTone(dailyControl.costControl.gross_profit_halalas)" />
                        </span>
                        <span class="rounded-2xl bg-warning-50 px-3 py-3">
                            <span class="block text-sm font-semibold text-warning-700">{{ t('operations.overtimeMinutes', 'OT minutes') }}</span>
                            <span class="mt-1 block text-base font-bold text-gray-900">{{ formatMinutes(dailyControl.costControl.overtime_minutes) }}</span>
                        </span>
                    </div>
                </article>
            </aside>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(360px,0.85fr)]">
            <section class="ta-card p-4 md:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.createAssignment', 'Create recurring assignment') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('operations.createAssignmentSubtitle', 'Assign a worker to a contract, site, weekday, and time slot.') }}</p>
                    </div>
                    <span class="ta-icon-box bg-brand-50 text-brand-500">
                        <Plus class="h-5 w-5" />
                    </span>
                </div>

                <form class="mt-5 grid gap-4 lg:grid-cols-2" @submit.prevent="createAssignment">
                    <label class="text-sm font-medium text-gray-700 lg:col-span-2">
                        {{ t('operations.contract', 'Contract') }}
                        <select v-model="assignmentForm.contract_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option value="">{{ t('operations.selectContract', 'Select contract') }}</option>
                            <option v-for="contract in contractOptions" :key="contract.id" :value="contract.id">
                                {{ contract.reference }} / {{ contract.customer?.name ?? t('operations.customer', 'Customer') }}
                            </option>
                        </select>
                        <span v-if="assignmentForm.errors.contract_id" class="mt-1 block text-xs text-error-600">{{ assignmentForm.errors.contract_id }}</span>
                    </label>

                    <label class="text-sm font-medium text-gray-700">
                        {{ t('operations.siteFilter', 'Site') }}
                        <select v-model="assignmentForm.customer_site_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option value="">{{ t('operations.selectSite', 'Select site') }}</option>
                            <option v-if="selectedAssignmentContract?.site" :value="selectedAssignmentContract.site.id">
                                {{ selectedAssignmentContract.site.name }}
                            </option>
                        </select>
                        <span v-if="assignmentForm.errors.customer_site_id" class="mt-1 block text-xs text-error-600">{{ assignmentForm.errors.customer_site_id }}</span>
                    </label>

                    <label class="text-sm font-medium text-gray-700">
                        {{ t('operations.service', 'Service') }}
                        <select v-model="assignmentForm.service_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option value="">{{ t('operations.selectService', 'Select service') }}</option>
                            <option v-if="selectedAssignmentContract?.service" :value="selectedAssignmentContract.service.id">
                                {{ selectedAssignmentContract.service.title }}
                            </option>
                            <option v-for="service in serviceOptions" v-else :key="service.id" :value="service.id">{{ service.title }}</option>
                        </select>
                        <span v-if="assignmentForm.errors.service_id" class="mt-1 block text-xs text-error-600">{{ assignmentForm.errors.service_id }}</span>
                    </label>

                    <label class="text-sm font-medium text-gray-700">
                        {{ t('operations.worker', 'Worker') }}
                        <select v-model="assignmentForm.worker_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option value="">{{ t('operations.selectWorker', 'Select worker') }}</option>
                            <option v-for="worker in workerOptions" :key="worker.id" :value="worker.id">{{ worker.name }}</option>
                        </select>
                        <span v-if="assignmentForm.errors.worker_id" class="mt-1 block text-xs text-error-600">{{ assignmentForm.errors.worker_id }}</span>
                    </label>

                    <label class="text-sm font-medium text-gray-700">
                        {{ t('operations.weekday', 'Weekday') }}
                        <select v-model.number="assignmentForm.weekday" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option v-for="weekday in weekdayOptions" :key="weekday.key" :value="weekday.key">{{ weekdayLabel(weekday.key) }}</option>
                        </select>
                        <span v-if="assignmentForm.errors.weekday" class="mt-1 block text-xs text-error-600">{{ assignmentForm.errors.weekday }}</span>
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-sm font-medium text-gray-700">
                            {{ t('operations.startsAt', 'Starts') }}
                            <input v-model="assignmentForm.starts_at" type="time" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <span v-if="assignmentForm.errors.starts_at" class="mt-1 block text-xs text-error-600">{{ assignmentForm.errors.starts_at }}</span>
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            {{ t('operations.endsAt', 'Ends') }}
                            <input v-model="assignmentForm.ends_at" type="time" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <span v-if="assignmentForm.errors.ends_at" class="mt-1 block text-xs text-error-600">{{ assignmentForm.errors.ends_at }}</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-sm font-medium text-gray-700">
                            {{ t('operations.sharePercent', 'Share %') }}
                            <input v-model.number="assignmentForm.share_percent" type="number" min="1" max="100" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <span v-if="assignmentForm.errors.share_percent" class="mt-1 block text-xs text-error-600">{{ assignmentForm.errors.share_percent }}</span>
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            {{ t('operations.assignmentStatus', 'Assignment status') }}
                            <select v-model="assignmentForm.status" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                                <option v-for="status in assignmentStatuses" :key="status.key" :value="status.key">{{ statusLabel(status) }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 lg:col-span-2 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0 text-sm text-gray-600">
                            <span class="font-semibold text-gray-900">{{ selectedAssignmentContract?.reference ?? t('operations.noContractSelected', 'No contract selected') }}</span>
                            <span v-if="selectedAssignmentContract?.site" class="ms-2">{{ selectedAssignmentContract.site.name }}</span>
                        </div>
                        <button type="submit" class="ta-btn ta-btn-primary" :disabled="assignmentForm.processing">
                            <Plus class="h-4 w-4" />
                            {{ t('operations.saveAssignment', 'Save assignment') }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="space-y-5">
                <article class="ta-card p-4 md:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.generateVisits', 'Generate visits') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.generateVisitsSubtitle', 'Create daily work visits from active recurring assignments.') }}</p>
                        </div>
                        <span class="ta-icon-box bg-success-50 text-success-600">
                            <RefreshCcw class="h-5 w-5" />
                        </span>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="generateVisits">
                        <label class="block text-sm font-medium text-gray-700">
                            {{ t('operations.contract', 'Contract') }}
                            <select v-model="generateForm.contract_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                                <option value="">{{ t('operations.selectContract', 'Select contract') }}</option>
                                <option v-for="contract in contractOptions" :key="contract.id" :value="contract.id">
                                    {{ contract.reference }} / {{ contract.site?.name ?? '' }}
                                </option>
                            </select>
                            <span v-if="generateForm.errors.contract_id" class="mt-1 block text-xs text-error-600">{{ generateForm.errors.contract_id }}</span>
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-sm font-medium text-gray-700">
                                {{ t('operations.from', 'From') }}
                                <input v-model="generateForm.from" type="date" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                {{ t('operations.to', 'To') }}
                                <input v-model="generateForm.to" type="date" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            </label>
                        </div>

                        <div class="rounded-2xl border border-success-500/15 bg-success-50 px-4 py-3 text-sm text-success-700">
                            {{ selectedGenerationContract?.reference ?? t('operations.chooseContractToGenerate', 'Choose a contract to generate its schedule.') }}
                        </div>

                        <button type="submit" class="ta-btn ta-btn-primary w-full" :disabled="generateForm.processing">
                            <RefreshCcw class="h-4 w-4" />
                            {{ t('operations.generateSchedule', 'Generate schedule') }}
                        </button>
                    </form>
                </article>

                <article class="ta-card p-4 md:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.workloadGraph', 'Worker workload') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.workloadSubtitle', 'Weekly assigned hours against a practical 40-hour ceiling.') }}</p>
                        </div>
                        <BarChart3 class="h-5 w-5 text-brand-500" />
                    </div>

                    <div class="mt-5 space-y-4">
                        <div v-for="row in workload" :key="row.worker.id" class="space-y-2">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="min-w-0 truncate font-semibold text-gray-800">{{ row.worker.name }}</span>
                                <span class="shrink-0 text-gray-500">{{ formatMinutes(row.assignment_minutes) }} / {{ row.utilization_percent }}%</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-500 via-success-500 to-warning-500" :style="{ width: workloadWidth(row.assignment_minutes) }" />
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>{{ row.visits_count }} {{ t('operations.visitsShort', 'visits') }}</span>
                                <span>{{ row.completed_visits }} {{ t('operations.completedShort', 'completed') }}</span>
                            </div>
                        </div>

                        <p v-if="workload.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                            {{ t('operations.noWorkload', 'No worker workload yet for this week.') }}
                        </p>
                    </div>
                </article>
            </section>
        </div>

        <section class="ta-card p-4 md:p-5">
            <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.calendarTitle', 'Weekly calendar') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('operations.calendarSubtitle', 'Generated visits and recurring assignments for the selected week.') }}</p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-600">
                    <CalendarDays class="h-4 w-4 text-brand-500" />
                    {{ weekStart }} - {{ addDays(weekStart, 6) }}
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
                <article v-for="day in calendarDays" :key="day.date" class="flex min-h-72 flex-col rounded-2xl border border-gray-200 bg-white p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-gray-400">{{ day.label }}</p>
                            <h3 class="text-xl font-bold text-gray-900">{{ day.day }}</h3>
                        </div>
                        <div class="flex items-end gap-1">
                            <span class="w-2 rounded-full bg-brand-500" :style="{ height: calendarHeight(day.visits_count) }" />
                            <span class="w-2 rounded-full bg-success-500" :style="{ height: calendarHeight(day.completed_count) }" />
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
                        <span>{{ day.visits_count }} {{ t('operations.visitsShort', 'visits') }}</span>
                        <span>{{ day.assignments.length }} {{ t('operations.assignmentsShort', 'posts') }}</span>
                    </div>

                    <div class="mt-4 flex-1 space-y-2">
                        <div
                            v-for="visit in day.visits.slice(0, 3)"
                            :key="visit.id"
                            class="rounded-xl border border-brand-500/15 bg-brand-50 px-3 py-2 text-xs"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate font-semibold text-gray-900">{{ siteName(visit) }}</span>
                                <StatusBadge :status="visit.status" />
                            </div>
                            <p class="mt-1 truncate text-gray-600">{{ workerName(visit) }}</p>
                            <p class="mt-1 text-gray-500">{{ visit.starts_at }} - {{ visit.ends_at }}</p>
                        </div>

                        <div
                            v-for="assignment in day.assignments.slice(0, day.visits.length > 0 ? 2 : 4)"
                            :key="`assignment-${day.date}-${assignment.id}`"
                            class="rounded-xl border border-gray-200 px-3 py-2 text-xs"
                        >
                            <p class="truncate font-semibold text-gray-800">{{ assignmentTitle(assignment) }}</p>
                            <p class="mt-1 truncate text-gray-500">{{ assignment.site?.name ?? t('operations.site') }}</p>
                        </div>

                        <p v-if="day.visits.length === 0 && day.assignments.length === 0" class="rounded-xl border border-dashed border-gray-200 px-3 py-8 text-center text-xs text-gray-400">
                            {{ t('operations.noDaySchedule', 'No work scheduled') }}
                        </p>
                    </div>
                </article>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_430px]">
            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.dailyVisits', 'Daily visits') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('operations.dailyVisitsSubtitle', 'Attendance and checklist actions for the selected date.') }}</p>
                    </div>
                    <div class="ta-btn ta-btn-secondary">
                        <ClipboardCheck class="h-4 w-4" />
                        {{ t('operations.visitsScheduled', undefined, { count: visits.length }) }}
                    </div>
                </div>

                <article v-for="visit in visits" :key="visit.id" class="ta-card p-5">
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-lg font-semibold text-gray-800">{{ siteName(visit) }}</h3>
                                <StatusBadge :status="visit.status" />
                            </div>
                            <p class="mt-1 text-sm text-gray-500">{{ customerName(visit) }} / {{ serviceName(visit) }}</p>
                            <div class="mt-3 flex flex-wrap gap-3 text-sm text-gray-600">
                                <span class="inline-flex items-center gap-2">
                                    <Clock class="h-4 w-4 text-brand-500" />
                                    {{ visit.starts_at }} - {{ visit.ends_at }}
                                </span>
                                <span class="inline-flex items-center gap-2">
                                    <UsersRound class="h-4 w-4 text-brand-500" />
                                    {{ workerName(visit) }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span v-if="visit.is_late" class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-semibold text-warning-700">
                                    <AlertTriangle class="h-3.5 w-3.5" />
                                    {{ t('operations.lateCheckIn', 'Late check-in') }}
                                </span>
                                <span v-if="visit.is_missed_candidate" class="inline-flex items-center gap-1 rounded-full bg-error-50 px-2.5 py-1 text-xs font-semibold text-error-700">
                                    <XCircle class="h-3.5 w-3.5" />
                                    {{ t('operations.missedCandidate', 'Past end time') }}
                                </span>
                                <span v-if="visit.requires_acknowledgement" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">
                                    <ShieldCheck class="h-3.5 w-3.5" />
                                    {{ t('operations.needsAcknowledgement', 'Needs acknowledgement') }}
                                </span>
                                <span v-if="visit.overtime_status === 'pending_approval'" class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-semibold text-warning-700">
                                    <TimerReset class="h-3.5 w-3.5" />
                                    {{ t('operations.overtimePending', 'Overtime pending') }}
                                </span>
                                <span v-if="visit.overtime_status === 'approved'" class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2.5 py-1 text-xs font-semibold text-success-700">
                                    <CircleDollarSign class="h-3.5 w-3.5" />
                                    {{ t('operations.overtimeApproved', 'Overtime approved') }}
                                </span>
                                <span v-if="visit.issue_note" class="inline-flex items-center gap-1 rounded-full bg-error-50 px-2.5 py-1 text-xs font-semibold text-error-700">
                                    <MessageSquareWarning class="h-3.5 w-3.5" />
                                    {{ t('operations.issueNoted', 'Issue noted') }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button class="ta-btn ta-btn-secondary" type="button" @click="post(`/app/operations/visits/${visit.id}/check-in`)">
                                {{ t('operations.checkIn') }}
                            </button>
                            <button class="ta-btn ta-btn-secondary" type="button" @click="completeChecklist(visit)">
                                {{ t('operations.completeChecklist') }}
                            </button>
                            <button class="ta-btn ta-btn-primary" type="button" @click="post(`/app/operations/visits/${visit.id}/check-out`)">
                                {{ t('operations.checkOut') }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border border-brand-500/15 bg-brand-50/40 p-4">
                        <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-start">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">{{ t('operations.jobExecutionCost', 'Job execution & cost control') }}</h4>
                                <p class="mt-1 text-sm text-gray-500">{{ t('operations.overtimeStatus', 'Overtime status') }}: {{ overtimeLabel(visit.overtime_status) }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:min-w-[560px]">
                                <span class="rounded-xl bg-white px-3 py-2 ring-1 ring-gray-200">
                                    <span class="block text-xs font-semibold text-gray-500">{{ t('operations.planned', 'Planned') }}</span>
                                    <span class="mt-1 block text-sm font-bold text-gray-900">{{ formatMinutes(visit.planned_minutes ?? 0) }}</span>
                                </span>
                                <span class="rounded-xl bg-white px-3 py-2 ring-1 ring-gray-200">
                                    <span class="block text-xs font-semibold text-gray-500">{{ t('operations.actual', 'Actual') }}</span>
                                    <span class="mt-1 block text-sm font-bold text-gray-900">{{ formatMinutes(visit.actual_minutes ?? 0) }}</span>
                                </span>
                                <span class="rounded-xl bg-white px-3 py-2 ring-1 ring-gray-200">
                                    <span class="block text-xs font-semibold text-gray-500">{{ t('operations.variance', 'Variance') }}</span>
                                    <span class="mt-1 block text-sm font-bold" :class="(visit.variance_minutes ?? 0) > 0 ? 'text-warning-700' : 'text-gray-900'">{{ formatMinutes(Math.abs(visit.variance_minutes ?? 0)) }}</span>
                                </span>
                                <span class="rounded-xl bg-white px-3 py-2 ring-1 ring-gray-200">
                                    <span class="block text-xs font-semibold text-gray-500">{{ t('operations.profit', 'Profit') }}</span>
                                    <MoneyText :value="visit.gross_profit_halalas ?? 0" class="mt-1 block text-sm font-bold" :class="profitTone(visit.gross_profit_halalas)" />
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_240px]">
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <label class="text-sm font-medium text-gray-700">
                                    {{ t('operations.actualCheckIn', 'Actual check-in') }}
                                    <input v-model="executionForm(visit).checked_in_at" type="datetime-local" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                                </label>
                                <label class="text-sm font-medium text-gray-700">
                                    {{ t('operations.actualCheckOut', 'Actual check-out') }}
                                    <input v-model="executionForm(visit).checked_out_at" type="datetime-local" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                                </label>
                                <label class="text-sm font-medium text-gray-700">
                                    {{ t('operations.materialCostSar', 'Material cost (SAR)') }}
                                    <input v-model="executionForm(visit).material_cost_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                                </label>
                                <label class="text-sm font-medium text-gray-700">
                                    {{ t('operations.overtimeStatus', 'Overtime status') }}
                                    <select v-model="executionForm(visit).overtime_status" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                                        <option v-for="status in overtimeStatuses" :key="status.key" :value="status.key">{{ overtimeLabel(status.key) }}</option>
                                    </select>
                                </label>
                                <label class="text-sm font-medium text-gray-700 sm:col-span-2">
                                    {{ t('operations.executionNotes', 'Execution notes') }}
                                    <input v-model="executionForm(visit).execution_notes" type="text" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                                </label>
                                <label class="text-sm font-medium text-gray-700 sm:col-span-2 xl:col-span-3">
                                    {{ t('operations.materialsUsed', 'Materials used') }}
                                    <textarea
                                        v-model="executionForm(visit).materials_used_text"
                                        class="ta-input mt-1 min-h-20 w-full px-3 py-2 text-sm"
                                        :placeholder="t('operations.materialsUsedPlaceholder', 'Disinfectant | 2 bottles | 25.50')"
                                    />
                                </label>
                            </div>

                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <span class="rounded-xl bg-white px-3 py-2 ring-1 ring-gray-200">
                                        <span class="block text-gray-500">{{ t('operations.revenue', 'Revenue') }}</span>
                                        <MoneyText :value="visit.planned_revenue_halalas ?? 0" class="font-bold text-gray-900" />
                                    </span>
                                    <span class="rounded-xl bg-white px-3 py-2 ring-1 ring-gray-200">
                                        <span class="block text-gray-500">{{ t('operations.laborCost', 'Labor') }}</span>
                                        <MoneyText :value="visit.labor_cost_halalas ?? 0" class="font-bold text-gray-900" />
                                    </span>
                                    <span class="rounded-xl bg-white px-3 py-2 ring-1 ring-gray-200">
                                        <span class="block text-gray-500">{{ t('operations.materialCost', 'Materials') }}</span>
                                        <MoneyText :value="visit.material_cost_halalas ?? 0" class="font-bold text-gray-900" />
                                    </span>
                                    <span class="rounded-xl bg-white px-3 py-2 ring-1 ring-gray-200">
                                        <span class="block text-gray-500">{{ t('operations.overtimeBillable', 'Overtime') }}</span>
                                        <MoneyText :value="visit.billable_overtime_halalas ?? 0" class="font-bold text-gray-900" />
                                    </span>
                                </div>
                                <button type="button" class="ta-btn ta-btn-primary w-full" @click="updateExecution(visit)">
                                    <TimerReset class="h-4 w-4" />
                                    {{ t('operations.saveExecution', 'Save execution') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 lg:grid-cols-[1fr_280px]">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">{{ t('operations.issueEvidence', 'Issue and photo evidence') }}</h4>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <label class="text-sm font-medium text-gray-700">
                                    {{ t('operations.issueNote', 'Issue note') }}
                                    <textarea
                                        v-model="issueForm(visit).issue_note"
                                        class="ta-input mt-1 min-h-20 w-full px-3 py-2 text-sm"
                                        :placeholder="t('operations.issueNotePlaceholder', 'Describe a site issue, customer request, or variance')"
                                    />
                                </label>
                                <label class="text-sm font-medium text-gray-700">
                                    {{ t('operations.photoPaths', 'Photo paths') }}
                                    <textarea
                                        v-model="issueForm(visit).photos"
                                        class="ta-input mt-1 min-h-20 w-full px-3 py-2 text-sm"
                                        :placeholder="t('operations.photoPathsPlaceholder', 'One uploaded path per line')"
                                    />
                                </label>
                            </div>
                            <div v-if="visit.photos?.length" class="mt-3 flex flex-wrap gap-2">
                                <span v-for="photo in visit.photos" :key="photo" class="inline-flex max-w-full items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-200">
                                    <Camera class="h-3.5 w-3.5 text-success-600" />
                                    <span class="truncate">{{ photo }}</span>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <button type="button" class="ta-btn ta-btn-secondary w-full" @click="reportIssue(visit)">
                                <MessageSquareWarning class="h-4 w-4" />
                                {{ t('operations.recordIssue', 'Record issue') }}
                            </button>
                            <div v-if="visit.status === 'scheduled' || visit.is_late || visit.is_missed_candidate" class="space-y-2">
                                <input
                                    v-model="missedForm(visit).reason"
                                    type="text"
                                    class="ta-input h-10 w-full px-3 text-sm"
                                    :placeholder="t('operations.missedReason', 'Missed reason')"
                                >
                                <button type="button" class="ta-btn ta-btn-secondary w-full border-error-500/20 text-error-600 hover:bg-error-50" @click="markMissed(visit)">
                                    <XCircle class="h-4 w-4" />
                                    {{ t('operations.markMissed', 'Mark missed') }}
                                </button>
                            </div>
                            <div v-if="visit.requires_acknowledgement" class="space-y-2">
                                <input
                                    v-model="acknowledgementForm(visit).supervisor_note"
                                    type="text"
                                    class="ta-input h-10 w-full px-3 text-sm"
                                    :placeholder="t('operations.supervisorNote', 'Supervisor note')"
                                >
                                <button type="button" class="ta-btn ta-btn-primary w-full" @click="acknowledgeVisit(visit)">
                                    <CheckCheck class="h-4 w-4" />
                                    {{ t('operations.acknowledge', 'Acknowledge') }}
                                </button>
                            </div>
                            <div v-if="visit.supervisor_acknowledged_at" class="rounded-xl border border-success-500/20 bg-success-50 px-3 py-2 text-xs text-success-700">
                                {{ t('operations.acknowledgedBy', 'Acknowledged by') }} {{ visit.acknowledged_by?.name ?? t('operations.supervisor', 'Supervisor') }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="item in checklist(visit)"
                            :key="item.id"
                            class="rounded-xl border border-gray-200 px-3 py-3 text-sm"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="min-w-0 truncate font-medium text-gray-700">{{ item.label }}</span>
                                <StatusBadge :status="item.status" />
                            </div>
                            <div class="mt-3 grid gap-2 sm:grid-cols-[120px_1fr]">
                                <select v-model="checklistEvidenceForm(item).status" class="ta-input h-10 px-3 text-sm">
                                    <option value="pending">{{ t('statuses.pending', 'Pending') }}</option>
                                    <option value="done">{{ t('statuses.done', 'Done') }}</option>
                                    <option value="blocked">{{ t('statuses.blocked', 'Blocked') }}</option>
                                </select>
                                <input
                                    v-model="checklistEvidenceForm(item).notes"
                                    type="text"
                                    class="ta-input h-10 px-3 text-sm"
                                    :placeholder="t('operations.checklistNotes', 'Checklist notes')"
                                >
                            </div>
                            <div class="mt-2 grid gap-2 sm:grid-cols-[1fr_auto]">
                                <input
                                    v-model="checklistEvidenceForm(item).photo_path"
                                    type="text"
                                    class="ta-input h-10 px-3 text-sm"
                                    :placeholder="t('operations.checklistPhoto', 'Checklist photo path')"
                                >
                                <button type="button" class="ta-btn ta-btn-secondary" @click="updateChecklistEvidence(item)">
                                    <Camera class="h-4 w-4" />
                                    {{ t('operations.saveEvidence', 'Save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </article>

                <div v-if="visits.length === 0" class="ta-card px-5 py-10 text-center text-gray-500">
                    {{ t('operations.noVisits') }}
                </div>
            </section>

            <section class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">{{ t('operations.weeklyMap') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('operations.weeklyMapSubtitle') }}</p>
                    </div>
                    <MapPinned class="h-5 w-5 text-success-500" />
                </div>

                <div class="mt-5 space-y-3">
                    <div v-for="assignment in assignments" :key="assignment.id" class="rounded-xl border border-gray-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 truncate font-semibold text-gray-800">{{ assignment.worker?.name ?? t('operations.unassigned') }}</div>
                            <span class="shrink-0 rounded-full bg-brand-50 px-2 py-1 text-xs font-medium text-brand-500">{{ weekdayLabel(assignment.weekday) }}</span>
                        </div>
                        <div class="mt-2 truncate text-sm text-gray-600">
                            {{ assignment.site?.customer?.name ?? assignment.contract?.customer?.name ?? '' }} / {{ assignment.site?.name ?? t('operations.site') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ assignment.service?.title ?? t('operations.contractService') }} / {{ assignment.starts_at }} - {{ assignment.ends_at }} / {{ assignment.share_percent }}%
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <StatusBadge :status="assignment.status" />
                            <CheckCircle2 class="h-4 w-4 text-success-500" />
                        </div>
                    </div>

                    <p v-if="assignments.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('operations.noAssignments', 'No recurring assignments match the filters.') }}
                    </p>
                </div>
            </section>
        </div>
    </section>
</template>
