<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    CircleDollarSign,
    ClipboardList,
    FileText,
    Package,
    Plus,
    ReceiptText,
    TrendingUp,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import { useI18n } from '../../lib/i18n';

type Option = {
    key: string;
    label: string;
};

type ExpenseCategory = {
    id: number;
    name: string;
    code: string;
    expense_type: string;
    description?: string | null;
    is_active: boolean;
    expenses_count: number;
};

type ExpenseRow = {
    id: number;
    expense_date?: string | null;
    expense_category_id?: number | null;
    expense_type: string;
    category: string;
    category_name?: string | null;
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
};

type ExpenseSummary = {
    direct_cost_halalas: number;
    operating_expenses_halalas: number;
    vat_halalas: number;
    records_count: number;
    cost_items_count: number;
};

type CostItem = {
    id: number;
    name: string;
    item_type: string;
    unit: string;
    purchase_cost_halalas: number;
    purchase_cost_sar: string;
    estimated_life_months: number;
    estimated_monthly_uses: number;
    default_cost_per_use_halalas: number;
    default_cost_per_use_sar: string;
    notes?: string | null;
    is_active: boolean;
    service_cost_items_count: number;
};

type ServiceOption = {
    id: number;
    title: string;
    category: string;
    base_price_halalas: number;
    base_price_sar: string;
};

type ServiceCostBreakdownItem = {
    id: number;
    cost_item_id: number;
    name?: string | null;
    item_type?: string | null;
    unit?: string | null;
    quantity: string | number;
    cost_per_use_halalas: number;
    cost_per_use_sar: string;
    line_total_halalas: number;
    line_total_sar: string;
    charge_customer: boolean;
    notes?: string | null;
};

type ServiceCostBreakdown = {
    service: ServiceOption;
    items: ServiceCostBreakdownItem[];
    total_cost_halalas: number;
    total_cost_sar: string;
    gross_profit_estimate_halalas: number;
    gross_profit_estimate_sar: string;
};

const props = defineProps<{
    categories: ExpenseCategory[];
    expenses: ExpenseRow[];
    summary: ExpenseSummary;
    costItems: CostItem[];
    serviceOptions: ServiceOption[];
    serviceCostBreakdowns: ServiceCostBreakdown[];
    expenseTypes: Option[];
    expenseStatuses: Option[];
    itemTypes: Option[];
}>();

const { t } = useI18n();

const paymentMethods: Option[] = [
    { key: 'bank_transfer', label: 'Bank transfer' },
    { key: 'cash', label: 'Cash' },
    { key: 'card', label: 'Card' },
    { key: 'cheque', label: 'Cheque' },
    { key: 'other', label: 'Other' },
];

const categoryForm = useForm({
    name: '',
    code: '',
    expense_type: 'operating_expense',
    description: '',
    is_active: true,
});

const expenseForm = useForm({
    expense_date: today(),
    expense_category_id: props.categories[0] ? String(props.categories[0].id) : '',
    vendor: '',
    description: '',
    amount_sar: '0.00',
    vat_sar: '0.00',
    payment_method: 'bank_transfer',
    payment_reference: '',
    status: 'paid',
    receipt_path: '',
});

const costItemForm = useForm({
    name: '',
    item_type: 'material',
    unit: 'unit',
    purchase_cost_sar: '0.00',
    estimated_life_months: 12,
    estimated_monthly_uses: 30,
    default_cost_per_use_sar: '',
    notes: '',
    is_active: true,
});

const serviceCostForm = useForm({
    service_id: props.serviceOptions[0] ? String(props.serviceOptions[0].id) : '',
    cost_item_id: props.costItems[0] ? String(props.costItems[0].id) : '',
    quantity: '1.00',
    cost_per_use_sar: props.costItems[0]?.default_cost_per_use_sar ?? '0.00',
    charge_customer: false,
    notes: '',
});

