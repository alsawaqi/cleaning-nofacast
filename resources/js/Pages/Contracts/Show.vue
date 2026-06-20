<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    BadgePercent,
    BriefcaseBusiness,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    Clock,
    Download,
    FileText,
    ListChecks,
    MapPin,
    Pencil,
    Plus,
    Printer,
    ReceiptText,
    Send,
    ShieldCheck,
    Signature,
    Trash2,
    UsersRound,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type { Assignment, Contract, Money, SlaReport, WorkerSummary } from '../../types';

type Option<T extends string | number = string> = {
    key: T;
    label: string;
};

type PaymentPlanSettlement = {
    label: string;
    day: number;
    percent: number;
    amount_halalas: Money;
    due_on?: string | null;
    paid_halalas: Money;
    balance_halalas: Money;
    status: string;
};

type ContractInvoicePayment = {
    id: number;
    amount_halalas: Money;
    method: string;
    reference?: string | null;
    received_at?: string | null;
};

type ContractInvoice = {
    id: number;
    number: string;
    status: string;
    issue_date?: string | null;
    due_date?: string | null;
    gross_total_halalas: Money;
    paid_total_halalas: Money;
    balance_halalas: Money;
    payments: ContractInvoicePayment[];
};

type FinanceSummary = {
    gross_total_halalas: Money;
    paid_total_halalas: Money;
    balance_halalas: Money;
    invoices_count: number;
    payments_count: number;
};

type AssignmentForm = {
    worker_id: string;
    team_role: string;
    weekday: number;
    starts_at: string;
    ends_at: string;
    share_percent: number;
    status: string;
    task_instructions: AssignmentTask[];
};

type AssignmentTask = {
    label: string;
    is_required: boolean;
};

type AssignmentFollowUp = {
    type: string;
    severity: string;
    count: number;
};

type AssignmentSlot = {
    key: string;
    weekday: number;
    starts_at: string;
    ends_at: string;
    workers_count: number;
    task_count: number;
    has_main_worker: boolean;
    has_required_workers: boolean;
    ready: boolean;
    main_worker?: WorkerSummary | null;
    assignments: Assignment[];
};

type AssignmentAssessment = {
    required_visits_per_week: number;
    covered_visits_per_week: number;
    missing_visits_per_week: number;
    required_workers_per_visit: number;
    ready_slots_count: number;
    slots: AssignmentSlot[];
    follow_ups: AssignmentFollowUp[];
};

type SlaReports = {
    weekly: SlaReport;
    monthly: SlaReport;
};

type ContractDecision = {
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
    review_url: string;
    customer?: {
        id: number;
        name: string;
        email?: string | null;
    } | null;
    reviewed_by?: {
        id: number;
        name: string;
    } | null;
};

type DecisionReviewForm = {
    admin_note: string;
    processing: boolean;
};

type Panel = 'overview' | 'assignments' | 'sla' | 'finance' | 'acceptance' | 'scope';

const props = defineProps<{
    contract: Contract;
    assignments: Assignment[];
    assignmentAssessment: AssignmentAssessment;
    slaReports: SlaReports;
    invoices: ContractInvoice[];
    paymentPlan: PaymentPlanSettlement[];
    finance: FinanceSummary;
    contractDecisions: ContractDecision[];
    workerOptions: WorkerSummary[];
    weekdayOptions: Option<number>[];
    assignmentStatuses: Option[];
    assignmentStoreUrl: string;
    canManageAssignments: boolean;
    backUrl: string;
    initialPanel?: Panel;
}>();

const { t } = useI18n();
const activePanel = ref<Panel>(props.initialPanel ?? 'overview');

const panels = computed<Array<{ key: Panel; label: string; icon: typeof BriefcaseBusiness }>>(() => [
    { key: 'overview', label: t('contractsAdmin.overviewTab', 'Overview'), icon: BriefcaseBusiness },
    { key: 'assignments', label: t('contractsAdmin.assignmentsTab', 'Workers'), icon: UsersRound },
    { key: 'sla', label: t('contractsAdmin.slaKpiTab', 'SLA / KPI'), icon: ShieldCheck },
    { key: 'finance', label: t('contractsAdmin.financeTab', 'Payments'), icon: ReceiptText },
    { key: 'acceptance', label: t('contractsAdmin.acceptanceTab', 'Acceptance'), icon: Signature },
    { key: 'scope', label: t('contractsAdmin.scopeTab', 'Scope'), icon: FileText },
]);

const firstWorker = computed(() => props.workerOptions[0]);
const assignmentForm = useForm<AssignmentForm>({
    worker_id: firstWorker.value ? String(firstWorker.value.id) : '',
    team_role: 'main',
    weekday: 1,
    starts_at: '08:00',
    ends_at: '12:00',
    share_percent: 100,
    status: 'active',
    task_instructions: [{ label: '', is_required: true }],
});
const decisionReviewForms = reactive<Record<number, DecisionReviewForm>>({});

const assignedWorkerCount = computed(() => new Set(props.assignments.map((assignment) => assignment.worker_id)).size);
const assignmentRoleOptions = computed<Option[]>(() => [
    { key: 'main', label: t('contractsAdmin.mainWorker', 'Main worker') },
    { key: 'support', label: t('contractsAdmin.supportWorker', 'Support worker') },
]);
const previousAssignment = computed(() => props.assignments.find((assignment) => assignment.team_role === assignmentForm.team_role) ?? props.assignments[0]);
const slaReportCards = computed(() => [
    {
        key: 'weekly',
        label: t('contractsAdmin.weeklySlaReport', 'Weekly SLA report'),
        body: t('contractsAdmin.weeklySlaReportBody', 'Current week quality, attendance, evidence, and supervisor review.'),
        report: props.slaReports.weekly,
    },
    {
        key: 'monthly',
        label: t('contractsAdmin.monthlySlaReport', 'Monthly SLA report'),
        body: t('contractsAdmin.monthlySlaReportBody', 'Month-to-date SLA/KPI performance for this contract.'),
        report: props.slaReports.monthly,
    },
]);

