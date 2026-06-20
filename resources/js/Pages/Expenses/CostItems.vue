<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { FileText, Package, Pencil, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import { useI18n } from '../../lib/i18n';
import type { CostItem, ExpenseSummary, Option } from './types';

const props = defineProps<{
    costItems: CostItem[];
    summary: ExpenseSummary;
    itemTypes: Option[];
}>();

const { t } = useI18n();
const editingCostItemId = ref<number | null>(null);

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
const deleteForm = useForm({});

const summaryCards = computed(() => [
    { label: t('expensesAdmin.catalogItems', 'Catalog items'), value: props.summary.cost_items_count, tone: 'bg-brand-50 text-brand-600' },
]);

function optionLabel(namespace: string, option: Option): string {
    return t(`${namespace}.${option.key}`, option.label);
}

function itemTypeLabel(type: string): string {
    return t(`expensesAdmin.itemTypes.${type}`, type.replaceAll('_', ' '));
}

function submitCostItem(): void {
    if (editingCostItemId.value) {
        costItemForm.patch(`/app/expenses/cost-items/${editingCostItemId.value}`, {
            preserveScroll: true,
            onSuccess: () => resetCostItemForm(),
        });

        return;
    }

    costItemForm.post('/app/expenses/cost-items', {
        preserveScroll: true,
        onSuccess: () => resetCostItemForm(),
    });
}

function editCostItem(item: CostItem): void {
    editingCostItemId.value = item.id;
    costItemForm.clearErrors();
    costItemForm.name = item.name;
    costItemForm.item_type = item.item_type;
    costItemForm.unit = item.unit;
    costItemForm.purchase_cost_sar = item.purchase_cost_sar;
    costItemForm.estimated_life_months = item.estimated_life_months;
    costItemForm.estimated_monthly_uses = item.estimated_monthly_uses;
    costItemForm.default_cost_per_use_sar = item.default_cost_per_use_sar;
    costItemForm.notes = item.notes ?? '';
    costItemForm.is_active = item.is_active;
}

function destroyCostItem(item: CostItem): void {
    if (item.service_cost_items_count > 0 || ! confirm(t('expensesAdmin.deleteCostItemConfirm', 'Delete this catalog item?'))) {
        return;
    }

    deleteForm.delete(`/app/expenses/cost-items/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingCostItemId.value === item.id) {
                resetCostItemForm();
            }
        },
    });
}

function resetCostItemForm(): void {
    editingCostItemId.value = null;
    costItemForm.clearErrors();
    costItemForm.name = '';
    costItemForm.item_type = 'material';
    costItemForm.unit = 'unit';
    costItemForm.purchase_cost_sar = '0.00';
    costItemForm.estimated_life_months = 12;
    costItemForm.estimated_monthly_uses = 30;
    costItemForm.default_cost_per_use_sar = '';
    costItemForm.notes = '';
    costItemForm.is_active = true;
}
</script>

<template>
    <Head :title="t('expensesAdmin.costCatalogTitle', 'Equipment and materials catalog')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('expensesAdmin.costCatalogTitle', 'Equipment and materials catalog') }}</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ t('expensesAdmin.costCatalogBody', 'Register machines, tools, vehicles, supplies, and materials with an estimated cost per use.') }}
                </p>
            </div>
            <div v-for="card in summaryCards" :key="card.label" class="inline-flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold" :class="card.tone">
                <Package class="h-4 w-4" />
                {{ card.label }}: {{ card.value }}
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <article class="ta-card p-5">
                <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ editingCostItemId ? t('expensesAdmin.updateCostItem', 'Update catalog item') : t('expensesAdmin.saveCostItem', 'Save catalog item') }}
                        </h2>
                        <p v-if="editingCostItemId" class="mt-1 text-sm text-brand-600">
                            {{ t('expensesAdmin.editingExistingRecord', 'Editing an existing record') }}
                        </p>
                    </div>
                    <button v-if="editingCostItemId" type="button" class="ta-btn ta-btn-secondary" @click="resetCostItemForm">
                        <X class="h-4 w-4" />
                        {{ t('expensesAdmin.cancel', 'Cancel') }}
                    </button>
                </div>

                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitCostItem">
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
                            {{ editingCostItemId ? t('expensesAdmin.updateCostItem', 'Update catalog item') : t('expensesAdmin.saveCostItem', 'Save catalog item') }}
                        </button>
                    </div>
                </form>
            </article>

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
                                <div class="mt-3 flex justify-start gap-2 sm:justify-end">
                                    <button type="button" class="ta-btn ta-btn-secondary px-3 py-2" @click="editCostItem(item)">
                                        <Pencil class="h-4 w-4" />
                                        <span class="sr-only">{{ t('expensesAdmin.edit', 'Edit') }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="ta-btn ta-btn-secondary px-3 py-2 text-error-600 disabled:text-gray-400"
                                        :disabled="item.service_cost_items_count > 0 || deleteForm.processing"
                                        :title="item.service_cost_items_count > 0 ? t('expensesAdmin.inUseCannotDelete', 'Used records cannot be deleted') : t('expensesAdmin.delete', 'Delete')"
                                        @click="destroyCostItem(item)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        <span class="sr-only">{{ t('expensesAdmin.delete', 'Delete') }}</span>
                                    </button>
                                </div>
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
        </div>
    </section>
</template>