const summaryCards = computed(() => [
    {
        label: t('expensesAdmin.directJobCosts', 'Direct job costs'),
        value: props.summary.direct_cost_halalas,
        icon: Wrench,
        tone: 'bg-warning-50 text-warning-600',
        money: true,
    },
    {
        label: t('expensesAdmin.operatingExpenses', 'Operating expenses'),
        value: props.summary.operating_expenses_halalas,
        icon: Banknote,
        tone: 'bg-error-50 text-error-600',
        money: true,
    },
    {
        label: t('expensesAdmin.expenseVat', 'Expense VAT'),
        value: props.summary.vat_halalas,
        icon: ReceiptText,
        tone: 'bg-brand-50 text-brand-600',
        money: true,
    },
    {
        label: t('expensesAdmin.records', 'Records'),
        value: props.summary.records_count,
        icon: ClipboardList,
        tone: 'bg-success-50 text-success-600',
        money: false,
    },
    {
        label: t('expensesAdmin.catalogItems', 'Catalog items'),
        value: props.summary.cost_items_count,
        icon: Package,
        tone: 'bg-gray-100 text-gray-700',
        money: false,
    },
]);

function optionLabel(namespace: string, option: Option): string {
    return t(`${namespace}.${option.key}`, option.label);
}

function categoryTypeLabel(type: string): string {
    const option = props.expenseTypes.find((item) => item.key === type);

    return optionLabel('expensesAdmin.expenseTypes', option ?? { key: type, label: type.replaceAll('_', ' ') });
}

function statusLabel(status: string): string {
    const option = props.expenseStatuses.find((item) => item.key === status);

    return optionLabel('expensesAdmin.statuses', option ?? { key: status, label: status.replaceAll('_', ' ') });
}

function itemTypeLabel(type: string): string {
    const option = props.itemTypes.find((item) => item.key === type);

    return optionLabel('expensesAdmin.itemTypes', option ?? { key: type, label: type.replaceAll('_', ' ') });
}

function paymentMethodLabel(method: string): string {
    const option = paymentMethods.find((item) => item.key === method);

    return optionLabel('expensesAdmin.methods', option ?? { key: method, label: method.replaceAll('_', ' ') });
}

function statusTone(status: string): string {
    return {
        paid: 'border-success-500/20 bg-success-50 text-success-600',
        approved: 'border-brand-500/20 bg-brand-50 text-brand-600',
        draft: 'border-gray-200 bg-gray-50 text-gray-600',
        rejected: 'border-error-500/20 bg-error-50 text-error-600',
    }[status] ?? 'border-gray-200 bg-gray-50 text-gray-600';
}

function selectedCostItem(): CostItem | undefined {
    return props.costItems.find((item) => String(item.id) === String(serviceCostForm.cost_item_id));
}

function applyCostItemDefault(): void {
    const item = selectedCostItem();

    if (item) {
        serviceCostForm.cost_per_use_sar = item.default_cost_per_use_sar;
    }
}

function submitCategory(): void {
    categoryForm.post('/app/expenses/categories', {
        preserveScroll: true,
        onSuccess: () => categoryForm.reset('name', 'code', 'description'),
    });
}

function submitExpense(): void {
    expenseForm.post('/app/expenses', {
        preserveScroll: true,
        onSuccess: () => {
            expenseForm.reset('vendor', 'description', 'amount_sar', 'vat_sar', 'payment_reference', 'receipt_path');
            expenseForm.amount_sar = '0.00';
            expenseForm.vat_sar = '0.00';
        },
    });
}

function submitCostItem(): void {
    costItemForm.post('/app/expenses/cost-items', {
        preserveScroll: true,
        onSuccess: () => {
            costItemForm.reset('name', 'notes', 'default_cost_per_use_sar');
            costItemForm.purchase_cost_sar = '0.00';
        },
    });
}

