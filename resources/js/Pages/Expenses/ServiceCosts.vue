<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CircleDollarSign, Pencil, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import { useI18n } from '../../lib/i18n';
import type { CostItem, Option, ServiceCostBreakdown, ServiceCostBreakdownItem, ServiceOption } from './types';

const props = defineProps<{
    costItems: CostItem[];
    serviceOptions: ServiceOption[];
    serviceCostBreakdowns: ServiceCostBreakdown[];
    itemTypes: Option[];
}>();

const { t } = useI18n();
const editingServiceCostItemId = ref<number | null>(null);

const serviceCostForm = useForm({
    service_id: defaultServiceId(),
    cost_item_id: defaultCostItemId(),
    quantity: '1.00',
    cost_per_use_sar: defaultCostPerUse(),
    charge_customer: false,
    notes: '',
});
const deleteForm = useForm({});

const selectedCostItem = computed(() => props.costItems.find((item) => String(item.id) === String(serviceCostForm.cost_item_id)));

function itemTypeLabel(type?: string | null): string {
    if (! type) {
        return t('servicesAdmin.none', 'None');
    }

    return t(`expensesAdmin.itemTypes.${type}`, type.replaceAll('_', ' '));
}

function applyCostItemDefault(): void {
    if (selectedCostItem.value) {
        serviceCostForm.cost_per_use_sar = selectedCostItem.value.default_cost_per_use_sar;
    }
}

function submitServiceCostItem(): void {
    if (editingServiceCostItemId.value) {
        serviceCostForm.patch(`/app/expenses/service-cost-items/${editingServiceCostItemId.value}`, {
            preserveScroll: true,
            onSuccess: () => resetServiceCostForm(),
        });

        return;
    }

    serviceCostForm.post('/app/expenses/service-cost-items', {
        preserveScroll: true,
        onSuccess: () => resetServiceCostForm(),
    });
}

function editServiceCostItem(breakdown: ServiceCostBreakdown, item: ServiceCostBreakdownItem): void {
    editingServiceCostItemId.value = item.id;
    serviceCostForm.clearErrors();
    serviceCostForm.service_id = String(breakdown.service.id);
    serviceCostForm.cost_item_id = String(item.cost_item_id);
    serviceCostForm.quantity = String(item.quantity);
    serviceCostForm.cost_per_use_sar = item.cost_per_use_sar;
    serviceCostForm.charge_customer = item.charge_customer;
    serviceCostForm.notes = item.notes ?? '';
}

function destroyServiceCostItem(item: ServiceCostBreakdownItem): void {
    if (! confirm(t('expensesAdmin.deleteServiceCostConfirm', 'Delete this service cost link?'))) {
        return;
    }

    deleteForm.delete(`/app/expenses/service-cost-items/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingServiceCostItemId.value === item.id) {
                resetServiceCostForm();
            }
        },
    });
}

function resetServiceCostForm(): void {
    editingServiceCostItemId.value = null;
    serviceCostForm.clearErrors();
    serviceCostForm.service_id = defaultServiceId();
    serviceCostForm.cost_item_id = defaultCostItemId();
    serviceCostForm.quantity = '1.00';
    serviceCostForm.cost_per_use_sar = defaultCostPerUse();
    serviceCostForm.charge_customer = false;
    serviceCostForm.notes = '';
}

function defaultServiceId(): string {
    return props.serviceOptions[0] ? String(props.serviceOptions[0].id) : '';
}

function defaultCostItemId(): string {
    return props.costItems[0] ? String(props.costItems[0].id) : '';
}

function defaultCostPerUse(): string {
    return props.costItems.find((item) => String(item.id) === defaultCostItemId())?.default_cost_per_use_sar ?? '0.00';
}
</script>

<template>
    <Head :title="t('expensesAdmin.serviceCostBreakdownTitle', 'Service cost breakdown')" />

    <section class="space-y-6">
        <div>
            <h1 class="ta-page-title">{{ t('expensesAdmin.linkServiceCostTitle', 'Link costs to services') }}</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                {{ t('expensesAdmin.linkServiceCostBody', 'Attach equipment or material usage to a service so every quotation and report can estimate real margin.') }}
            </p>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
            <article class="ta-card p-5">
                <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ editingServiceCostItemId ? t('expensesAdmin.updateServiceCostItem', 'Update service cost') : t('expensesAdmin.linkCostItem', 'Link cost item') }}
                        </h2>
                        <p v-if="editingServiceCostItemId" class="mt-1 text-sm text-brand-600">
                            {{ t('expensesAdmin.editingExistingRecord', 'Editing an existing record') }}
                        </p>
                    </div>
                    <button v-if="editingServiceCostItemId" type="button" class="ta-btn ta-btn-secondary" @click="resetServiceCostForm">
                        <X class="h-4 w-4" />
                        {{ t('expensesAdmin.cancel', 'Cancel') }}
                    </button>
                </div>

                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitServiceCostItem">
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
                            {{ editingServiceCostItemId ? t('expensesAdmin.updateServiceCostItem', 'Update service cost') : t('expensesAdmin.linkCostItem', 'Link cost item') }}
                        </button>
                    </div>
                </form>

                <div class="mt-5 rounded-2xl border border-brand-500/15 bg-brand-50 px-4 py-3 text-sm text-brand-700">
                    <p class="font-semibold">{{ t('expensesAdmin.costLogicTitle', 'Cost logic') }}</p>
                    <p class="mt-1 leading-6">{{ t('expensesAdmin.costLogicBody', 'Purchase expenses track cash spent. Service cost links estimate the cost consumed each time that service is sold or performed.') }}</p>
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
                                        <th class="px-2 py-2 text-start">{{ t('expensesAdmin.itemType', 'Item type') }}</th>
                                        <th class="px-2 py-2 text-start">{{ t('expensesAdmin.quantity', 'Quantity') }}</th>
                                        <th class="px-2 py-2 text-start">{{ t('expensesAdmin.costPerUseShort', 'Cost/use') }}</th>
                                        <th class="px-2 py-2 text-start">{{ t('expensesAdmin.total', 'Total') }}</th>
                                        <th class="px-2 py-2 text-end">{{ t('expensesAdmin.actions', 'Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="item in breakdown.items" :key="item.id">
                                        <td class="px-2 py-2 font-medium text-gray-800">{{ item.name }}</td>
                                        <td class="px-2 py-2 text-gray-600">{{ itemTypeLabel(item.item_type) }}</td>
                                        <td class="px-2 py-2 text-gray-600">{{ item.quantity }} {{ item.unit }}</td>
                                        <td class="px-2 py-2 text-gray-600"><MoneyText :value="item.cost_per_use_halalas" /></td>
                                        <td class="px-2 py-2 font-semibold text-gray-900"><MoneyText :value="item.line_total_halalas" /></td>
                                        <td class="px-2 py-2">
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="ta-btn ta-btn-secondary px-3 py-2" @click="editServiceCostItem(breakdown, item)">
                                                    <Pencil class="h-4 w-4" />
                                                    <span class="sr-only">{{ t('expensesAdmin.edit', 'Edit') }}</span>
                                                </button>
                                                <button type="button" class="ta-btn ta-btn-secondary px-3 py-2 text-error-600" :disabled="deleteForm.processing" @click="destroyServiceCostItem(item)">
                                                    <Trash2 class="h-4 w-4" />
                                                    <span class="sr-only">{{ t('expensesAdmin.delete', 'Delete') }}</span>
                                                </button>
                                            </div>
                                        </td>
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
    </section>
</template>