function submitAssignment(): void {
    assignmentForm
        .transform((data) => ({
            ...data,
            task_instructions: data.task_instructions
                .filter((task) => task.label.trim() !== '')
                .map((task) => ({
                    label: task.label.trim(),
                    is_required: task.is_required,
                })),
        }))
        .post(props.assignmentStoreUrl, {
            preserveScroll: true,
            onSuccess: () => {
                assignmentForm.reset('team_role', 'starts_at', 'ends_at', 'share_percent', 'status', 'task_instructions');
                assignmentForm.team_role = 'main';
                assignmentForm.starts_at = '08:00';
                assignmentForm.ends_at = '12:00';
                assignmentForm.share_percent = 100;
                assignmentForm.status = 'active';
                assignmentForm.task_instructions = [{ label: '', is_required: true }];
            },
        });
}

function usePreviousAssignment(): void {
    if (! previousAssignment.value) {
        return;
    }

    assignmentForm.worker_id = String(previousAssignment.value.worker_id);
    assignmentForm.team_role = previousAssignment.value.team_role ?? 'main';
    assignmentForm.starts_at = previousAssignment.value.starts_at;
    assignmentForm.ends_at = previousAssignment.value.ends_at;
    assignmentForm.share_percent = previousAssignment.value.share_percent;
    assignmentForm.status = previousAssignment.value.status;
    assignmentForm.task_instructions = previousAssignment.value.task_instructions?.map((task) => ({
        label: task.label,
        is_required: task.is_required ?? true,
    })) ?? [{ label: '', is_required: true }];
}

function addAssignmentTask(): void {
    assignmentForm.task_instructions.push({ label: '', is_required: true });
}

function removeAssignmentTask(index: number): void {
    assignmentForm.task_instructions.splice(index, 1);

    if (assignmentForm.task_instructions.length === 0) {
        addAssignmentTask();
    }
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
    const option = props.weekdayOptions.find((item) => item.key === weekday);
    const key = keys[weekday];

    return key ? t(`operations.weekdays.${key}`, option?.label ?? String(weekday)) : option?.label ?? String(weekday);
}

function assignmentStatusLabel(status: string): string {
    const option = props.assignmentStatuses.find((item) => item.key === status);

    return t(`statuses.${status}`, option?.label ?? status.replaceAll('_', ' '));
}

function assignmentRoleLabel(role: string): string {
    const option = assignmentRoleOptions.value.find((item) => item.key === role);

    return option?.label ?? role.replaceAll('_', ' ');
}

function followUpText(followUp: AssignmentFollowUp): string {
    const messages: Record<string, string> = {
        missing_visit_slots: t('contractsAdmin.missingVisitSlotsFollowUp', 'Add {count} more weekly visit slot(s) for this contract.'),
        missing_main_worker: t('contractsAdmin.missingMainWorkerFollowUp', 'Assign a main worker for {count} team slot(s).'),
        team_understaffed: t('contractsAdmin.teamUnderstaffedFollowUp', 'Add support workers for {count} understaffed team slot(s).'),
    };

    return (messages[followUp.type] ?? followUp.type.replaceAll('_', ' ')).replace('{count}', String(followUp.count));
}

function followUpClass(severity: string): string {
    return severity === 'danger'
        ? 'border-error-200 bg-error-50 text-error-700'
        : 'border-warning-200 bg-warning-50 text-warning-700';
}

function paymentPlanStatusLabel(status: string): string {
    return t(`contractsAdmin.paymentStatuses.${status}`, status.replaceAll('_', ' '));
}

function metricCards(report: SlaReport): Array<{ label: string; value: string; note: string }> {
    return [
        {
            label: t('contractsAdmin.attendanceRate', 'Attendance'),
            value: `${report.metrics.attendance_rate}%`,
            note: `${report.metrics.checked_in_visits}/${report.metrics.scheduled_visits}`,
        },
        {
            label: t('contractsAdmin.checklistCompletionRate', 'Checklist'),
            value: `${report.metrics.checklist_completion_rate}%`,
            note: `${report.metrics.checklist_done}/${report.metrics.checklist_items}`,
        },
        {
            label: t('contractsAdmin.supervisorReviewRate', 'Supervisor review'),
            value: `${report.metrics.supervisor_review_rate}%`,
            note: `${report.metrics.supervisor_review_count}/${report.metrics.scheduled_visits}`,
        },
        {
            label: t('contractsAdmin.completedVisits', 'Completed visits'),
            value: String(report.metrics.completed_visits),
            note: `${t('contractsAdmin.scheduledVisits', 'Scheduled')} ${report.metrics.scheduled_visits}`,
        },
        {
            label: t('contractsAdmin.issues', 'Issues'),
            value: String(report.metrics.issue_count),
            note: `${t('contractsAdmin.missedVisits', 'Missed')} ${report.metrics.missed_visits}`,
        },
        {
            label: t('contractsAdmin.photoEvidence', 'Photo evidence'),
            value: String(report.metrics.photo_count),
            note: `${report.metrics.visits_with_photos} ${t('contractsAdmin.visitsWithPhotos', 'visits')}`,
        },
    ];
}

function kpiValue(value: number | null, unit: string): string {
    if (value === null) {
        return '-';
    }

    return unit === 'percent' ? `${value}%` : String(value);
}

