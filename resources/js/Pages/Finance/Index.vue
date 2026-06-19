<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Banknote,
    CalendarDays,
    CheckCircle2,
    CreditCard,
    Download,
    FileText,
    ReceiptText,
    Send,
    TrendingUp,
} from '@lucide/vue';
import { computed, reactive } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type { InvoiceRow } from '../../types';

type FinanceMetrics = {
    due: number;
    paid: number;
    overdue: number;
    expected30: number;
    heldCheques: number;
    pendingCreditNotes: number;
};

type Aging = {
    current: number;
    oneToThirty: number;
    thirtyOneToSixty: number;
    sixtyPlus: number;
};

type ExpectedCollection = {
    invoice_id: number;
    number: string;
    customer: string;
    due_date: string;
    balance_halalas: number;
    balance_sar: string;
};

type ChequeRow = {
    id: number;
    customer?: string | null;
    invoice_id?: number | null;
    invoice_number?: string | null;
    cheque_number: string;
    amount_halalas: number;
    amount_sar: string;
    due_date?: string | null;
    cleared_date?: string | null;
    status: string;
};

type CreditNoteRow = {
    id: number;
    invoice_id: number;
    invoice_number?: string | null;
    customer?: string | null;
    number: string;
    amount_halalas: number;
    amount_sar: string;
    status: string;
    reason: string;
};

type ExpenseRow = {
    id: number;
    expense_date?: string | null;
    expense_type: string;
    category: string;
    vendor?: string | null;
    description?: string | null;
    amount_halalas: number;
    amount_sar: string;
    vat_halalas: number;
    vat_sar: string;
    payment_method: string;
    payment_reference?: string | null;
    status: string;
    receipt_path?: string | null;
    customer?: string | null;
    contract?: string | null;
};

type ExpenseSummary = {
    direct_cost_halalas: number;
    operating_expenses_halalas: number;
    vat_halalas: number;
    pending_halalas: number;
};

type ExpenseOption = {
    key: string;
    label: string;
};

type PaymentForm = {
    amount_sar: string;
    method: string;
    reference: string;
};

type CreditNoteForm = {
    amount_sar: string;
    reason: string;
};

type ChequeForm = {
    invoice_id: string;
    cheque_number: string;
    amount_sar: string;
    due_date: string;
};

type ExpenseForm = {
    expense_date: string;
    expense_type: string;
    category: string;
    vendor: string;
    description: string;
    amount_sar: string;
    vat_sar: string;
    payment_method: string;
    payment_reference: string;
    status: string;
    receipt_path: string;
};

const props = defineProps<{
    invoices: InvoiceRow[];
    metrics: FinanceMetrics;
    aging: Aging;
    expectedCollections: ExpectedCollection[];
    cheques: ChequeRow[];
    creditNotes: CreditNoteRow[];
    expenses: ExpenseRow[];
    expenseSummary: ExpenseSummary;
    expenseCategories: ExpenseOption[];
    expenseTypes: ExpenseOption[];
    expenseStatuses: ExpenseOption[];
}>();

const { t } = useI18n();

const payments = reactive<Record<number, PaymentForm>>({});
const invoiceStatuses = reactive<Record<number, string>>({});
const creditForms = reactive<Record<number, CreditNoteForm>>({});
const chequeForm = reactive<ChequeForm>({
    invoice_id: props.invoices[0] ? String(props.invoices[0].id) : '',
    cheque_number: '',
    amount_sar: props.invoices[0]?.balance_sar ?? '0.00',
    due_date: today(),
});
const expenseForm = reactive<ExpenseForm>({
    expense_date: today(),
    expense_type: 'operating_expense',
    category: props.expenseCategories[0]?.key ?? 'miscellaneous',
    vendor: '',
    description: '',
    amount_sar: '0.00',
    vat_sar: '0.00',
    payment_method: 'bank_transfer',
    payment_reference: '',
    status: 'paid',
    receipt_path: '',
});

for (const invoice of props.invoices) {
    payments[invoice.id] = {
        amount_sar: invoice.balance_sar ?? '0.00',
        method: 'bank_transfer',
        reference: '',
    };
    invoiceStatuses[invoice.id] = invoice.status === 'overdue' ? 'sent' : invoice.status;
    creditForms[invoice.id] = {
        amount_sar: '0.00',
        reason: '',
    };
}

