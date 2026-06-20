<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Banknote, ClipboardList, Pencil, ReceiptText, Trash2, Wrench, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import { useI18n } from '../../lib/i18n';
import type { ExpenseCategory, ExpenseRow, ExpenseSummary, Option } from './types';

const props = defineProps<{
    categories: ExpenseCategory[];
    expenses: ExpenseRow[];
    summary: ExpenseSummary;
    expenseStatuses: Option[];
}>();

const { t } = useI18n();
const editingExpenseId = ref<number | null>(null);

const paymentMethods: Option[] = [
    { key: 'bank_transfer', label: 'Bank transfer' },
    { key: 'cash', label: 'Cash' },
    { key: 'card', label: 'Card' },
    { key: 'cheque', label: 'Cheque' },
    { key: 'other', label: 'Other' },
];

const expenseForm = useForm({
    expense_date: today(),
    expense_category_id: defaultExpenseCategoryId(),
    vendor: '',
    description: '',
    amount_sar: '0.00',
    vat_sar: '0.00',
    payment_method: 'bank_transfer',
    payment_reference: '',
    status: 'paid',
    receipt_path: '',
});
const deleteForm = useForm({});

const summaryCards = computed(() => [
    { label: t('expensesAdmin.directJobCosts', 'Direct job costs'), value: props.summary.direct_cost_halalas, icon: Wrench, tone: 'bg-warning-50 text-warning-600' },
    { label: t('expensesAdmin.operatingExpenses', 'Operating expenses'), value: props.summary.operating_expenses_halalas, icon: Banknote, tone: 'bg-error-50 text-error-600' },
    { label: t('expensesAdmin.expenseVat', 'Expense VAT'), value: props.summary.vat_halalas, icon: ReceiptText, tone: 'bg-brand-50 text-brand-600' },
    { label: t('expensesAdmin.records', 'Records'), value: props.summary.records_count, icon: ClipboardList, tone: 'bg-success-50 text-success-600', count: true },
]);

function optionLabel(namespace: string, option: Option): string {
    return t(`${namespace}.${option.key}`, option.label);
}

function categoryTypeLabel(type: string): string {
    return t(`expensesAdmin.expenseTypes.${type}`, type.replaceAll('_', ' '));
}

function statusLabel(status: string): string {
    return t(`expensesAdmin.statuses.${status}`, status.replaceAll('_', ' '));
}

function paymentMethodLabel(method: string): string {
    return t(`expensesAdmin.methods.${method}`, method.replaceAll('_', ' '));
}

function statusTone(status: string): string {
    return {
        paid: 'border-success-500/20 bg-success-50 text-success-600',
        approved: 'border-brand-500/20 bg-brand-50 text-brand-600',
        draft: 'border-gray-200 bg-gray-50 text-gray-600',
        rejected: 'border-error-500/20 bg-error-50 text-error-600',
    }[status] ?? 'border-gray-200 bg-gray-50 text-gray-600';
}

function submitExpense(): void {
    if (editingExpenseId.value) {
        expenseForm.patch(`/app/expenses/${editingExpenseId.value}`, {
            preserveScroll: true,
            onSuccess: () => resetExpenseForm(),
        });

        return;
    }

    expenseForm.post('/app/expenses', {
        preserveScroll: true,
        onSuccess: () => resetExpenseForm(),
    });
}

function editExpense(expense: ExpenseRow): void {
    editingExpenseId.value = expense.id;
    expenseForm.clearErrors();
    expenseForm.expense_date = expense.expense_date ?? today();
    expenseForm.expense_category_id = expense.expense_category_id ? String(expense.expense_category_id) : '';
    expenseForm.vendor = expense.vendor ?? '';
    expenseForm.description = expense.description ?? '';
    expenseForm.amount_sar = expense.amount_sar;
    expenseForm.vat_sar = expense.vat_sar;
    expenseForm.payment_method = expense.payment_method;
    expenseForm.payment_reference = expense.payment_reference ?? '';
    expenseForm.status = expense.status;
    expenseForm.receipt_path = expense.receipt_path ?? '';
}

