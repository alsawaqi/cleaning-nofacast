<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Building2, CalendarCheck, CheckCircle2, ClipboardCheck, MapPin, ShieldCheck, Sparkles, UserPlus } from '@lucide/vue';
import { computed } from 'vue';
import PublicHeader from '../../Components/PublicHeader.vue';
import { useI18n } from '../../lib/i18n';
import type { AppSharedProps } from '../../types';

const page = usePage<AppSharedProps>();
const success = computed(() => page.props.flash?.success);
const { t } = useI18n();

const heroImage = 'https://images.unsplash.com/photo-1647381518264-97ff1835026f?auto=format&fit=crop&fm=jpg&q=70&w=2400';
const siteImage = 'https://images.unsplash.com/photo-1686178827149-6d55c72d81df?auto=format&fit=crop&fm=jpg&q=70&w=1600';
const floorImage = 'https://images.unsplash.com/photo-1740657254989-42fe9c3b8cce?auto=format&fit=crop&fm=jpg&q=70&w=1600';

const form = useForm({
    name: '',
    phone: '',
    email: '',
    service_interest: 'Monthly facility cleaning',
    city: 'Riyadh',
    notes: '',
});

const heroStyle = computed(() => ({
    backgroundImage: `linear-gradient(90deg, rgba(5, 22, 48, 0.86), rgba(5, 22, 48, 0.62), rgba(5, 22, 48, 0.18)), url('${heroImage}')`,
}));

const serviceCards = computed(() => [
    {
        title: t('publicLead.serviceFacilities', 'Facility cleaning'),
        body: t('publicLead.serviceFacilitiesBody', 'Scheduled teams for offices, clinics, shops, showrooms, and warehouses.'),
        icon: Building2,
    },
    {
        title: t('publicLead.serviceHomes', 'Home cleaning'),
        body: t('publicLead.serviceHomesBody', 'One-time deep cleaning or recurring visits for villas and apartments.'),
        icon: Sparkles,
    },
    {
        title: t('publicLead.serviceSpecialized', 'Specialized cleaning'),
        body: t('publicLead.serviceSpecializedBody', 'Floors, glass, facades, upholstery, sanitation, and post-fit-out cleaning.'),
        icon: ShieldCheck,
    },
]);

const qualityPoints = computed(() => [
    t('publicLead.qualityUniformed', 'Uniformed, trained teams'),
    t('publicLead.qualitySupervisor', 'Supervisor inspections and evidence'),
    t('publicLead.qualityReports', 'Monthly SLA and KPI review'),
    t('publicLead.qualityMaterials', 'Materials and equipment planned per site'),
]);

function submit(): void {
    form.post('/request-quotation', {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'phone', 'email', 'notes'),
    });
}

</script>

