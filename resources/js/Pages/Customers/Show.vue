<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart3,
    BriefcaseBusiness,
    Building2,
    FileText,
    MapPin,
    Pencil,
    ReceiptText,
    UsersRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type { Customer, Money } from '../../types';

type CatalogOption = {
    key: string;
    label: string;
};

type Catalog = {
    customerTypes: CatalogOption[];
    channels: CatalogOption[];
    locales: CatalogOption[];
    statuses: CatalogOption[];
};

type Summary = {
    sites_count: number;
    contracts_count: number;
    active_contracts_count: number;
    assigned_workers_count: number;
    visits_count: number;
    completed_visits_count: number;
    worked_minutes: number;
};

type FinanceSummary = {
    gross_total_halalas: Money;
    paid_total_halalas: Money;
    balance_halalas: Money;
    overdue_halalas: Money;
    invoices_count: number;
    payments_count: number;
};

type CustomerSiteRow = {
    id: number;
    name: string;
    city?: string | null;
    district?: string | null;
    address?: string | null;
    contracts_count: number;
    workers_count: number;
    visits_count: number;
    completed_visits_count: number;
    worked_minutes: number;
    gross_total_halalas: Money;
    balance_halalas: Money;
};

type CustomerContractRow = {
    id: number;
    reference: string;
    status: string;
    site?: { id: number; name: string } | null;
    service?: { id: number; title: string } | null;
    monthly_fee_halalas: Money;
    workers_count: number;
    assignments_count: number;
    visits_count: number;
    completed_visits_count: number;
    worked_minutes: number;
    gross_total_halalas: Money;
    paid_total_halalas: Money;
    balance_halalas: Money;
    detail_url: string;
};

type CustomerWorkerRow = {
    id: number;
    name: string;
    employee_code?: string | null;
    status?: string | null;
    job_role?: string | null;
    assignments_count: number;
    contracts_count: number;
    sites_count: number;
    visits_count: number;
    completed_visits_count: number;
    worked_minutes: number;
    latest_visit?: string | null;
};

type TrendRow = {
    month: string;
    label: string;
    visits_count: number;
    completed_visits_count: number;
    worked_minutes: number;
    billed_halalas: Money;
    paid_halalas: Money;
    balance_halalas: Money;
};

type InvoicePayment = {
    id: number;
    amount_halalas: Money;
    method: string;
    reference?: string | null;
    received_at?: string | null;
};

type CustomerInvoiceRow = {
    id: number;
    number: string;
    status: string;
    issue_date?: string | null;
    due_date?: string | null;
    contract?: string | null;
    site?: string | null;
    gross_total_halalas: Money;
    paid_total_halalas: Money;
    balance_halalas: Money;
    payments: InvoicePayment[];
};

type Panel = 'overview' | 'sites' | 'contracts' | 'workers' | 'finance';

const props = defineProps<{
    customer: Customer;
    summary: Summary;
    finance: FinanceSummary;
    sites: CustomerSiteRow[];
    contracts: CustomerContractRow[];
    workers: CustomerWorkerRow[];
    performanceTrend: TrendRow[];
    invoices: CustomerInvoiceRow[];
    catalog: Catalog;
    backUrl: string;
}>();

const { t } = useI18n();
const activePanel = ref<Panel>('overview');

const panels = computed(() => [
    { key: 'overview' as Panel, label: t('customersAdmin.overviewTab', 'Overview'), icon: Building2 },
    { key: 'sites' as Panel, label: t('customersAdmin.sitesTab', 'Sites'), icon: MapPin },
    { key: 'contracts' as Panel, label: t('customersAdmin.contractsTab', 'Contracts'), icon: BriefcaseBusiness },
    { key: 'workers' as Panel, label: t('customersAdmin.workersTab', 'Workers'), icon: UsersRound },
    { key: 'finance' as Panel, label: t('customersAdmin.financeTab', 'Finance'), icon: ReceiptText },
]);

const maxTrendValue = computed(() => Math.max(1, ...props.performanceTrend.map((row) => Math.max(row.completed_visits_count, Math.ceil(row.worked_minutes / 60)))));

