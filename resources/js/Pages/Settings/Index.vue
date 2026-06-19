<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    Check,
    Landmark,
    Save,
    Settings2,
} from '@lucide/vue';
import { useI18n } from '../../lib/i18n';

type SettingsPayload = {
    'company.name': string;
    'company.vat_number': string;
    'operations.default_city': string;
    'operations.working_week': string[];
    'finance.vat_rate': number;
};

const props = defineProps<{
    settings: Record<string, string | number | string[] | null>;
    schema: {
        weekdays: Array<{ value: string; label: string }>;
        vatRates: number[];
    };
}>();

const { t } = useI18n();

const form = useForm<SettingsPayload>({
    'company.name': String(props.settings['company.name'] ?? ''),
    'company.vat_number': String(props.settings['company.vat_number'] ?? ''),
    'operations.default_city': String(props.settings['operations.default_city'] ?? ''),
    'operations.working_week': Array.isArray(props.settings['operations.working_week'])
        ? props.settings['operations.working_week']
        : [],
    'finance.vat_rate': Number(props.settings['finance.vat_rate'] ?? 15),
});

function toggleWorkingDay(day: string): void {
    const selected = form['operations.working_week'];

    form['operations.working_week'] = selected.includes(day)
        ? selected.filter((value) => value !== day)
        : [...selected, day];
}

function submit(): void {
    form.post('/app/settings', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('settings.headTitle', 'Audit & Settings')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('settings.title', 'Audit & Settings') }}</h1>
                <p class="mt-1 text-sm leading-6 text-gray-500">
                    {{ t('settings.subtitle', 'Company defaults used by contracts, operations scheduling, finance, and tax invoice preparation.') }}
                </p>
            </div>

            <button
                type="button"
                class="ta-btn ta-btn-primary"
                :disabled="form.processing"
                @click="submit"
            >
                <Save class="h-4 w-4" />
                {{ t('settings.saveSettings', 'Save settings') }}
            </button>
        </div>

        <form class="grid grid-cols-12 gap-4 md:gap-6" @submit.prevent="submit">
            <article class="ta-card col-span-12 p-5 md:p-6 xl:col-span-7">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <div class="ta-icon-box">
                            <Building2 class="h-6 w-6" />
                        </div>
                        <h2 class="mt-4 text-lg font-semibold text-gray-800">{{ t('settings.companyProfile', 'Company profile') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('settings.companyProfileBody', 'Commercial identity used on quotations, contracts, receipts, and invoices.') }}</p>
                    </div>
                    <Settings2 class="mt-2 h-5 w-5 text-gray-400" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('settings.companyName', 'Company name') }}</span>
                        <input
                            v-model="form['company.name']"
                            type="text"
                            class="ta-input h-11 w-full px-4 text-sm"
                            autocomplete="organization"
                        >
                        <span v-if="form.errors['company.name']" class="mt-1.5 block text-xs font-medium text-error-600">
                            {{ form.errors['company.name'] }}
                        </span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('settings.vatNumber', 'VAT number') }}</span>
                        <input
                            v-model="form['company.vat_number']"
                            type="text"
                            class="ta-input h-11 w-full px-4 text-sm"
                            inputmode="numeric"
                        >
                        <span v-if="form.errors['company.vat_number']" class="mt-1.5 block text-xs font-medium text-error-600">
                            {{ form.errors['company.vat_number'] }}
                        </span>
                    </label>
                </div>
            </article>

            <article class="ta-card col-span-12 p-5 md:p-6 xl:col-span-5">
                <div class="mb-6">
                    <div class="ta-icon-box">
                        <Landmark class="h-6 w-6" />
                    </div>
                    <h2 class="mt-4 text-lg font-semibold text-gray-800">{{ t('settings.financeDefaults', 'Finance defaults') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('settings.financeDefaultsBody', 'VAT assumptions for new service prices and tax invoice drafts.') }}</p>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('settings.defaultVatRate', 'Default VAT rate') }}</span>
                    <select
                        v-model.number="form['finance.vat_rate']"
                        class="ta-input h-11 w-full px-4 text-sm"
                    >
                        <option v-for="rate in schema.vatRates" :key="rate" :value="rate">
                            {{ rate }}%
                        </option>
                    </select>
                    <span v-if="form.errors['finance.vat_rate']" class="mt-1.5 block text-xs font-medium text-error-600">
                        {{ form.errors['finance.vat_rate'] }}
                    </span>
                </label>

                <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm font-semibold text-gray-800">{{ t('settings.phaseTaxStance', 'Phase 1 tax stance') }}</p>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        {{ t('settings.phaseTaxBody', 'Invoices can generate Saudi VAT values and QR payloads locally while live ZATCA submission stays provider-ready.') }}
                    </p>
                </div>
            </article>

            <article class="ta-card col-span-12 p-5 md:p-6">
                <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="ta-icon-box">
                            <CalendarDays class="h-6 w-6" />
                        </div>
                        <h2 class="mt-4 text-lg font-semibold text-gray-800">{{ t('settings.operationsDefaults', 'Operations defaults') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('settings.operationsDefaultsBody', 'Used when creating customer sites, contract schedules, and recurring visit templates.') }}</p>
                    </div>

                    <label class="block w-full lg:w-72">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('settings.defaultCity', 'Default city') }}</span>
                        <input
                            v-model="form['operations.default_city']"
                            type="text"
                            class="ta-input h-11 w-full px-4 text-sm"
                        >
                        <span v-if="form.errors['operations.default_city']" class="mt-1.5 block text-xs font-medium text-error-600">
                            {{ form.errors['operations.default_city'] }}
                        </span>
                    </label>
                </div>

                <div>
                    <span class="mb-3 block text-sm font-medium text-gray-700">{{ t('settings.workingWeek', 'Working week') }}</span>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                        <button
                            v-for="day in schema.weekdays"
                            :key="day.value"
                            type="button"
                            class="flex h-12 items-center justify-between rounded-lg border px-3 text-sm font-semibold transition-colors"
                            :class="form['operations.working_week'].includes(day.value)
                                ? 'border-brand-500 bg-brand-50 text-brand-500'
                                : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                            @click="toggleWorkingDay(day.value)"
                        >
                            <span>{{ t(`settings.weekdays.${day.value}`, day.label) }}</span>
                            <Check v-if="form['operations.working_week'].includes(day.value)" class="h-4 w-4" />
                        </button>
                    </div>
                    <span v-if="form.errors['operations.working_week']" class="mt-2 block text-xs font-medium text-error-600">
                        {{ form.errors['operations.working_week'] }}
                    </span>
                </div>
            </article>
        </form>
    </section>
</template>
