<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleDollarSign,
    ClipboardList,
    Package,
    Pencil,
    Plus,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type { Service, ServiceCostItem, ServicePackage, ServicePricingRule } from '../../types';

type CatalogOption = {
    key: string;
    label: string;
};

type Catalog = {
    categories: CatalogOption[];
    pricingTypes: CatalogOption[];
    frequencies: CatalogOption[];
    billingCycles: CatalogOption[];
    certificates: CatalogOption[];
    materialPolicies: CatalogOption[];
    overtimePolicies: CatalogOption[];
};

const props = defineProps<{
    services: Service[];
    catalog: Catalog;
    createUrl: string;
}>();

const { t } = useI18n();

const metrics = computed(() => ({
    services: props.services.length,
    active: props.services.filter((service) => service.is_active).length,
    packages: props.services.reduce((total, service) => total + (service.packages?.length ?? 0), 0),
    rules: props.services.reduce((total, service) => total + (service.pricing_rules?.length ?? 0), 0),
}));

const metricCards = computed(() => [
    { label: t('servicesAdmin.totalServices', 'Total services'), value: metrics.value.services, icon: Wrench, tone: 'bg-brand-50 text-brand-600' },
    { label: t('servicesAdmin.activeServices', 'Active services'), value: metrics.value.active, icon: ClipboardList, tone: 'bg-success-50 text-success-600' },
    { label: t('servicesAdmin.packages', 'Packages'), value: metrics.value.packages, icon: Package, tone: 'bg-warning-50 text-warning-600' },
    { label: t('servicesAdmin.pricingRules', 'Pricing rules'), value: metrics.value.rules, icon: CircleDollarSign, tone: 'bg-error-50 text-error-600' },
]);