function destroyExpense(expense: ExpenseRow): void {
    if (! confirm(t('expensesAdmin.deleteExpenseConfirm', 'Delete this expense record?'))) {
        return;
    }

    deleteForm.delete(`/app/expenses/${expense.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingExpenseId.value === expense.id) {
                resetExpenseForm();
            }
        },
    });
}

function resetExpenseForm(): void {
    editingExpenseId.value = null;
    expenseForm.clearErrors();
    expenseForm.expense_date = today();
    expenseForm.expense_category_id = defaultExpenseCategoryId();
    expenseForm.vendor = '';
    expenseForm.description = '';
    expenseForm.amount_sar = '0.00';
    expenseForm.vat_sar = '0.00';
    expenseForm.payment_method = 'bank_transfer';
    expenseForm.payment_reference = '';
    expenseForm.status = 'paid';
    expenseForm.receipt_path = '';
}

function defaultExpenseCategoryId(): string {
    return props.categories[0] ? String(props.categories[0].id) : '';
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
    <Head :title="t('expensesAdmin.recordExpenseTitle', 'Record company expense')" />

    <section class="space-y-6">
        <div>
            <h1 class="ta-page-title">{{ t('expensesAdmin.recordExpenseTitle', 'Record company expense') }}</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                {{ t('expensesAdmin.recordExpenseBody', 'Use this for salaries, rent, utilities, equipment purchases, and direct job expenses that affect net profit.') }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in summaryCards" :key="card.label" class="ta-card p-5">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500">{{ card.label }}</p>
                        <p class="mt-2 truncate text-2xl font-bold text-gray-900">
                            <span v-if="card.count">{{ card.value }}</span>
                            <MoneyText v-else :value="card.value" />
                        </p>
                    </div>
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="card.tone">
                        <component :is="card.icon" class="h-5 w-5" />
                    </span>
                </div>
            </article>
        </div>

        <article class="ta-card p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ editingExpenseId ? t('expensesAdmin.updateExpense', 'Update expense') : t('expensesAdmin.recordExpense', 'Record expense') }}
                    </h2>
                    <p v-if="editingExpenseId" class="mt-1 text-sm text-brand-600">
                        {{ t('expensesAdmin.editingExistingRecord', 'Editing an existing record') }}
                    </p>
                </div>
                <button v-if="editingExpenseId" type="button" class="ta-btn ta-btn-secondary" @click="resetExpenseForm">
                    <X class="h-4 w-4" />
                    {{ t('expensesAdmin.cancel', 'Cancel') }}
                </button>
            </div>

            <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitExpense">
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.expenseDate', 'Expense date') }}</span>
                    <input v-model="expenseForm.expense_date" type="date" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                    <span v-if="expenseForm.errors.expense_date" class="mt-1 block text-xs font-medium text-error-600">{{ expenseForm.errors.expense_date }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.category', 'Category') }}</span>
                    <select v-model="expenseForm.expense_category_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                        <option value="">{{ t('expensesAdmin.selectCategory', 'Select category') }}</option>
                        <option v-for="category in categories" :key="category.id" :value="String(category.id)">
                            {{ category.name }}
                        </option>
                    </select>
                    <span v-if="expenseForm.errors.expense_category_id" class="mt-1 block text-xs font-medium text-error-600">{{ expenseForm.errors.expense_category_id }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.vendor', 'Vendor') }}</span>
                    <input v-model="expenseForm.vendor" type="text" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                    <span v-if="expenseForm.errors.vendor" class="mt-1 block text-xs font-medium text-error-600">{{ expenseForm.errors.vendor }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.status', 'Status') }}</span>
                    <select v-model="expenseForm.status" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                        <option v-for="option in expenseStatuses" :key="option.key" :value="option.key">
                            {{ optionLabel('expensesAdmin.statuses', option) }}
                        </option>
                    </select>
                    <span v-if="expenseForm.errors.status" class="mt-1 block text-xs font-medium text-error-600">{{ expenseForm.errors.status }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.amountSar', 'Amount (SAR)') }}</span>
                    <input v-model="expenseForm.amount_sar" type="number" min="0.01" step="0.01" inputmode="decimal" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                    <span v-if="expenseForm.errors.amount_sar" class="mt-1 block text-xs font-medium text-error-600">{{ expenseForm.errors.amount_sar }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.vatSar', 'VAT (SAR)') }}</span>
                    <input v-model="expenseForm.vat_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                    <span v-if="expenseForm.errors.vat_sar" class="mt-1 block text-xs font-medium text-error-600">{{ expenseForm.errors.vat_sar }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.paymentMethod', 'Payment method') }}</span>
                    <select v-model="expenseForm.payment_method" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                        <option v-for="method in paymentMethods" :key="method.key" :value="method.key">
                            {{ optionLabel('expensesAdmin.methods', method) }}
                        </option>
                    </select>
                    <span v-if="expenseForm.errors.payment_method" class="mt-1 block text-xs font-medium text-error-600">{{ expenseForm.errors.payment_method }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.paymentReference', 'Payment reference') }}</span>
                    <input v-model="expenseForm.payment_reference" type="text" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                    <span v-if="expenseForm.errors.payment_reference" class="mt-1 block text-xs font-medium text-error-600">{{ expenseForm.errors.payment_reference }}</span>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.receiptPath', 'Receipt path') }}</span>
                    <input v-model="expenseForm.receipt_path" type="text" class="ta-input mt-1 h-11 w-full px-3 text-sm" placeholder="expense-receipts/rent.pdf">
                    <span v-if="expenseForm.errors.receipt_path" class="mt-1 block text-xs font-medium text-error-600">{{ expenseForm.errors.receipt_path }}</span>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.description', 'Description') }}</span>
                    <textarea v-model="expenseForm.description" class="ta-input mt-1 min-h-20 w-full px-3 py-2 text-sm" />
                    <span v-if="expenseForm.errors.description" class="mt-1 block text-xs font-medium text-error-600">{{ expenseForm.errors.description }}</span>
                </label>

                <div class="md:col-span-2">
                    <button type="submit" class="ta-btn ta-btn-primary w-full sm:w-auto" :disabled="! expenseForm.expense_category_id || expenseForm.processing">
                        {{ editingExpenseId ? t('expensesAdmin.updateExpense', 'Update expense') : t('expensesAdmin.recordExpense', 'Record expense') }}
                    </button>
                </div>
            </form>
        </article>

        <article class="ta-card p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('expensesAdmin.expenseLedgerTitle', 'Expense ledger') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('expensesAdmin.expenseLedgerBody', 'Latest company expenses recorded by the admin team.') }}</p>
                </div>
                <ReceiptText class="h-5 w-5 text-brand-500" />
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase text-gray-400">
                            <th class="px-3 py-3 text-start">{{ t('expensesAdmin.expenseDate', 'Expense date') }}</th>
                            <th class="px-3 py-3 text-start">{{ t('expensesAdmin.category', 'Category') }}</th>
                            <th class="px-3 py-3 text-start">{{ t('expensesAdmin.vendor', 'Vendor') }}</th>
                            <th class="px-3 py-3 text-start">{{ t('expensesAdmin.amountSar', 'Amount (SAR)') }}</th>
                            <th class="px-3 py-3 text-start">{{ t('expensesAdmin.paymentMethod', 'Payment method') }}</th>
                            <th class="px-3 py-3 text-start">{{ t('expensesAdmin.status', 'Status') }}</th>
                            <th class="px-3 py-3 text-end">{{ t('expensesAdmin.actions', 'Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="expense in expenses" :key="expense.id">
                            <td class="px-3 py-3 text-gray-600">{{ expense.expense_date }}</td>
                            <td class="px-3 py-3">
                                <p class="font-medium text-gray-900">{{ expense.category_name ?? expense.category }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ categoryTypeLabel(expense.expense_type) }}</p>
                            </td>
                            <td class="px-3 py-3 text-gray-600">{{ expense.vendor ?? t('expensesAdmin.noVendor', 'No vendor') }}</td>
                            <td class="px-3 py-3 font-semibold text-gray-900"><MoneyText :value="expense.amount_halalas" /></td>
                            <td class="px-3 py-3 text-gray-600">{{ paymentMethodLabel(expense.payment_method) }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium" :class="statusTone(expense.status)">
                                    {{ statusLabel(expense.status) }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="ta-btn ta-btn-secondary px-3 py-2" @click="editExpense(expense)">
                                        <Pencil class="h-4 w-4" />
                                        <span class="sr-only">{{ t('expensesAdmin.edit', 'Edit') }}</span>
                                    </button>
                                    <button type="button" class="ta-btn ta-btn-secondary px-3 py-2 text-error-600" :disabled="deleteForm.processing" @click="destroyExpense(expense)">
                                        <Trash2 class="h-4 w-4" />
                                        <span class="sr-only">{{ t('expensesAdmin.delete', 'Delete') }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="expenses.length === 0" class="mt-5 rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                {{ t('expensesAdmin.noExpenses', 'No expenses recorded yet.') }}
            </p>
        </article>
    </section>
</template>