<template>
    <Head :title="t('publicLead.headTitle', 'Nofa Clean Saudi cleaning services')" />

    <main class="min-h-screen bg-white text-[#071b3d]">
        <PublicHeader />

        <section id="home" class="bg-cover bg-center" :style="heroStyle">
            <div class="mx-auto max-w-7xl px-5 py-20 text-white lg:px-8 lg:py-28">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-md bg-white/12 px-3 py-1 text-sm font-bold text-[#ffdd72] backdrop-blur">
                        {{ t('publicLead.heroBadge', 'Jordanian cleaning expertise, built for Saudi operations') }}
                    </div>
                    <h1 class="mt-6 text-4xl font-extrabold leading-tight md:text-5xl lg:text-6xl">
                        {{ t('publicLead.heroTitle', 'Nofa Clean Saudi Cleaning Services') }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-blue-50 md:text-lg">
                        {{ t('publicLead.heroBody', 'Professional cleaning, facility care, maintenance support, and recurring service contracts for homes and businesses across Saudi Arabia.') }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#quote" class="inline-flex items-center gap-2 rounded-md bg-[#ffb703] px-5 py-3 text-sm font-extrabold text-[#071b3d] hover:bg-[#ffcc38]">
                            <CalendarCheck class="h-4 w-4" />
                            {{ t('publicLead.heroPrimaryCta', 'Request a quotation') }}
                        </a>
                        <Link href="/register" class="inline-flex items-center gap-2 rounded-md bg-white px-5 py-3 text-sm font-extrabold text-[#071b3d] hover:bg-slate-100">
                            <UserPlus class="h-4 w-4" />
                            {{ t('publicLead.heroSecondaryCta', 'Create customer account') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-slate-200 bg-[#f7f9fd]">
            <div class="mx-auto grid max-w-7xl gap-4 px-5 py-6 sm:grid-cols-3 lg:px-8">
                <div class="flex items-center gap-3">
                    <CheckCircle2 class="h-5 w-5 text-[#1040b6]" />
                    <span class="text-sm font-bold">{{ t('publicLead.statContracts', 'Monthly and one-time contracts') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <ClipboardCheck class="h-5 w-5 text-[#1040b6]" />
                    <span class="text-sm font-bold">{{ t('publicLead.statProof', 'Checklist and photo proof') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <MapPin class="h-5 w-5 text-[#1040b6]" />
                    <span class="text-sm font-bold">{{ t('publicLead.statMarket', 'Focused on Saudi service standards') }}</span>
                </div>
            </div>
        </section>

        <section id="services" class="py-20 lg:py-24">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-extrabold uppercase text-[#1040b6]">{{ t('publicLead.servicesKicker', 'What we do') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-tight md:text-4xl">{{ t('publicLead.servicesTitle', 'Cleaning plans for homes, shops, offices, clinics, and facilities') }}</h2>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    <article v-for="card in serviceCards" :key="card.title" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <component :is="card.icon" class="h-8 w-8 text-[#1040b6]" />
                        <h3 class="mt-5 text-lg font-extrabold">{{ card.title }}</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ card.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="quality" class="bg-[#0c349b] py-20 text-white lg:py-24">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div>
                    <p class="text-sm font-extrabold uppercase text-[#ffdd72]">{{ t('publicLead.qualityKicker', 'SLA and KPI ready') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-tight md:text-4xl">{{ t('publicLead.qualityTitle', 'Built around scheduled visits, inspection, reporting, and accountability') }}</h2>
                    <p class="mt-5 text-base leading-8 text-blue-50">
                        {{ t('publicLead.qualityBody', 'The company profile and SLA documents focus on trained staff, clear duties, monthly performance reports, and a supervisor layer. Nofa Clean brings that method into a customer-facing Saudi workflow.') }}
                    </p>
                    <ul class="mt-7 grid gap-3">
                        <li v-for="point in qualityPoints" :key="point" class="flex items-center gap-3 text-sm font-bold text-blue-50">
                            <CheckCircle2 class="h-5 w-5 text-[#ffb703]" />
                            {{ point }}
                        </li>
                    </ul>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <img :src="siteImage" class="h-72 w-full rounded-lg object-cover shadow-2xl shadow-blue-950/25" alt="Professional cleaning team preparing a site">
                    <img :src="floorImage" class="h-72 w-full rounded-lg object-cover shadow-2xl shadow-blue-950/25 sm:mt-12" alt="Commercial floor cleaning service">
                </div>
            </div>
        </section>

        <section id="process" class="bg-[#f7f9fd] py-20 lg:py-24">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-extrabold uppercase text-[#1040b6]">{{ t('publicLead.processKicker', 'How it works') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-tight md:text-4xl">{{ t('publicLead.processTitle', 'A simple path from request to scheduled service') }}</h2>
                </div>
                <div class="mt-10 grid gap-5 md:grid-cols-4">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <span class="text-sm font-extrabold text-[#1040b6]">01</span>
                        <h3 class="mt-3 font-extrabold">{{ t('publicLead.processRequest', 'Request') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('publicLead.processRequestBody', 'Send the city, site type, and service need.') }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <span class="text-sm font-extrabold text-[#1040b6]">02</span>
                        <h3 class="mt-3 font-extrabold">{{ t('publicLead.processPackage', 'Choose') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('publicLead.processPackageBody', 'Select a package or ask for a custom plan.') }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <span class="text-sm font-extrabold text-[#1040b6]">03</span>
                        <h3 class="mt-3 font-extrabold">{{ t('publicLead.processSchedule', 'Schedule') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('publicLead.processScheduleBody', 'Operations assigns workers and visit times.') }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <span class="text-sm font-extrabold text-[#1040b6]">04</span>
                        <h3 class="mt-3 font-extrabold">{{ t('publicLead.processProof', 'Track') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('publicLead.processProofBody', 'Follow service history, invoices, and confirmations.') }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="quote" class="py-20 lg:py-24">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div class="self-center">
                    <p class="text-sm font-extrabold uppercase text-[#1040b6]">{{ t('publicLead.quoteKicker', 'Start now') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-tight md:text-4xl">{{ t('publicLead.quoteTitle', 'Request a quotation or register to manage bookings') }}</h2>
                    <p class="mt-5 text-base leading-8 text-slate-600">
                        {{ t('publicLead.quoteBody', 'Guests can send a quick quotation request. Registered customers can choose service packages, request booking dates, and later manage contracts, visits, invoices, and payments from their dashboard.') }}
                    </p>
                    <Link href="/register" class="mt-7 inline-flex items-center gap-2 rounded-md border border-[#1040b6] px-5 py-3 text-sm font-extrabold text-[#1040b6] hover:bg-[#1040b6] hover:text-white">
                        <UserPlus class="h-4 w-4" />
                        {{ t('publicLead.registerCta', 'Open customer account') }}
                    </Link>
                </div>

                <form class="rounded-lg border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/70 md:p-8" @submit.prevent="submit">
                    <h3 class="text-xl font-extrabold">{{ t('publicLead.requestQuotation', 'Request a quotation') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ t('publicLead.formIntro', "Tell us the basics and we'll contact you with the right package or contract plan.") }}
                    </p>

                    <div v-if="success" class="mt-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                        {{ success }}
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('publicLead.name', 'Name') }}
                            <input v-model="form.name" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="text">
                            <span v-if="form.errors.name" class="mt-1 block text-xs font-bold text-red-600">{{ form.errors.name }}</span>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('publicLead.phone', 'Phone') }}
                            <input v-model="form.phone" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="tel">
                            <span v-if="form.errors.phone" class="mt-1 block text-xs font-bold text-red-600">{{ form.errors.phone }}</span>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('publicLead.email', 'Email') }}
                            <input v-model="form.email" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="email">
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('publicLead.city', 'City') }}
                            <input v-model="form.city" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="text">
                        </label>
                    </div>

                    <label class="mt-4 block text-sm font-bold text-slate-700">
                        {{ t('publicLead.serviceInterest', 'Service interest') }}
                        <select v-model="form.service_interest" class="mt-2 w-full rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100">
                            <option value="Monthly facility cleaning">{{ t('publicLead.optionMonthlyFacility', 'Monthly facility cleaning') }}</option>
                            <option value="Home deep cleaning">{{ t('publicLead.optionHomeDeep', 'Home deep cleaning') }}</option>
                            <option value="Office cleaning">{{ t('publicLead.optionOffice', 'Office cleaning') }}</option>
                            <option value="Glass and facade cleaning">{{ t('publicLead.optionGlassFacade', 'Glass and facade cleaning') }}</option>
                            <option value="Custom quotation">{{ t('publicLead.optionCustom', 'Custom quotation') }}</option>
                        </select>
                    </label>

                    <label class="mt-4 block text-sm font-bold text-slate-700">
                        {{ t('publicLead.notes', 'Notes') }}
                        <textarea v-model="form.notes" class="mt-2 min-h-32 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" />
                    </label>

                    <button type="submit" class="mt-6 inline-flex items-center gap-2 rounded-md bg-[#1040b6] px-5 py-3 text-sm font-extrabold text-white hover:bg-[#0a2f95]" :disabled="form.processing">
                        <CalendarCheck class="h-4 w-4" />
                        {{ t('publicLead.sendRequest', 'Send request') }}
                    </button>
                </form>
            </div>
        </section>
    </main>
</template>