const financeCards = computed(() => [
    { label: t('finance.openBalance', 'Open balance'), value: props.metrics.due, tone: 'text-gray-800', icon: Banknote },
    { label: t('finance.collected', 'Collected'), value: props.metrics.paid, tone: 'text-success-600', icon: CheckCircle2 },
    { label: t('finance.overdue', 'Overdue'), value: props.metrics.overdue, tone: 'text-error-600', icon: CalendarDays },
    { label: t('finance.expected30Days', 'Expected 30 days'), value: props.metrics.expected30, tone: 'text-brand-500', icon: TrendingUp },
    { label: t('finance.heldCheques', 'Held cheques'), value: props.metrics.heldCheques, tone: 'text-warning-600', icon: CreditCard },
    { label: t('finance.pendingCredits', 'Pending credits'), value: props.metrics.pendingCreditNotes, tone: 'text-brand-500', icon: FileText },
]);

const agingCards = computed(() => [
    { label: t('finance.current', 'Current'), value: props.aging.current, tone: 'bg-success-500' },
    { label: t('finance.oneToThirty', '1-30 days'), value: props.aging.oneToThirty, tone: 'bg-warning-500' },
    { label: t('finance.thirtyOneToSixty', '31-60 days'), value: props.aging.thirtyOneToSixty, tone: 'bg-error-500' },
    { label: t('finance.sixtyPlus', '61+ days'), value: props.aging.sixtyPlus, tone: 'bg-gray-700' },
]);

const expenseCards = computed(() => [
    { label: t('finance.directJobCosts', 'Direct job costs'), value: props.expenseSummary.direct_cost_halalas, tone: 'text-warning-600' },
    { label: t('finance.operatingExpenses', 'Operating expenses'), value: props.expenseSummary.operating_expenses_halalas, tone: 'text-error-600' },
    { label: t('finance.expenseVat', 'Expense VAT'), value: props.expenseSummary.vat_halalas, tone: 'text-brand-500' },
    { label: t('finance.pendingExpenses', 'Pending draft'), value: props.expenseSummary.pending_halalas, tone: 'text-gray-800' },
]);

const maxAging = computed(() => Math.max(1, ...agingCards.value.map((item) => item.value)));

function submitPayment(invoice: InvoiceRow): void {
    router.post(`/app/finance/invoices/${invoice.id}/payments`, payments[invoice.id], { preserveScroll: true });
}

function updateInvoiceStatus(invoice: InvoiceRow): void {
    router.patch(`/app/finance/invoices/${invoice.id}/status`, {
        status: invoiceStatuses[invoice.id],
    }, { preserveScroll: true });
}

function submitCreditNote(invoice: InvoiceRow): void {
    router.post(`/app/finance/invoices/${invoice.id}/credit-notes`, creditForms[invoice.id], { preserveScroll: true });
}

function approveCreditNote(creditNote: CreditNoteRow): void {
    router.patch(`/app/finance/credit-notes/${creditNote.id}/approve`, {}, { preserveScroll: true });
}

function submitCheque(): void {
    router.post('/app/finance/cheques', chequeForm, { preserveScroll: true });
}

function submitExpense(): void {
    router.post('/app/finance/expenses', expenseForm, {
        preserveScroll: true,
        onSuccess: () => {
            expenseForm.vendor = '';
            expenseForm.description = '';
            expenseForm.amount_sar = '0.00';
            expenseForm.vat_sar = '0.00';
            expenseForm.payment_reference = '';
            expenseForm.receipt_path = '';
        },
    });
}

function updateChequeStatus(cheque: ChequeRow, status: string): void {
    router.patch(`/app/finance/cheques/${cheque.id}/status`, {
        status,
        cleared_date: status === 'cleared' ? today() : null,
    }, { preserveScroll: true });
}

function agingWidth(value: number): string {
    return `${Math.max(4, Math.round((value / maxAging.value) * 100))}%`;
}

function expenseOptionLabel(namespace: string, option: ExpenseOption): string {
    return t(`${namespace}.${option.key}`, option.label);
}