function catalogLabel(group: keyof Catalog, key: string): string {
    const option = props.catalog[group].find((item) => item.key === key);

    return t(`servicesAdmin.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
}

function packageSummary(packages: ServicePackage[] | undefined): ServicePackage[] {
    return (packages ?? []).slice(0, 3);
}

function ruleSummary(rules: ServicePricingRule[] | undefined): ServicePricingRule[] {
    return (rules ?? []).slice(0, 3);
}

function costItemsSummary(items: ServiceCostItem[] | undefined): ServiceCostItem[] {
    return (items ?? []).slice(0, 3);
}

function costItemTypeLabel(type?: string | null): string {
    if (! type) {
        return t('servicesAdmin.none', 'None');
    }

    return t(`expensesAdmin.itemTypes.${type}`, type.replaceAll('_', ' '));
}
</script>

<template>
    <Head :title="t('layout.services', 'Services')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('servicesAdmin.title', 'Services, packages & pricing') }}</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ t('servicesAdmin.subtitle', 'Define what the company sells, how it is priced, what workers need, and which checklist proves completion.') }}
                </p>
            </div>

            <Link :href="createUrl" class="ta-btn ta-btn-primary">
                <Plus class="h-4 w-4" />
                {{ t('servicesAdmin.newService', 'New service') }}
            </Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in metricCards" :key="card.label" class="ta-card p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ card.label }}</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ card.value }}</p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl" :class="card.tone">
                        <component :is="card.icon" class="h-6 w-6" />
                    </span>
                </div>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <article v-for="service in services" :key="service.id" class="ta-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-gray-900">{{ service.title }}</h2>
                            <StatusBadge :status="service.is_active ? 'active' : 'draft'" />
                        </div>
                        <p class="mt-1 text-sm capitalize text-gray-500">
                            {{ catalogLabel('categories', service.category) }} / {{ catalogLabel('pricingTypes', service.pricing_type) }}
                        </p>
                        <p v-if="service.description" class="mt-2 max-w-3xl text-sm leading-6 text-gray-500">{{ service.description }}</p>
                    </div>

                    <Link :href="service.edit_url ?? `/app/services/${service.id}/edit`" class="ta-btn ta-btn-secondary h-10 w-10 p-0" :aria-label="t('servicesAdmin.edit', 'Edit')">
                        <Pencil class="h-4 w-4" />
                    </Link>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('servicesAdmin.basePrice', 'Base price') }}</p>
                        <p class="mt-2 text-lg font-bold text-gray-900"><MoneyText :value="service.base_price_halalas" /></p>
                        <p class="mt-1 text-xs text-gray-500">{{ service.vat_rate }}% / {{ service.prices_include_vat ? t('servicesAdmin.vatInclusive', 'VAT inclusive') : t('servicesAdmin.vatExclusive', 'VAT exclusive') }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('servicesAdmin.workload', 'Workload') }}</p>
                        <p class="mt-2 text-sm font-semibold text-gray-900">
                            {{ service.default_workers }} {{ t('servicesAdmin.workersShort', 'workers') }} / {{ service.default_duration_minutes }} {{ t('servicesAdmin.minutesShort', 'min') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">{{ t('servicesAdmin.minimumBillableMinutes', 'Minimum billable minutes') }}: {{ service.minimum_billable_minutes }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('servicesAdmin.materials', 'Materials') }}</p>
                        <p class="mt-2 text-sm font-semibold text-gray-900">{{ catalogLabel('materialPolicies', service.material_policy) }}</p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ t('servicesAdmin.defaultMaterialCost', 'Default material cost') }}:
                            <MoneyText :value="service.default_material_cost_halalas ?? 0" />
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('servicesAdmin.certificates', 'Certificates') }}</p>
                        <p class="mt-2 text-sm font-semibold text-gray-900">
                            {{ service.required_certificates.length ? service.required_certificates.map((key) => catalogLabel('certificates', key)).join(', ') : t('servicesAdmin.none', 'None') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">{{ service.allowed_frequencies.map((key) => catalogLabel('frequencies', key)).join(', ') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-4">
                    <section>
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <Package class="h-4 w-4 text-brand-500" />
                            {{ t('servicesAdmin.packages', 'Packages') }}
                        </div>
                        <div class="space-y-2">
                            <div v-for="item in packageSummary(service.packages)" :key="item.id" class="rounded-lg bg-gray-50 px-3 py-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="font-semibold text-gray-800">{{ item.name }}</div>
                                    <MoneyText :value="item.price_halalas" class="text-sm font-semibold text-gray-700" />
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ catalogLabel('billingCycles', item.billing_cycle) }} / {{ item.worker_count }} {{ t('servicesAdmin.workersShort', 'workers') }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ item.visits_per_week ?? 1 }} {{ t('servicesAdmin.visitsShort', 'visits') }} / {{ item.hours_per_visit ?? '0.00' }}h /
                                    <MoneyText :value="item.material_cost_halalas ?? 0" />
                                </p>
                            </div>
                            <p v-if="!service.packages?.length" class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-sm text-gray-500">{{ t('servicesAdmin.noPackages', 'No packages yet.') }}</p>
                        </div>
                    </section>

                    <section>
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <CircleDollarSign class="h-4 w-4 text-brand-500" />
                            {{ t('servicesAdmin.pricingRules', 'Pricing rules') }}
                        </div>
                        <div class="space-y-2">
                            <div v-for="item in ruleSummary(service.pricing_rules)" :key="item.id" class="rounded-lg bg-gray-50 px-3 py-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="font-semibold text-gray-800">{{ item.name }}</div>
                                    <MoneyText :value="item.unit_price_halalas" class="text-sm font-semibold text-gray-700" />
                                </div>
                                <p class="mt-1 text-xs text-gray-500">{{ catalogLabel('pricingTypes', item.pricing_type) }} / {{ item.unit_label }}</p>
                            </div>
                            <p v-if="!service.pricing_rules?.length" class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-sm text-gray-500">{{ t('servicesAdmin.noRules', 'No pricing rules yet.') }}</p>
                        </div>
                    </section>

                    <section>
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <Wrench class="h-4 w-4 text-brand-500" />
                            {{ t('expensesAdmin.serviceCostBreakdownTitle', 'Service costs') }}
                        </div>
                        <div class="space-y-2">
                            <div v-for="item in costItemsSummary(service.cost_items)" :key="item.id" class="rounded-lg bg-gray-50 px-3 py-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="font-semibold text-gray-800">{{ item.name }}</div>
                                    <MoneyText :value="item.line_total_halalas" class="text-sm font-semibold text-gray-700" />
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ costItemTypeLabel(item.item_type) }} / {{ item.quantity }} {{ item.unit }} / <MoneyText :value="item.cost_per_use_halalas" />
                                </p>
                            </div>
                            <div v-if="service.cost_breakdown?.items_count" class="rounded-lg border border-success-500/20 bg-success-50 px-3 py-2">
                                <div class="flex items-center justify-between gap-3 text-xs font-semibold text-success-700">
                                    <span>{{ t('expensesAdmin.grossProfitShort', 'Gross profit') }}</span>
                                    <MoneyText :value="service.cost_breakdown.gross_profit_estimate_halalas" />
                                </div>
                            </div>
                            <p v-if="!service.cost_items?.length" class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-sm text-gray-500">{{ t('expensesAdmin.noCostItems', 'No cost items linked yet.') }}</p>
                        </div>
                    </section>

                    <section>
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <ClipboardList class="h-4 w-4 text-brand-500" />
                            {{ t('servicesAdmin.checklist', 'Checklist') }}
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="item in service.checklist_template.slice(0, 8)"
                                :key="item.label"
                                class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600"
                            >
                                {{ item.label }}
                            </span>
                            <span v-if="service.checklist_template.length === 0" class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-500">
                                {{ t('servicesAdmin.noChecklist', 'No checklist') }}
                            </span>
                        </div>
                    </section>
                </div>
            </article>

            <article v-if="services.length === 0" class="ta-card px-6 py-14 text-center xl:col-span-2">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-500">
                    <Wrench class="h-6 w-6" />
                </div>
                <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ t('servicesAdmin.emptyTitle', 'No services yet') }}</h2>
            </article>
        </div>
    </section>
</template>
