<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Banknote, ClipboardList, Plus, Wrench } from '@lucide/vue';
import { computed } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import { useI18n } from '../../lib/i18n';
import type { ExpenseCategory, ExpenseSummary, Option } from './types';

const props = defineProps<{
    categories: ExpenseCategory[];
    summary: ExpenseSummary;
    expenseTypes: Option[];
}>();

const { t } = useI18n();

const categoryForm = useForm({
    name: '',
    code: '',
    expense_type: 'operating_expense',
    description: '',
    is_active: true,
});

const summaryCards = computed(() => [
    { label: t('expensesAdmin.directJobCosts', 'Direct job costs'), value: props.summary.direct_cost_halalas, icon: Wrench, tone: 'bg-warning-50 text-warning-600' },
    { label: t('expensesAdmin.operatingExpenses', 'Operating expenses'), value: props.summary.operating_expenses_halalas, icon: Banknote, tone: 'bg-error-50 text-error-600' },
    { label: t('expensesAdmin.records', 'Records'), value: props.summary.records_count, icon: ClipboardList, tone: 'bg-success-50 text-success-600', count: true },
]);

function optionLabel(namespace: string, option: Option): string {
    return t(`${namespace}.${option.key}`, option.label);
}

function categoryTypeLabel(type: string): string {
    return t(`expensesAdmin.expenseTypes.${type}`, type.replaceAll('_', ' '));
}

function submitCategory(): void {
    categoryForm.post('/app/expenses/categories', {
        preserveScroll: true,
        onSuccess: () => categoryForm.reset('name', 'code', 'description'),
    });
}
</script>

<template>
    <Head :title="t('expensesAdmin.expenseTypesTitle', 'Expense types')" />

    <section class="space-y-6">
        <div>
            <h1 class="ta-page-title">{{ t('expensesAdmin.expenseTypesTitle', 'Expense types') }}</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                {{ t('expensesAdmin.expenseTypesBody', 'Create the categories used for salaries, rent, equipment, utilities, and direct job costs.') }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
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

        <div class="grid gap-5 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
            <article class="ta-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('expensesAdmin.saveExpenseType', 'Save type') }}</h2>
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
            </article>

            <article class="ta-card p-5">
                <h2 class="text-lg font-semibold text-gray-900">{{ t('expensesAdmin.expenseTypesTitle', 'Expense types') }}</h2>
                <div class="mt-5 space-y-3">
                    <div v-for="category in categories" :key="category.id" class="rounded-xl border border-gray-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-semibold text-gray-900">{{ category.name }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ category.code }} / {{ categoryTypeLabel(category.expense_type) }}</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                                {{ t('expensesAdmin.recordsCount', ':count records', { count: category.expenses_count }) }}
                            </span>
                        </div>
                        <p v-if="category.description" class="mt-3 text-sm leading-6 text-gray-500">{{ category.description }}</p>
                    </div>
                    <p v-if="categories.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                        {{ t('expensesAdmin.noExpenseTypes', 'No expense types yet.') }}
                    </p>
                </div>
            </article>
        </div>
    </section>
</template>