function today(): string {
    const value = new Date();
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}
</script>

<template>
    <Head :title="t('finance.headTitle', 'Finance')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('finance.title', 'Billing and collections') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('finance.subtitle', 'Invoice actions, receivables aging, cheques, credit notes, and expected collections.') }}</p>
            </div>
            <div class="ta-btn ta-btn-secondary">
                <TrendingUp class="h-4 w-4" />
                {{ t('finance.expectedCollections', 'Expected collections') }}
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <article v-for="item in financeCards" :key="item.label" class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm text-gray-500">{{ item.label }}</div>
                    <component :is="item.icon" class="h-5 w-5 text-gray-400" />
                </div>
                <div class="mt-3 text-2xl font-bold" :class="item.tone"><MoneyText :value="item.value" /></div>
            </article>
        </div>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('finance.receivablesAging', 'Receivables aging') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('finance.receivablesAgingBody', 'Open balances by due-date bucket after payments and approved credit notes.') }}</p>
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
                            <div class="h-full rounded-full" :class="bucket.tone" :style="{ width: agingWidth(bucket.value) }" />
                        </div>
                    </div>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('finance.expectedCollections', 'Expected collections') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('finance.expectedCollectionsBody', 'Invoices due or overdue within the next 30 days.') }}</p>
                    </div>
                    <TrendingUp class="h-5 w-5 text-success-600" />
                </div>

                <div class="mt-5 space-y-3">
                    <div v-for="item in expectedCollections" :key="item.invoice_id" class="rounded-2xl border border-gray-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900">{{ item.number }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ item.customer }}</p>
                            </div>
                            <MoneyText class="font-semibold text-gray-900" :value="item.balance_halalas" />
                        </div>
                        <p class="mt-2 text-xs text-gray-500">{{ t('finance.dueDate', 'Due :date', { date: item.due_date }) }}</p>
                    </div>

                    <p v-if="expectedCollections.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('finance.noExpectedCollections', 'No expected collections in the next 30 days.') }}
                    </p>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('finance.expenseEntry', 'Expense entry') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('finance.expenseEntryBody', 'Track direct job costs and operating expenses for net-profit reporting.') }}</p>
                    </div>
                    <ReceiptText class="h-5 w-5 text-brand-500" />
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div v-for="item in expenseCards" :key="item.label" class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <p class="text-xs font-bold uppercase text-gray-400">{{ item.label }}</p>
                        <MoneyText class="mt-1 block text-lg font-bold" :class="item.tone" :value="item.value" />
                    </div>
                </div>

                <form class="mt-5 grid gap-3 md:grid-cols-2" @submit.prevent="submitExpense">
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('finance.expenseDate', 'Expense date') }}
                        <input v-model="expenseForm.expense_date" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="date">
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('finance.expenseType', 'Expense type') }}
                        <select v-model="expenseForm.expense_type" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option v-for="option in expenseTypes" :key="option.key" :value="option.key">{{ expenseOptionLabel('finance.expenseTypes', option) }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('finance.category', 'Category') }}
                        <select v-model="expenseForm.category" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option v-for="option in expenseCategories" :key="option.key" :value="option.key">{{ expenseOptionLabel('finance.expenseCategories', option) }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('finance.vendor', 'Vendor') }}
                        <input v-model="expenseForm.vendor" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="text">
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('finance.amountSar', 'Amount (SAR)') }}
                        <input v-model="expenseForm.amount_sar" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="number" min="0.01" step="0.01" inputmode="decimal">
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('finance.vatSar', 'VAT (SAR)') }}
                        <input v-model="expenseForm.vat_sar" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="number" min="0" step="0.01" inputmode="decimal">
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('finance.paymentMethod', 'Payment method') }}
                        <select v-model="expenseForm.payment_method" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option value="bank_transfer">{{ t('finance.methods.bank_transfer', 'Bank transfer') }}</option>
                            <option value="cash">{{ t('finance.methods.cash', 'Cash') }}</option>
                            <option value="card">{{ t('finance.methods.card', 'Card') }}</option>
                            <option value="cheque">{{ t('finance.methods.cheque', 'Cheque') }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('finance.status', 'Status') }}
                        <select v-model="expenseForm.status" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option v-for="option in expenseStatuses" :key="option.key" :value="option.key">{{ expenseOptionLabel('statuses', option) }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700 md:col-span-2">
                        {{ t('finance.reference', 'Reference') }}
                        <input v-model="expenseForm.payment_reference" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="text">
                    </label>
                    <label class="text-sm font-medium text-gray-700 md:col-span-2">
                        {{ t('finance.receiptPath', 'Receipt path') }}
                        <input v-model="expenseForm.receipt_path" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="text" placeholder="expense-receipts/file.pdf">
                    </label>
                    <label class="text-sm font-medium text-gray-700 md:col-span-2">
                        {{ t('finance.description', 'Description') }}
                        <textarea v-model="expenseForm.description" class="ta-input mt-1 min-h-24 w-full px-3 py-2 text-sm" />
                    </label>
                    <button class="ta-btn ta-btn-primary md:col-span-2" type="submit">
                        {{ t('finance.recordExpense', 'Record expense') }}
                    </button>
                </form>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('finance.expenseLedger', 'Expense ledger') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('finance.expenseLedgerBody', 'Recent costs classified for gross-profit and net-profit reports.') }}</p>
                    </div>
                    <FileText class="h-5 w-5 text-warning-600" />
                </div>

                <div class="mt-5 space-y-3">
                    <div v-for="expense in expenses" :key="expense.id" class="rounded-2xl border border-gray-200 p-3">
                        <div class="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">{{ expense.vendor || expenseOptionLabel('finance.expenseCategories', { key: expense.category, label: expense.category }) }}</p>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ expense.expense_date }} / {{ expenseOptionLabel('finance.expenseTypes', { key: expense.expense_type, label: expense.expense_type }) }}
                                </p>
                                <p v-if="expense.description" class="mt-1 break-words text-xs text-gray-500">{{ expense.description }}</p>
                            </div>
                            <div class="shrink-0 text-start md:text-end">
                                <MoneyText class="font-semibold text-gray-900" :value="expense.amount_halalas" />
                                <div class="mt-2 flex flex-wrap gap-2 md:justify-end">
                                    <StatusBadge :status="expense.status" />
                                    <span class="rounded-full bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-500">{{ expenseOptionLabel('finance.expenseCategories', { key: expense.category, label: expense.category }) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="expenses.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('finance.noExpenses', 'No expenses recorded yet.') }}
                    </p>
                </div>
            </article>
        </section>

        <article class="ta-card overflow-hidden px-4 pb-3 pt-4 sm:px-6">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">{{ t('finance.invoices', 'Invoices') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('finance.invoicesBody', 'Record collections, update invoice state, and raise credit notes.') }}</p>
                </div>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="ta-table min-w-[1320px]">
                    <thead>
                        <tr>
                            <th>{{ t('finance.invoice', 'Invoice') }}</th>
                            <th>{{ t('finance.customer', 'Customer') }}</th>
                            <th>{{ t('finance.due', 'Due') }}</th>
                            <th>{{ t('finance.aging', 'Aging') }}</th>
                            <th>{{ t('finance.total', 'Total') }}</th>
                            <th>{{ t('finance.paid', 'Paid') }}</th>
                            <th>{{ t('finance.credit', 'Credit') }}</th>
                            <th>{{ t('finance.balance', 'Balance') }}</th>
                            <th>{{ t('finance.status', 'Status') }}</th>
                            <th>{{ t('finance.payment', 'Payment') }}</th>
                            <th>{{ t('finance.creditNote', 'Credit note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices" :key="invoice.id" class="align-top">
                            <td class="whitespace-nowrap">
                                <div class="font-semibold text-gray-800">{{ invoice.number }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ invoice.contract }}</div>
                                <a :href="invoice.print_url" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-500 hover:text-brand-700" target="_blank">
                                    <Download class="h-3.5 w-3.5" />
                                    {{ t('finance.htmlInvoice', 'HTML invoice') }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap">{{ invoice.customer }}</td>
                            <td class="whitespace-nowrap">
                                <div>{{ invoice.due_date }}</div>
                                <div v-if="invoice.days_overdue" class="mt-1 text-xs text-error-600">{{ t('finance.daysOverdue', ':count days overdue', { count: invoice.days_overdue }) }}</div>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="rounded-full bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600">{{ invoice.aging_bucket }}</span>
                            </td>
                            <td class="whitespace-nowrap"><MoneyText :value="invoice.gross_total_halalas" /></td>
                            <td class="whitespace-nowrap"><MoneyText :value="invoice.paid_total_halalas" /></td>
                            <td class="whitespace-nowrap"><MoneyText :value="invoice.credit_total_halalas" /></td>
                            <td class="whitespace-nowrap font-semibold text-gray-800"><MoneyText :value="invoice.balance_halalas ?? invoice.balance" /></td>
                            <td class="min-w-44">
                                <StatusBadge :status="invoice.status" />
                                <div class="mt-2 flex items-center gap-2">
                                    <select v-model="invoiceStatuses[invoice.id]" class="ta-input h-9 w-28 px-2 text-xs">
                                        <option value="draft">{{ t('statuses.draft', 'Draft') }}</option>
                                        <option value="issued">{{ t('statuses.issued', 'Issued') }}</option>
                                        <option value="sent">{{ t('statuses.sent', 'Sent') }}</option>
                                        <option value="paid">{{ t('statuses.paid', 'Paid') }}</option>
                                        <option value="cancelled">{{ t('statuses.cancelled', 'Cancelled') }}</option>
                                        <option value="void">{{ t('statuses.void', 'Void') }}</option>
                                    </select>
                                    <button class="ta-btn ta-btn-secondary px-3 py-2 text-xs" type="button" @click="updateInvoiceStatus(invoice)">
                                        <Send class="h-3.5 w-3.5" />
                                        {{ t('finance.save', 'Save') }}
                                    </button>
                                </div>
                            </td>
                            <td class="min-w-72">
                                <div class="grid grid-cols-[96px_1fr] gap-2">
                                    <input
                                        v-model="payments[invoice.id].amount_sar"
                                        class="ta-input h-9 px-2 text-xs"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        inputmode="decimal"
                                    >
                                    <select v-model="payments[invoice.id].method" class="ta-input h-9 px-2 text-xs">
                                        <option value="bank_transfer">{{ t('finance.methods.bank_transfer', 'Bank transfer') }}</option>
                                        <option value="cash">{{ t('finance.methods.cash', 'Cash') }}</option>
                                        <option value="card">{{ t('finance.methods.card', 'Card') }}</option>
                                        <option value="cheque">{{ t('finance.methods.cheque', 'Cheque') }}</option>
                                    </select>
                                </div>
                                <div class="mt-2 grid grid-cols-[1fr_auto] gap-2">
                                    <input v-model="payments[invoice.id].reference" class="ta-input h-9 px-2 text-xs" type="text" :placeholder="t('finance.reference', 'Reference')">
                                    <button class="ta-btn ta-btn-primary px-3 py-2 text-xs" type="button" @click="submitPayment(invoice)">
                                        {{ t('finance.save', 'Save') }}
                                    </button>
                                </div>
                            </td>
                            <td class="min-w-72">
                                <div class="grid grid-cols-[96px_1fr] gap-2">
                                    <input
                                        v-model="creditForms[invoice.id].amount_sar"
                                        class="ta-input h-9 px-2 text-xs"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        inputmode="decimal"
                                    >
                                    <input v-model="creditForms[invoice.id].reason" class="ta-input h-9 px-2 text-xs" type="text" :placeholder="t('finance.reason', 'Reason')">
                                </div>
                                <button class="ta-btn ta-btn-secondary mt-2 w-full px-3 py-2 text-xs" type="button" @click="submitCreditNote(invoice)">
                                    {{ t('finance.createCreditNote', 'Create credit note') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </article>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('finance.chequeTracking', 'Cheque tracking') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('finance.chequeTrackingBody', 'Hold, clear, bounce, or return customer cheques.') }}</p>
                    </div>
                    <CreditCard class="h-5 w-5 text-warning-600" />
                </div>

                <form class="mt-5 grid gap-3 md:grid-cols-4" @submit.prevent="submitCheque">
                    <label class="text-sm font-medium text-gray-700 md:col-span-2">
                        {{ t('finance.invoice', 'Invoice') }}
                        <select v-model="chequeForm.invoice_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option v-for="invoice in invoices" :key="invoice.id" :value="invoice.id">{{ invoice.number }} / {{ invoice.customer }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('finance.chequeNumber', 'Cheque number') }}
                        <input v-model="chequeForm.cheque_number" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="text">
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        {{ t('finance.amountSar', 'Amount (SAR)') }}
                        <input v-model="chequeForm.amount_sar" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="number" min="0.01" step="0.01" inputmode="decimal">
                    </label>
                    <label class="text-sm font-medium text-gray-700 md:col-span-2">
                        {{ t('finance.dueDateLabel', 'Due date') }}
                        <input v-model="chequeForm.due_date" class="ta-input mt-1 h-11 w-full px-3 text-sm" type="date">
                    </label>
                    <div class="flex items-end md:col-span-2">
                        <button type="submit" class="ta-btn ta-btn-primary w-full">
                            {{ t('finance.recordCheque', 'Record cheque') }}
                        </button>
                    </div>
                </form>

                <div class="mt-5 space-y-3">
                    <div v-for="cheque in cheques" :key="cheque.id" class="rounded-2xl border border-gray-200 p-3">
                        <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                            <div>
                                <p class="font-semibold text-gray-900">{{ cheque.cheque_number }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ cheque.customer }} / {{ cheque.invoice_number }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ t('finance.dueDate', 'Due :date', { date: cheque.due_date ?? '' }) }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <MoneyText class="font-semibold text-gray-900" :value="cheque.amount_halalas" />
                                <StatusBadge :status="cheque.status" />
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" class="ta-btn ta-btn-secondary px-3 py-2 text-xs" @click="updateChequeStatus(cheque, 'cleared')">{{ t('finance.clear', 'Clear') }}</button>
                            <button type="button" class="ta-btn ta-btn-secondary px-3 py-2 text-xs" @click="updateChequeStatus(cheque, 'bounced')">{{ t('finance.bounce', 'Bounce') }}</button>
                            <button type="button" class="ta-btn ta-btn-secondary px-3 py-2 text-xs" @click="updateChequeStatus(cheque, 'returned')">{{ t('finance.return', 'Return') }}</button>
                        </div>
                    </div>

                    <p v-if="cheques.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('finance.noCheques', 'No cheques recorded yet.') }}
                    </p>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('finance.creditNotes', 'Credit notes') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('finance.creditNotesBody', 'Review and approve invoice reductions.') }}</p>
                    </div>
                    <FileText class="h-5 w-5 text-brand-500" />
                </div>

                <div class="mt-5 space-y-3">
                    <div v-for="creditNote in creditNotes" :key="creditNote.id" class="rounded-2xl border border-gray-200 p-3">
                        <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                            <div>
                                <p class="font-semibold text-gray-900">{{ creditNote.number }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ creditNote.customer }} / {{ creditNote.invoice_number }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ creditNote.reason }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <MoneyText class="font-semibold text-gray-900" :value="creditNote.amount_halalas" />
                                <StatusBadge :status="creditNote.status" />
                            </div>
                        </div>
                        <button
                            v-if="creditNote.status === 'pending_approval'"
                            type="button"
                            class="ta-btn ta-btn-primary mt-3 w-full px-3 py-2 text-xs"
                            @click="approveCreditNote(creditNote)"
                        >
                            {{ t('finance.approveCreditNote', 'Approve credit note') }}
                        </button>
                    </div>

                    <p v-if="creditNotes.length === 0" class="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('finance.noCreditNotes', 'No credit notes recorded yet.') }}
                    </p>
                </div>
            </article>
        </section>

        <div class="rounded-2xl border border-brand-500/20 bg-brand-50 p-4 text-sm leading-6 text-brand-800">
            <ReceiptText class="me-2 inline h-4 w-4" />
            {{ t('finance.providerNote', 'Live payment provider submission remains abstracted in Phase 1. Cheques, credit notes, invoice PDFs, and receivables controls are operational for admin tracking.') }}
        </div>
    </section>
</template>