function submitServiceCostItem(): void {
    serviceCostForm.post('/app/expenses/service-cost-items', {
        preserveScroll: true,
        onSuccess: () => serviceCostForm.reset('notes'),
    });
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
    <Head :title="t('expensesAdmin.headTitle', 'Expenses')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('expensesAdmin.title', 'Expenses and service costs') }}</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ t('expensesAdmin.subtitle', 'Record company expenses, define expense types, maintain equipment and material costs, and link those costs to services for profit reporting.') }}
                </p>
            </div>
            <div class="inline-flex items-center gap-2 rounded-2xl border border-brand-500/20 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-700">
                <TrendingUp class="h-4 w-4" />
                {{ t('expensesAdmin.profitReady', 'Gross and net profit ready') }}
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article v-for="card in summaryCards" :key="card.label" class="ta-card p-5">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500">{{ card.label }}</p>
                        <p class="mt-2 truncate text-2xl font-bold text-gray-900">
                            <MoneyText v-if="card.money" :value="card.value" />
                            <span v-else>{{ card.value }}</span>
                        </p>
                    </div>
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="card.tone">
                        <component :is="card.icon" class="h-5 w-5" />
                    </span>
                </div>
            </article>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <article class="ta-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('expensesAdmin.expenseTypesTitle', 'Expense types') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('expensesAdmin.expenseTypesBody', 'Create the categories used for salaries, rent, equipment, utilities, and direct job costs.') }}</p>
                    </div>
                    <Plus class="h-5 w-5 text-brand-500" />
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submitCategory">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.name', 'Name') }}</span>
                            <input v-model="categoryForm.name" type="text" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <span v-if="categoryForm.errors.name" class="mt-1 block text-xs font-medium text-error-600">{{ categoryForm.errors.name }}</span>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.code', 'Code') }}</span>
                            <input v-model="categoryForm.code" type="text" dir="ltr" class="ta-input mt-1 h-11 w-full px-3 text-sm" placeholder="office_rent">
                            <span v-if="categoryForm.errors.code" class="mt-1 block text-xs font-medium text-error-600">{{ categoryForm.errors.code }}</span>
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.expenseType', 'Expense type') }}</span>
                        <select v-model="categoryForm.expense_type" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option v-for="option in expenseTypes" :key="option.key" :value="option.key">
                                {{ optionLabel('expensesAdmin.expenseTypes', option) }}
                            </option>
                        </select>
                        <span v-if="categoryForm.errors.expense_type" class="mt-1 block text-xs font-medium text-error-600">{{ categoryForm.errors.expense_type }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.description', 'Description') }}</span>
                        <textarea v-model="categoryForm.description" class="ta-input mt-1 min-h-20 w-full px-3 py-2 text-sm" />
                        <span v-if="categoryForm.errors.description" class="mt-1 block text-xs font-medium text-error-600">{{ categoryForm.errors.description }}</span>
                    </label>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input v-model="categoryForm.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            {{ t('expensesAdmin.active', 'Active') }}
                        </label>
                        <button type="submit" class="ta-btn ta-btn-primary" :disabled="categoryForm.processing">
                            {{ t('expensesAdmin.saveExpenseType', 'Save type') }}
                        </button>
                    </div>
                </form>

                <div class="mt-5 space-y-2">
                    <div v-for="category in categories" :key="category.id" class="rounded-xl border border-gray-200 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-semibold text-gray-900">{{ category.name }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ category.code }} / {{ categoryTypeLabel(category.expense_type) }}</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                                {{ t('expensesAdmin.recordsCount', ':count records', { count: category.expenses_count }) }}
                            </span>
                        </div>
                    </div>
                    <p v-if="categories.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('expensesAdmin.noExpenseTypes', 'No expense types yet.') }}
                    </p>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('expensesAdmin.recordExpenseTitle', 'Record company expense') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('expensesAdmin.recordExpenseBody', 'Use this for salaries, rent, utilities, equipment purchases, and direct job expenses that affect net profit.') }}</p>
                    </div>
                    <ReceiptText class="h-5 w-5 text-brand-500" />
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
                            {{ t('expensesAdmin.recordExpense', 'Record expense') }}
                        </button>
                    </div>
                </form>
            </article>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <article class="ta-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('expensesAdmin.costCatalogTitle', 'Equipment and materials catalog') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('expensesAdmin.costCatalogBody', 'Register machines, tools, vehicles, supplies, and materials with an estimated cost per use.') }}</p>
                    </div>
                    <Package class="h-5 w-5 text-brand-500" />
                </div>

                <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitCostItem">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.itemName', 'Item name') }}</span>
                        <input v-model="costItemForm.name" type="text" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                        <span v-if="costItemForm.errors.name" class="mt-1 block text-xs font-medium text-error-600">{{ costItemForm.errors.name }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.itemType', 'Item type') }}</span>
                        <select v-model="costItemForm.item_type" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option v-for="option in itemTypes" :key="option.key" :value="option.key">
                                {{ optionLabel('expensesAdmin.itemTypes', option) }}
                            </option>
                        </select>
                        <span v-if="costItemForm.errors.item_type" class="mt-1 block text-xs font-medium text-error-600">{{ costItemForm.errors.item_type }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.unit', 'Unit') }}</span>
                        <input v-model="costItemForm.unit" type="text" class="ta-input mt-1 h-11 w-full px-3 text-sm" placeholder="machine">
                        <span v-if="costItemForm.errors.unit" class="mt-1 block text-xs font-medium text-error-600">{{ costItemForm.errors.unit }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.purchaseCost', 'Purchase cost (SAR)') }}</span>
                        <input v-model="costItemForm.purchase_cost_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                        <span v-if="costItemForm.errors.purchase_cost_sar" class="mt-1 block text-xs font-medium text-error-600">{{ costItemForm.errors.purchase_cost_sar }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.lifeMonths', 'Life months') }}</span>
                        <input v-model.number="costItemForm.estimated_life_months" type="number" min="1" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                        <span v-if="costItemForm.errors.estimated_life_months" class="mt-1 block text-xs font-medium text-error-600">{{ costItemForm.errors.estimated_life_months }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.monthlyUses', 'Monthly uses') }}</span>
                        <input v-model.number="costItemForm.estimated_monthly_uses" type="number" min="1" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                        <span v-if="costItemForm.errors.estimated_monthly_uses" class="mt-1 block text-xs font-medium text-error-600">{{ costItemForm.errors.estimated_monthly_uses }}</span>
                    </label>

                    <label class="block md:col-span-2">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.defaultCostPerUse', 'Default cost per use (SAR)') }}</span>
                        <input v-model="costItemForm.default_cost_per_use_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input mt-1 h-11 w-full px-3 text-sm" :placeholder="t('expensesAdmin.autoCalculate', 'Auto-calculate if empty')">
                        <span v-if="costItemForm.errors.default_cost_per_use_sar" class="mt-1 block text-xs font-medium text-error-600">{{ costItemForm.errors.default_cost_per_use_sar }}</span>
                    </label>

                    <label class="block md:col-span-2">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.notes', 'Notes') }}</span>
                        <textarea v-model="costItemForm.notes" class="ta-input mt-1 min-h-20 w-full px-3 py-2 text-sm" />
                        <span v-if="costItemForm.errors.notes" class="mt-1 block text-xs font-medium text-error-600">{{ costItemForm.errors.notes }}</span>
                    </label>

                    <div class="flex flex-col gap-3 md:col-span-2 sm:flex-row sm:items-center sm:justify-between">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input v-model="costItemForm.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            {{ t('expensesAdmin.active', 'Active') }}
                        </label>
                        <button type="submit" class="ta-btn ta-btn-primary" :disabled="costItemForm.processing">
                            {{ t('expensesAdmin.saveCostItem', 'Save catalog item') }}
                        </button>
                    </div>
                </form>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('expensesAdmin.linkServiceCostTitle', 'Link costs to services') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('expensesAdmin.linkServiceCostBody', 'Attach equipment or material usage to a service so every quotation and report can estimate real margin.') }}</p>
                    </div>
                    <CircleDollarSign class="h-5 w-5 text-brand-500" />
                </div>

                <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitServiceCostItem">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.service', 'Service') }}</span>
                        <select v-model="serviceCostForm.service_id" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                            <option value="">{{ t('expensesAdmin.selectService', 'Select service') }}</option>
                            <option v-for="service in serviceOptions" :key="service.id" :value="String(service.id)">
                                {{ service.title }}
                            </option>
                        </select>
                        <span v-if="serviceCostForm.errors.service_id" class="mt-1 block text-xs font-medium text-error-600">{{ serviceCostForm.errors.service_id }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.costItem', 'Cost item') }}</span>
                        <select v-model="serviceCostForm.cost_item_id" class="ta-input mt-1 h-11 w-full px-3 text-sm" @change="applyCostItemDefault">
                            <option value="">{{ t('expensesAdmin.selectCostItem', 'Select item') }}</option>
                            <option v-for="item in costItems" :key="item.id" :value="String(item.id)">
                                {{ item.name }}
                            </option>
                        </select>
                        <span v-if="serviceCostForm.errors.cost_item_id" class="mt-1 block text-xs font-medium text-error-600">{{ serviceCostForm.errors.cost_item_id }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.quantity', 'Quantity') }}</span>
                        <input v-model="serviceCostForm.quantity" type="number" min="0.01" step="0.01" inputmode="decimal" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                        <span v-if="serviceCostForm.errors.quantity" class="mt-1 block text-xs font-medium text-error-600">{{ serviceCostForm.errors.quantity }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.costPerUse', 'Cost per use (SAR)') }}</span>
                        <input v-model="serviceCostForm.cost_per_use_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input mt-1 h-11 w-full px-3 text-sm">
                        <span v-if="serviceCostForm.errors.cost_per_use_sar" class="mt-1 block text-xs font-medium text-error-600">{{ serviceCostForm.errors.cost_per_use_sar }}</span>
                    </label>

                    <label class="block md:col-span-2">
                        <span class="text-sm font-medium text-gray-700">{{ t('expensesAdmin.notes', 'Notes') }}</span>
                        <textarea v-model="serviceCostForm.notes" class="ta-input mt-1 min-h-20 w-full px-3 py-2 text-sm" />
                        <span v-if="serviceCostForm.errors.notes" class="mt-1 block text-xs font-medium text-error-600">{{ serviceCostForm.errors.notes }}</span>
                    </label>

                    <div class="flex flex-col gap-3 md:col-span-2 sm:flex-row sm:items-center sm:justify-between">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input v-model="serviceCostForm.charge_customer" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            {{ t('expensesAdmin.chargeCustomer', 'Charge customer') }}
                        </label>
                        <button type="submit" class="ta-btn ta-btn-primary" :disabled="! serviceCostForm.service_id || ! serviceCostForm.cost_item_id || serviceCostForm.processing">
                            {{ t('expensesAdmin.linkCostItem', 'Link cost item') }}
                        </button>
                    </div>
                </form>

                <div class="mt-5 rounded-2xl border border-brand-500/15 bg-brand-50 px-4 py-3 text-sm text-brand-700">
                    <p class="font-semibold">{{ t('expensesAdmin.costLogicTitle', 'Cost logic') }}</p>
                    <p class="mt-1 leading-6">{{ t('expensesAdmin.costLogicBody', 'Purchase expenses track cash spent. Service cost links estimate the cost consumed each time that service is sold or performed.') }}</p>
                </div>
            </article>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('expensesAdmin.costCatalogLedger', 'Catalog ledger') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('expensesAdmin.costCatalogLedgerBody', 'Current equipment and material cost assumptions.') }}</p>
                    </div>
                    <FileText class="h-5 w-5 text-brand-500" />
                </div>

                <div class="mt-5 space-y-3">
                    <div v-for="item in costItems" :key="item.id" class="rounded-xl border border-gray-200 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-semibold text-gray-900">{{ item.name }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ itemTypeLabel(item.item_type) }} / {{ item.unit }}</p>
                            </div>
                            <div class="text-start sm:text-end">
                                <p class="text-sm font-semibold text-gray-900"><MoneyText :value="item.default_cost_per_use_halalas" /></p>
                                <p class="mt-1 text-xs text-gray-500">{{ t('expensesAdmin.perUse', 'per use') }}</p>
                            </div>
                        </div>
                        <div class="mt-3 grid gap-2 text-xs text-gray-500 sm:grid-cols-3">
                            <span>{{ t('expensesAdmin.purchaseCostShort', 'Purchase') }}: <MoneyText :value="item.purchase_cost_halalas" /></span>
                            <span>{{ t('expensesAdmin.lifeMonthsShort', 'Life') }}: {{ item.estimated_life_months }}</span>
                            <span>{{ t('expensesAdmin.usedByServices', 'Services') }}: {{ item.service_cost_items_count }}</span>
                        </div>
                    </div>
                    <p v-if="costItems.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('expensesAdmin.noCostItems', 'No equipment or material items yet.') }}
                    </p>
                </div>
            </article>

            <article class="ta-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('expensesAdmin.serviceCostBreakdownTitle', 'Service cost breakdown') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('expensesAdmin.serviceCostBreakdownBody', 'Base price compared with equipment and material cost per service use.') }}</p>
                    </div>
                    <CircleDollarSign class="h-5 w-5 text-brand-500" />
                </div>

                <div class="mt-5 space-y-4">
                    <div v-for="breakdown in serviceCostBreakdowns" :key="breakdown.service.id" class="rounded-2xl border border-gray-200 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ breakdown.service.title }}</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ breakdown.service.category }}</p>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center text-xs sm:min-w-[340px]">
                                <div class="rounded-xl bg-gray-50 px-3 py-2">
                                    <p class="text-gray-500">{{ t('expensesAdmin.basePriceShort', 'Base') }}</p>
                                    <p class="mt-1 font-semibold text-gray-900"><MoneyText :value="breakdown.service.base_price_halalas" /></p>
                                </div>
                                <div class="rounded-xl bg-warning-50 px-3 py-2">
                                    <p class="text-warning-700">{{ t('expensesAdmin.costShort', 'Cost') }}</p>
                                    <p class="mt-1 font-semibold text-warning-700"><MoneyText :value="breakdown.total_cost_halalas" /></p>
                                </div>
                                <div class="rounded-xl bg-success-50 px-3 py-2">
                                    <p class="text-success-700">{{ t('expensesAdmin.grossProfitShort', 'Gross') }}</p>
                                    <p class="mt-1 font-semibold text-success-700"><MoneyText :value="breakdown.gross_profit_estimate_halalas" /></p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead>
                                    <tr class="text-start text-xs font-semibold uppercase text-gray-400">
                                        <th class="px-2 py-2 text-start">{{ t('expensesAdmin.itemName', 'Item name') }}</th>
                                        <th class="px-2 py-2 text-start">{{ t('expensesAdmin.quantity', 'Quantity') }}</th>
                                        <th class="px-2 py-2 text-start">{{ t('expensesAdmin.costPerUseShort', 'Cost/use') }}</th>
                                        <th class="px-2 py-2 text-start">{{ t('expensesAdmin.total', 'Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="item in breakdown.items" :key="item.id">
                                        <td class="px-2 py-2 font-medium text-gray-800">{{ item.name }}</td>
                                        <td class="px-2 py-2 text-gray-600">{{ item.quantity }} {{ item.unit }}</td>
                                        <td class="px-2 py-2 text-gray-600"><MoneyText :value="item.cost_per_use_halalas" /></td>
                                        <td class="px-2 py-2 font-semibold text-gray-900"><MoneyText :value="item.line_total_halalas" /></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <p v-if="serviceCostBreakdowns.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('expensesAdmin.noServiceBreakdowns', 'No services have linked cost items yet.') }}
                    </p>
                </div>
            </article>
        </div>

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