function kpiDirectionText(direction: string): string {
    return direction === 'at_most'
        ? t('contractsAdmin.atMost', 'At most')
        : t('contractsAdmin.atLeast', 'At least');
}

function reportToneClass(report: SlaReport): string {
    return report.status === 'healthy'
        ? 'border-success-200 bg-success-50 text-success-700'
        : 'border-warning-200 bg-warning-50 text-warning-700';
}

function kpiToneClass(passed: boolean): string {
    return passed
        ? 'border-success-200 bg-success-50 text-success-700'
        : 'border-warning-200 bg-warning-50 text-warning-700';
}

function decisionLabel(decision: string): string {
    const labels: Record<string, string> = {
        accepted: t('contractsAdmin.decisionAccepted', 'Accepted and signed'),
        change_requested: t('contractsAdmin.decisionChangeRequested', 'Changes requested'),
    };

    return labels[decision] ?? decision.replaceAll('_', ' ');
}

function decisionReviewForm(decision: ContractDecision): DecisionReviewForm {
    decisionReviewForms[decision.id] ??= {
        admin_note: decision.admin_note ?? '',
        processing: false,
    };

    return decisionReviewForms[decision.id];
}

function submitDecisionReview(decision: ContractDecision): void {
    const form = decisionReviewForm(decision);
    form.processing = true;

    router.patch(decision.review_url, {
        admin_note: form.admin_note,
    }, {
        preserveScroll: true,
        onFinish: () => {
            form.processing = false;
        },
    });
}
</script>

