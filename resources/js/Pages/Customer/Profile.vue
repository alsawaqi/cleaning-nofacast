<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, BellRing, CheckCircle2, Languages, LocateFixed, LogOut, MapPin, Pencil, Plus, Save, Sparkles, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import GoogleMapPicker from '../../Components/GoogleMapPicker.vue';
import { useI18n } from '../../lib/i18n';
import type { AppSharedProps } from '../../types';

type CatalogOption = {
    key: string;
    label: string;
};

type Catalog = {
    customerTypes: CatalogOption[];
    channels: CatalogOption[];
    locales: CatalogOption[];
};

type PortalSite = {
    id: number;
    country_code?: string | null;
    name: string;
    city: string;
    district?: string | null;
    address?: string | null;
    latitude?: string | number | null;
    longitude?: string | number | null;
    google_place_id?: string | null;
    formatted_address?: string | null;
    is_default: boolean;
    contact_name?: string | null;
    contact_phone?: string | null;
};

type PortalCustomer = {
    id: number;
    name: string;
    customer_type: string;
    phone?: string | null;
    email?: string | null;
    preferred_channel: string;
    preferred_locale: string;
    vat_number?: string | null;
    status: string;
    sites: PortalSite[];
};

type SiteForm = {
    name: string;
    country_code: string;
    city: string;
    district: string;
    address: string;
    latitude: string;
    longitude: string;
    google_place_id: string;
    formatted_address: string;
    contact_name: string;
    contact_phone: string;
    is_default: boolean;
};

const props = defineProps<{
    customer: PortalCustomer;
    catalog: Catalog;
    backUrl: string;
}>();

const page = usePage<AppSharedProps>();
const { locale, t } = useI18n();
const success = computed(() => page.props.flash?.success);
const queryTab = new URLSearchParams(page.url.split('?')[1] ?? '').get('tab');
const activeTab = ref<'profile' | 'sites'>(queryTab === 'sites' ? 'sites' : 'profile');
const siteMode = ref<'list' | 'create' | 'edit'>('list');
const editingSiteId = ref<number | null>(null);
const coordinateFocusRequest = ref(0);
const areaFocusRequest = ref(0);

const profileForm = useForm({
    customer_type: props.customer.customer_type,
    name: props.customer.name,
    phone: props.customer.phone ?? '',
    email: props.customer.email ?? '',
    preferred_channel: props.customer.preferred_channel,
    preferred_locale: props.customer.preferred_locale,
    vat_number: props.customer.vat_number ?? '',
});

function blankSite(): SiteForm {
    return {
        name: '',
        country_code: 'SA',
        city: 'Riyadh',
        district: '',
        address: '',
        latitude: '',
        longitude: '',
        google_place_id: '',
        formatted_address: '',
        contact_name: props.customer.name,
        contact_phone: props.customer.phone ?? '',
        is_default: props.customer.sites.length === 0,
    };
}

const siteForm = useForm<SiteForm>(blankSite());

function setSiteForm(site: SiteForm): void {
    siteForm.name = site.name;
    siteForm.country_code = site.country_code;
    siteForm.city = site.city;
    siteForm.district = site.district;
    siteForm.address = site.address;
    siteForm.latitude = site.latitude;
    siteForm.longitude = site.longitude;
    siteForm.google_place_id = site.google_place_id;
    siteForm.formatted_address = site.formatted_address;
    siteForm.contact_name = site.contact_name;
    siteForm.contact_phone = site.contact_phone;
    siteForm.is_default = site.is_default;
}

function siteToForm(site: PortalSite): SiteForm {
    return {
        name: site.name,
        country_code: site.country_code ?? 'SA',
        city: site.city,
        district: site.district ?? '',
        address: site.address ?? '',
        latitude: site.latitude === null || site.latitude === undefined ? '' : String(site.latitude),
        longitude: site.longitude === null || site.longitude === undefined ? '' : String(site.longitude),
        google_place_id: site.google_place_id ?? '',
        formatted_address: site.formatted_address ?? '',
        contact_name: site.contact_name ?? '',
        contact_phone: site.contact_phone ?? '',
        is_default: site.is_default,
    };
}

function changeLocale(): void {
    router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveScroll: true });
}

function logout(): void {
    router.post('/logout');
}

