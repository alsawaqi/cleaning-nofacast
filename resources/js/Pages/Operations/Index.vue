<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BriefcaseBusiness,
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
    ShieldCheck,
    Star,
    TimerReset,
    UsersRound,
    XCircle,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
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

type CustomerOption = {
    id: number;
    name: string;
    status: string;
};

type Metrics = {
    visitsToday: number;
    weeklyVisits: number;
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
        pending_reviews: number;
        approved_reviews: number;
        corrections_requested: number;
        quality_reviews: number;
        quality_passed: number;
        quality_failed: number;
        quality_follow_ups: number;
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

type OperationsContractSummary = {
    id: number;
    reference: string;
    status: string;
    starts_on?: string | null;
    ends_on?: string | null;
    detail_url: string;
    customer?: {
        id: number;
        name: string;
    } | null;
    site?: SiteSummary | null;
    service?: ServiceSummary | null;
};

type AssignmentFollowUp = {
    type: string;
    severity: string;
    count: number;
};

type AssignmentFollowUpSlot = {
    key: string;
    weekday: number;
    starts_at: string;
    ends_at: string;
    workers_count: number;
    missing_support_workers: number;
    task_count: number;
    has_main_worker: boolean;
    has_required_workers: boolean;
    ready: boolean;
    main_worker?: WorkerSummary | null;
    workers: Array<{
        assignment_id: number;
        team_role: string;
        worker?: WorkerSummary | null;
    }>;
};

type AssignmentFollowUpItem = {
    contract: {
        id: number;
        reference: string;
        status: string;
        starts_on?: string | null;
        ends_on?: string | null;
        detail_url: string;
        customer?: {
            id: number;
            name: string;
        } | null;
        site?: SiteSummary | null;
        service?: ServiceSummary | null;
    };
    next_due_on: string;
    severity: string;
    open_items: number;
    required_visits_per_week: number;
    covered_visits_per_week: number;
    missing_visit_slots: number;
    required_workers_per_visit: number;
    ready_slots_count: number;
    missing_main_worker_slots: number;
    understaffed_slots: number;
    follow_ups: AssignmentFollowUp[];
    slots: AssignmentFollowUpSlot[];
};

type AssignmentFollowUps = {
    week_start: string;
    week_end: string;
    summary: {
        contracts_due: number;
        open_items: number;
        missing_visit_slots: number;
        missing_main_worker_slots: number;
        understaffed_slots: number;
        ready_contracts: number;
    };
    items: AssignmentFollowUpItem[];
};

type ContractActionItem = {
    notice_date: string;
    notice_key: string;
    severity: string;
    contract: OperationsContractSummary;
    action_url: string;
    starts_at?: string | null;
    ends_at?: string | null;
    visits_count: number;
    assignment_slots_count: number;
    assigned_workers_count: number;
    task_count: number;
    open_items: number;
    missing_visit_slots: number;
    missing_main_worker_slots: number;
    understaffed_slots: number;
    follow_ups: AssignmentFollowUp[];
    workers: WorkerSummary[];
};

type ContractActionBoard = {
    window_start: string;
    window_end: string;
    summary: {
        total_contracts: number;
        today_count: number;
        tomorrow_count: number;
        open_items: number;
    };
    items: ContractActionItem[];
};

type PaymentAttentionItem = {
    severity: string;
    due_label: string;
    days_overdue: number;
    invoice: {
        id: number;
        number: string;
        status: string;
        due_date?: string | null;
        gross_total_halalas: number;
        paid_total_halalas: number;
        balance_halalas: number;
    };
    contract?: OperationsContractSummary | null;
    customer?: {
        id: number;
        name: string;
    } | null;
    action_url: string;
};

type PaymentAttention = {
    window_start: string;
    window_end: string;
    summary: {
        overdue_count: number;
        due_soon_count: number;
        outstanding_halalas: number;
    };
    items: PaymentAttentionItem[];
};

type BookingRequestItem = {
    id: number;
    status: string;
    requested_for?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    worker_count: number;
    duration_minutes: number;
    customer_note?: string | null;
    admin_note?: string | null;
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
    approve_url: string;
    reject_url: string;
    contract_create_url: string;
};

type BookingRequestsBoard = {
    summary: {
        pending_count: number;
        approved_count: number;
        converted_count: number;
    };
    items: BookingRequestItem[];
};

type ServiceRequestItem = {
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
    customer?: {
        id: number;
        name: string;
    } | null;
    contract?: OperationsContractSummary | null;
    visit?: {
        id: number;
        scheduled_for?: string | null;
        starts_at?: string | null;
        ends_at?: string | null;
        status: string;
        worker?: WorkerSummary | null;
        feedback?: {
            id: number;
            rating: number;
            comment?: string | null;
            submitted_at?: string | null;
        } | null;
    } | null;
    approve_url: string;
    reject_url: string;
    in_review_url: string;
    resolve_url: string;
};

type ServiceRequestsBoard = {
    summary: {
        pending_count: number;
        in_review_count: number;
        approved_count: number;
        resolved_count: number;
        rejected_count: number;
    };
    items: ServiceRequestItem[];
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
    quality_status: string;
    quality_score: string;
    create_follow_up: boolean;
};

type BoardTabKey = 'command' | 'requests' | 'follow_up' | 'capacity' | 'schedule' | 'visits';

type BoardTab = {
    key: BoardTabKey;
    label: string;
    description: string;
    count: number;
    countLabel: string;
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
    assignmentFollowUps: AssignmentFollowUps;
    contractActionBoard: ContractActionBoard;
    paymentAttention: PaymentAttention;
    bookingRequests: BookingRequestsBoard;
    serviceRequests: ServiceRequestsBoard;
    metrics: Metrics;
    workerOptions: WorkerSummary[];
    customerOptions: CustomerOption[];
    siteOptions: SiteSummary[];
    statusOptions: Option[];
    overtimeStatuses: Option[];
    weekdayOptions: Option<number>[];
}>();

const { t } = useI18n();

const filterForm = reactive({
    date: props.filters.date ?? props.date,
    worker_id: props.filters.worker_id ? String(props.filters.worker_id) : '',
    customer_id: props.filters.customer_id ? String(props.filters.customer_id) : '',
    customer_site_id: props.filters.customer_site_id ? String(props.filters.customer_site_id) : '',
    status: props.filters.status ?? '',
});

const maxVisitsInDay = computed(() => Math.max(1, ...props.calendarDays.map((day) => day.visits_count)));
const openServiceRequestCount = computed(() => props.serviceRequests.summary.pending_count + props.serviceRequests.summary.in_review_count);
const assignmentRoleOptions = computed<Option[]>(() => [
    { key: 'main', label: t('operations.mainWorker', 'Main worker') },
    { key: 'support', label: t('operations.supportWorker', 'Support worker') },
]);
const qualityStatusOptions = computed<Option[]>(() => [
    { key: 'passed', label: t('operations.qualityStatuses.passed', 'Passed') },
    { key: 'failed', label: t('operations.qualityStatuses.failed', 'Failed') },
    { key: 'needs_correction', label: t('operations.qualityStatuses.needsCorrection', 'Needs correction') },
    { key: 'customer_follow_up', label: t('operations.qualityStatuses.customerFollowUp', 'Customer follow-up') },
]);
const issueForms = reactive<Record<number, IssueForm>>({});
const missedForms = reactive<Record<number, ReasonForm>>({});
const acknowledgementForms = reactive<Record<number, AcknowledgeForm>>({});
const allowedTabs: BoardTabKey[] = ['command', 'requests', 'follow_up', 'capacity', 'schedule', 'visits'];
const queryTab = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('tab') : null;
const activeTab = ref<BoardTabKey>(allowedTabs.includes(queryTab as BoardTabKey) ? queryTab as BoardTabKey : 'command');

const boardTabs = computed<BoardTab[]>(() => [
    {
        key: 'command',
        label: t('operations.boardTabs.command.label', 'Command'),
        description: t('operations.boardTabs.command.description', 'Contract actions, alerts, payment attention, and day profitability.'),
        count: props.contractActionBoard.summary.open_items
            + props.dailyControl.alerts.length
            + props.metrics.awaitingAcknowledgement
            + props.paymentAttention.summary.overdue_count
            + props.paymentAttention.summary.due_soon_count,
        countLabel: t('operations.boardTabCountLabels.actionItems', 'action items'),
    },
    {
        key: 'requests',
        label: t('operations.boardTabs.requests.label', 'Requests'),
        description: t('operations.boardTabs.requests.description', 'Customer booking, reschedule, and support requests awaiting operations action.'),
        count: props.bookingRequests.summary.pending_count + props.bookingRequests.summary.approved_count + openServiceRequestCount.value,
        countLabel: t('operations.boardTabCountLabels.requests', 'requests'),
    },
    {
        key: 'follow_up',
        label: t('operations.boardTabs.followUp.label', 'Follow-up'),
        description: t('operations.boardTabs.followUp.description', 'Contracts coming this week that still need assignment action.'),
        count: props.assignmentFollowUps.summary.open_items,
        countLabel: t('operations.boardTabCountLabels.openItems', 'open items'),
    },
    {
        key: 'capacity',
        label: t('operations.boardTabs.capacity.label', 'Capacity'),
        description: t('operations.boardTabs.capacity.description', 'Availability calendar and worker lanes for the selected date.'),
        count: props.availabilityBoard.summary.available_workers,
        countLabel: t('operations.boardTabCountLabels.availableWorkers', 'available'),
    },
    {
        key: 'schedule',
        label: t('operations.boardTabs.schedule.label', 'Schedule'),
        description: t('operations.boardTabs.schedule.description', 'Weekly calendar and recurring assignment map.'),
        count: props.metrics.weeklyVisits,
        countLabel: t('operations.boardTabCountLabels.weekVisits', 'week visits'),
    },
    {
        key: 'visits',
        label: t('operations.boardTabs.visits.label', 'Visits'),
        description: t('operations.boardTabs.visits.description', 'Daily visit monitoring, evidence review, and overtime alerts.'),
        count: props.metrics.visitsToday,
        countLabel: t('operations.boardTabCountLabels.todayVisits', 'today'),
    },
]);

const activeBoardTab = computed<BoardTab>(() => boardTabs.value.find((tab) => tab.key === activeTab.value) ?? {
    key: 'command',
    label: t('operations.boardTabs.command.label', 'Command'),
    description: t('operations.boardTabs.command.description', 'Contract actions, alerts, payment attention, and day profitability.'),
    count: 0,
    countLabel: t('operations.boardTabCountLabels.actionItems', 'action items'),
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

function reviewVisit(visit: Visit, decision: 'approved' | 'needs_correction'): void {
    const form = acknowledgementForm(visit);

    router.patch(`/app/operations/visits/${visit.id}/completion-review`, {
        decision,
        supervisor_note: form.supervisor_note,
        quality_status: form.quality_status,
        quality_score: form.quality_score === '' ? null : Number(form.quality_score),
        create_follow_up: form.create_follow_up,
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
        supervisor_note: visit.completion_review_note ?? visit.supervisor_note ?? '',
        quality_status: visit.evidence_review?.quality_status ?? (visit.evidence_review?.status === 'needs_correction' ? 'needs_correction' : 'passed'),
        quality_score: visit.evidence_review?.quality_score !== null && visit.evidence_review?.quality_score !== undefined
            ? String(visit.evidence_review.quality_score)
            : '',
        create_follow_up: Boolean(visit.evidence_review?.quality_follow_up_required || visit.evidence_review?.quality_status === 'customer_follow_up'),
    };

    return acknowledgementForms[visit.id];
}

function qualityStatusLabel(status?: string | null): string {
    const labels: Record<string, string> = {
        passed: t('operations.qualityStatuses.passed', 'Passed'),
        failed: t('operations.qualityStatuses.failed', 'Failed'),
        needs_correction: t('operations.qualityStatuses.needsCorrection', 'Needs correction'),
        customer_follow_up: t('operations.qualityStatuses.customerFollowUp', 'Customer follow-up'),
    };

    return labels[status ?? ''] ?? (status ? status.replaceAll('_', ' ') : t('operations.notRecorded', 'Not recorded'));
}

function qualityStatusClass(status?: string | null): string {
    if (status === 'passed') {
        return 'bg-success-50 text-success-700 ring-success-500/20';
    }

    if (status === 'failed') {
        return 'bg-error-50 text-error-700 ring-error-500/20';
    }

    if (status === 'customer_follow_up') {
        return 'bg-brand-50 text-brand-700 ring-brand-500/20';
    }

    if (status === 'needs_correction') {
        return 'bg-warning-50 text-warning-700 ring-warning-500/20';
    }

    return 'bg-gray-50 text-gray-600 ring-gray-200';
}

function ratingText(rating?: number | null): string {
    if (!rating) {
        return t('operations.noCustomerFeedback', 'No customer feedback');
    }

    return t('operations.ratingValue', ':count/5', { count: rating });
}

function reviewStatusLabel(status?: string | null): string {
    const labels: Record<string, string> = {
        pending_review: t('operations.reviewPending', 'Pending review'),
        approved: t('operations.reviewApproved', 'Approved'),
        needs_correction: t('operations.reviewNeedsCorrection', 'Needs correction'),
    };

    return labels[status ?? 'pending_review'] ?? (status ?? t('operations.reviewPending', 'Pending review'));
}

function reviewStatusClass(status?: string | null): string {
    if (status === 'approved') {
        return 'bg-success-50 text-success-700 ring-success-500/20';
    }

    if (status === 'needs_correction') {
        return 'bg-warning-50 text-warning-700 ring-warning-500/20';
    }

    return 'bg-brand-50 text-brand-700 ring-brand-500/20';
}

function photoList(value: string): string[] {
    return value
        .split(/\r?\n|,/)
        .map((item) => item.trim())
        .filter(Boolean);
}

function materialText(visit: Visit): string {
    return (visit.materials_used ?? [])
        .map((item) => [item.name, item.quantity ?? '', item.cost_sar ?? sarString(item.cost_halalas)].join(' | '))
        .join('\n');
}

function formatDateTime(value?: string | null): string {
    if (value) {
        return value.slice(0, 16).replace('T', ' ');
    }

    return t('operations.notRecorded', 'Not recorded');
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

function assignmentRoleLabel(role: string): string {
    const option = assignmentRoleOptions.value.find((item) => item.key === role);

    return option?.label ?? role.replaceAll('_', ' ');
}

function assignmentFollowUpText(followUp: AssignmentFollowUp): string {
    const messages: Record<string, string> = {
        missing_visit_slots: t('operations.missingVisitSlotsFollowUp', 'Add {count} weekly visit slot(s).'),
        missing_main_worker: t('operations.missingMainWorkerFollowUp', 'Assign a main worker for {count} team slot(s).'),
        team_understaffed: t('operations.teamUnderstaffedFollowUp', 'Add support workers for {count} understaffed team slot(s).'),
    };

    return (messages[followUp.type] ?? followUp.type.replaceAll('_', ' ')).replace('{count}', String(followUp.count));
}

function noticeLabel(key: string): string {
    const labels: Record<string, string> = {
        today: t('operations.today', 'Today'),
        tomorrow: t('operations.tomorrow', 'Tomorrow'),
    };

    return labels[key] ?? key.replaceAll('_', ' ');
}

function paymentDueText(item: PaymentAttentionItem): string {
    if (item.due_label === 'overdue') {
        return t('operations.paymentOverdueDays', ':count day(s) overdue', { count: item.days_overdue });
    }

    const labels: Record<string, string> = {
        today: t('operations.dueToday', 'Due today'),
        tomorrow: t('operations.dueTomorrow', 'Due tomorrow'),
    };

    return labels[item.due_label] ?? item.due_label.replaceAll('_', ' ');
}

function approveBookingRequest(item: BookingRequestItem): void {
    router.post(item.approve_url, {}, {
        preserveScroll: false,
    });
}

function rejectBookingRequest(item: BookingRequestItem): void {
    const adminNote = window.prompt(t('operations.rejectBookingPrompt', 'Reason for rejection (optional)'));

    if (adminNote === null) {
        return;
    }

    router.patch(item.reject_url, {
        admin_note: adminNote,
    }, {
        preserveScroll: true,
    });
}

function approveServiceRequest(item: ServiceRequestItem): void {
    const adminNote = window.prompt(t('operations.serviceRequestApprovalPrompt', 'Approval note (optional)'));

    if (adminNote === null) {
        return;
    }

    router.patch(item.approve_url, {
        admin_note: adminNote,
    }, {
        preserveScroll: true,
    });
}

function rejectServiceRequest(item: ServiceRequestItem): void {
    const adminNote = window.prompt(t('operations.rejectServiceRequestPrompt', 'Reason for rejection (optional)'));

    if (adminNote === null) {
        return;
    }

    router.patch(item.reject_url, {
        admin_note: adminNote,
    }, {
        preserveScroll: true,
    });
}

function markServiceRequestInReview(item: ServiceRequestItem): void {
    const adminNote = window.prompt(t('operations.serviceRequestReviewPrompt', 'Review note (optional)'));

    if (adminNote === null) {
        return;
    }

    router.patch(item.in_review_url, {
        admin_note: adminNote,
    }, {
        preserveScroll: true,
    });
}

function resolveServiceRequest(item: ServiceRequestItem): void {
    const adminNote = window.prompt(t('operations.resolveServiceRequestPrompt', 'Resolution note (optional)'));

    if (adminNote === null) {
        return;
    }

    router.patch(item.resolve_url, {
        admin_note: adminNote,
    }, {
        preserveScroll: true,
    });
}

function bookingRequestStatusLabel(status: string): string {
    return t(`operations.bookingStatuses.${status}`, status.replaceAll('_', ' '));
}

function bookingRequestStatusTone(status: string): string {
    if (status === 'approved' || status === 'converted') {
        return 'border-success-200 bg-success-50 text-success-700';
    }

    if (status === 'rejected') {
        return 'border-error-200 bg-error-50 text-error-700';
    }

    return 'border-warning-200 bg-warning-50 text-warning-700';
}

function serviceRequestTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        reschedule: t('operations.serviceRequestTypes.reschedule', 'Reschedule'),
        support_ticket: t('operations.serviceRequestTypes.supportTicket', 'Support ticket'),
        quality_follow_up: t('operations.serviceRequestTypes.qualityFollowUp', 'Quality follow-up'),
    };

    return labels[type] ?? type.replaceAll('_', ' ');
}

function serviceRequestStatusLabel(status: string): string {
    return t(`operations.serviceRequestStatuses.${status}`, status.replaceAll('_', ' '));
}

function serviceRequestStatusTone(status: string): string {
    if (['approved', 'resolved'].includes(status)) {
        return 'border-success-200 bg-success-50 text-success-700';
    }

    if (['rejected', 'cancelled'].includes(status)) {
        return 'border-error-200 bg-error-50 text-error-700';
    }

    if (status === 'in_review') {
        return 'border-brand-200 bg-brand-50 text-brand-700';
    }

    return 'border-warning-200 bg-warning-50 text-warning-700';
}

function serviceRequestPriorityLabel(priority: string): string {
    const labels: Record<string, string> = {
        low: t('operations.priorities.low', 'Low'),
        normal: t('operations.priorities.normal', 'Normal'),
        high: t('operations.priorities.high', 'High'),
        urgent: t('operations.priorities.urgent', 'Urgent'),
    };

    return labels[priority] ?? priority.replaceAll('_', ' ');
}

function serviceRequestPriorityTone(priority: string): string {
    if (priority === 'urgent') {
        return 'bg-error-50 text-error-700';
    }

    if (priority === 'high') {
        return 'bg-warning-50 text-warning-700';
    }

    return 'bg-gray-100 text-gray-600';
}

function followUpTone(severity: string): string {
    if (severity === 'danger' || severity === 'error') {
        return 'border-error-200 bg-error-50 text-error-700';
    }

    if (severity === 'success') {
        return 'border-success-200 bg-success-50 text-success-700';
    }

    return 'border-warning-200 bg-warning-50 text-warning-700';
}

function slotTone(slot: AssignmentFollowUpSlot): string {
    return slot.ready ? 'bg-success-50 text-success-700' : 'bg-warning-50 text-warning-700';
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
                    <p class="text-xs font-medium text-warning-600">{{ t('operations.openItems', 'Open items') }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ assignmentFollowUps.summary.open_items }}</p>
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

        <section class="ta-card p-2">
            <div class="flex gap-2 overflow-x-auto pb-1 lg:grid lg:grid-cols-6 lg:overflow-visible lg:pb-0" role="tablist" :aria-label="t('operations.boardTabsLabel', 'Operations board sections')">
                <button
                    v-for="tab in boardTabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.key"
                    class="group flex min-h-24 w-[230px] shrink-0 flex-col justify-between rounded-xl border px-4 py-3 text-start transition lg:min-h-28 lg:w-auto"
                    :class="activeTab === tab.key ? 'border-brand-500 bg-brand-50 text-brand-700 shadow-sm' : 'border-transparent bg-white text-gray-600 hover:border-gray-200 hover:bg-gray-50'"
                    @click="activeTab = tab.key"
                >
                    <span>
                        <span class="block text-sm font-bold text-gray-900" :class="activeTab === tab.key ? 'text-brand-700' : ''">{{ tab.label }}</span>
                        <span class="mt-1 line-clamp-2 block text-xs leading-5" :class="activeTab === tab.key ? 'text-brand-600' : 'text-gray-500'">{{ tab.description }}</span>
                    </span>
                    <span class="mt-3 inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="activeTab === tab.key ? 'bg-white text-brand-700 ring-1 ring-brand-500/20' : 'bg-gray-100 text-gray-600'">
                        {{ tab.count }} {{ tab.countLabel }}
                    </span>
                </button>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase text-gray-400">{{ t('operations.activeWorkspace', 'Active workspace') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900">{{ activeBoardTab.label }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ activeBoardTab.description }}</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-full bg-gray-50 px-3 py-1.5 text-sm font-semibold text-gray-700">
                    {{ activeBoardTab.count }} {{ activeBoardTab.countLabel }}
                </span>
            </div>
        </section>

        <section v-if="activeTab === 'requests'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]" role="tabpanel">
            <div class="space-y-5">
                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <article class="rounded-2xl border border-warning-500/15 bg-warning-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-warning-700">{{ t('operations.pendingRequests', 'Pending') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ bookingRequests.summary.pending_count }}</p>
                    </article>
                    <article class="rounded-2xl border border-success-500/15 bg-success-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-success-700">{{ t('operations.approvedRequests', 'Approved') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ bookingRequests.summary.approved_count }}</p>
                    </article>
                    <article class="rounded-2xl border border-brand-500/15 bg-brand-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-brand-700">{{ t('operations.convertedRequests', 'Converted') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ bookingRequests.summary.converted_count }}</p>
                    </article>
                    <article class="rounded-2xl border border-warning-500/15 bg-warning-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-warning-700">{{ t('operations.pendingServiceRequests', 'Service pending') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ serviceRequests.summary.pending_count }}</p>
                    </article>
                    <article class="rounded-2xl border border-brand-500/15 bg-brand-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-brand-700">{{ t('operations.inReviewServiceRequests', 'In review') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ serviceRequests.summary.in_review_count }}</p>
                    </article>
                </section>

                <article class="ta-card p-4 md:p-5">
                    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.bookingRequestQueue', 'Booking request queue') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.bookingRequestQueueSubtitle', 'Review customer slot requests, approve availability, then continue into the contract wizard.') }}</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-gray-50 px-3 py-1.5 text-sm font-semibold text-gray-700">
                            <CalendarDays class="h-4 w-4 text-brand-500" />
                            {{ bookingRequests.summary.pending_count }} {{ t('operations.pendingRequestsShort', 'pending') }}
                        </span>
                    </div>

                    <div class="mt-5 space-y-4">
                        <article v-for="item in bookingRequests.items" :key="item.id" class="rounded-2xl border border-gray-200 p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border px-2.5 py-1 text-xs font-semibold" :class="bookingRequestStatusTone(item.status)">
                                            {{ bookingRequestStatusLabel(item.status) }}
                                        </span>
                                        <h3 class="truncate text-base font-semibold text-gray-900">{{ item.customer?.name ?? t('operations.customer', 'Customer') }}</h3>
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                                            {{ item.requested_for }} / {{ item.starts_at }} - {{ item.ends_at }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ item.site?.name ?? t('operations.site', 'Site') }} /
                                        {{ item.service?.title ?? t('operations.service', 'Service') }} /
                                        {{ item.service_package?.name ?? t('operations.customPackage', 'Custom package') }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <button v-if="item.status === 'pending'" type="button" class="ta-btn ta-btn-primary" @click="approveBookingRequest(item)">
                                        <CheckCircle2 class="h-4 w-4" />
                                        {{ t('operations.approveCreateContract', 'Approve & create contract') }}
                                    </button>
                                    <a v-else :href="item.contract_create_url" class="ta-btn ta-btn-primary">
                                        <BriefcaseBusiness class="h-4 w-4" />
                                        {{ t('operations.continueContract', 'Continue contract') }}
                                    </a>
                                    <button type="button" class="ta-btn ta-btn-secondary" @click="rejectBookingRequest(item)">
                                        <XCircle class="h-4 w-4" />
                                        {{ t('operations.reject', 'Reject') }}
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.requiredTeam', 'Required team') }}</span>
                                    <span class="font-bold text-gray-900">{{ item.worker_count }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.duration', 'Duration') }}</span>
                                    <span class="font-bold text-gray-900">{{ formatMinutes(item.duration_minutes) }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.city', 'City') }}</span>
                                    <span class="font-bold text-gray-900">{{ item.site?.city ?? '-' }}</span>
                                </span>
                            </div>

                            <p v-if="item.customer_note" class="mt-4 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm leading-6 text-gray-600">
                                {{ item.customer_note }}
                            </p>
                        </article>

                        <p v-if="bookingRequests.items.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500">
                            {{ t('operations.noBookingRequests', 'No customer booking requests need action right now.') }}
                        </p>
                    </div>
                </article>

                <article class="ta-card p-4 md:p-5">
                    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.serviceRequestQueue', 'Service request queue') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.serviceRequestQueueSubtitle', 'Review reschedule requests and support tickets submitted from customer contracts.') }}</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-gray-50 px-3 py-1.5 text-sm font-semibold text-gray-700">
                            <MessageSquareWarning class="h-4 w-4 text-brand-500" />
                            {{ openServiceRequestCount }} {{ t('operations.openRequestsShort', 'open') }}
                        </span>
                    </div>

                    <div class="mt-5 space-y-4">
                        <article v-for="item in serviceRequests.items" :key="item.id" class="rounded-2xl border border-gray-200 p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border px-2.5 py-1 text-xs font-semibold" :class="serviceRequestStatusTone(item.status)">
                                            {{ serviceRequestStatusLabel(item.status) }}
                                        </span>
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                                            {{ serviceRequestTypeLabel(item.type) }}
                                        </span>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="serviceRequestPriorityTone(item.priority)">
                                            {{ serviceRequestPriorityLabel(item.priority) }}
                                        </span>
                                        <h3 class="truncate text-base font-semibold text-gray-900">{{ item.subject ?? serviceRequestTypeLabel(item.type) }}</h3>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ item.customer?.name ?? item.contract?.customer?.name ?? t('operations.customer', 'Customer') }} /
                                        {{ item.contract?.reference ?? t('operations.contract', 'Contract') }} /
                                        {{ item.contract?.site?.name ?? t('operations.site', 'Site') }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <button v-if="item.type === 'reschedule'" type="button" class="ta-btn ta-btn-primary" @click="approveServiceRequest(item)">
                                        <CheckCircle2 class="h-4 w-4" />
                                        {{ t('operations.approveReschedule', 'Approve reschedule') }}
                                    </button>
                                    <button v-if="['support_ticket', 'quality_follow_up'].includes(item.type)" type="button" class="ta-btn ta-btn-primary" @click="resolveServiceRequest(item)">
                                        <CheckCheck class="h-4 w-4" />
                                        {{ t('operations.resolve', 'Resolve') }}
                                    </button>
                                    <button type="button" class="ta-btn ta-btn-secondary" @click="markServiceRequestInReview(item)">
                                        <Clock class="h-4 w-4" />
                                        {{ t('operations.inReview', 'In review') }}
                                    </button>
                                    <button type="button" class="ta-btn ta-btn-secondary" @click="rejectServiceRequest(item)">
                                        <XCircle class="h-4 w-4" />
                                        {{ t('operations.reject', 'Reject') }}
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-2 sm:grid-cols-4">
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.contractService', 'Service') }}</span>
                                    <span class="font-bold text-gray-900">{{ item.contract?.service?.title ?? '-' }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.visit', 'Visit') }}</span>
                                    <span class="font-bold text-gray-900">{{ item.visit?.scheduled_for ?? '-' }} {{ item.visit?.starts_at ?? '' }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.requestedTime', 'Requested time') }}</span>
                                    <span class="font-bold text-gray-900">{{ item.requested_for ?? '-' }} {{ item.starts_at ? `/ ${item.starts_at} - ${item.ends_at}` : '' }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.customerRating', 'Customer rating') }}</span>
                                    <span class="inline-flex items-center gap-1 font-bold text-gray-900">
                                        <Star class="h-3.5 w-3.5 text-warning-500" />
                                        {{ ratingText(item.visit?.feedback?.rating) }}
                                    </span>
                                </span>
                            </div>

                            <p v-if="item.description" class="mt-4 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm leading-6 text-gray-600">
                                {{ item.description }}
                            </p>
                            <p v-if="item.admin_note" class="mt-3 rounded-xl border border-brand-500/15 bg-brand-50 px-3 py-2 text-sm leading-6 text-brand-700">
                                {{ item.admin_note }}
                            </p>
                            <a v-if="item.contract?.detail_url" :href="item.contract.detail_url" class="mt-4 inline-flex text-sm font-semibold text-brand-600 hover:text-brand-700">
                                {{ t('operations.openContract', 'Open contract') }}
                            </a>
                        </article>

                        <p v-if="serviceRequests.items.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500">
                            {{ t('operations.noServiceRequests', 'No reschedule or support requests need action right now.') }}
                        </p>
                    </div>
                </article>
            </div>

            <aside class="space-y-5">
                <article class="ta-card p-4 md:p-5">
                    <div class="flex items-center gap-3">
                        <span class="ta-icon-box bg-brand-50 text-brand-600">
                            <MessageSquareWarning class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.requestWorkflow', 'Request workflow') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.requestWorkflowSubtitle', 'Approve only when the requested slot is still available. The contract wizard remains the final agreement step.') }}</p>
                        </div>
                    </div>
                    <div class="mt-5 space-y-3 text-sm text-gray-600">
                        <p class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">{{ t('operations.requestStepOne', '1. Customer selects a service, package, site, and available slot.') }}</p>
                        <p class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">{{ t('operations.requestStepTwo', '2. Operations approves the request after checking business readiness.') }}</p>
                        <p class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">{{ t('operations.requestStepThree', '3. Contract wizard opens prefilled, then assignments are handled inside contract details.') }}</p>
                    </div>
                </article>
            </aside>
        </section>

        <section v-if="activeTab === 'follow_up'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]" role="tabpanel">
            <div class="space-y-5">
                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-brand-500/15 bg-brand-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-brand-600">{{ t('operations.contractsDue', 'Contracts due') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ assignmentFollowUps.summary.contracts_due }}</p>
                    </article>
                    <article class="rounded-2xl border border-warning-500/15 bg-warning-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-warning-700">{{ t('operations.openItems', 'Open items') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ assignmentFollowUps.summary.open_items }}</p>
                    </article>
                    <article class="rounded-2xl border border-error-500/15 bg-error-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-error-700">{{ t('operations.missingMainWorkerSlots', 'Missing main') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ assignmentFollowUps.summary.missing_main_worker_slots }}</p>
                    </article>
                    <article class="rounded-2xl border border-success-500/15 bg-success-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-success-700">{{ t('operations.readyContracts', 'Ready contracts') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ assignmentFollowUps.summary.ready_contracts }}</p>
                    </article>
                </section>

                <article class="ta-card p-4 md:p-5">
                    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.assignmentFollowUpQueue', 'Assignment follow-up') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.assignmentFollowUpQueueSubtitle', 'Contracts in this week that still need visit slots, main workers, or support workers.') }}</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-gray-50 px-3 py-1.5 text-sm font-semibold text-gray-700">
                            <CalendarDays class="h-4 w-4 text-brand-500" />
                            {{ assignmentFollowUps.week_start }} - {{ assignmentFollowUps.week_end }}
                        </span>
                    </div>

                    <div class="mt-5 space-y-4">
                        <article v-for="item in assignmentFollowUps.items" :key="item.contract.id" class="rounded-2xl border border-gray-200 p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="truncate text-base font-semibold text-gray-900">{{ item.contract.reference }}</h3>
                                        <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="followUpTone(item.severity)">
                                            {{ item.open_items }} {{ t('operations.openItems', 'open items') }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ item.contract.customer?.name ?? t('operations.customer', 'Customer') }} /
                                        {{ item.contract.site?.name ?? t('operations.site', 'Site') }} /
                                        {{ item.contract.service?.title ?? t('operations.service', 'Service') }}
                                    </p>
                                    <p class="mt-2 text-xs font-semibold text-gray-500">
                                        {{ t('operations.nextDue', 'Next due') }}: {{ item.next_due_on }}
                                    </p>
                                </div>
                                <a :href="item.contract.detail_url" class="ta-btn ta-btn-secondary shrink-0">
                                    <UsersRound class="h-4 w-4" />
                                    {{ t('operations.openContractAssignment', 'Open contract assignment') }}
                                </a>
                            </div>

                            <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.requiredVisits', 'Required visits') }}</span>
                                    <span class="font-bold text-gray-900">{{ item.required_visits_per_week }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.coveredSlots', 'Covered slots') }}</span>
                                    <span class="font-bold text-gray-900">{{ item.covered_visits_per_week }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.missingVisitSlots', 'Missing slots') }}</span>
                                    <span class="font-bold" :class="item.missing_visit_slots > 0 ? 'text-warning-700' : 'text-success-700'">{{ item.missing_visit_slots }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                    <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.requiredTeam', 'Required team') }}</span>
                                    <span class="font-bold text-gray-900">{{ item.required_workers_per_visit }}</span>
                                </span>
                            </div>

                            <div v-if="item.follow_ups.length" class="mt-4 flex flex-wrap gap-2">
                                <span v-for="followUp in item.follow_ups" :key="followUp.type" class="rounded-full border px-3 py-1 text-xs font-semibold" :class="followUpTone(followUp.severity)">
                                    {{ assignmentFollowUpText(followUp) }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                <div v-for="slot in item.slots" :key="slot.key" class="rounded-2xl border border-gray-200 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ weekdayLabel(slot.weekday) }} / {{ slot.starts_at }} - {{ slot.ends_at }}</p>
                                            <p class="mt-1 text-sm text-gray-500">
                                                {{ t('operations.mainWorker', 'Main worker') }}:
                                                <span class="font-semibold text-gray-800">{{ slot.main_worker?.name ?? t('operations.noMainWorker', 'No main worker') }}</span>
                                            </p>
                                        </div>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="slotTone(slot)">
                                            {{ slot.ready ? t('operations.slotReady', 'Ready') : t('operations.slotNeedsFollowUp', 'Needs follow-up') }}
                                        </span>
                                    </div>

                                    <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                                        <span class="rounded-xl bg-gray-50 px-3 py-2">
                                            <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.workers', 'Workers') }}</span>
                                            <span class="font-bold text-gray-900">{{ slot.workers_count }}</span>
                                        </span>
                                        <span class="rounded-xl bg-gray-50 px-3 py-2">
                                            <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.tasks', 'Tasks') }}</span>
                                            <span class="font-bold text-gray-900">{{ slot.task_count }}</span>
                                        </span>
                                        <span class="rounded-xl bg-gray-50 px-3 py-2">
                                            <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.supportNeeded', 'Support') }}</span>
                                            <span class="font-bold" :class="slot.missing_support_workers > 0 ? 'text-warning-700' : 'text-success-700'">{{ slot.missing_support_workers }}</span>
                                        </span>
                                    </div>

                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span v-for="worker in slot.workers" :key="worker.assignment_id" class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700">
                                            {{ worker.worker?.name ?? t('operations.unassigned', 'Unassigned') }} / {{ assignmentRoleLabel(worker.team_role) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <p v-if="assignmentFollowUps.items.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500">
                            {{ t('operations.noAssignmentFollowUps', 'No contracts need assignment follow-up for this week.') }}
                        </p>
                    </div>
                </article>
            </div>

            <aside class="space-y-5">
                <article class="ta-card p-4 md:p-5">
                    <div class="flex items-center gap-3">
                        <span class="ta-icon-box bg-warning-50 text-warning-600">
                            <AlertTriangle class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.followUpReasons', 'Follow-up reasons') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.followUpReasonsSubtitle', 'The queue separates missing schedule slots from team readiness gaps.') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3">
                            <p class="text-sm font-semibold text-warning-800">{{ t('operations.missingVisitSlots', 'Missing visit slots') }}</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900">{{ assignmentFollowUps.summary.missing_visit_slots }}</p>
                        </div>
                        <div class="rounded-2xl border border-error-200 bg-error-50 px-4 py-3">
                            <p class="text-sm font-semibold text-error-800">{{ t('operations.missingMainWorkerSlots', 'Missing main worker') }}</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900">{{ assignmentFollowUps.summary.missing_main_worker_slots }}</p>
                        </div>
                        <div class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3">
                            <p class="text-sm font-semibold text-warning-800">{{ t('operations.understaffedSlots', 'Understaffed slots') }}</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900">{{ assignmentFollowUps.summary.understaffed_slots }}</p>
                        </div>
                    </div>
                </article>
            </aside>
        </section>

        <section v-if="activeTab === 'capacity'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_430px]" role="tabpanel">
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

        <section v-if="activeTab === 'command'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]" role="tabpanel">
            <div class="space-y-5">
            <article class="ta-card p-4 md:p-5">
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.contractControlQueue', 'Contract control queue') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('operations.contractControlQueueSubtitle', 'Today and tomorrow contracts that may need workers, tasks, payment follow-up, or supervisor attention.') }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <span class="rounded-2xl border border-brand-500/15 bg-brand-50 px-3 py-2">
                            <span class="block text-lg font-bold text-brand-700">{{ contractActionBoard.summary.today_count }}</span>
                            <span class="text-xs text-brand-700">{{ t('operations.today', 'Today') }}</span>
                        </span>
                        <span class="rounded-2xl border border-warning-500/15 bg-warning-50 px-3 py-2">
                            <span class="block text-lg font-bold text-warning-700">{{ contractActionBoard.summary.tomorrow_count }}</span>
                            <span class="text-xs text-warning-700">{{ t('operations.tomorrow', 'Tomorrow') }}</span>
                        </span>
                        <span class="rounded-2xl border border-error-500/15 bg-error-50 px-3 py-2">
                            <span class="block text-lg font-bold text-error-700">{{ contractActionBoard.summary.open_items }}</span>
                            <span class="text-xs text-error-700">{{ t('operations.openItems', 'Open items') }}</span>
                        </span>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <article v-for="item in contractActionBoard.items" :key="`${item.contract.id}-${item.notice_date}`" class="rounded-2xl border border-gray-200 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold" :class="followUpTone(item.severity)">
                                        {{ noticeLabel(item.notice_key) }}
                                    </span>
                                    <h3 class="truncate text-base font-semibold text-gray-900">{{ item.contract.reference }}</h3>
                                    <span v-if="item.starts_at || item.ends_at" class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                                        {{ item.starts_at ?? '--:--' }} - {{ item.ends_at ?? '--:--' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ item.contract.customer?.name ?? t('operations.customer', 'Customer') }} /
                                    {{ item.contract.site?.name ?? t('operations.site', 'Site') }} /
                                    {{ item.contract.service?.title ?? t('operations.service', 'Service') }}
                                </p>
                            </div>
                            <a :href="item.action_url" class="ta-btn ta-btn-secondary shrink-0">
                                <UsersRound class="h-4 w-4" />
                                {{ t('operations.assignWorkersAndTasks', 'Assign workers & tasks') }}
                            </a>
                        </div>

                        <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                            <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.visits', 'Visits') }}</span>
                                <span class="font-bold text-gray-900">{{ item.visits_count }}</span>
                            </span>
                            <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.workers', 'Workers') }}</span>
                                <span class="font-bold text-gray-900">{{ item.assigned_workers_count }}</span>
                            </span>
                            <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.tasks', 'Tasks') }}</span>
                                <span class="font-bold text-gray-900">{{ item.task_count }}</span>
                            </span>
                            <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.openItems', 'Open items') }}</span>
                                <span class="font-bold" :class="item.open_items > 0 ? 'text-warning-700' : 'text-success-700'">{{ item.open_items }}</span>
                            </span>
                            <span class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="block text-xs font-semibold uppercase text-gray-400">{{ t('operations.assignmentSlots', 'Slots') }}</span>
                                <span class="font-bold text-gray-900">{{ item.assignment_slots_count }}</span>
                            </span>
                        </div>

                        <div v-if="item.follow_ups.length" class="mt-4 flex flex-wrap gap-2">
                            <span v-for="followUp in item.follow_ups" :key="followUp.type" class="rounded-full border px-3 py-1 text-xs font-semibold" :class="followUpTone(followUp.severity)">
                                {{ assignmentFollowUpText(followUp) }}
                            </span>
                        </div>

                        <div v-if="item.workers.length" class="mt-4 flex flex-wrap gap-2">
                            <span v-for="worker in item.workers" :key="worker.id" class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700">
                                {{ worker.name }}
                            </span>
                        </div>
                    </article>

                    <p v-if="contractActionBoard.items.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500">
                        {{ t('operations.noContractActions', 'No contracts need operations action today or tomorrow.') }}
                    </p>
                </div>
            </article>

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
            </div>

            <aside class="space-y-5">
                <article class="ta-card p-4 md:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.paymentAttention', 'Payment attention') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('operations.paymentAttentionSubtitle', 'Invoices overdue or due by tomorrow for contracts operations may need to follow up.') }}</p>
                        </div>
                        <CircleDollarSign class="h-5 w-5 text-warning-600" />
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <span class="rounded-2xl border border-error-500/15 bg-error-50 px-3 py-2">
                            <span class="block text-lg font-bold text-error-700">{{ paymentAttention.summary.overdue_count }}</span>
                            <span class="text-xs text-error-700">{{ t('operations.overdue', 'Overdue') }}</span>
                        </span>
                        <span class="rounded-2xl border border-warning-500/15 bg-warning-50 px-3 py-2">
                            <span class="block text-lg font-bold text-warning-700">{{ paymentAttention.summary.due_soon_count }}</span>
                            <span class="text-xs text-warning-700">{{ t('operations.dueSoon', 'Due soon') }}</span>
                        </span>
                        <span class="rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2">
                            <MoneyText :value="paymentAttention.summary.outstanding_halalas" class="block text-sm font-bold text-gray-900" />
                            <span class="text-xs text-gray-500">{{ t('operations.outstanding', 'Outstanding') }}</span>
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div v-for="item in paymentAttention.items" :key="item.invoice.id" class="rounded-2xl border border-gray-200 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border px-2.5 py-1 text-xs font-semibold" :class="followUpTone(item.severity)">
                                            {{ paymentDueText(item) }}
                                        </span>
                                        <p class="truncate font-semibold text-gray-900">{{ item.invoice.number }}</p>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ item.contract?.reference ?? t('operations.contract', 'Contract') }} /
                                        {{ item.customer?.name ?? item.contract?.customer?.name ?? t('operations.customer', 'Customer') }}
                                    </p>
                                </div>
                                <MoneyText :value="item.invoice.balance_halalas" class="shrink-0 text-sm font-bold text-gray-900" />
                            </div>
                            <a :href="item.action_url" class="ta-btn ta-btn-secondary mt-3 w-full">
                                <CircleDollarSign class="h-4 w-4" />
                                {{ t('operations.openPaymentView', 'Open payment view') }}
                            </a>
                        </div>

                        <p v-if="paymentAttention.items.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                            {{ t('operations.noPaymentAttention', 'No overdue or due-soon invoices for this operations window.') }}
                        </p>
                    </div>
                </article>

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
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ siteName(visit) }}</p>
                                    <p class="mt-1 text-sm text-gray-500">{{ workerName(visit) }} / {{ visit.starts_at }} - {{ visit.ends_at }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold ring-1" :class="reviewStatusClass(visit.evidence_review?.status)">
                                    {{ reviewStatusLabel(visit.evidence_review?.status) }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                                <span class="rounded-xl bg-gray-50 px-2 py-2">
                                    <span class="block font-bold text-gray-900">{{ visit.evidence_review?.photo_count ?? 0 }}</span>
                                    <span class="text-gray-500">{{ t('operations.photos', 'Photos') }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-2 py-2">
                                    <span class="block font-bold text-gray-900">{{ visit.evidence_review?.checklist_done ?? 0 }}/{{ visit.evidence_review?.checklist_total ?? 0 }}</span>
                                    <span class="text-gray-500">{{ t('operations.checklist', 'Checklist') }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-2 py-2">
                                    <span class="block font-bold" :class="visit.check_in_location && visit.check_out_location ? 'text-success-700' : 'text-warning-700'">
                                        {{ visit.check_in_location && visit.check_out_location ? t('operations.yes', 'Yes') : t('operations.no', 'No') }}
                                    </span>
                                    <span class="text-gray-500">{{ t('operations.gpsEvidence', 'GPS') }}</span>
                                </span>
                            </div>
                            <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                                <label class="font-medium text-gray-700">
                                    {{ t('operations.qualityOutcome', 'Quality outcome') }}
                                    <select v-model="acknowledgementForm(visit).quality_status" class="ta-input mt-1 h-10 w-full px-3 text-sm">
                                        <option v-for="option in qualityStatusOptions" :key="option.key" :value="option.key">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </label>
                                <label class="font-medium text-gray-700">
                                    {{ t('operations.qualityScore', 'Quality score') }}
                                    <input v-model="acknowledgementForm(visit).quality_score" type="number" min="0" max="100" class="ta-input mt-1 h-10 w-full px-3 text-sm">
                                </label>
                            </div>
                            <div class="mt-3 rounded-xl bg-gray-50 px-3 py-2 text-sm">
                                <span class="text-xs font-bold uppercase text-gray-500">{{ t('operations.customerRating', 'Customer rating') }}</span>
                                <span class="mt-1 flex items-center gap-1 font-semibold text-gray-800">
                                    <Star class="h-4 w-4 text-warning-500" />
                                    {{ ratingText(visit.evidence_review?.customer_feedback?.rating) }}
                                </span>
                                <p v-if="visit.evidence_review?.customer_feedback?.comment" class="mt-1 text-gray-600">{{ visit.evidence_review.customer_feedback.comment }}</p>
                            </div>
                            <div class="mt-3 flex flex-col gap-2">
                                <input
                                    v-model="acknowledgementForm(visit).supervisor_note"
                                    type="text"
                                    class="ta-input h-10 w-full px-3 text-sm"
                                    :placeholder="t('operations.reviewNotePlaceholder', 'Review note or correction instructions')"
                                >
                                <label class="inline-flex items-center gap-2 rounded-xl bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">
                                    <input v-model="acknowledgementForm(visit).create_follow_up" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600">
                                    {{ t('operations.createCustomerFollowUp', 'Create customer follow-up') }}
                                </label>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button type="button" class="ta-btn ta-btn-primary w-full" @click="reviewVisit(visit, 'approved')">
                                        <CheckCheck class="h-4 w-4" />
                                        {{ t('operations.approveCompletion', 'Approve completion') }}
                                    </button>
                                    <button type="button" class="ta-btn ta-btn-secondary w-full border-warning-500/20 text-warning-700 hover:bg-warning-50" @click="reviewVisit(visit, 'needs_correction')">
                                        <MessageSquareWarning class="h-4 w-4" />
                                        {{ t('operations.requestCorrection', 'Request correction') }}
                                    </button>
                                </div>
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

                    <div class="mt-4 grid grid-cols-2 gap-3 text-center sm:grid-cols-3">
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
                        <span class="rounded-2xl bg-brand-50 px-3 py-3">
                            <span class="block text-xl font-bold text-brand-700">{{ dailyControl.evidence.pending_reviews }}</span>
                            <span class="text-xs text-brand-700">{{ t('operations.reviewPending', 'Pending review') }}</span>
                        </span>
                        <span class="rounded-2xl bg-success-50 px-3 py-3">
                            <span class="block text-xl font-bold text-success-700">{{ dailyControl.evidence.approved_reviews }}</span>
                            <span class="text-xs text-success-700">{{ t('operations.reviewApproved', 'Approved') }}</span>
                        </span>
                        <span class="rounded-2xl bg-warning-50 px-3 py-3">
                            <span class="block text-xl font-bold text-warning-700">{{ dailyControl.evidence.corrections_requested }}</span>
                            <span class="text-xs text-warning-700">{{ t('operations.reviewNeedsCorrection', 'Needs correction') }}</span>
                        </span>
                        <span class="rounded-2xl bg-gray-50 px-3 py-3">
                            <span class="block text-xl font-bold text-gray-900">{{ dailyControl.evidence.quality_reviews }}</span>
                            <span class="text-xs text-gray-500">{{ t('operations.qualityReviews', 'Quality reviews') }}</span>
                        </span>
                        <span class="rounded-2xl bg-error-50 px-3 py-3">
                            <span class="block text-xl font-bold text-error-700">{{ dailyControl.evidence.quality_failed }}</span>
                            <span class="text-xs text-error-700">{{ t('operations.qualityFailed', 'Quality failed') }}</span>
                        </span>
                        <span class="rounded-2xl bg-brand-50 px-3 py-3">
                            <span class="block text-xl font-bold text-brand-700">{{ dailyControl.evidence.quality_follow_ups }}</span>
                            <span class="text-xs text-brand-700">{{ t('operations.qualityFollowUps', 'Follow-ups') }}</span>
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

        <section v-if="activeTab === 'schedule'" class="ta-card p-4 md:p-5" role="tabpanel">
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

        <div v-if="activeTab === 'visits' || activeTab === 'schedule'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_430px]">
            <section v-if="activeTab === 'visits'" class="space-y-4" role="tabpanel">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('operations.dailyVisits', 'Daily visits') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('operations.dailyVisitsSubtitle', 'Generated visits, timing, evidence, and field notes.') }}</p>
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
                                    {{ t('operations.lateCheckIn', 'Late arrival') }}
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
                                <span v-if="visit.status === 'completed'" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1" :class="reviewStatusClass(visit.evidence_review?.status)">
                                    <ShieldCheck class="h-3.5 w-3.5" />
                                    {{ reviewStatusLabel(visit.evidence_review?.status) }}
                                </span>
                            </div>
                        </div>

                    </div>

                    <div class="mt-5 rounded-2xl border border-brand-500/15 bg-brand-50/40 p-4">
                        <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-start">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">{{ t('operations.jobExecutionCost', 'Visit monitoring & cost control') }}</h4>
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
                                <div class="rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-gray-200">
                                    <span class="block text-xs font-semibold text-gray-500">{{ t('operations.actualCheckIn', 'Started at') }}</span>
                                    <span class="mt-1 block font-semibold text-gray-800">{{ formatDateTime(visit.checked_in_at) }}</span>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-gray-200">
                                    <span class="block text-xs font-semibold text-gray-500">{{ t('operations.actualCheckOut', 'Finished at') }}</span>
                                    <span class="mt-1 block font-semibold text-gray-800">{{ formatDateTime(visit.checked_out_at) }}</span>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-gray-200">
                                    <span class="block text-xs font-semibold text-gray-500">{{ t('operations.materialCostSar', 'Material cost (SAR)') }}</span>
                                    <MoneyText :value="visit.material_cost_halalas ?? 0" class="mt-1 block font-semibold text-gray-800" />
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-gray-200">
                                    <span class="block text-xs font-semibold text-gray-500">{{ t('operations.overtimeStatus', 'Overtime status') }}</span>
                                    <span class="mt-1 block font-semibold text-gray-800">{{ overtimeLabel(visit.overtime_status) }}</span>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-gray-200 sm:col-span-2">
                                    <span class="block text-xs font-semibold text-gray-500">{{ t('operations.executionNotes', 'Field notes') }}</span>
                                    <span class="mt-1 block text-gray-700">{{ visit.execution_notes || t('operations.notRecorded', 'Not recorded') }}</span>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-gray-200 sm:col-span-2 xl:col-span-3">
                                    <span class="block text-xs font-semibold text-gray-500">{{ t('operations.materialsUsed', 'Materials used') }}</span>
                                    <span class="mt-1 block whitespace-pre-line text-gray-700">{{ materialText(visit) || t('operations.notRecorded', 'Not recorded') }}</span>
                                </div>
                            </div>

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
                        </div>
                    </div>

                    <div v-if="visit.status === 'completed'" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4">
                        <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-start">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-semibold text-gray-900">{{ t('operations.supervisorEvidenceReview', 'Supervisor evidence review') }}</h4>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1" :class="reviewStatusClass(visit.evidence_review?.status)">
                                        {{ reviewStatusLabel(visit.evidence_review?.status) }}
                                    </span>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1" :class="qualityStatusClass(visit.evidence_review?.quality_status)">
                                        {{ qualityStatusLabel(visit.evidence_review?.quality_status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">{{ t('operations.supervisorEvidenceReviewSubtitle', 'Review GPS, photos, checklist, issues, and field notes before approving completion.') }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-center text-xs sm:grid-cols-4 lg:min-w-[420px]">
                                <span class="rounded-xl bg-gray-50 px-3 py-2">
                                    <span class="block text-base font-bold text-gray-900">{{ visit.evidence_review?.photo_count ?? 0 }}</span>
                                    <span class="text-gray-500">{{ t('operations.photos', 'Photos') }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2">
                                    <span class="block text-base font-bold text-gray-900">{{ visit.evidence_review?.checklist_done ?? 0 }}/{{ visit.evidence_review?.checklist_total ?? 0 }}</span>
                                    <span class="text-gray-500">{{ t('operations.checklist', 'Checklist') }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2">
                                    <span class="block text-base font-bold" :class="visit.evidence_review?.has_issue ? 'text-error-700' : 'text-success-700'">
                                        {{ visit.evidence_review?.has_issue ? t('operations.yes', 'Yes') : t('operations.no', 'No') }}
                                    </span>
                                    <span class="text-gray-500">{{ t('operations.issue', 'Issue') }}</span>
                                </span>
                                <span class="rounded-xl bg-gray-50 px-3 py-2">
                                    <span class="flex items-center justify-center gap-1 text-base font-bold text-gray-900">
                                        <Star class="h-3.5 w-3.5 text-warning-500" />
                                        {{ ratingText(visit.evidence_review?.customer_feedback?.rating) }}
                                    </span>
                                    <span class="text-gray-500">{{ t('operations.customerRating', 'Customer rating') }}</span>
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                            <div class="space-y-3">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <a
                                        v-if="visit.check_in_location"
                                        :href="visit.check_in_location.maps_url"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="rounded-xl border border-success-500/20 bg-success-50 px-3 py-2 text-sm font-semibold text-success-700"
                                    >
                                        <span class="block text-xs uppercase">{{ t('operations.gpsStartedAt', 'Check-in GPS') }}</span>
                                        <span class="mt-1 block">{{ visit.check_in_location.latitude }}, {{ visit.check_in_location.longitude }}</span>
                                    </a>
                                    <div v-else class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                                        {{ t('operations.noCheckInGps', 'No check-in GPS evidence') }}
                                    </div>

                                    <a
                                        v-if="visit.check_out_location"
                                        :href="visit.check_out_location.maps_url"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="rounded-xl border border-brand-500/20 bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-700"
                                    >
                                        <span class="block text-xs uppercase">{{ t('operations.gpsFinishedAt', 'Check-out GPS') }}</span>
                                        <span class="mt-1 block">{{ visit.check_out_location.latitude }}, {{ visit.check_out_location.longitude }}</span>
                                    </a>
                                    <div v-else class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                                        {{ t('operations.noCheckOutGps', 'No check-out GPS evidence') }}
                                    </div>
                                </div>

                                <div class="rounded-xl bg-gray-50 px-3 py-3">
                                    <p class="text-xs font-bold uppercase text-gray-500">{{ t('operations.workerPhotos', 'Worker photos') }}</p>
                                    <div v-if="visit.photos?.length" class="mt-2 flex flex-wrap gap-2">
                                        <a
                                            v-for="(photo, index) in visit.photos"
                                            :key="photo"
                                            :href="visit.photo_urls?.[index] ?? photo"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="inline-flex max-w-full items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-200"
                                        >
                                            <Camera class="h-3.5 w-3.5 text-success-600" />
                                            <span class="truncate">{{ photo }}</span>
                                        </a>
                                    </div>
                                    <p v-else class="mt-2 text-sm text-gray-500">{{ t('operations.noWorkerPhotos', 'No worker photos uploaded.') }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label class="text-sm font-medium text-gray-700">
                                        {{ t('operations.qualityOutcome', 'Quality outcome') }}
                                        <select v-model="acknowledgementForm(visit).quality_status" class="ta-input mt-1 h-10 w-full px-3 text-sm">
                                            <option v-for="option in qualityStatusOptions" :key="option.key" :value="option.key">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">
                                        {{ t('operations.qualityScore', 'Quality score') }}
                                        <input v-model="acknowledgementForm(visit).quality_score" type="number" min="0" max="100" class="ta-input mt-1 h-10 w-full px-3 text-sm">
                                    </label>
                                </div>
                                <textarea
                                    v-model="acknowledgementForm(visit).supervisor_note"
                                    class="ta-input min-h-24 w-full px-3 py-2 text-sm"
                                    :placeholder="t('operations.reviewNotePlaceholder', 'Review note or correction instructions')"
                                />
                                <label class="inline-flex w-full items-center gap-2 rounded-xl bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">
                                    <input v-model="acknowledgementForm(visit).create_follow_up" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600">
                                    {{ t('operations.createCustomerFollowUp', 'Create customer follow-up') }}
                                </label>
                                <div class="rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                    <span class="font-bold uppercase text-gray-500">{{ t('operations.customerRating', 'Customer rating') }}</span>
                                    <span class="mt-1 flex items-center gap-1 text-sm font-semibold text-gray-800">
                                        <Star class="h-4 w-4 text-warning-500" />
                                        {{ ratingText(visit.evidence_review?.customer_feedback?.rating) }}
                                    </span>
                                    <p v-if="visit.evidence_review?.customer_feedback?.comment" class="mt-1 text-sm text-gray-600">{{ visit.evidence_review.customer_feedback.comment }}</p>
                                </div>
                                <div v-if="visit.evidence_review?.reviewed_at" class="rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                    {{ t('operations.reviewedBy', 'Reviewed by') }}
                                    {{ visit.evidence_review.reviewed_by?.name ?? t('operations.supervisor', 'Supervisor') }}
                                    / {{ formatDateTime(visit.evidence_review.reviewed_at) }}
                                </div>
                                <div v-if="visit.evidence_review?.quality_reviewed_at" class="rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                    {{ t('operations.qualityReview', 'Quality review') }}
                                    {{ visit.evidence_review.quality_reviewed_by?.name ?? t('operations.supervisor', 'Supervisor') }}
                                    / {{ formatDateTime(visit.evidence_review.quality_reviewed_at) }}
                                </div>
                                <button v-if="visit.evidence_review?.needs_review || visit.evidence_review?.status === 'needs_correction'" type="button" class="ta-btn ta-btn-primary w-full" @click="reviewVisit(visit, 'approved')">
                                    <CheckCheck class="h-4 w-4" />
                                    {{ t('operations.approveCompletion', 'Approve completion') }}
                                </button>
                                <button v-if="visit.evidence_review?.needs_review || visit.evidence_review?.status === 'approved'" type="button" class="ta-btn ta-btn-secondary w-full border-warning-500/20 text-warning-700 hover:bg-warning-50" @click="reviewVisit(visit, 'needs_correction')">
                                    <MessageSquareWarning class="h-4 w-4" />
                                    {{ t('operations.requestCorrection', 'Request correction') }}
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
                            <div v-if="visit.supervisor_acknowledged_at" class="rounded-xl border border-success-500/20 bg-success-50 px-3 py-2 text-xs text-success-700">
                                {{ t('operations.acknowledgedBy', 'Acknowledged by') }} {{ visit.acknowledged_by?.name ?? t('operations.supervisor', 'Supervisor') }}
                            </div>
                        </div>
                    </div>

                    <div v-if="checklist(visit).length" class="mt-5 grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="item in checklist(visit)"
                            :key="item.id"
                            class="rounded-xl border border-gray-200 px-3 py-3 text-sm"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="min-w-0 truncate font-medium text-gray-700">{{ item.label }}</span>
                                <StatusBadge :status="item.status" />
                            </div>
                            <div v-if="item.notes || item.photo_path" class="mt-3 space-y-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                <p v-if="item.notes">{{ item.notes }}</p>
                                <p v-if="item.photo_path" class="inline-flex max-w-full items-center gap-1">
                                    <Camera class="h-3.5 w-3.5 text-success-600" />
                                    <span class="truncate">{{ item.photo_path }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </article>

                <div v-if="visits.length === 0" class="ta-card px-5 py-10 text-center text-gray-500">
                    {{ t('operations.noVisits') }}
                </div>
            </section>

            <section v-if="activeTab === 'schedule'" class="ta-card p-5" role="tabpanel">
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
                            {{ assignment.service?.title ?? t('operations.contractService') }} / {{ assignment.starts_at }} - {{ assignment.ends_at }} / {{ assignment.share_percent }}% / {{ assignmentRoleLabel(assignment.team_role) }}
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
