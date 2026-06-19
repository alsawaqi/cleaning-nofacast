<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Banknote,
    BarChart3,
    CalendarDays,
    ClipboardCheck,
    CreditCard,
    Download,
    FileText,
    Printer,
    Target,
    TrendingUp,
} from '@lucide/vue';
import { computed, reactive, type Component } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { money, percent } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

type Filters = {
    from: string;
    to: string;
};

type Summary = {
    revenue: number;
    collected: number;
    openReceivables: number;
    overdueReceivables: number;
    heldCheques: number;
    activeContracts: number;
    completedVisits: number;
    missedVisits: number;
    workerUtilizationPercent: number;
    newLeads: number;
    plannedRevenue: number;
    billableOvertime: number;
    directExpenses: number;
    operatingExpenses: number;
    totalExpenses: number;
    grossProfit: number;
    grossMarginPercent: number;
    netProfit: number;
    netMarginPercent: number;
    laborCost: number;
    materialCost: number;
    overtimeMinutes: number;
    pendingOvertimeVisits: number;
};

type Aging = {
    current: number;
    oneToThirty: number;
    thirtyOneToSixty: number;
    sixtyPlus: number;
};

type PaymentMethod = {
    method: string;
    amount_halalas: number;
    amount_sar: string;
    count: number;
};

type ChequeStatus = {
    status: string;
    amount_halalas: number;
    amount_sar: string;
    count: number;
};

type ExpenseSummary = {
    direct_cost_halalas: number;
    operating_expenses_halalas: number;
    vat_halalas: number;
    total_halalas: number;
};

type ExpenseByCategory = {
    category: string;
    expense_type: string;
    amount_halalas: number;
    vat_halalas: number;
    count: number;
};

type ExpenseReportRow = {
    id: number;
    expense_date?: string | null;
    expense_type: string;
    category: string;
    vendor?: string | null;
    amount_halalas: number;
    vat_halalas: number;
    status: string;
};

type ReportInvoice = {
    id: number;
    number: string;
    customer?: string | null;
    contract?: string | null;
    status: string;
    issue_date: string;
    due_date: string;
    gross_total_halalas: number;
    paid_total_halalas: number;
    balance_halalas: number;
};

type FinanceReport = {
    aging: Aging;
    paymentsByMethod: PaymentMethod[];
    chequeStatuses: ChequeStatus[];
    expenseSummary: ExpenseSummary;
    expensesByCategory: ExpenseByCategory[];
    expenses: ExpenseReportRow[];
    invoices: ReportInvoice[];
};

type DailyVisit = {
    date: string;
    label: string;
    scheduled: number;
    in_progress: number;
    completed: number;
    missed: number;
    issue_reported: number;
};

type OperationsReport = {
    statuses: Record<string, number>;
    dailyVisits: DailyVisit[];
};

type WorkerReport = {
    worker_id: number;
    worker: string;
    status: string;
    target_halalas: number;
    actual_halalas: number;
    pace_percent: number;
    completed_visits: number;
    visits_count: number;
    utilization_percent: number;
};

type ContractRevenue = {
    id: number;
    reference: string;
    customer?: string | null;
    status: string;
    monthly_fee_halalas: number;
    invoiced_halalas: number;
    collected_halalas: number;
    balance_halalas: number;
};

type ProfitAggregate = {
    visits_count: number;
    completed_visits: number;
    planned_revenue_halalas: number;
    billable_overtime_halalas: number;
    revenue_halalas: number;
    labor_cost_halalas: number;
    material_cost_halalas: number;
    direct_expenses_halalas?: number;
    operating_expenses_halalas?: number;
    gross_profit_halalas: number;
    gross_margin_percent: number;
    net_profit_halalas?: number;
    net_margin_percent?: number;
    overtime_minutes: number;
    overtime_visits: number;
    pending_overtime_visits: number;
    average_profit_per_visit_halalas?: number;
};

type ContractProfitability = ProfitAggregate & {
    id?: number | null;
    reference: string;
    customer?: string | null;
    status: string;
    worker_count: number;
    completion_rate_percent?: number;
    overtime_rate_percent?: number;
};

type CustomerProfitability = ProfitAggregate & {
    id?: number | null;
    customer: string;
};

type WorkerProfitability = ProfitAggregate & {
    worker_id: number;
    worker: string;
    status: string;
    actual_minutes: number;
    profit_per_hour_halalas: number;
};

type ServiceProfitability = ProfitAggregate & {
    service_id?: number | null;
    service: string;
    category?: string | null;
};

type MaterialUsage = {
    name: string;
    rows_count: number;
    visits_count: number;
    total_cost_halalas: number;
    quantities: string[];
};