function catalogLabel(group: keyof Catalog, key?: string | null): string {
    if (! key) {
        return '-';
    }

    const option = props.catalog[group].find((item) => item.key === key);

    return t(`customersAdmin.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
}

function formatHours(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    if (hours === 0) {
        return `${mins}m`;
    }

    return mins === 0 ? `${hours}h` : `${hours}h ${mins}m`;
}

function trendHeight(row: TrendRow): string {
    return `${Math.max(8, Math.round((Math.max(row.completed_visits_count, Math.ceil(row.worked_minutes / 60)) / maxTrendValue.value) * 112))}px`;
}
</script>

<template>
    <Head :title="t('customersAdmin.detailHeadTitle', 'Customer details')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <Link :href="backUrl" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-brand-600">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('customersAdmin.backToCustomers', 'Back to customers') }}
                </Link>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="ta-page-title">{{ customer.name }}</h1>
                    <StatusBadge :status="customer.status" />
                </div>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ catalogLabel('customerTypes', customer.customer_type) }} / {{ customer.phone || t('customersAdmin.noPhone', 'No phone') }} / {{ customer.email || t('customersAdmin.noEmail', 'No email') }}
                </p>
            </div>

            <Link :href="customer.edit_url ?? `/app/customers/${customer.id}/edit`" class="ta-btn ta-btn-secondary">
                <Pencil class="h-4 w-4" />
                {{ t('customersAdmin.editCustomer', 'Edit customer') }}
            </Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('customersAdmin.activeContracts', 'Active contracts') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ summary.active_contracts_count }} / {{ summary.contracts_count }}</p>
            </article>
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('customersAdmin.assignedWorkers', 'Assigned workers') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ summary.assigned_workers_count }}</p>
            </article>
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('customersAdmin.hoursWorked', 'Hours worked') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ formatHours(summary.worked_minutes) }}</p>
            </article>
            <article class="ta-card p-5">
                <p class="text-sm font-medium text-gray-500">{{ t('customersAdmin.pendingBalance', 'Pending balance') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="finance.balance_halalas" /></p>
            </article>
        </div>

        <div class="ta-card p-2">
            <div class="grid gap-2 sm:grid-cols-5">
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
                        <Building2 class="h-5 w-5" />
                    </span>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('customersAdmin.customerOverview', 'Customer overview') }}</h2>
                </div>

                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('customersAdmin.preferredChannel', 'Preferred channel') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ catalogLabel('channels', customer.preferred_channel) }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('customersAdmin.preferredLocale', 'Preferred locale') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ catalogLabel('locales', customer.preferred_locale) }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('customersAdmin.vatNumber', 'VAT number') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ customer.vat_number || t('customersAdmin.noVat', 'No VAT number') }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase text-gray-400">{{ t('customersAdmin.completedVisits', 'Completed visits') }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900">{{ summary.completed_visits_count }} / {{ summary.visits_count }}</dd>
                    </div>
                </dl>
            </article>

            <article class="ta-card p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-50 text-success-600">
                        <BarChart3 class="h-5 w-5" />
                    </span>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('customersAdmin.performanceTrend', 'Performance trend') }}</h2>
                </div>

                <div class="flex min-h-[180px] items-end gap-3 overflow-x-auto rounded-xl bg-gray-50 p-4">
                    <div v-for="row in performanceTrend" :key="row.month" class="flex min-w-20 flex-1 flex-col items-center justify-end gap-2">
                        <div class="flex h-32 w-full items-end justify-center rounded-lg bg-white px-3">
                            <div class="w-full rounded-t-lg bg-brand-500" :style="{ height: trendHeight(row) }"></div>
                        </div>
                        <p class="text-xs font-semibold text-gray-700">{{ row.label }}</p>
                        <p class="text-xs text-gray-500">{{ row.completed_visits_count }} / {{ formatHours(row.worked_minutes) }}</p>
                    </div>
                    <p v-if="performanceTrend.length === 0" class="w-full py-8 text-center text-sm text-gray-500">
                        {{ t('customersAdmin.noPerformanceData', 'No performance data yet.') }}
                    </p>
                </div>
            </article>
        </section>

        <section v-else-if="activePanel === 'sites'" class="ta-card overflow-hidden p-5">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <MapPin class="h-5 w-5" />
                </span>
                <h2 class="text-lg font-semibold text-gray-900">{{ t('customersAdmin.sitePerformance', 'Site performance') }}</h2>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="ta-table min-w-[920px]">
                    <thead>
                        <tr>
                            <th>{{ t('customersAdmin.site', 'Site') }}</th>
                            <th>{{ t('customersAdmin.contracts', 'Contracts') }}</th>
                            <th>{{ t('customersAdmin.workers', 'Workers') }}</th>
                            <th>{{ t('customersAdmin.visits', 'Visits') }}</th>
                            <th>{{ t('customersAdmin.hoursWorked', 'Hours worked') }}</th>
                            <th>{{ t('customersAdmin.outstanding', 'Outstanding') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="site in sites" :key="site.id">
                            <td>
                                <p class="font-semibold text-gray-900">{{ site.name }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ site.city }}<span v-if="site.district"> / {{ site.district }}</span></p>
                            </td>
                            <td>{{ site.contracts_count }}</td>
                            <td>{{ site.workers_count }}</td>
                            <td>{{ site.completed_visits_count }} / {{ site.visits_count }}</td>
                            <td>{{ formatHours(site.worked_minutes) }}</td>
                            <td><MoneyText :value="site.balance_halalas" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else-if="activePanel === 'contracts'" class="ta-card overflow-hidden p-5">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <BriefcaseBusiness class="h-5 w-5" />
                </span>
                <h2 class="text-lg font-semibold text-gray-900">{{ t('customersAdmin.contractPerformance', 'Contract performance') }}</h2>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="ta-table min-w-[1040px]">
                    <thead>
                        <tr>
                            <th>{{ t('customersAdmin.contract', 'Contract') }}</th>
                            <th>{{ t('customersAdmin.site', 'Site') }}</th>
                            <th>{{ t('customersAdmin.workers', 'Workers') }}</th>
                            <th>{{ t('customersAdmin.visits', 'Visits') }}</th>
                            <th>{{ t('customersAdmin.hoursWorked', 'Hours worked') }}</th>
                            <th>{{ t('customersAdmin.billed', 'Billed') }}</th>
                            <th>{{ t('customersAdmin.balance', 'Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="contract in contracts" :key="contract.id">
                            <td>
                                <Link :href="contract.detail_url" class="font-semibold text-brand-600 hover:text-brand-700">{{ contract.reference }}</Link>
                                <div class="mt-1"><StatusBadge :status="contract.status" /></div>
                            </td>
                            <td>{{ contract.site?.name ?? '-' }}</td>
                            <td>{{ contract.workers_count }}</td>
                            <td>{{ contract.completed_visits_count }} / {{ contract.visits_count }}</td>
                            <td>{{ formatHours(contract.worked_minutes) }}</td>
                            <td><MoneyText :value="contract.gross_total_halalas" /></td>
                            <td><MoneyText :value="contract.balance_halalas" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else-if="activePanel === 'workers'" class="ta-card overflow-hidden p-5">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-50 text-success-600">
                    <UsersRound class="h-5 w-5" />
                </span>
                <h2 class="text-lg font-semibold text-gray-900">{{ t('customersAdmin.workerPerformance', 'Worker performance') }}</h2>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="ta-table min-w-[920px]">
                    <thead>
                        <tr>
                            <th>{{ t('customersAdmin.worker', 'Worker') }}</th>
                            <th>{{ t('customersAdmin.contracts', 'Contracts') }}</th>
                            <th>{{ t('customersAdmin.sites', 'Sites') }}</th>
                            <th>{{ t('customersAdmin.visits', 'Visits') }}</th>
                            <th>{{ t('customersAdmin.hoursWorked', 'Hours worked') }}</th>
                            <th>{{ t('customersAdmin.latestVisit', 'Latest visit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="worker in workers" :key="worker.id">
                            <td>
                                <p class="font-semibold text-gray-900">{{ worker.name }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ worker.employee_code }}</p>
                            </td>
                            <td>{{ worker.contracts_count }}</td>
                            <td>{{ worker.sites_count }}</td>
                            <td>{{ worker.completed_visits_count }} / {{ worker.visits_count }}</td>
                            <td>{{ formatHours(worker.worked_minutes) }}</td>
                            <td>{{ worker.latest_visit ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-4">
                <article class="ta-card p-5">
                    <p class="text-sm font-medium text-gray-500">{{ t('customersAdmin.billed', 'Billed') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="finance.gross_total_halalas" /></p>
                </article>
                <article class="ta-card p-5">
                    <p class="text-sm font-medium text-gray-500">{{ t('customersAdmin.paid', 'Paid') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="finance.paid_total_halalas" /></p>
                </article>
                <article class="ta-card p-5">
                    <p class="text-sm font-medium text-gray-500">{{ t('customersAdmin.balance', 'Balance') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="finance.balance_halalas" /></p>
                </article>
                <article class="ta-card p-5">
                    <p class="text-sm font-medium text-gray-500">{{ t('customersAdmin.overdue', 'Overdue') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><MoneyText :value="finance.overdue_halalas" /></p>
                </article>
            </div>

            <article class="ta-card overflow-hidden p-5">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <FileText class="h-5 w-5" />
                    </span>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('customersAdmin.invoiceHistory', 'Invoice history') }}</h2>
                </div>

                <div class="space-y-3">
                    <div v-for="invoice in invoices" :key="invoice.id" class="rounded-xl border border-gray-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900">{{ invoice.number }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ invoice.contract }} / {{ invoice.site }} / {{ t('customersAdmin.dueDateLabel', 'Due date') }} {{ invoice.due_date }}</p>
                            </div>
                            <StatusBadge :status="invoice.status" />
                        </div>
                        <div class="mt-4 grid gap-2 sm:grid-cols-3">
                            <MoneyText :value="invoice.gross_total_halalas" class="font-semibold text-gray-900" />
                            <MoneyText :value="invoice.paid_total_halalas" class="font-semibold text-success-700" />
                            <MoneyText :value="invoice.balance_halalas" class="font-semibold text-error-700" />
                        </div>
                        <div v-if="invoice.payments.length" class="mt-4 space-y-2">
                            <p class="text-xs font-semibold uppercase text-gray-400">{{ t('customersAdmin.payments', 'Payments') }}</p>
                            <div v-for="payment in invoice.payments" :key="payment.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm">
                                <span class="font-semibold text-gray-800">{{ payment.method }} / {{ payment.reference ?? '-' }}</span>
                                <MoneyText :value="payment.amount_halalas" class="font-semibold text-gray-900" />
                            </div>
                        </div>
                    </div>
                    <p v-if="invoices.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('customersAdmin.noInvoicesForCustomer', 'No invoices recorded for this customer yet.') }}
                    </p>
                </div>
            </article>
        </section>
    </section>
</template>