function catalogLabel(group: keyof Catalog, key: string): string {
    const option = props.catalog[group].find((item) => item.key === key);

    return t(`customerPortal.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
}

function openCreateSite(): void {
    editingSiteId.value = null;
    setSiteForm(blankSite());
    siteForm.clearErrors();
    siteMode.value = 'create';
}

function openEditSite(site: PortalSite): void {
    editingSiteId.value = site.id;
    setSiteForm(siteToForm(site));
    siteForm.clearErrors();
    siteMode.value = 'edit';
}

function cancelSiteForm(): void {
    siteMode.value = 'list';
    editingSiteId.value = null;
    siteForm.clearErrors();
}

function submitProfile(): void {
    profileForm.patch('/app/customer/profile', {
        preserveScroll: true,
    });
}

function submitSite(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            cancelSiteForm();
        },
    };

    if (siteMode.value === 'edit' && editingSiteId.value) {
        siteForm.patch(`/app/customer/sites/${editingSiteId.value}`, options);
        return;
    }

    siteForm.post('/app/customer/sites', options);
}

function setDefaultSite(site: PortalSite): void {
    router.patch(`/app/customer/sites/${site.id}/default`, {}, { preserveScroll: true });
}

function focusCoordinates(): void {
    coordinateFocusRequest.value += 1;
}

function focusArea(): void {
    areaFocusRequest.value += 1;
}
</script>

<template>
    <Head :title="t('customerPortal.profileHeadTitle', 'Profile and sites')" />

    <main class="min-h-screen bg-[#f7f9fd] text-[#071b3d]">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4 lg:px-8">
                <Link href="/" class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-lg bg-[#1040b6] text-[#ffb703] shadow-lg shadow-blue-900/15">
                        <Sparkles class="h-6 w-6" />
                    </span>
                    <span>
                        <span class="block text-xl font-extrabold leading-none">{{ t('publicLead.brand', 'Nofa Clean') }}</span>
                        <span class="mt-1 block text-xs font-bold uppercase text-slate-500">{{ t('customerPortal.portal', 'Customer portal') }}</span>
                    </span>
                </Link>
                <div class="flex items-center gap-2">
                    <Link href="/app/customer/notifications" :aria-label="t('customerPortal.notifications.link', 'Notifications')" :title="t('customerPortal.notifications.link', 'Notifications')" class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50">
                        <BellRing class="h-4 w-4" />
                    </Link>
                    <button type="button" class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50" @click="changeLocale">
                        <Languages class="h-4 w-4" />
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50" @click="logout">
                        <LogOut class="h-4 w-4" />
                        {{ t('customerPortal.logout', 'Logout') }}
                    </button>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-7xl px-5 py-8 lg:px-8">
            <Link :href="backUrl" class="inline-flex items-center gap-2 text-sm font-extrabold text-[#1040b6]">
                <ArrowLeft class="h-4 w-4 rtl:rotate-180" />
                {{ t('customerPortal.backToDashboard', 'Back to dashboard') }}
            </Link>

            <div class="mt-5 rounded-lg bg-[#071b3d] p-6 text-white lg:p-8">
                <p class="text-sm font-extrabold uppercase text-[#ffdd72]">{{ t('customerPortal.profileAndSites', 'Profile and sites') }}</p>
                <h1 class="mt-3 text-3xl font-extrabold md:text-4xl">{{ customer.name }}</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">
                    {{ t('customerPortal.profileAndSitesBody', 'Keep contact details and saved service locations ready before booking new cleaning work.') }}
                </p>
            </div>

            <div v-if="success" class="mt-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ success }}
            </div>

            <div class="mt-6 flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                <button type="button" class="rounded-md px-4 py-2 text-sm font-extrabold" :class="activeTab === 'profile' ? 'bg-[#1040b6] text-white' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'profile'">
                    {{ t('customerPortal.profileTab', 'Profile') }}
                </button>
                <button type="button" class="rounded-md px-4 py-2 text-sm font-extrabold" :class="activeTab === 'sites' ? 'bg-[#1040b6] text-white' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'sites'">
                    {{ t('customerPortal.sitesTab', 'Saved sites') }}
                </button>
            </div>

            <section v-if="activeTab === 'profile'" class="mt-6 grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
                <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-lg bg-blue-50 text-[#1040b6]">
                            <UserRound class="h-5 w-5" />
                        </span>
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.profileDetails', 'Profile details') }}</h2>
                    </div>
                    <dl class="mt-5 grid gap-4 text-sm">
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.email', 'Email') }}</dt>
                            <dd class="mt-1 font-bold">{{ customer.email ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.phone', 'Phone') }}</dt>
                            <dd class="mt-1 font-bold">{{ customer.phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-500">{{ t('customerPortal.status', 'Status') }}</dt>
                            <dd class="mt-1 font-bold">{{ customer.status }}</dd>
                        </div>
                    </dl>
                </article>

                <form class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submitProfile">
                    <h2 class="text-xl font-extrabold">{{ t('customerPortal.updateProfile', 'Update profile') }}</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-bold text-slate-600">
                            {{ t('customerPortal.customerType', 'Customer type') }}
                            <select v-model="profileForm.customer_type" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                <option v-for="option in catalog.customerTypes" :key="option.key" :value="option.key">{{ catalogLabel('customerTypes', option.key) }}</option>
                            </select>
                        </label>
                        <label class="text-sm font-bold text-slate-600">
                            {{ t('customerPortal.fullName', 'Full name') }}
                            <input v-model="profileForm.name" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                        </label>
                        <label class="text-sm font-bold text-slate-600">
                            {{ t('customerPortal.email', 'Email') }}
                            <input v-model="profileForm.email" type="email" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                        </label>
                        <label class="text-sm font-bold text-slate-600">
                            {{ t('customerPortal.phone', 'Phone') }}
                            <input v-model="profileForm.phone" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                        </label>
                        <label class="text-sm font-bold text-slate-600">
                            {{ t('customerPortal.preferredChannel', 'Preferred channel') }}
                            <select v-model="profileForm.preferred_channel" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                <option v-for="option in catalog.channels" :key="option.key" :value="option.key">{{ catalogLabel('channels', option.key) }}</option>
                            </select>
                        </label>
                        <label class="text-sm font-bold text-slate-600">
                            {{ t('customerPortal.preferredLocale', 'Preferred language') }}
                            <select v-model="profileForm.preferred_locale" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                <option v-for="option in catalog.locales" :key="option.key" :value="option.key">{{ catalogLabel('locales', option.key) }}</option>
                            </select>
                        </label>
                        <label class="text-sm font-bold text-slate-600 md:col-span-2">
                            {{ t('customerPortal.vatNumber', 'VAT number') }}
                            <input v-model="profileForm.vat_number" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                        </label>
                    </div>
                    <button type="submit" class="mt-6 inline-flex items-center gap-2 rounded-md bg-[#1040b6] px-5 py-3 text-sm font-extrabold text-white disabled:opacity-60" :disabled="profileForm.processing">
                        <Save class="h-4 w-4" />
                        {{ t('customerPortal.saveProfile', 'Save profile') }}
                    </button>
                </form>
            </section>

            <section v-if="activeTab === 'sites'" class="mt-6">
                <div v-if="siteMode === 'list'" class="space-y-5">
                    <div class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-xl font-extrabold">{{ t('customerPortal.savedSitesTitle', 'Saved sites') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('customerPortal.savedSitesBody', 'Choose from saved service locations when requesting a booking.') }}</p>
                        </div>
                        <button type="button" class="inline-flex w-fit items-center gap-2 rounded-md bg-[#1040b6] px-4 py-2 text-sm font-extrabold text-white" @click="openCreateSite">
                            <Plus class="h-4 w-4" />
                            {{ t('customerPortal.addSite', 'Add site') }}
                        </button>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <article v-for="site in customer.sites" :key="site.id" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-extrabold">{{ site.name }}</h3>
                                        <span v-if="site.is_default" class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-extrabold text-emerald-700">{{ t('customerPortal.defaultSite', 'Default') }}</span>
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ site.city }}<span v-if="site.district"> / {{ site.district }}</span></p>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ site.address ?? '-' }}</p>
                                </div>
                                <MapPin class="h-5 w-5 text-[#1040b6]" />
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50" @click="openEditSite(site)">
                                    <Pencil class="h-4 w-4" />
                                    {{ t('customerPortal.editSite', 'Edit') }}
                                </button>
                                <button v-if="!site.is_default" type="button" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50" @click="setDefaultSite(site)">
                                    <CheckCircle2 class="h-4 w-4" />
                                    {{ t('customerPortal.setDefaultSite', 'Set default') }}
                                </button>
                            </div>
                        </article>
                        <div v-if="customer.sites.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm font-bold text-slate-500 lg:col-span-2">
                            {{ t('customerPortal.noSavedSites', 'No saved sites yet.') }}
                        </div>
                    </div>
                </div>

                <form v-else class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]" @submit.prevent="submitSite">
                    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-extrabold">{{ siteMode === 'edit' ? t('customerPortal.editSiteTitle', 'Edit site') : t('customerPortal.createSiteTitle', 'Create site') }}</h2>
                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-bold text-slate-600 md:col-span-2">
                                {{ t('customerPortal.siteName', 'Site name') }}
                                <input v-model="siteForm.name" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.countryCode', 'Country') }}
                                <select v-model="siteForm.country_code" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                    <option value="SA">{{ t('customerPortal.saudiArabia', 'Saudi Arabia') }}</option>
                                </select>
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.city', 'City') }}
                                <input v-model="siteForm.city" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.district', 'District') }}
                                <input v-model="siteForm.district" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.contactName', 'Site contact') }}
                                <input v-model="siteForm.contact_name" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.contactPhone', 'Contact phone') }}
                                <input v-model="siteForm.contact_phone" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600 md:col-span-2">
                                {{ t('customerPortal.address', 'Address') }}
                                <textarea v-model="siteForm.address" rows="3" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-800"></textarea>
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.latitude', 'Latitude') }}
                                <input v-model="siteForm.latitude" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.longitude', 'Longitude') }}
                                <input v-model="siteForm.longitude" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600 md:col-span-2">
                                {{ t('customerPortal.formattedAddress', 'Formatted address') }}
                                <input v-model="siteForm.formatted_address" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600 md:col-span-2">
                                {{ t('customerPortal.googlePlaceId', 'Google Place ID') }}
                                <input v-model="siteForm.google_place_id" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="inline-flex items-start gap-3 rounded-lg bg-slate-50 p-3 text-sm font-bold text-slate-700 md:col-span-2">
                                <input v-model="siteForm.is_default" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-[#1040b6] focus:ring-[#1040b6]">
                                <span>{{ t('customerPortal.makeDefaultSite', 'Use as default booking site') }}</span>
                            </label>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-extrabold">{{ t('customerPortal.mapLocation', 'Map location') }}</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('customerPortal.mapLocationBody', 'Pin the exact service entrance or use the latitude and longitude fields.') }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50" :aria-label="t('customerPortal.locateArea', 'Locate area')" @click="focusArea">
                                    <MapPin class="h-4 w-4" />
                                </button>
                                <button type="button" class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50" :aria-label="t('customerPortal.locateCoordinates', 'Locate coordinates')" @click="focusCoordinates">
                                    <LocateFixed class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div class="mt-5">
                            <GoogleMapPicker
                                v-model:latitude="siteForm.latitude"
                                v-model:longitude="siteForm.longitude"
                                v-model:formatted-address="siteForm.formatted_address"
                                v-model:place-id="siteForm.google_place_id"
                                :country-code="siteForm.country_code"
                                :city="siteForm.city"
                                :district="siteForm.district"
                                :address="siteForm.address"
                                :coordinate-focus-request="coordinateFocusRequest"
                                :area-focus-request="areaFocusRequest"
                            />
                        </div>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-[#1040b6] px-5 py-3 text-sm font-extrabold text-white disabled:opacity-60" :disabled="siteForm.processing">
                                <Save class="h-4 w-4" />
                                {{ siteMode === 'edit' ? t('customerPortal.updateSite', 'Update site') : t('customerPortal.saveSite', 'Save site') }}
                            </button>
                            <button type="button" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-5 py-3 text-sm font-extrabold text-slate-700 hover:bg-slate-50" @click="cancelSiteForm">
                                {{ t('customerPortal.cancel', 'Cancel') }}
                            </button>
                        </div>
                    </section>
                </form>
            </section>
        </section>
    </main>
</template>