type OvertimeTrend = {
    date: string;
    label: string;
    visits_count: number;
    overtime_minutes: number;
    approved_minutes: number;
    pending_minutes: number;
    rejected_minutes: number;
    billable_overtime_halalas: number;
};

type ProfitabilityReport = {
    totals: ProfitAggregate;
    byContract: ContractProfitability[];
    byCustomer: CustomerProfitability[];
    byWorker: WorkerProfitability[];
    byService: ServiceProfitability[];
    materialUsage: MaterialUsage[];
    overtimeTrend: OvertimeTrend[];
    contractPerformance: ContractProfitability[];
};

type ExportLinks = {
    finance: string;
    operations: string;
    workers: string;
    contracts: string;
    profitability: string;
};

type SummaryCard = {
    label: string;
    value: string;
    helper: string;
    icon: Component;
    tone: string;
};

const props = defineProps<{
    filters: Filters;
    summary: Summary;
    finance: FinanceReport;
    operations: OperationsReport;
    workerPerformance: WorkerReport[];
    contractRevenue: ContractRevenue[];
    profitability: ProfitabilityReport;
    exportLinks: ExportLinks;
}>();

const { t } = useI18n();

const filters = reactive<Filters>({
    from: props.filters.from,
    to: props.filters.to,
});

const summaryCards = computed<SummaryCard[]>(() => [
    {
        label: t('reports.revenue', 'Revenue'),
        value: money(props.summary.revenue),
        helper: t('reports.billedInRange', 'Billed in range'),
        icon: TrendingUp,
        tone: 'text-brand-500',
    },
    {
        label: t('reports.collected', 'Collected'),
        value: money(props.summary.collected),
        helper: t('reports.paymentsReceived', 'Payments received'),
        icon: Banknote,
        tone: 'text-success-600',
    },
    {
        label: t('reports.openReceivables', 'Open receivables'),
        value: money(props.summary.openReceivables),
        helper: t('reports.unpaidBalance', 'Unpaid balance'),
        icon: FileText,
        tone: 'text-gray-800',
    },
    {
        label: t('reports.overdue', 'Overdue'),
        value: money(props.summary.overdueReceivables),
        helper: t('reports.pastDueBalance', 'Past due balance'),
        icon: CalendarDays,
        tone: 'text-error-600',
    },
    {
        label: t('reports.utilization', 'Utilization'),
        value: percent(props.summary.workerUtilizationPercent),
        helper: t('reports.completedVisitsHelper', ':count completed visits', { count: props.summary.completedVisits }),
        icon: ClipboardCheck,
        tone: 'text-success-600',
    },
    {
        label: t('reports.grossProfit', 'Gross profit'),
        value: money(props.summary.grossProfit),
        helper: t('reports.grossMarginHelper', ':value gross margin', { value: percent(props.summary.grossMarginPercent) }),
        icon: TrendingUp,
        tone: props.summary.grossProfit >= 0 ? 'text-success-600' : 'text-error-600',
    },
    {
        label: t('reports.netProfit', 'Net profit'),
        value: money(props.summary.netProfit),
        helper: t('reports.netMarginHelper', ':value net margin', { value: percent(props.summary.netMarginPercent) }),
        icon: Target,
        tone: props.summary.netProfit >= 0 ? 'text-success-600' : 'text-error-600',
    },
    {
        label: t('reports.operatingExpenses', 'Operating expenses'),
        value: money(props.summary.operatingExpenses),
        helper: t('reports.afterGrossProfit', 'Subtracted after gross profit'),
        icon: FileText,
        tone: 'text-error-600',
    },
    {
        label: t('reports.materials', 'Materials'),
        value: money(props.summary.materialCost),
        helper: t('reports.overtimeMinutesHelper', ':count overtime minutes', { count: props.summary.overtimeMinutes }),
        icon: FileText,
        tone: 'text-warning-600',
    },
    {
        label: t('reports.heldCheques', 'Held cheques'),
        value: money(props.summary.heldCheques),
        helper: t('reports.awaitingClearance', 'Awaiting clearance'),
        icon: CreditCard,
        tone: 'text-warning-600',
    },
]);

const agingCards = computed(() => [
    { label: t('reports.current', 'Current'), value: props.finance.aging.current, class: 'bg-success-500' },
    { label: t('reports.oneToThirty', '1-30 days'), value: props.finance.aging.oneToThirty, class: 'bg-warning-500' },
    { label: t('reports.thirtyOneToSixty', '31-60 days'), value: props.finance.aging.thirtyOneToSixty, class: 'bg-error-500' },
    { label: t('reports.sixtyPlus', '61+ days'), value: props.finance.aging.sixtyPlus, class: 'bg-gray-700' },
]);

