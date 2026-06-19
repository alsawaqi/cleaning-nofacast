<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    ClipboardList,
    Languages,
    MapPin,
    Pencil,
    Plus,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type { Customer } from '../../types';

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

const props = defineProps<{
    customers: Customer[];
    catalog: Catalog;
    metrics: {
        total: number;
        active: number;
        prospects: number;
        sites: number;
    };
    createUrl: string;
}>();

const { t } = useI18n();

const metricCards = computed(() => [
    { label: t('customersAdmin.totalCustomers', 'Total customers'), value: props.metrics.total, icon: UsersRound, tone: 'bg-brand-50 text-brand-600' },
    { label: t('customersAdmin.activeCustomers', 'Active customers'), value: props.metrics.active, icon: Building2, tone: 'bg-success-50 text-success-600' },
    { label: t('customersAdmin.prospects', 'Prospects'), value: props.metrics.prospects, icon: ClipboardList, tone: 'bg-warning-50 text-warning-600' },
    { label: t('customersAdmin.sites', 'Sites'), value: props.metrics.sites, icon: MapPin, tone: 'bg-error-50 text-error-600' },
]);

function catalogLabel(group: keyof Catalog, key: string): string {
    const option = props.catalog[group].find((item) => item.key === key);

    return t(`customersAdmin.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
}
</script>

<template>
    <Head :title="t('layout.customers', 'Customers')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('customersAdmin.title', 'Customers & sites') }}</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ t('customersAdmin.subtitle', 'Manage company and individual customers, branch contacts, VAT data, and site locations for future contracts.') }}
                </p>
            </div>

            <Link :href="createUrl" class="ta-btn ta-btn-primary">
                <Plus class="h-4 w-4" />
                {{ t('customersAdmin.newCustomer', 'New customer') }}
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
            <article v-for="customer in customers" :key="customer.id" class="ta-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-gray-900">{{ customer.name }}</h2>
                            <StatusBadge :status="customer.status" />
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ catalogLabel('customerTypes', customer.customer_type) }} / {{ customer.phone || t('customersAdmin.noPhone', 'No phone') }}
                        </p>
                        <p class="mt-2 text-sm text-gray-500">{{ customer.email || t('customersAdmin.noEmail', 'No email') }}</p>
                    </div>

                    <Link :href="customer.edit_url ?? `/app/customers/${customer.id}/edit`" class="ta-btn ta-btn-secondary h-10 w-10 p-0" :aria-label="t('customersAdmin.edit', 'Edit')">
                        <Pencil class="h-4 w-4" />
                    </Link>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('customersAdmin.sites', 'Sites') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ customer.sites_count ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('customersAdmin.contracts', 'Contracts') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ customer.contracts_count ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('customersAdmin.invoices', 'Invoices') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ customer.invoices_count ?? 0 }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 lg:grid-cols-2">
                    <section class="rounded-xl bg-gray-50 p-4">
                        <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <Languages class="h-4 w-4 text-brand-500" />
                            {{ t('customersAdmin.preferences', 'Preferences') }}
                        </div>
                        <p class="text-sm text-gray-600">
                            {{ catalogLabel('channels', customer.preferred_channel) }} / {{ catalogLabel('locales', customer.preferred_locale) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">{{ customer.vat_number || t('customersAdmin.noVat', 'No VAT number') }}</p>
                    </section>

                    <section class="rounded-xl bg-gray-50 p-4">
                        <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <MapPin class="h-4 w-4 text-brand-500" />
                            {{ t('customersAdmin.primarySites', 'Sites') }}
                        </div>
                        <div class="space-y-2">
                            <div v-for="site in (customer.sites ?? []).slice(0, 3)" :key="site.id" class="rounded-lg bg-white px-3 py-2">
                                <div class="text-sm font-semibold text-gray-800">{{ site.name }}</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ site.city }}<span v-if="site.district"> / {{ site.district }}</span>
                                </div>
                            </div>
                            <p v-if="!customer.sites?.length" class="text-sm text-gray-500">{{ t('customersAdmin.noSites', 'No sites yet.') }}</p>
                        </div>
                    </section>
                </div>
            </article>

            <article v-if="customers.length === 0" class="ta-card px-6 py-14 text-center xl:col-span-2">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-500">
                    <UsersRound class="h-6 w-6" />
                </div>
                <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ t('customersAdmin.emptyTitle', 'No customers yet') }}</h2>
            </article>
        </div>
    </section>
</template>
