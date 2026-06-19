<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BadgePercent,
    BriefcaseBusiness,
    CalendarDays,
    ClipboardList,
    FilePlus2,
    Pencil,
    Plus,
    ReceiptText,
} from '@lucide/vue';
import { computed } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type { Contract } from '../../types';

type CatalogOption = {
    key: string;
    label: string;
};

type Catalog = {
    statuses: CatalogOption[];
    billingCycles: CatalogOption[];
    pricingModels: CatalogOption[];
    materialPolicies: CatalogOption[];
};

const props = defineProps<{
    contracts: Contract[];
    catalog: Catalog;
    metrics: {
        total: number;
        active: number;
        draft: number;
        monthlyValue: number;
    };
    createUrl: string;
}>();

const { t } = useI18n();

const metricCards = computed(() => [
    { label: t('contractsAdmin.totalContracts', 'Total contracts'), value: props.metrics.total, icon: BriefcaseBusiness, tone: 'bg-brand-50 text-brand-600' },
    { label: t('contractsAdmin.activeContracts', 'Active contracts'), value: props.metrics.active, icon: CalendarDays, tone: 'bg-success-50 text-success-600' },
    { label: t('contractsAdmin.draftContracts', 'Draft contracts'), value: props.metrics.draft, icon: ClipboardList, tone: 'bg-warning-50 text-warning-600' },
    { label: t('contractsAdmin.monthlyValue', 'Monthly value'), value: props.metrics.monthlyValue, icon: ReceiptText, tone: 'bg-error-50 text-error-600', money: true },
]);

function catalogLabel(group: keyof Catalog, key: string): string {
    const option = props.catalog[group].find((item) => item.key === key);

    return t(`contractsAdmin.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
}
</script>

<template>
    <Head :title="t('layout.contracts', 'Contracts')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('contractsAdmin.title', 'Contracts & payment plans') }}</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ t('contractsAdmin.subtitle', 'Create contract terms, select the customer site and package, track payment cadence, and record addendums.') }}
                </p>
            </div>

            <Link :href="createUrl" class="ta-btn ta-btn-primary">
                <Plus class="h-4 w-4" />
                {{ t('contractsAdmin.newContract', 'New contract') }}
            </Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in metricCards" :key="card.label" class="ta-card p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ card.label }}</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">
                            <MoneyText v-if="card.money" :value="card.value" />
                            <span v-else>{{ card.value }}</span>
                        </p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl" :class="card.tone">
                        <component :is="card.icon" class="h-6 w-6" />
                    </span>
                </div>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <article v-for="contract in contracts" :key="contract.id" class="ta-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-gray-900">{{ contract.reference }}</h2>
                            <StatusBadge :status="contract.status" />
                        </div>
                        <p class="mt-1 text-sm text-gray-500">{{ contract.customer.name }} / {{ contract.site.name }}</p>
                        <p class="mt-2 text-sm text-gray-500">{{ contract.service?.title ?? t('contractsAdmin.service', 'Service') }} / {{ contract.service_package?.name ?? t('contractsAdmin.package', 'Package') }}</p>
                    </div>

                    <Link :href="contract.edit_url ?? `/app/contracts/${contract.id}/edit`" class="ta-btn ta-btn-secondary h-10 w-10 p-0" :aria-label="t('contractsAdmin.edit', 'Edit')">
                        <Pencil class="h-4 w-4" />
                    </Link>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.monthlyFee', 'Monthly fee') }}</p>
                        <p class="mt-2 text-sm font-bold text-gray-900"><MoneyText :value="contract.monthly_fee_halalas" /></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.vat', 'VAT') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ contract.vat_rate }}%</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.notice', 'Notice') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ contract.notice_days }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.workload', 'Workload') }}</p>
                        <p class="mt-2 text-sm font-bold text-gray-900">{{ contract.agreed_workers }} / {{ contract.planned_weekly_minutes }}m</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('contractsAdmin.invoices', 'Invoices') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ contract.invoices_count ?? 0 }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-4">
                    <section class="rounded-xl bg-gray-50 p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <ClipboardList class="h-4 w-4 text-brand-500" />
                            {{ t('contractsAdmin.pricingScope', 'Pricing & scope') }}
                        </div>
                        <div class="space-y-2 text-sm text-gray-600">
                            <p>{{ catalogLabel('pricingModels', contract.pricing_model) }}</p>
                            <p>{{ contract.visits_per_week ?? 0 }} {{ t('contractsAdmin.visitsShort', 'visits') }} / {{ contract.hours_per_visit ?? '0.00' }}h</p>
                            <p>{{ catalogLabel('materialPolicies', contract.material_policy) }}</p>
                            <p>
                                {{ t('contractsAdmin.materialCost', 'Material cost') }}:
                                <MoneyText :value="contract.estimated_material_cost_halalas ?? 0" />
                            </p>
                        </div>
                    </section>

                    <section class="rounded-xl bg-gray-50 p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <BadgePercent class="h-4 w-4 text-brand-500" />
                            {{ t('contractsAdmin.paymentPlan', 'Payment plan') }}
                        </div>
                        <div class="space-y-2">
                            <div v-for="item in contract.payment_plan" :key="`${item.label}-${item.day}`" class="rounded-lg bg-white px-3 py-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-800">{{ item.label }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ t('contractsAdmin.day', 'Day') }} {{ item.day }} / {{ item.percent }}%</div>
                                    </div>
                                    <MoneyText :value="item.amount_halalas ?? 0" class="text-sm font-semibold text-gray-700" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl bg-gray-50 p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <FilePlus2 class="h-4 w-4 text-brand-500" />
                            {{ t('contractsAdmin.addendums', 'Addendums') }}
                        </div>
                        <div class="space-y-2">
                            <div v-for="item in contract.addendums.slice(0, 4)" :key="item.id" class="rounded-lg bg-white px-3 py-2">
                                <div class="text-sm font-semibold text-gray-800">{{ item.number }}. {{ item.title }}</div>
                                <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ item.summary }}</p>
                            </div>
                            <p v-if="contract.addendums.length === 0" class="text-sm text-gray-500">{{ t('contractsAdmin.noAddendums', 'No addendums yet.') }}</p>
                        </div>
                    </section>

                    <section class="rounded-xl bg-gray-50 p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <CalendarDays class="h-4 w-4 text-brand-500" />
                            {{ t('contractsAdmin.term', 'Term') }}
                        </div>
                        <p class="text-sm text-gray-600">{{ contract.starts_on }} - {{ contract.ends_on ?? t('contractsAdmin.openEnded', 'Open ended') }}</p>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ catalogLabel('billingCycles', contract.billing_cycle) }} / {{ contract.auto_renews ? t('contractsAdmin.autoRenews', 'Auto renews') : t('contractsAdmin.noAutoRenewal', 'No auto renewal') }}
                        </p>
                        <p v-if="contract.special_terms" class="mt-3 rounded-lg bg-white px-3 py-2 text-xs leading-5 text-gray-500">{{ contract.special_terms }}</p>
                    </section>
                </div>
            </article>

            <article v-if="contracts.length === 0" class="ta-card px-6 py-14 text-center xl:col-span-2">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-500">
                    <BriefcaseBusiness class="h-6 w-6" />
                </div>
                <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ t('contractsAdmin.emptyTitle', 'No contracts yet') }}</h2>
            </article>
        </div>
    </section>
</template>