const operationBars = computed(() => [
    { label: t('reports.scheduled', 'Scheduled'), key: 'scheduled', value: props.operations.statuses.scheduled ?? 0, class: 'bg-brand-500' },
    { label: t('reports.inProgress', 'In progress'), key: 'in_progress', value: props.operations.statuses.in_progress ?? 0, class: 'bg-warning-500' },
    { label: t('reports.completed', 'Completed'), key: 'completed', value: props.operations.statuses.completed ?? 0, class: 'bg-success-500' },
    { label: t('reports.missed', 'Missed'), key: 'missed', value: props.operations.statuses.missed ?? 0, class: 'bg-error-500' },
    { label: t('reports.issues', 'Issues'), key: 'issue_reported', value: props.operations.statuses.issue_reported ?? 0, class: 'bg-gray-700' },
]);
const maxExpenseCategory = computed(() => Math.max(1, ...props.finance.expensesByCategory.map((item) => item.amount_halalas)));

const maxAging = computed(() => Math.max(1, ...agingCards.value.map((item) => item.value)));
const maxOperation = computed(() => Math.max(1, ...operationBars.value.map((item) => item.value)));
const maxContractProfit = computed(() => Math.max(1, ...props.profitability.byContract.map((item) => Math.abs(item.gross_profit_halalas))));
const maxServiceProfit = computed(() => Math.max(1, ...props.profitability.byService.map((item) => Math.abs(item.gross_profit_halalas))));
const maxMaterialCost = computed(() => Math.max(1, ...props.profitability.materialUsage.map((item) => item.total_cost_halalas)));
const maxOvertime = computed(() => Math.max(1, ...props.profitability.overtimeTrend.map((item) => item.overtime_minutes)));

function applyFilters(): void {
    router.get('/app/reports', filters, {
        preserveScroll: true,
        preserveState: true,
    });
}

function printPage(): void {
    window.print();
}

function barWidth(value: number, max: number): string {
    if (value <= 0) {
        return '0%';
    }

    return `${Math.max(4, Math.round((value / max) * 100))}%`;
}

function label(value: string): string {
    const fallback = value.replaceAll('_', ' ');
    const methodLabel = t(`finance.methods.${value}`, fallback);

    return t(`statuses.${value}`, methodLabel);
}

function expenseLabel(namespace: string, value: string): string {
    return t(`${namespace}.${value}`, value.replaceAll('_', ' '));
}

function minutes(value?: number | null): string {
    const total = value ?? 0;
    const hours = Math.floor(total / 60);
    const mins = total % 60;

    return hours > 0
        ? t('workerPortal.hoursMinutes', ':hoursh :minutesm', { hours, minutes: mins })
        : t('workerPortal.minutesOnly', ':minutesm', { minutes: mins });
}

function profitTone(value: number): string {
    return value >= 0 ? 'text-success-600' : 'text-error-600';
}
</script>

