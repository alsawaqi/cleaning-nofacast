<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { MessageCircle, Receipt, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '../../lib/i18n';

const page = usePage();
const success = computed(() => (page.props.flash as { success?: string | null }).success);
const { t } = useI18n();

const serviceOptions = [
    { value: 'Monthly cleaning contract', labelKey: 'monthly_cleaning_contract' },
    { value: 'Home deep cleaning', labelKey: 'home_deep_cleaning' },
    { value: 'Office cleaning', labelKey: 'office_cleaning' },
    { value: 'Glass facade cleaning', labelKey: 'glass_facade_cleaning' },
    { value: 'Custom quotation', labelKey: 'custom_quotation' },
];

const form = useForm({
    name: '',
    phone: '',
    email: '',
    service_interest: 'Monthly cleaning contract',
    city: 'Riyadh',
    notes: '',
});

function submit(): void {
    form.post('/request-quotation', {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'phone', 'email', 'notes'),
    });
}
</script>

<template>
    <Head :title="t('publicLead.headTitle', 'Request quotation')" />

    <main class="min-h-screen bg-white text-slate-950">
        <section class="mx-auto grid min-h-screen max-w-7xl gap-10 px-5 py-8 lg:grid-cols-[0.9fr_1fr] lg:px-8">
            <div class="flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="text-lg font-bold text-teal-800">{{ t('publicLead.brand', 'Nofacast Clean') }}</div>
                    <a href="/login" class="rounded-md border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        {{ t('publicLead.staffLogin', 'Staff login') }}
                    </a>
                </div>

                <div class="py-16">
                    <h1 class="max-w-xl text-4xl font-bold leading-tight tracking-normal md:text-5xl">
                        {{ t('publicLead.heroTitle', 'Professional cleaning contracts and one-time visits in Saudi Arabia.') }}
                    </h1>
                    <p class="mt-5 max-w-xl text-base leading-7 text-slate-600">
                        {{ t('publicLead.heroBody', 'Send your location, site type, and service needs. The operations team will turn it into a quotation or monthly contract inside the system.') }}
                    </p>
                    <div class="mt-8 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <MessageCircle class="h-5 w-5 text-teal-700" />
                            <div class="mt-3 text-sm font-bold">{{ t('publicLead.whatsappFirst', 'WhatsApp first') }}</div>
                            <div class="mt-1 text-xs leading-5 text-slate-500">{{ t('publicLead.whatsappFirstBody', 'Fast confirmations and service updates.') }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-4">
                            <ShieldCheck class="h-5 w-5 text-teal-700" />
                            <div class="mt-3 text-sm font-bold">{{ t('publicLead.qualityProof', 'Quality proof') }}</div>
                            <div class="mt-1 text-xs leading-5 text-slate-500">{{ t('publicLead.qualityProofBody', 'Attendance and checklist records.') }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-4">
                            <Receipt class="h-5 w-5 text-teal-700" />
                            <div class="mt-3 text-sm font-bold">{{ t('publicLead.vatReady', 'VAT ready') }}</div>
                            <div class="mt-1 text-xs leading-5 text-slate-500">{{ t('publicLead.vatReadyBody', 'Saudi e-invoice data prepared.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <form class="panel self-center rounded-lg p-6 md:p-8" @submit.prevent="submit">
                <h2 class="text-xl font-bold text-slate-950">{{ t('publicLead.requestQuotation', 'Request a quotation') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ t('publicLead.formIntro', "Tell us the basics and we'll contact you with the right package or contract plan.") }}
                </p>

                <div v-if="success" class="mt-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {{ success }}
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-semibold text-slate-700">
                        {{ t('publicLead.name', 'Name') }}
                        <input v-model="form.name" class="focus-ring mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm" type="text">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">
                        {{ t('publicLead.phone', 'Phone') }}
                        <input v-model="form.phone" class="focus-ring mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm" type="tel">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">
                        {{ t('publicLead.email', 'Email') }}
                        <input v-model="form.email" class="focus-ring mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm" type="email">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">
                        {{ t('publicLead.city', 'City') }}
                        <input v-model="form.city" class="focus-ring mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm" type="text">
                    </label>
                </div>

                <label class="mt-4 block text-sm font-semibold text-slate-700">
                    {{ t('publicLead.serviceInterest', 'Service interest') }}
                    <select v-model="form.service_interest" class="focus-ring mt-2 w-full rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm">
                        <option v-for="option in serviceOptions" :key="option.value" :value="option.value">
                            {{ t(`publicLead.serviceOptions.${option.labelKey}`, option.value) }}
                        </option>
                    </select>
                </label>

                <label class="mt-4 block text-sm font-semibold text-slate-700">
                    {{ t('publicLead.notes', 'Notes') }}
                    <textarea v-model="form.notes" class="focus-ring mt-2 min-h-32 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm" />
                </label>

                <button type="submit" class="focus-ring mt-6 rounded-md bg-teal-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-teal-800" :disabled="form.processing">
                    {{ t('publicLead.sendRequest', 'Send request') }}
                </button>
            </form>
        </section>
    </main>
</template>
