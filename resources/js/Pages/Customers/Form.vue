<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    LocateFixed,
    MapPin,
    Plus,
    Save,
    Trash2,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import GoogleMapPicker from '../../Components/GoogleMapPicker.vue';
import { useI18n } from '../../lib/i18n';
import type { Customer, CustomerSite } from '../../types';

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

type LocationCity = {
    key: string;
    label: string;
    country_code: string;
    center: {
        lat: number;
        lng: number;
    };
    districts: CatalogOption[];
};

type LocationCatalog = {
    countries: CatalogOption[];
    cities: LocationCity[];
};

type Step = {
    key: 'profile' | 'sites';
    label: string;
};

type SiteForm = {
    id: number | null;
    country_code: string;
    name: string;
    city: string;
    district: string;
    address: string;
    latitude: string;
    longitude: string;
    google_place_id: string;
    formatted_address: string;
    contact_name: string;
    contact_phone: string;
};

type CustomerForm = {
    customer_type: string;
    name: string;
    phone: string;
    email: string;
    preferred_channel: string;
    preferred_locale: string;
    vat_number: string;
    status: string;
    sites: SiteForm[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    customer: Customer | null;
    catalog: Catalog;
    locationCatalog: LocationCatalog;
    steps: Step[];
    submitUrl: string;
    backUrl: string;
}>();

const { t } = useI18n();
const currentStep = ref(0);
const coordinateFocusRequests = ref<Record<number, number>>({});
const areaFocusRequests = ref<Record<number, number>>({});

const stepFields: Record<Step['key'], string[]> = {
    profile: ['customer_type', 'name', 'phone', 'email', 'preferred_channel', 'preferred_locale', 'vat_number', 'status'],
    sites: ['sites'],
};

function blankSite(): SiteForm {
    return {
        id: null,
        country_code: 'SA',
        name: '',
        city: 'Riyadh',
        district: '',
        address: '',
        latitude: '',
        longitude: '',
        google_place_id: '',
        formatted_address: '',
        contact_name: '',
        contact_phone: '',
    };
}

function siteToForm(site: CustomerSite): SiteForm {
    return {
        id: site.id,
        country_code: site.country_code ?? 'SA',
        name: site.name,
        city: site.city,
        district: site.district ?? '',
        address: site.address ?? '',
        latitude: site.latitude === null || site.latitude === undefined ? '' : String(site.latitude),
        longitude: site.longitude === null || site.longitude === undefined ? '' : String(site.longitude),
        google_place_id: site.google_place_id ?? '',
        formatted_address: site.formatted_address ?? '',
        contact_name: site.contact_name ?? '',
        contact_phone: site.contact_phone ?? '',
    };
}

function defaultFormData(): CustomerForm {
    if (props.customer) {
        return {
            customer_type: props.customer.customer_type,
            name: props.customer.name,
            phone: props.customer.phone ?? '',
            email: props.customer.email ?? '',
            preferred_channel: props.customer.preferred_channel,
            preferred_locale: props.customer.preferred_locale,
            vat_number: props.customer.vat_number ?? '',
            status: props.customer.status,
            sites: props.customer.sites?.length ? props.customer.sites.map(siteToForm) : [blankSite()],
        };
    }

    return {
        customer_type: 'company',
        name: '',
        phone: '',
        email: '',
        preferred_channel: 'whatsapp',
        preferred_locale: 'ar',
        vat_number: '',
        status: 'active',
        sites: [blankSite()],
    };
}

const form = useForm<CustomerForm>(defaultFormData());
const pageTitle = computed(() => props.mode === 'edit' ? t('customersAdmin.updateCustomer', 'Update customer') : t('customersAdmin.newCustomer', 'New customer'));
const currentStepKey = computed(() => props.steps[currentStep.value]?.key ?? 'profile');
const isFirstStep = computed(() => currentStep.value === 0);
const isLastStep = computed(() => currentStep.value === props.steps.length - 1);

function stepLabel(step: Step): string {
    return t(`customersAdmin.steps.${step.key}`, step.label);
}

function catalogLabel(group: keyof Catalog, key: string): string {
    const option = props.catalog[group].find((item) => item.key === key);

    return t(`customersAdmin.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
}

function translationKey(value: string): string {
    return value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
}

function locationLabel(group: 'countries' | 'cities' | 'districts', key: string, fallback: string): string {
    return t(`customersAdmin.location.${group}.${translationKey(key)}`, fallback);
}

function errorFor(key: string): string | undefined {
    return form.errors[key as keyof typeof form.errors] as string | undefined;
}

function stepHasErrors(step: Step): boolean {
    const fields = stepFields[step.key];
    const errorKeys = Object.keys(form.errors);

    return errorKeys.some((key) => fields.some((field) => key === field || key.startsWith(`${field}.`)));
}

function goToStep(index: number): void {
    currentStep.value = Math.max(0, Math.min(index, props.steps.length - 1));
}

function addSite(): void {
    form.sites.push(blankSite());
}

function removeSite(index: number): void {
    form.sites.splice(index, 1);

    if (form.sites.length === 0) {
        form.sites.push(blankSite());
    }
}

function countryLabel(countryCode: string): string {
    const country = props.locationCatalog.countries.find((item) => item.key === countryCode);

    return locationLabel('countries', country?.key ?? countryCode, country?.label ?? countryCode);
}

function citiesForCountry(countryCode: string): LocationCity[] {
    return props.locationCatalog.cities.filter((city) => city.country_code === countryCode);
}

function cityLabel(city: LocationCity): string {
    return locationLabel('cities', city.key, city.label);
}

function cityLabelForSite(site: SiteForm): string {
    const city = cityOption(site);

    return city ? cityLabel(city) : site.city;
}

function cityOption(site: SiteForm): LocationCity | undefined {
    return props.locationCatalog.cities.find((city) => city.country_code === site.country_code && city.key === site.city);
}

function districtOptions(site: SiteForm): CatalogOption[] {
    return cityOption(site)?.districts ?? [];
}

function districtLabel(district: CatalogOption): string {
    return locationLabel('districts', district.key, district.label);
}

function districtLabelForSite(site: SiteForm): string {
    const district = districtOptions(site).find((item) => item.key === site.district);

    return district ? districtLabel(district) : site.district;
}

function locationAddress(site: SiteForm): string {
    return [districtLabelForSite(site), cityLabelForSite(site), countryLabel(site.country_code)].filter(Boolean).join(', ');
}

function requestAreaFocus(index: number): void {
    areaFocusRequests.value = {
        ...areaFocusRequests.value,
        [index]: (areaFocusRequests.value[index] ?? 0) + 1,
    };
}

function requestCoordinateFocus(index: number): void {
    coordinateFocusRequests.value = {
        ...coordinateFocusRequests.value,
        [index]: (coordinateFocusRequests.value[index] ?? 0) + 1,
    };
}

function onCountryChange(site: SiteForm, index: number): void {
    const cities = citiesForCountry(site.country_code);

    if (!cities.some((city) => city.key === site.city)) {
        site.city = cities[0]?.key ?? '';
    }

    site.district = '';
    site.formatted_address = locationAddress(site);
    requestAreaFocus(index);
}

function onCityChange(site: SiteForm, index: number): void {
    site.district = '';
    site.formatted_address = locationAddress(site);
    requestAreaFocus(index);
}

function onDistrictChange(site: SiteForm, index: number): void {
    site.formatted_address = locationAddress(site);
    requestAreaFocus(index);
}

function hasCoordinates(site: SiteForm): boolean {
    return site.latitude.trim().length > 0 && site.longitude.trim().length > 0;
}

function buildPayload(data: CustomerForm) {
    return {
        customer_type: data.customer_type,
        name: data.name,
        phone: data.phone,
        email: data.email,
        preferred_channel: data.preferred_channel,
        preferred_locale: data.preferred_locale,
        vat_number: data.vat_number,
        status: data.status,
        sites: data.sites
            .filter((site) => site.name.trim().length > 0)
            .map((site) => ({
                id: site.id,
                country_code: site.country_code,
                name: site.name,
                city: site.city,
                district: site.district,
                address: site.address,
                latitude: site.latitude,
                longitude: site.longitude,
                google_place_id: site.google_place_id,
                formatted_address: site.formatted_address,
                contact_name: site.contact_name,
                contact_phone: site.contact_phone,
            })),
    };
}

function jumpToFirstError(): void {
    const index = props.steps.findIndex((step) => stepHasErrors(step));

    if (index >= 0) {
        currentStep.value = index;
    }
}

function submit(): void {
    const options = {
        preserveScroll: false,
        onError: () => jumpToFirstError(),
    };

    if (props.mode === 'edit') {
        form.transform(buildPayload).patch(props.submitUrl, options);
        return;
    }

    form.transform(buildPayload).post(props.submitUrl, options);
}
</script>

<template>
    <Head :title="pageTitle" />

    <section class="space-y-6">
        <div>
            <Link :href="backUrl" class="mb-3 inline-flex items-center gap-2 text-sm font-semibold text-brand-600">
                <ArrowLeft class="h-4 w-4 rtl:rotate-180" />
                {{ t('customersAdmin.backToCustomers', 'Back to customers') }}
            </Link>
            <h1 class="ta-page-title">{{ pageTitle }}</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                {{ t('customersAdmin.formSubtitle', 'Customer profile and one or more service sites.') }}
            </p>
        </div>

        <div class="ta-card overflow-hidden p-3">
            <div class="grid gap-2 sm:grid-cols-2">
                <button
                    v-for="(step, index) in steps"
                    :key="step.key"
                    type="button"
                    class="flex min-h-14 items-center justify-between rounded-lg border px-4 py-3 text-start text-sm font-semibold transition"
                    :class="[
                        currentStep === index ? 'border-brand-500 bg-brand-50 text-brand-600' : 'border-gray-200 bg-white text-gray-600 hover:border-brand-200',
                        stepHasErrors(step) ? 'border-error-500 bg-error-50 text-error-600' : '',
                    ]"
                    @click="goToStep(index)"
                >
                    <span>{{ stepLabel(step) }}</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white text-xs">{{ index + 1 }}</span>
                </button>
            </div>
        </div>

        <form class="ta-card p-5" @submit.prevent="submit">
            <div class="mb-5 flex items-start gap-4">
                <div class="ta-icon-box">
                    <UserRound v-if="currentStepKey === 'profile'" class="h-6 w-6" />
                    <MapPin v-else class="h-6 w-6" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ stepLabel(steps[currentStep]) }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('customersAdmin.stepCount', 'Step :current of :total', { current: currentStep + 1, total: steps.length }) }}
                    </p>
                </div>
            </div>

            <div v-if="currentStepKey === 'profile'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.customerName', 'Customer name') }}</span>
                    <input v-model="form.name" type="text" class="ta-input h-11 w-full px-4 text-sm">
                    <span v-if="form.errors.name" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.name }}</span>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.customerType', 'Customer type') }}</span>
                    <select v-model="form.customer_type" class="ta-input h-11 w-full px-4 text-sm">
                        <option v-for="option in catalog.customerTypes" :key="option.key" :value="option.key">
                            {{ catalogLabel('customerTypes', option.key) }}
                        </option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.status', 'Status') }}</span>
                    <select v-model="form.status" class="ta-input h-11 w-full px-4 text-sm">
                        <option v-for="option in catalog.statuses" :key="option.key" :value="option.key">
                            {{ catalogLabel('statuses', option.key) }}
                        </option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.phone', 'Phone') }}</span>
                    <input v-model="form.phone" type="text" class="ta-input h-11 w-full px-4 text-sm">
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.email', 'Email') }}</span>
                    <input v-model="form.email" type="email" class="ta-input h-11 w-full px-4 text-sm">
                    <span v-if="form.errors.email" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.email }}</span>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.preferredChannel', 'Preferred channel') }}</span>
                    <select v-model="form.preferred_channel" class="ta-input h-11 w-full px-4 text-sm">
                        <option v-for="option in catalog.channels" :key="option.key" :value="option.key">
                            {{ catalogLabel('channels', option.key) }}
                        </option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.preferredLocale', 'Preferred language') }}</span>
                    <select v-model="form.preferred_locale" class="ta-input h-11 w-full px-4 text-sm">
                        <option v-for="option in catalog.locales" :key="option.key" :value="option.key">
                            {{ catalogLabel('locales', option.key) }}
                        </option>
                    </select>
                </label>

                <label class="block sm:col-span-2 xl:col-span-3">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.vatNumber', 'VAT number') }}</span>
                    <input v-model="form.vat_number" type="text" class="ta-input h-11 w-full px-4 text-sm">
                </label>
            </div>

            <div v-else class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('customersAdmin.sites', 'Sites') }}</h3>
                    <button type="button" class="ta-btn ta-btn-secondary px-3" @click="addSite">
                        <Plus class="h-4 w-4" />
                    </button>
                </div>

                <fieldset v-for="(site, index) in form.sites" :key="`site-${index}-${site.id ?? 'new'}`" class="space-y-3 rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <legend class="text-sm font-semibold text-gray-800">{{ t('customersAdmin.site', 'Site') }} {{ index + 1 }}</legend>
                        <button type="button" class="ta-btn ta-btn-secondary h-9 w-9 p-0" @click="removeSite(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="block sm:col-span-2 xl:col-span-3">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.siteName', 'Site name') }}</span>
                            <input v-model="site.name" type="text" class="ta-input h-11 w-full px-4 text-sm">
                            <span v-if="errorFor(`sites.${index}.name`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`sites.${index}.name`) }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.country', 'Country') }}</span>
                            <select v-model="site.country_code" class="ta-input h-11 w-full px-4 text-sm" @change="onCountryChange(site, index)">
                                <option v-for="country in locationCatalog.countries" :key="country.key" :value="country.key">
                                    {{ locationLabel('countries', country.key, country.label) }}
                                </option>
                            </select>
                            <span v-if="errorFor(`sites.${index}.country_code`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`sites.${index}.country_code`) }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.city', 'City') }}</span>
                            <select v-model="site.city" class="ta-input h-11 w-full px-4 text-sm" @change="onCityChange(site, index)">
                                <option v-for="city in citiesForCountry(site.country_code)" :key="city.key" :value="city.key">
                                    {{ cityLabel(city) }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.district', 'District') }}</span>
                            <select v-model="site.district" class="ta-input h-11 w-full px-4 text-sm" @change="onDistrictChange(site, index)">
                                <option value="">{{ t('customersAdmin.selectDistrict', 'Select district') }}</option>
                                <option
                                    v-if="site.district && ! districtOptions(site).some((district) => district.key === site.district)"
                                    :value="site.district"
                                >
                                    {{ site.district }}
                                </option>
                                <option v-for="district in districtOptions(site)" :key="district.key" :value="district.key">
                                    {{ districtLabel(district) }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.contactName', 'Contact name') }}</span>
                            <input v-model="site.contact_name" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.contactPhone', 'Contact phone') }}</span>
                            <input v-model="site.contact_phone" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        </label>

                        <label class="block sm:col-span-2 xl:col-span-3">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.address', 'Address') }}</span>
                            <textarea v-model="site.address" class="ta-input min-h-20 w-full px-4 py-3 text-sm" />
                        </label>
                    </div>

                    <div class="space-y-3 border-t border-gray-100 pt-4">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800">{{ t('customersAdmin.siteMapLocation', 'Site map location') }}</h4>
                                <p class="text-sm text-gray-500">
                                    {{ t('customersAdmin.siteMapLocationHint', 'Pin the exact service entrance or type coordinates manually.') }}
                                </p>
                            </div>
                        </div>

                        <GoogleMapPicker
                            v-model:latitude="site.latitude"
                            v-model:longitude="site.longitude"
                            v-model:formatted-address="site.formatted_address"
                            v-model:place-id="site.google_place_id"
                            :city="site.city"
                            :district="site.district"
                            :address="site.address"
                            :country-code="site.country_code"
                            :country-label="countryLabel(site.country_code)"
                            :area-focus-request="areaFocusRequests[index] ?? 0"
                            :coordinate-focus-request="coordinateFocusRequests[index] ?? 0"
                        />

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                            <label class="block xl:col-span-2">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.latitude', 'Latitude') }}</span>
                                <input
                                    v-model="site.latitude"
                                    type="text"
                                    inputmode="decimal"
                                    class="ta-input h-11 w-full px-4 text-sm"
                                    placeholder="24.7135517"
                                >
                                <span v-if="errorFor(`sites.${index}.latitude`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`sites.${index}.latitude`) }}</span>
                            </label>

                            <label class="block xl:col-span-2">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.longitude', 'Longitude') }}</span>
                                <input
                                    v-model="site.longitude"
                                    type="text"
                                    inputmode="decimal"
                                    class="ta-input h-11 w-full px-4 text-sm"
                                    placeholder="46.6752957"
                                >
                                <span v-if="errorFor(`sites.${index}.longitude`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`sites.${index}.longitude`) }}</span>
                            </label>

                            <div class="flex items-end sm:col-span-2 xl:col-span-2">
                                <button
                                    type="button"
                                    class="ta-btn ta-btn-secondary h-11 w-full whitespace-nowrap px-3"
                                    :disabled="! hasCoordinates(site)"
                                    @click="requestCoordinateFocus(index)"
                                >
                                    <LocateFixed class="h-4 w-4" />
                                    {{ t('customersAdmin.locateCoordinates', 'Locate coordinates') }}
                                </button>
                            </div>

                            <label class="block sm:col-span-2 xl:col-span-4">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.formattedAddress', 'Map address') }}</span>
                                <input v-model="site.formatted_address" type="text" class="ta-input h-11 w-full px-4 text-sm">
                                <span v-if="errorFor(`sites.${index}.formatted_address`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`sites.${index}.formatted_address`) }}</span>
                            </label>

                            <label class="block sm:col-span-2 xl:col-span-2">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('customersAdmin.googlePlaceId', 'Google place ID') }}</span>
                                <input v-model="site.google_place_id" type="text" class="ta-input h-11 w-full px-4 text-sm">
                                <span v-if="errorFor(`sites.${index}.google_place_id`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`sites.${index}.google_place_id`) }}</span>
                            </label>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="mt-6 flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" class="ta-btn ta-btn-secondary" :disabled="isFirstStep" @click="goToStep(currentStep - 1)">
                    <ChevronLeft class="h-4 w-4 rtl:rotate-180" />
                    {{ t('customersAdmin.previousStep', 'Previous') }}
                </button>

                <button v-if="! isLastStep" type="button" class="ta-btn ta-btn-primary sm:ms-auto" @click="goToStep(currentStep + 1)">
                    {{ t('customersAdmin.nextStep', 'Next') }}
                    <ChevronRight class="h-4 w-4 rtl:rotate-180" />
                </button>

                <button v-else type="submit" class="ta-btn ta-btn-primary sm:ms-auto" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('customersAdmin.saveCustomer', 'Save customer') }}
                </button>
            </div>
        </form>
    </section>
</template>