<template>
    <Head :title="t('reports.headTitle', 'Reports')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('reports.title', 'Reports & management') }}</h1>
                <p class="mt-1 text-sm leading-6 text-gray-500">{{ t('reports.subtitle', 'Finance, operations, worker target, and contract revenue views.') }}</p>
            </div>

            <form class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] xl:min-w-[620px]" @submit.prevent="applyFilters">
                <label class="text-sm font-medium text-gray-700">
                    {{ t('reports.from', 'From') }}
                    <input v-model="filters.from" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="date">
                </label>
                <label class="text-sm font-medium text-gray-700">
                    {{ t('reports.to', 'To') }}
                    <input v-model="filters.to" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="date">
                </label>
                <div class="flex items-end">
                    <button class="ta-btn ta-btn-primary h-11 w-full px-4" type="submit">
                        <BarChart3 class="h-4 w-4" />
                        {{ t('reports.apply', 'Apply') }}
                    </button>
                </div>
                <div class="flex items-end">
                    <button class="ta-btn ta-btn-secondary h-11 w-full px-4" type="button" @click="printPage">
                        <Printer class="h-4 w-4" />
                        {{ t('reports.print', 'Print') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="flex flex-wrap gap-3">
            <a class="ta-btn ta-btn-secondary" :href="exportLinks.finance">
                <Download class="h-4 w-4" />
                {{ t('reports.financeCsv', 'Finance CSV') }}
            </a>
            <a class="ta-btn ta-btn-secondary" :href="exportLinks.operations">
                <Download class="h-4 w-4" />
                {{ t('reports.operationsCsv', 'Operations CSV') }}
            </a>
            <a class="ta-btn ta-btn-secondary" :href="exportLinks.workers">
                <Download class="h-4 w-4" />
                {{ t('reports.workersCsv', 'Workers CSV') }}
            </a>
            <a class="ta-btn ta-btn-secondary" :href="exportLinks.contracts">
                <Download class="h-4 w-4" />
                {{ t('reports.contractsCsv', 'Contracts CSV') }}
            </a>
            <a class="ta-btn ta-btn-secondary" :href="exportLinks.profitability">
                <Download class="h-4 w-4" />
                {{ t('reports.profitabilityCsv', 'Profitability CSV') }}
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <article v-for="card in summaryCards" :key="card.label" class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-gray-500">{{ card.label }}</span>
                    <component :is="card.icon" class="h-5 w-5" :class="card.tone" />
                </div>
                <p class="mt-3 text-2xl font-bold text-gray-900">{{ card.value }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ card.helper }}</p>
            </article>
        </div>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_430px]">
            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('reports.profitLoss', 'Profit and loss') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.profitLossBody', 'Income minus direct service costs and operating expenses for the selected period.') }}</p>
                    </div>
                    <Banknote class="h-5 w-5 text-success-600" />
                </div>

                <div class="mt-5 space-y-3">
                    <div class="flex items-center justify-between rounded-2xl bg-brand-50 px-4 py-3">
                        <span class="font-semibold text-brand-700">{{ t('reports.income', 'Income') }}</span>
                        <MoneyText class="font-bold text-gray-900" :value="summary.revenue" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl bg-gray-50 px-4 py-3">
                        <span class="text-gray-600">{{ t('reports.laborMaterials', 'Labor + materials') }}</span>
                        <MoneyText class="font-semibold text-gray-900" :value="summary.laborCost + summary.materialCost" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl bg-gray-50 px-4 py-3">
                        <span class="text-gray-600">{{ t('reports.directExpenses', 'Direct expenses') }}</span>
                        <MoneyText class="font-semibold text-gray-900" :value="summary.directExpenses" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl bg-success-50 px-4 py-3">
                        <span class="font-semibold text-success-700">{{ t('reports.grossProfit', 'Gross profit') }}</span>
                        <MoneyText class="font-bold" :class="profitTone(summary.grossProfit)" :value="summary.grossProfit" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl bg-error-50 px-4 py-3">
                        <span class="font-semibold text-error-700">{{ t('reports.operatingExpenses', 'Operating expenses') }}</span>
                        <MoneyText class="font-bold text-gray-900" :value="summary.operatingExpenses" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl border border-brand-500/20 bg-white px-4 py-4">
                        <div>
                            <p class="font-semibold text-gray-900">{{ t('reports.netProfit', 'Net profit') }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ t('reports.netMarginHelper', ':value net margin', { value: percent(summary.netMarginPercent) }) }}</p>
                        </div>
                        <MoneyText class="text-xl font-bold" :class="profitTone(summary.netProfit)" :value="summary.netProfit" />
                    </div>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('reports.expensesByCategory', 'Expenses by category') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.expensesByCategoryBody', 'Approved and paid expenses grouped by category.') }}</p>
                    </div>
                    <FileText class="h-5 w-5 text-warning-600" />
                </div>

                <div class="mt-5 space-y-4">
                    <div v-for="item in finance.expensesByCategory" :key="`${item.expense_type}-${item.category}`" class="space-y-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0 break-words font-semibold text-gray-800">{{ expenseLabel('finance.expenseCategories', item.category) }}</span>
                            <MoneyText class="font-semibold text-gray-900" :value="item.amount_halalas" />
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-warning-500" :style="{ width: `${Math.max(4, Math.round((item.amount_halalas / maxExpenseCategory) * 100))}%` }" />
                        </div>
                        <p class="text-xs text-gray-500">
                            {{ expenseLabel('finance.expenseTypes', item.expense_type) }} / {{ item.count }} {{ t('reports.records', 'records') }}
                        </p>
                    </div>

                    <p v-if="finance.expensesByCategory.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('reports.noExpensesInRange', 'No approved or paid expenses in this range.') }}
                    </p>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-3">
            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('reports.receivablesAging', 'Receivables aging') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.receivablesAgingBody', 'Open balances by due-date bucket.') }}</p>
                    </div>
                    <CalendarDays class="h-5 w-5 text-brand-500" />
                </div>

                <div class="mt-5 space-y-4">
                    <div v-for="bucket in agingCards" :key="bucket.label" class="space-y-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-semibold text-gray-800">{{ bucket.label }}</span>
                            <MoneyText class="text-gray-600" :value="bucket.value" />
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full" :class="bucket.class" :style="{ width: barWidth(bucket.value, maxAging) }" />
                        </div>
                    </div>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('reports.operationsStatus', 'Operations status') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.operationsStatusBody', 'Visit outcomes in the selected range.') }}</p>
                    </div>
                    <ClipboardCheck class="h-5 w-5 text-success-600" />
                </div>

                <div class="mt-5 space-y-4">
                    <div v-for="item in operationBars" :key="item.key" class="space-y-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-semibold text-gray-800">{{ item.label }}</span>
                            <span class="text-gray-600">{{ item.value }}</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full" :class="item.class" :style="{ width: barWidth(item.value, maxOperation) }" />
                        </div>
                    </div>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('reports.collectionsMix', 'Collections mix') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.collectionsMixBody', 'Payments and cheque exposure.') }}</p>
                    </div>
                    <Banknote class="h-5 w-5 text-success-600" />
                </div>

                <div class="mt-5 space-y-4">
                    <div v-for="method in finance.paymentsByMethod" :key="method.method" class="flex items-center justify-between gap-3 border-b border-gray-100 pb-3 last:border-b-0 last:pb-0">
                        <div>
                            <p class="font-semibold capitalize text-gray-800">{{ label(method.method) }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ t('reports.paymentsCount', ':count payments', { count: method.count }) }}</p>
                        </div>
                        <MoneyText class="font-semibold text-gray-900" :value="method.amount_halalas" />
                    </div>
                    <p v-if="finance.paymentsByMethod.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('reports.noPayments', 'No payments in this range.') }}
                    </p>
                </div>

                <div class="mt-5 border-t border-gray-100 pt-4">
                    <div v-for="cheque in finance.chequeStatuses" :key="cheque.status" class="mt-3 flex items-center justify-between text-sm first:mt-0">
                        <span class="capitalize text-gray-600">{{ label(cheque.status) }}</span>
                        <span class="font-semibold text-gray-900">{{ cheque.count }} / {{ money(cheque.amount_halalas) }}</span>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[0.95fr_1.45fr]">
            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('reports.profitabilityEngine', 'Profitability engine') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.profitabilityEngineBody', 'Revenue, job costs, overtime, and gross margin from field execution.') }}</p>
                    </div>
                    <TrendingUp class="h-5 w-5 text-success-600" />
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <p class="text-xs font-bold uppercase text-gray-400">{{ t('reports.revenue', 'Revenue') }}</p>
                        <MoneyText class="mt-1 block text-lg font-bold text-gray-900" :value="profitability.totals.revenue_halalas" />
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <p class="text-xs font-bold uppercase text-gray-400">{{ t('reports.grossProfit', 'Gross profit') }}</p>
                        <MoneyText class="mt-1 block text-lg font-bold" :class="profitTone(profitability.totals.gross_profit_halalas)" :value="profitability.totals.gross_profit_halalas" />
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <p class="text-xs font-bold uppercase text-gray-400">{{ t('reports.laborMaterials', 'Labor + materials') }}</p>
                        <p class="mt-1 text-lg font-bold text-gray-900">
                            {{ money(profitability.totals.labor_cost_halalas + profitability.totals.material_cost_halalas) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <p class="text-xs font-bold uppercase text-gray-400">{{ t('reports.margin', 'Margin') }}</p>
                        <p class="mt-1 text-lg font-bold" :class="profitTone(profitability.totals.gross_profit_halalas)">
                            {{ percent(profitability.totals.gross_margin_percent) }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 border-t border-gray-100 pt-5 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-gray-400">{{ t('reports.visits', 'Visits') }}</p>
                        <p class="mt-1 font-semibold text-gray-800">{{ profitability.totals.completed_visits }} / {{ profitability.totals.visits_count }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase text-gray-400">{{ t('reports.overtime', 'Overtime') }}</p>
                        <p class="mt-1 font-semibold text-gray-800">{{ minutes(profitability.totals.overtime_minutes) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase text-gray-400">{{ t('reports.pendingOt', 'Pending OT') }}</p>
                        <p class="mt-1 font-semibold text-gray-800">{{ profitability.totals.pending_overtime_visits }}</p>
                    </div>
                </div>
            </article>

            <article class="ta-card overflow-hidden px-4 pb-3 pt-4 sm:px-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">{{ t('reports.contractMargins', 'Contract margins') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.contractMarginsBody', 'Actual profitability per agreement after labor, materials, and overtime.') }}</p>
                    </div>
                    <FileText class="h-5 w-5 text-brand-500" />
                </div>

                <div class="w-full overflow-x-auto">
                    <table class="ta-table min-w-[880px]">
                        <thead>
                            <tr>
                                <th>{{ t('reports.contract', 'Contract') }}</th>
                                <th>{{ t('reports.customer', 'Customer') }}</th>
                                <th>{{ t('reports.revenue', 'Revenue') }}</th>
                                <th>{{ t('reports.costs', 'Costs') }}</th>
                                <th>{{ t('reports.profit', 'Profit') }}</th>
                                <th>{{ t('reports.margin', 'Margin') }}</th>
                                <th>{{ t('reports.overtime', 'Overtime') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="contract in profitability.byContract" :key="contract.reference">
                                <td class="font-semibold text-gray-800">{{ contract.reference }}</td>
                                <td>{{ contract.customer }}</td>
                                <td><MoneyText :value="contract.revenue_halalas" /></td>
                                <td><MoneyText :value="contract.labor_cost_halalas + contract.material_cost_halalas" /></td>
                                <td class="font-semibold" :class="profitTone(contract.gross_profit_halalas)"><MoneyText :value="contract.gross_profit_halalas" /></td>
                                <td>
                                    <div class="flex min-w-36 items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-full rounded-full" :class="contract.gross_profit_halalas >= 0 ? 'bg-success-500' : 'bg-error-500'" :style="{ width: barWidth(Math.abs(contract.gross_profit_halalas), maxContractProfit) }" />
                                        </div>
                                        <span class="text-xs font-semibold text-gray-600">{{ percent(contract.gross_margin_percent) }}</span>
                                    </div>
                                </td>
                                <td>{{ minutes(contract.overtime_minutes) }}</td>
                            </tr>
                            <tr v-if="profitability.byContract.length === 0">
                                <td colspan="7" class="py-8 text-center text-gray-500">{{ t('reports.noProfitabilityData', 'No profitability data for this range.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-3">
            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('reports.serviceMargins', 'Service margins') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.serviceMarginsBody', 'Services ranked by actual gross profit.') }}</p>
                    </div>
                    <BarChart3 class="h-5 w-5 text-brand-500" />
                </div>

                <div class="mt-5 space-y-4">
                    <div v-for="service in profitability.byService" :key="service.service" class="space-y-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0 break-words font-semibold text-gray-800">{{ service.service }}</span>
                            <span class="font-semibold" :class="profitTone(service.gross_profit_halalas)">{{ money(service.gross_profit_halalas) }}</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-brand-500" :style="{ width: barWidth(Math.abs(service.gross_profit_halalas), maxServiceProfit) }" />
                        </div>
                        <div class="flex justify-between text-xs text-gray-500">
                            <span>{{ service.completed_visits }} / {{ service.visits_count }} {{ t('reports.visits', 'Visits') }}</span>
                            <span>{{ percent(service.gross_margin_percent) }}</span>
                        </div>
                    </div>
                    <p v-if="profitability.byService.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('reports.noServiceMarginData', 'No service margin data in this range.') }}
                    </p>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('reports.materialUsage', 'Material usage') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.materialUsageBody', 'Actual consumables recorded in job execution.') }}</p>
                    </div>
                    <CreditCard class="h-5 w-5 text-warning-600" />
                </div>

                <div class="mt-5 space-y-4">
                    <div v-for="item in profitability.materialUsage" :key="item.name" class="space-y-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0 break-words font-semibold text-gray-800">{{ item.name }}</span>
                            <MoneyText class="font-semibold text-gray-900" :value="item.total_cost_halalas" />
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-warning-500" :style="{ width: barWidth(item.total_cost_halalas, maxMaterialCost) }" />
                        </div>
                        <p class="text-xs text-gray-500">{{ t('reports.recordsAcrossVisits', ':records records across :visits visits', { records: item.rows_count, visits: item.visits_count }) }}</p>
                    </div>
                    <p v-if="profitability.materialUsage.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('reports.noMaterialsRecorded', 'No materials recorded in this range.') }}
                    </p>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('reports.overtimeTrend', 'Overtime trend') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.overtimeTrendBody', 'Approved, pending, and rejected field overtime.') }}</p>
                    </div>
                    <CalendarDays class="h-5 w-5 text-error-600" />
                </div>

                <div class="mt-5 space-y-4">
                    <div v-for="day in profitability.overtimeTrend" :key="day.date" class="space-y-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-semibold text-gray-800">{{ day.label }}</span>
                            <span class="text-gray-600">{{ minutes(day.overtime_minutes) }}</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-error-500" :style="{ width: barWidth(day.overtime_minutes, maxOvertime) }" />
                        </div>
                        <div class="flex justify-between text-xs text-gray-500">
                            <span>{{ t('reports.approvedWithMinutes', 'Approved :minutes', { minutes: minutes(day.approved_minutes) }) }}</span>
                            <span>{{ t('reports.pendingWithMinutes', 'Pending :minutes', { minutes: minutes(day.pending_minutes) }) }}</span>
                        </div>
                    </div>
                    <p v-if="profitability.overtimeTrend.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('reports.noOvertimeRecorded', 'No overtime recorded in this range.') }}
                    </p>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="ta-card overflow-hidden px-4 pb-3 pt-4 sm:px-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">{{ t('reports.workerProfitProductivity', 'Worker profit productivity') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.workerProfitProductivityBody', 'Worker profit, worked time, and overtime contribution.') }}</p>
                    </div>
                    <Target class="h-5 w-5 text-success-600" />
                </div>

                <div class="w-full overflow-x-auto">
                    <table class="ta-table min-w-[760px]">
                        <thead>
                            <tr>
                                <th>{{ t('reports.worker', 'Worker') }}</th>
                                <th>{{ t('reports.profit', 'Profit') }}</th>
                                <th>{{ t('reports.profitPerHour', 'Profit / hour') }}</th>
                                <th>{{ t('reports.worked', 'Worked') }}</th>
                                <th>{{ t('reports.overtime', 'Overtime') }}</th>
                                <th>{{ t('reports.visits', 'Visits') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="worker in profitability.byWorker" :key="worker.worker_id">
                                <td class="font-semibold text-gray-800">{{ worker.worker }}</td>
                                <td class="font-semibold" :class="profitTone(worker.gross_profit_halalas)"><MoneyText :value="worker.gross_profit_halalas" /></td>
                                <td><MoneyText :value="worker.profit_per_hour_halalas" /></td>
                                <td>{{ minutes(worker.actual_minutes) }}</td>
                                <td>{{ minutes(worker.overtime_minutes) }}</td>
                                <td>{{ worker.completed_visits }} / {{ worker.visits_count }}</td>
                            </tr>
                            <tr v-if="profitability.byWorker.length === 0">
                                <td colspan="6" class="py-8 text-center text-gray-500">{{ t('reports.noWorkerProfitData', 'No worker profit data for this range.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="ta-card overflow-hidden px-4 pb-3 pt-4 sm:px-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">{{ t('reports.contractPerformance', 'Contract performance') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.contractPerformanceBody', 'Completion rate, overtime exposure, and average profit by agreement.') }}</p>
                    </div>
                    <ClipboardCheck class="h-5 w-5 text-brand-500" />
                </div>

                <div class="w-full overflow-x-auto">
                    <table class="ta-table min-w-[820px]">
                        <thead>
                            <tr>
                                <th>{{ t('reports.contract', 'Contract') }}</th>
                                <th>{{ t('reports.completion', 'Completion') }}</th>
                                <th>{{ t('reports.overtimeRate', 'Overtime rate') }}</th>
                                <th>{{ t('reports.avgProfitVisit', 'Avg profit / visit') }}</th>
                                <th>{{ t('reports.workers', 'Workers') }}</th>
                                <th>{{ t('reports.status', 'Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="contract in profitability.contractPerformance" :key="contract.reference">
                                <td class="font-semibold text-gray-800">{{ contract.reference }}</td>
                                <td>{{ percent(contract.completion_rate_percent ?? 0) }}</td>
                                <td>{{ percent(contract.overtime_rate_percent ?? 0) }}</td>
                                <td><MoneyText :value="contract.average_profit_per_visit_halalas ?? 0" /></td>
                                <td>{{ contract.worker_count }}</td>
                                <td><StatusBadge :status="contract.status" /></td>
                            </tr>
                            <tr v-if="profitability.contractPerformance.length === 0">
                                <td colspan="6" class="py-8 text-center text-gray-500">{{ t('reports.noContractPerformanceData', 'No contract performance data for this range.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="ta-card overflow-hidden px-4 pb-3 pt-4 sm:px-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">{{ t('reports.workerTargetActual', 'Worker target vs actual') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.workerTargetActualBody', 'Attributed contract revenue against worker targets.') }}</p>
                    </div>
                    <Target class="h-5 w-5 text-brand-500" />
                </div>

                <div class="w-full overflow-x-auto">
                    <table class="ta-table min-w-[760px]">
                        <thead>
                            <tr>
                                <th>{{ t('reports.worker', 'Worker') }}</th>
                                <th>{{ t('reports.target', 'Target') }}</th>
                                <th>{{ t('reports.actual', 'Actual') }}</th>
                                <th>{{ t('reports.pace', 'Pace') }}</th>
                                <th>{{ t('reports.visits', 'Visits') }}</th>
                                <th>{{ t('reports.status', 'Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="worker in workerPerformance" :key="worker.worker_id">
                                <td class="font-semibold text-gray-800">{{ worker.worker }}</td>
                                <td><MoneyText :value="worker.target_halalas" /></td>
                                <td><MoneyText :value="worker.actual_halalas" /></td>
                                <td>
                                    <div class="flex min-w-36 items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-full rounded-full bg-brand-500" :style="{ width: barWidth(Math.min(worker.pace_percent, 100), 100) }" />
                                        </div>
                                        <span class="text-xs font-semibold text-gray-600">{{ percent(worker.pace_percent) }}</span>
                                    </div>
                                </td>
                                <td>{{ worker.completed_visits }} / {{ worker.visits_count }}</td>
                                <td><StatusBadge :status="worker.status" /></td>
                            </tr>
                            <tr v-if="workerPerformance.length === 0">
                                <td colspan="6" class="py-8 text-center text-gray-500">{{ t('reports.noWorkerTargetData', 'No worker target data for this range.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="ta-card overflow-hidden px-4 pb-3 pt-4 sm:px-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">{{ t('reports.contractRevenue', 'Contract revenue') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('reports.contractRevenueBody', 'Monthly contract value, invoicing, collections, and balance.') }}</p>
                    </div>
                    <FileText class="h-5 w-5 text-brand-500" />
                </div>

                <div class="w-full overflow-x-auto">
                    <table class="ta-table min-w-[820px]">
                        <thead>
                            <tr>
                                <th>{{ t('reports.contract', 'Contract') }}</th>
                                <th>{{ t('reports.customer', 'Customer') }}</th>
                                <th>{{ t('reports.monthly', 'Monthly') }}</th>
                                <th>{{ t('reports.invoiced', 'Invoiced') }}</th>
                                <th>{{ t('reports.collected', 'Collected') }}</th>
                                <th>{{ t('reports.balance', 'Balance') }}</th>
                                <th>{{ t('reports.status', 'Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="contract in contractRevenue" :key="contract.id">
                                <td class="font-semibold text-gray-800">{{ contract.reference }}</td>
                                <td>{{ contract.customer }}</td>
                                <td><MoneyText :value="contract.monthly_fee_halalas" /></td>
                                <td><MoneyText :value="contract.invoiced_halalas" /></td>
                                <td><MoneyText :value="contract.collected_halalas" /></td>
                                <td class="font-semibold text-gray-800"><MoneyText :value="contract.balance_halalas" /></td>
                                <td><StatusBadge :status="contract.status" /></td>
                            </tr>
                            <tr v-if="contractRevenue.length === 0">
                                <td colspan="7" class="py-8 text-center text-gray-500">{{ t('reports.noContractRevenueData', 'No contract revenue data for this range.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <article class="ta-card overflow-hidden px-4 pb-3 pt-4 sm:px-6">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">{{ t('reports.financeReport', 'Finance report') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('reports.financeReportBody', 'Invoices issued in the selected range.') }}</p>
                </div>
                <Link href="/app/finance" class="ta-btn ta-btn-secondary">{{ t('reports.openFinance', 'Open finance') }}</Link>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="ta-table min-w-[880px]">
                    <thead>
                        <tr>
                            <th>{{ t('reports.invoice', 'Invoice') }}</th>
                            <th>{{ t('reports.customer', 'Customer') }}</th>
                            <th>{{ t('reports.contract', 'Contract') }}</th>
                            <th>{{ t('reports.due', 'Due') }}</th>
                            <th>{{ t('reports.gross', 'Gross') }}</th>
                            <th>{{ t('reports.paid', 'Paid') }}</th>
                            <th>{{ t('reports.balance', 'Balance') }}</th>
                            <th>{{ t('reports.status', 'Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in finance.invoices" :key="invoice.id">
                            <td class="font-semibold text-gray-800">{{ invoice.number }}</td>
                            <td>{{ invoice.customer }}</td>
                            <td>{{ invoice.contract }}</td>
                            <td>{{ invoice.due_date }}</td>
                            <td><MoneyText :value="invoice.gross_total_halalas" /></td>
                            <td><MoneyText :value="invoice.paid_total_halalas" /></td>
                            <td class="font-semibold text-gray-800"><MoneyText :value="invoice.balance_halalas" /></td>
                            <td><StatusBadge :status="invoice.status" /></td>
                        </tr>
                        <tr v-if="finance.invoices.length === 0">
                            <td colspan="8" class="py-8 text-center text-gray-500">{{ t('reports.noInvoices', 'No invoices in this range.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</template>