<template>
    <Head :title="t('contractsAdmin.detailHeadTitle', 'Contract details')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <Link :href="backUrl" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-brand-600">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('contractsAdmin.backToContracts', 'Back to contracts') }}
                </Link>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="ta-page-title">{{ contract.reference }}</h1>
                    <StatusBadge :status="contract.status" />
                </div>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ contract.customer.name }} / {{ contract.site.name }} / {{ contract.service?.title ?? t('contractsAdmin.service', 'Service') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a v-if="contract.print_url" :href="contract.print_url" class="ta-btn ta-btn-secondary" target="_blank">
                    <Printer class="h-4 w-4" />
                    {{ t('contractsAdmin.printContract', 'Print') }}
                </a>
                <a v-if="contract.download_url" :href="contract.download_url" class="ta-btn ta-btn-secondary">
                    <Download class="h-4 w-4" />
                    {{ t('contractsAdmin.downloadContractPdf', 'Download PDF') }}
                </a>
                <Link :href="contract.edit_url ?? `/app/contracts/${contract.id}/edit`" class="ta-btn ta-btn-secondary">
                    <Pencil class="h-4 w-4" />
                    {{ t('contractsAdmin.editContract', 'Edit contract') }}
                </Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.monthlyFee', 'Monthly fee') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="contract.monthly_fee_halalas" /></p>
            </article>
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.assignedWorkers', 'Assigned workers') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ assignedWorkerCount }} / {{ contract.agreed_workers }}</p>
            </article>
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.collected', 'Collected') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="finance.paid_total_halalas" /></p>
            </article>
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.outstanding', 'Outstanding') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="finance.balance_halalas" /></p>
            </article>
        </div>

        <div class="ta-card p-2">
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-6">
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

        <section v-if="activePanel === 'overview'" class="grid gap-5 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <BriefcaseBusiness class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('contractsAdmin.contractOverview', 'Contract overview') }}</h2>
                        <p class="text-sm text-gray-500">{{ t('contractsAdmin.contractOverviewBody', 'Core agreement details used by operations and finance.') }}</p>
                    </div>
                </div>

                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.customer', 'Customer') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ contract.customer.name }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.site', 'Site') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ contract.site.name }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.package', 'Package') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ contract.service_package?.name ?? t('contractsAdmin.noPackage', 'No package') }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.contractPeriod', 'Contract period') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ contract.starts_on }} - {{ contract.ends_on ?? t('contractsAdmin.openEnded', 'Open ended') }}</dd>
                    </div>
                </dl>
            </article>

            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-50 text-success-600">
                        <CalendarDays class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('contractsAdmin.plannedWorkload', 'Planned workload') }}</h2>
                        <p class="text-sm text-gray-500">{{ t('contractsAdmin.scheduleHint', 'Use worker assignments to turn this agreement into worker portal visits.') }}</p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.visitsPerWeek', 'Visits per week') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ contract.visits_per_week ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.hoursPerVisit', 'Hours per visit') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ contract.hours_per_visit ?? '0.00' }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.plannedWeeklyMinutes', 'Planned weekly minutes') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ contract.planned_weekly_minutes }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section v-else-if="activePanel === 'assignments'" class="space-y-5">
            <article class="ta-card overflow-hidden p-0">
                <div class="flex flex-col gap-4 border-b border-gray-200 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                            <ClipboardList class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">{{ t('contractsAdmin.assignmentAssessment', 'Assignment assessment') }}</h2>
                            <p class="mt-1 max-w-3xl text-sm text-gray-500">{{ t('contractsAdmin.assignmentAssessmentBody', 'Plan this contract by weekly visit slots, main worker ownership, and support worker coverage.') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">
                        <CalendarDays class="h-4 w-4 text-brand-500" />
                        {{ contract.visits_per_week ?? 0 }} {{ t('contractsAdmin.visitsPerWeek', 'visits/week') }}
                    </span>
                </div>

                <div class="grid divide-y divide-gray-200 border-b border-gray-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                    <div class="p-5">
                        <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.requiredVisits', 'Required visits') }}</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ assignmentAssessment.required_visits_per_week }}</p>
                    </div>
                    <div class="p-5">
                        <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.coveredSlots', 'Covered slots') }}</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ assignmentAssessment.covered_visits_per_week }}</p>
                    </div>
                    <div class="p-5">
                        <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.missingSlots', 'Missing slots') }}</p>
                        <p class="mt-2 text-3xl font-bold" :class="assignmentAssessment.missing_visits_per_week > 0 ? 'text-warning-600' : 'text-success-600'">
                            {{ assignmentAssessment.missing_visits_per_week }}
                        </p>
                    </div>
                    <div class="p-5">
                        <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.readySlots', 'Ready slots') }}</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ assignmentAssessment.ready_slots_count }}</p>
                    </div>
                </div>

                <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <section>
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">{{ t('contractsAdmin.teamSlots', 'Team slots') }}</h3>
                                <p class="text-sm text-gray-500">{{ t('contractsAdmin.teamSlotsBody', 'Each slot becomes one visit owned by the main worker.') }}</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                {{ assignmentAssessment.required_workers_per_visit }} {{ t('contractsAdmin.workersPerVisit', 'workers/visit') }}
                            </span>
                        </div>

                        <div class="space-y-3">
                            <div v-for="slot in assignmentAssessment.slots" :key="slot.key" class="rounded-2xl border border-gray-200 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ weekdayLabel(slot.weekday) }} / {{ slot.starts_at }} - {{ slot.ends_at }}</p>
                                        <p class="mt-1 text-sm text-gray-500">
                                            {{ t('contractsAdmin.mainWorker', 'Main worker') }}:
                                            <span class="font-semibold text-gray-800">{{ slot.main_worker?.name ?? t('contractsAdmin.noMainWorker', 'No main worker') }}</span>
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" :class="slot.ready ? 'bg-success-50 text-success-700' : 'bg-warning-50 text-warning-700'">
                                        <CheckCircle2 v-if="slot.ready" class="h-3.5 w-3.5" />
                                        <AlertTriangle v-else class="h-3.5 w-3.5" />
                                        {{ slot.ready ? t('contractsAdmin.ready', 'Ready') : t('contractsAdmin.needsFollowUp', 'Needs follow-up') }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-2 sm:grid-cols-3">
                                    <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                        <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.workers', 'Workers') }}</span>
                                        <span class="font-bold text-gray-900">{{ slot.workers_count }}</span>
                                    </span>
                                    <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                        <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.tasks', 'Tasks') }}</span>
                                        <span class="font-bold text-gray-900">{{ slot.task_count }}</span>
                                    </span>
                                    <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                        <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.requiredTeam', 'Required team') }}</span>
                                        <span class="font-bold text-gray-900">{{ assignmentAssessment.required_workers_per_visit }}</span>
                                    </span>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span v-for="assignment in slot.assignments" :key="assignment.id" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700">
                                        <ShieldCheck class="h-3.5 w-3.5" :class="assignment.team_role === 'main' ? 'text-brand-500' : 'text-gray-400'" />
                                        {{ assignment.worker?.name ?? t('operations.unassigned', 'Unassigned') }}
                                        <span class="text-gray-400">/</span>
                                        {{ assignmentRoleLabel(assignment.team_role) }}
                                    </span>
                                </div>
                            </div>

                            <p v-if="assignmentAssessment.slots.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                                {{ t('contractsAdmin.noTeamSlots', 'No weekly team slots are assigned yet.') }}
                            </p>
                        </div>
                    </section>

                    <aside class="rounded-2xl border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-warning-50 text-warning-600">
                                <Clock class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">{{ t('contractsAdmin.assignmentFollowUp', 'Follow-up') }}</h3>
                                <p class="text-sm text-gray-500">{{ t('contractsAdmin.assignmentFollowUpBody', 'Open items before this contract is fully scheduled.') }}</p>
                            </div>
                        </div>

                        <div v-if="assignmentAssessment.follow_ups.length" class="mt-4 space-y-3">
                            <div v-for="followUp in assignmentAssessment.follow_ups" :key="followUp.type" class="rounded-xl border px-3 py-2 text-sm font-semibold" :class="followUpClass(followUp.severity)">
                                {{ followUpText(followUp) }}
                            </div>
                        </div>
                        <div v-else class="mt-4 rounded-xl border border-success-200 bg-success-50 px-3 py-3 text-sm font-semibold text-success-700">
                            {{ t('contractsAdmin.noAssignmentFollowUps', 'No open assignment follow-ups.') }}
                        </div>
                    </aside>
                </div>
            </article>

            <article v-if="canManageAssignments" class="ta-card overflow-hidden p-0">
                <div class="flex flex-col gap-4 border-b border-gray-200 bg-gradient-to-r from-brand-50 via-white to-success-50 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white text-brand-600 shadow-theme-xs">
                            <Plus class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">{{ t('contractsAdmin.addWorkerAssignment', 'Add worker assignment') }}</h2>
                            <p class="mt-1 max-w-3xl text-sm text-gray-500">{{ t('contractsAdmin.addWorkerAssignmentBody', 'Assign a worker to this contract site and weekly time window.') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-theme-xs">
                        <BriefcaseBusiness class="h-4 w-4 text-brand-500" />
                        {{ contract.reference }}
                    </span>
                </div>

                <form class="space-y-5 p-5" @submit.prevent="submitAssignment">
                    <div class="grid gap-3 lg:grid-cols-3">
                        <div class="rounded-2xl border border-brand-100 bg-brand-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-500">{{ t('contractsAdmin.contract', 'Contract') }}</p>
                            <p class="mt-2 truncate text-sm font-semibold text-gray-900">{{ contract.reference }}</p>
                            <p class="mt-1 truncate text-xs text-gray-500">{{ contract.customer.name }}</p>
                        </div>
                        <div class="rounded-2xl border border-success-100 bg-success-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-success-600">{{ t('contractsAdmin.site', 'Site') }}</p>
                            <p class="mt-2 truncate text-sm font-semibold text-gray-900">{{ contract.site.name }}</p>
                            <p class="mt-1 flex items-center gap-1 truncate text-xs text-gray-500">
                                <MapPin class="h-3.5 w-3.5" />
                                {{ contract.site.city ?? '-' }}<span v-if="contract.site.district"> / {{ contract.site.district }}</span>
                            </p>
                        </div>
                        <div class="rounded-2xl border border-warning-100 bg-warning-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-warning-600">{{ t('contractsAdmin.service', 'Service') }}</p>
                            <p class="mt-2 truncate text-sm font-semibold text-gray-900">{{ contract.service?.title ?? '-' }}</p>
                            <p class="mt-1 truncate text-xs text-gray-500">{{ contract.service_package?.name ?? t('contractsAdmin.noPackage', 'No package') }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <div class="block">
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <span class="block text-sm font-semibold text-gray-700">{{ t('contractsAdmin.selectWorker', 'Select worker') }}</span>
                                <button v-if="previousAssignment" type="button" class="text-xs font-semibold text-brand-600 hover:text-brand-700" @click="usePreviousAssignment">
                                    {{ t('contractsAdmin.usePreviousWorker', 'Use previous worker') }}
                                </button>
                            </div>
                            <select v-model="assignmentForm.worker_id" class="ta-input h-12">
                                <option v-for="worker in workerOptions" :key="worker.id" :value="String(worker.id)">
                                    {{ worker.name }}
                                </option>
                            </select>
                            <span v-if="assignmentForm.errors.worker_id" class="mt-1 block text-xs font-semibold text-error-600">{{ assignmentForm.errors.worker_id }}</span>
                        </div>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-semibold text-gray-700">{{ t('contractsAdmin.teamRole', 'Team role') }}</span>
                            <select v-model="assignmentForm.team_role" class="ta-input h-12">
                                <option v-for="role in assignmentRoleOptions" :key="role.key" :value="role.key">
                                    {{ role.label }}
                                </option>
                            </select>
                            <span v-if="assignmentForm.errors.team_role" class="mt-1 block text-xs font-semibold text-error-600">{{ assignmentForm.errors.team_role }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-semibold text-gray-700">{{ t('contractsAdmin.weekday', 'Weekday') }}</span>
                            <select v-model.number="assignmentForm.weekday" class="ta-input h-12">
                                <option v-for="weekday in weekdayOptions" :key="weekday.key" :value="weekday.key">
                                    {{ weekdayLabel(weekday.key) }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-semibold text-gray-700">{{ t('contractsAdmin.startsAt', 'Starts at') }}</span>
                            <input v-model="assignmentForm.starts_at" type="time" class="ta-input h-12" />
                            <span v-if="assignmentForm.errors.starts_at" class="mt-1 block text-xs font-semibold text-error-600">{{ assignmentForm.errors.starts_at }}</span>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-semibold text-gray-700">{{ t('contractsAdmin.endsAt', 'Ends at') }}</span>
                            <input v-model="assignmentForm.ends_at" type="time" class="ta-input h-12" />
                            <span v-if="assignmentForm.errors.ends_at" class="mt-1 block text-xs font-semibold text-error-600">{{ assignmentForm.errors.ends_at }}</span>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-semibold text-gray-700">{{ t('contractsAdmin.sharePercent', 'Share percent') }}</span>
                            <input v-model.number="assignmentForm.share_percent" type="number" min="1" max="100" class="ta-input h-12" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-semibold text-gray-700">{{ t('contractsAdmin.assignmentStatus', 'Assignment status') }}</span>
                            <select v-model="assignmentForm.status" class="ta-input h-12">
                                <option v-for="status in assignmentStatuses" :key="status.key" :value="status.key">
                                    {{ assignmentStatusLabel(status.key) }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <section class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 shadow-theme-xs">
                                    <ListChecks class="h-5 w-5" />
                                </span>
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">{{ t('contractsAdmin.workTasks', 'Work tasks') }}</h3>
                                    <p class="text-sm text-gray-500">{{ t('contractsAdmin.workTasksBody', 'Tell the worker exactly what must be done at this site. These become visit checklist items.') }}</p>
                                </div>
                            </div>
                            <button type="button" class="ta-btn ta-btn-secondary h-10 px-3 text-sm" @click="addAssignmentTask">
                                <Plus class="h-4 w-4" />
                                {{ t('contractsAdmin.addTask', 'Add task') }}
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(task, index) in assignmentForm.task_instructions" :key="index" class="grid gap-3 rounded-xl border border-gray-200 bg-white p-3 lg:grid-cols-[minmax(0,1fr)_150px_44px] lg:items-center">
                                <label class="block">
                                    <span class="sr-only">{{ t('contractsAdmin.taskLabel', 'Task') }}</span>
                                    <input v-model="task.label" type="text" class="ta-input h-11" :placeholder="t('contractsAdmin.taskPlaceholder', 'Example: Clean reception glass')" />
                                    <span v-if="assignmentForm.errors[`task_instructions.${index}.label`]" class="mt-1 block text-xs font-semibold text-error-600">
                                        {{ assignmentForm.errors[`task_instructions.${index}.label`] }}
                                    </span>
                                </label>
                                <label class="inline-flex h-11 items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm font-semibold text-gray-700">
                                    <input v-model="task.is_required" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                    {{ t('contractsAdmin.requiredTask', 'Required') }}
                                </label>
                                <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:border-error-200 hover:bg-error-50 hover:text-error-600" :aria-label="t('contractsAdmin.removeTask', 'Remove task')" @click="removeAssignmentTask(index)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </section>

                    <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0 text-sm text-gray-600">
                            <span class="font-semibold text-gray-900">{{ contract.reference }}</span>
                            <span class="ms-2">{{ contract.site.name }}</span>
                            <span class="ms-2 text-gray-400">/</span>
                            <span class="ms-2">{{ weekdayLabel(assignmentForm.weekday) }} {{ assignmentForm.starts_at }} - {{ assignmentForm.ends_at }}</span>
                            <span class="ms-2 text-gray-400">/</span>
                            <span class="ms-2">{{ assignmentRoleLabel(assignmentForm.team_role) }}</span>
                        </div>
                        <button type="submit" class="ta-btn ta-btn-primary w-full lg:w-auto" :disabled="assignmentForm.processing">
                            <Plus class="h-4 w-4" />
                            {{ t('contractsAdmin.saveAssignment', 'Save assignment') }}
                        </button>
                    </div>
                </form>
            </article>
            <article v-else class="ta-card p-5">
                <div class="flex h-full min-h-[220px] flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center">
                    <UsersRound class="h-8 w-8 text-gray-300" />
                    <p class="mt-3 text-sm font-semibold text-gray-700">{{ t('contractsAdmin.assignmentRestricted', 'Operations permission is required to assign workers.') }}</p>
                </div>
            </article>

            <article class="ta-card overflow-hidden p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-50 text-success-600">
                        <UsersRound class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('contractsAdmin.currentAssignments', 'Current assignments') }}</h2>
                        <p class="text-sm text-gray-500">{{ t('contractsAdmin.currentAssignmentsBody', 'Recurring worker posts attached to this contract.') }}</p>
                    </div>
                </div>

                <div class="grid gap-3 lg:grid-cols-2">
                    <article v-for="assignment in assignments" :key="assignment.id" class="rounded-2xl border border-gray-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-gray-900">{{ assignment.worker?.name ?? t('operations.unassigned', 'Unassigned') }}</p>
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="assignment.team_role === 'main' ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-600'">
                                        {{ assignmentRoleLabel(assignment.team_role) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">{{ assignment.worker?.employee_code }} / {{ weekdayLabel(assignment.weekday) }} / {{ assignment.starts_at }} - {{ assignment.ends_at }}</p>
                            </div>
                            <StatusBadge :status="assignment.status" />
                        </div>

                        <div class="mt-4 grid gap-2 sm:grid-cols-4">
                            <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.teamRole', 'Team role') }}</span>
                                <span class="font-bold text-gray-900">{{ assignmentRoleLabel(assignment.team_role) }}</span>
                            </span>
                            <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.sharePercent', 'Share percent') }}</span>
                                <span class="font-bold text-gray-900">{{ assignment.share_percent }}%</span>
                            </span>
                            <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.tasks', 'Tasks') }}</span>
                                <span class="font-bold text-gray-900">{{ assignment.task_instructions?.length ?? 0 }}</span>
                            </span>
                            <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.service', 'Service') }}</span>
                                <span class="font-bold text-gray-900">{{ assignment.service?.title ?? '-' }}</span>
                            </span>
                        </div>

                        <div v-if="assignment.task_instructions?.length" class="mt-4 space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('contractsAdmin.workTasks', 'Work tasks') }}</p>
                            <div v-for="task in assignment.task_instructions" :key="task.label" class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="font-medium text-gray-800">{{ task.label }}</span>
                                <span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold" :class="task.is_required === false ? 'bg-gray-100 text-gray-500' : 'bg-success-50 text-success-700'">
                                    {{ task.is_required === false ? t('contractsAdmin.optionalTask', 'Optional') : t('contractsAdmin.requiredTask', 'Required') }}
                                </span>
                            </div>
                        </div>
                    </article>

                    <p v-if="assignments.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 lg:col-span-2">
                        {{ t('contractsAdmin.noAssignmentsForContract', 'No workers assigned to this contract yet.') }}
                    </p>
                </div>
            </article>
        </section>

        <section v-else-if="activePanel === 'sla'" class="space-y-5">
            <article class="ta-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                            <ShieldCheck class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">{{ t('contractsAdmin.slaKpiTitle', 'SLA and KPI control') }}</h2>
                            <p class="mt-1 max-w-3xl text-sm text-gray-500">
                                {{ t('contractsAdmin.slaKpiBody', 'The contract inherits its KPI template from the selected package or service, then reports performance from actual visits, checklists, photos, issues, and supervisor reviews.') }}
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">
                        <ListChecks class="h-4 w-4 text-brand-500" />
                        {{ contract.sla_kpi_template.length }} {{ t('contractsAdmin.kpiTargets', 'KPI targets') }}
                    </span>
                </div>
            </article>

            <div class="grid gap-5 xl:grid-cols-2">
                <article v-for="item in slaReportCards" :key="item.key" class="ta-card overflow-hidden p-0">
                    <div class="flex flex-col gap-4 border-b border-gray-200 p-5 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ item.label }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ item.body }}</p>
                            <p class="mt-2 text-xs font-semibold text-gray-400">{{ item.report.starts_on }} - {{ item.report.ends_on }}</p>
                        </div>
                        <span class="inline-flex min-w-24 items-center justify-center rounded-2xl border px-4 py-3 text-center text-sm font-bold" :class="reportToneClass(item.report)">
                            {{ item.report.score }}%
                        </span>
                    </div>

                    <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="metric in metricCards(item.report)" :key="`${item.key}-${metric.label}`" class="rounded-xl border border-gray-200 bg-white p-3">
                            <p class="text-xs font-semibold uppercase text-gray-400">{{ metric.label }}</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900">{{ metric.value }}</p>
                            <p class="mt-1 text-xs font-medium text-gray-500">{{ metric.note }}</p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 p-5">
                        <h4 class="text-sm font-semibold text-gray-900">{{ t('contractsAdmin.kpiResults', 'KPI results') }}</h4>
                        <div class="mt-3 space-y-2">
                            <div v-for="kpi in item.report.kpi_results" :key="`${item.key}-${kpi.code}`" class="rounded-xl border px-3 py-3" :class="kpiToneClass(kpi.passed)">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-bold">{{ kpi.label }}</p>
                                        <p class="mt-1 text-xs font-semibold opacity-80">
                                            {{ kpiDirectionText(kpi.direction) }} {{ kpiValue(kpi.target, kpi.unit) }}
                                        </p>
                                    </div>
                                    <div class="text-start sm:text-end">
                                        <p class="text-lg font-bold">{{ kpiValue(kpi.actual, kpi.unit) }}</p>
                                        <p class="text-xs font-semibold opacity-80">{{ kpi.performance }}%</p>
                                    </div>
                                </div>
                            </div>
                            <p v-if="item.report.kpi_results.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                                {{ t('contractsAdmin.noKpiResults', 'No KPI template is attached to this contract yet.') }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>

            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-50 text-success-600">
                        <ClipboardList class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('contractsAdmin.inheritedKpiTemplate', 'Inherited KPI template') }}</h2>
                        <p class="text-sm text-gray-500">{{ t('contractsAdmin.inheritedKpiTemplateBody', 'These are the SLA/KPI targets copied to this contract from its package or service.') }}</p>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="kpi in contract.sla_kpi_template" :key="kpi.code" class="rounded-xl border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900">{{ kpi.label }}</p>
                                <p class="mt-1 text-xs font-semibold text-gray-400">{{ kpi.code }}</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600">{{ kpi.weight }}%</span>
                        </div>
                        <p class="mt-4 text-sm font-semibold text-gray-700">
                            {{ kpiDirectionText(kpi.direction) }} {{ kpiValue(kpi.target, kpi.unit) }}
                        </p>
                    </div>
                    <p v-if="contract.sla_kpi_template.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 md:col-span-2 xl:col-span-3">
                        {{ t('contractsAdmin.noKpiTemplate', 'No SLA/KPI template is attached yet.') }}
                    </p>
                </div>
            </article>
        </section>

        <section v-else-if="activePanel === 'finance'" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <article class="ta-card p-5">
                    <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.billed', 'Billed') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="finance.gross_total_halalas" /></p>
                </article>
                <article class="ta-card p-5">
                    <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.paid', 'Paid') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="finance.paid_total_halalas" /></p>
                </article>
                <article class="ta-card p-5">
                    <p class="text-sm font-medium text-gray-500">{{ t('contractsAdmin.balance', 'Balance') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="finance.balance_halalas" /></p>
                </article>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <article class="ta-card p-5">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            <BadgePercent class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('contractsAdmin.paymentPlan', 'Payment plan') }}</h2>
                            <p class="text-sm text-gray-500">{{ t('contractsAdmin.paymentOverviewBody', 'Expected installments compared with collected payments.') }}</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div v-for="item in paymentPlan" :key="`${item.label}-${item.day}`" class="rounded-xl border border-gray-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ item.label }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('contractsAdmin.day', 'Day') }} {{ item.day }} / {{ item.percent }}%</p>
                                </div>
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                    {{ paymentPlanStatusLabel(item.status) }}
                                </span>
                            </div>
                            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                                <div>
                                    <p class="text-xs text-gray-400">{{ t('contractsAdmin.total', 'Total') }}</p>
                                    <MoneyText :value="item.amount_halalas" class="font-semibold text-gray-900" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">{{ t('contractsAdmin.paidAmount', 'Paid') }}</p>
                                    <MoneyText :value="item.paid_halalas" class="font-semibold text-success-700" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">{{ t('contractsAdmin.pendingAmount', 'Pending') }}</p>
                                    <MoneyText :value="item.balance_halalas" class="font-semibold text-error-700" />
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="ta-card overflow-hidden p-5">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-50 text-success-600">
                            <ReceiptText class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('contractsAdmin.invoiceHistory', 'Invoice and payment history') }}</h2>
                            <p class="text-sm text-gray-500">{{ t('contractsAdmin.invoiceHistoryBody', 'Invoices, recorded payments, and outstanding balance for this contract.') }}</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div v-for="invoice in invoices" :key="invoice.id" class="rounded-xl border border-gray-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ invoice.number }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('contractsAdmin.dueDateLabel', 'Due date') }} {{ invoice.due_date }}</p>
                                </div>
                                <StatusBadge :status="invoice.status" />
                            </div>
                            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                                <MoneyText :value="invoice.gross_total_halalas" class="font-semibold text-gray-900" />
                                <MoneyText :value="invoice.paid_total_halalas" class="font-semibold text-success-700" />
                                <MoneyText :value="invoice.balance_halalas" class="font-semibold text-error-700" />
                            </div>
                            <div v-if="invoice.payments.length" class="mt-4 space-y-2">
                                <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.payments', 'Payments') }}</p>
                                <div v-for="payment in invoice.payments" :key="payment.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm">
                                    <span class="font-semibold text-gray-800">{{ payment.method }} / {{ payment.reference ?? '-' }}</span>
                                    <MoneyText :value="payment.amount_halalas" class="font-semibold text-gray-900" />
                                </div>
                            </div>
                        </div>
                        <p v-if="invoices.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                            {{ t('contractsAdmin.noInvoicesForContract', 'No invoices recorded for this contract yet.') }}
                        </p>
                    </div>
                </article>
            </div>
        </section>

        <section v-else-if="activePanel === 'acceptance'" class="space-y-5">
            <article class="ta-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                            <Signature class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">{{ t('contractsAdmin.customerAcceptanceTitle', 'Customer acceptance') }}</h2>
                            <p class="mt-1 max-w-3xl text-sm text-gray-500">{{ t('contractsAdmin.customerAcceptanceBody', 'Review typed signatures, change requests, and customer notes for this contract.') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">
                        <Signature class="h-4 w-4 text-brand-500" />
                        {{ contractDecisions.length }} {{ t('contractsAdmin.decisionsCount', 'decision(s)') }}
                    </span>
                </div>
            </article>

            <div class="grid gap-5 xl:grid-cols-2">
                <article v-for="decision in contractDecisions" :key="decision.id" class="ta-card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-gray-400">{{ decision.created_at ?? '-' }}</p>
                            <h3 class="mt-2 text-lg font-semibold text-gray-900">{{ decisionLabel(decision.decision) }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ decision.customer?.name ?? contract.customer.name }}</p>
                        </div>
                        <StatusBadge :status="decision.status" />
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.signerName', 'Signer') }}</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900">{{ decision.signer_name ?? '-' }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ decision.signer_title ?? '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.acceptedAt', 'Accepted at') }}</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900">{{ decision.accepted_at ?? '-' }}</p>
                        </div>
                    </div>

                    <div v-if="decision.signature_text" class="mt-4 rounded-xl border border-brand-100 bg-brand-50/60 p-4">
                        <p class="text-xs font-semibold uppercase text-brand-500">{{ t('contractsAdmin.typedSignature', 'Typed signature') }}</p>
                        <p class="mt-3 font-serif text-2xl text-gray-900">{{ decision.signature_text }}</p>
                    </div>

                    <div class="mt-4 rounded-xl bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.customerNote', 'Customer note') }}</p>
                        <p class="mt-2 text-sm leading-6 text-gray-600">{{ decision.customer_note || t('contractsAdmin.noCustomerNote', 'No customer note.') }}</p>
                    </div>

                    <form v-if="decision.status === 'pending_review'" class="mt-4 rounded-xl border border-gray-200 p-4" @submit.prevent="submitDecisionReview(decision)">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-semibold text-gray-700">{{ t('contractsAdmin.adminReviewNote', 'Admin review note') }}</span>
                            <textarea v-model="decisionReviewForm(decision).admin_note" rows="3" class="ta-input min-h-24 py-3"></textarea>
                        </label>
                        <button type="submit" class="ta-btn ta-btn-primary mt-4" :disabled="decisionReviewForm(decision).processing">
                            <Send class="h-4 w-4" />
                            {{ t('contractsAdmin.markDecisionReviewed', 'Mark reviewed') }}
                        </button>
                    </form>

                    <div v-else class="mt-4 rounded-xl border border-success-200 bg-success-50 p-4 text-sm font-semibold text-success-700">
                        {{ t('contractsAdmin.decisionReviewedBy', 'Reviewed') }}
                        <span v-if="decision.reviewed_by"> / {{ decision.reviewed_by.name }}</span>
                        <span v-if="decision.reviewed_at"> / {{ decision.reviewed_at }}</span>
                    </div>
                </article>

                <p v-if="contractDecisions.length === 0" class="ta-card px-4 py-8 text-center text-sm text-gray-500 xl:col-span-2">
                    {{ t('contractsAdmin.noCustomerDecisions', 'No customer acceptance or change request has been submitted yet.') }}
                </p>
            </div>
        </section>

        <section v-else class="grid gap-5 xl:grid-cols-2">
            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <ClipboardList class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('contractsAdmin.scopeAndTerms', 'Scope and terms') }}</h2>
                        <p class="text-sm text-gray-500">{{ t('contractsAdmin.scopeAndTermsBody', 'Work areas, task notes, and special agreement terms.') }}</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <div v-for="item in contract.service_scope" :key="item.area" class="rounded-xl border border-gray-200 p-4">
                        <p class="font-semibold text-gray-900">{{ item.area }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ item.tasks || t('contractsAdmin.noTasksListed', 'No tasks listed') }}</p>
                    </div>
                    <p v-if="contract.service_scope.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('contractsAdmin.noScopeItems', 'No scope items listed.') }}
                    </p>
                </div>
            </article>

            <article class="ta-card p-5">
                <h2 class="text-lg font-semibold text-gray-900">{{ t('contractsAdmin.termsAndConditions', 'Terms and conditions') }}</h2>
                <p class="mt-3 rounded-xl bg-gray-50 p-4 text-sm leading-6 text-gray-600">
                    {{ contract.terms_and_conditions || contract.special_terms || t('contractsAdmin.noTerms', 'No contract terms recorded yet.') }}
                </p>

                <h3 class="mt-6 text-sm font-semibold uppercase text-gray-400">{{ t('contractsAdmin.addendums', 'Addendums') }}</h3>
                <div class="mt-3 space-y-3">
                    <div v-for="item in contract.addendums" :key="item.id" class="rounded-xl border border-gray-200 p-4">
                        <p class="font-semibold text-gray-900">{{ item.number }}. {{ item.title }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ item.summary }}</p>
                    </div>
                    <p v-if="contract.addendums.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('contractsAdmin.noAddendums', 'No addendums yet.') }}
                    </p>
                </div>
            </article>
        </section>
    </section>
</template>
