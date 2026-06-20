<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    CircleDollarSign,
    ClipboardList,
    Layers,
    Package,
    Plus,
    Save,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from '../../lib/i18n';
import type { Service, ServicePackage, ServicePricingRule } from '../../types';

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

type Step = {
    key: 'basics' | 'pricing' | 'packages' | 'pricing_rules';
    label: string;
};

type PackageForm = {
    name: string;
    description: string;
    billing_cycle: string;
    visit_frequency: string;
    visits_per_week: number;
    hours_per_visit: string;
    worker_count: number;
    duration_minutes: number;
    expected_labor_minutes: number;
    material_cost_sar: string;
    price_sar: string;
    vat_rate: number;
    prices_include_vat: boolean;
    checklist_text: string;
    sla_kpi_text: string;
    is_active: boolean;
};

type PricingRuleForm = {
    name: string;
    pricing_type: string;
    unit_label: string;
    unit_price_sar: string;
    minimum_quantity: number;
    maximum_quantity: number | null;
    vat_rate: number;
    prices_include_vat: boolean;
    applies_to_text: string;
    is_active: boolean;
};

type ServiceForm = {
    title: string;
    category: string;
    description: string;
    pricing_type: string;
    base_price_sar: string;
    vat_rate: number;
    prices_include_vat: boolean;
    materials_included: boolean;
    minimum_billable_minutes: number;
    default_workers: number;
    default_duration_minutes: number;
    default_material_cost_sar: string;
    material_policy: string;
    included_materials_text: string;
    extra_hour_rate_sar: string;
    overtime_policy: string;
    allowed_frequencies: string[];
    required_certificates: string[];
    checklist_text: string;
    sla_kpi_text: string;
    is_active: boolean;
    packages: PackageForm[];
    pricing_rules: PricingRuleForm[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    service: Service | null;
    catalog: Catalog;
    steps: Step[];
    submitUrl: string;
    backUrl: string;
}>();

const { t } = useI18n();
const currentStep = ref(0);

const stepFields: Record<Step['key'], string[]> = {
    basics: ['title', 'category', 'description', 'pricing_type', 'materials_included', 'is_active'],
    pricing: [
        'base_price_sar',
        'vat_rate',
        'prices_include_vat',
        'minimum_billable_minutes',
        'default_workers',
        'default_duration_minutes',
        'default_material_cost_sar',
        'material_policy',
        'included_materials',
        'extra_hour_rate_sar',
        'overtime_policy',
        'allowed_frequencies',
        'required_certificates',
        'checklist_template',
        'sla_kpi_template',
    ],
    packages: ['packages'],
    pricing_rules: ['pricing_rules'],
};

function blankPackage(): PackageForm {
    return {
        name: '',
        description: '',
        billing_cycle: 'monthly',
        visit_frequency: 'monthly',
        visits_per_week: 1,
        hours_per_visit: '2.00',
        worker_count: 1,
        duration_minutes: 120,
        expected_labor_minutes: 120,
        material_cost_sar: '0.00',
        price_sar: '0.00',
        vat_rate: 15,
        prices_include_vat: false,
        checklist_text: '',
        sla_kpi_text: '',
        is_active: true,
    };
}

function blankRule(): PricingRuleForm {
    return {
        name: '',
        pricing_type: 'per_unit',
        unit_label: 'unit',
        unit_price_sar: '0.00',
        minimum_quantity: 1,
        maximum_quantity: null,
        vat_rate: 15,
        prices_include_vat: false,
        applies_to_text: '',
        is_active: true,
    };
}

function checklistText(items: Array<{ label: string; is_required?: boolean }> | undefined): string {
    return (items ?? []).map((item) => item.label).join('\n');
}

function checklistFromText(text: string): Array<{ label: string; is_required: boolean }> {
    return text
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line.length > 0)
        .map((label) => ({ label, is_required: true }));
}

function slaKpiText(items: Array<{ code: string; label: string; target: number; unit: string; weight: number; direction: string }> | undefined): string {
    return (items ?? [])
        .map((item) => [item.label, item.target, item.unit || 'percent', item.weight || 0, item.direction || 'at_least', item.code].join(' | '))
        .join('\n');
}

function slaKpiFromText(text: string): Array<{ code: string; label: string; target: number; unit: string; weight: number; direction: string }> {
    return text
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line.length > 0)
        .map((line) => {
            const [label = '', target = '0', unit = 'percent', weight = '0', direction = 'at_least', code = ''] = line
                .split('|')
                .map((part) => part.trim());

            return {
                code,
                label,
                target: Number(target || 0),
                unit: unit || 'percent',
                weight: Number(weight || 0),
                direction: direction === 'at_most' ? 'at_most' : 'at_least',
            };
        });
}

function splitList(text: string): string[] {
    return text
        .split(/[,\n]/)
        .map((item) => item.trim())
        .filter((item) => item.length > 0);
}

function packageToForm(item: ServicePackage): PackageForm {
    return {
        name: item.name,
        description: item.description ?? '',
        billing_cycle: item.billing_cycle,
        visit_frequency: item.visit_frequency,
        visits_per_week: item.visits_per_week ?? 1,
        hours_per_visit: String(item.hours_per_visit ?? (item.duration_minutes / 60).toFixed(2)),
        worker_count: item.worker_count,
        duration_minutes: item.duration_minutes,
        expected_labor_minutes: item.expected_labor_minutes ?? item.worker_count * item.duration_minutes * (item.visits_per_week ?? 1),
        material_cost_sar: item.material_cost_sar ?? '0.00',
        price_sar: item.price_sar ?? '0.00',
        vat_rate: item.vat_rate,
        prices_include_vat: item.prices_include_vat,
        checklist_text: checklistText(item.checklist_template),
        sla_kpi_text: slaKpiText(item.sla_kpi_template),
        is_active: item.is_active,
    };
}

function ruleToForm(item: ServicePricingRule): PricingRuleForm {
    return {
        name: item.name,
        pricing_type: item.pricing_type,
        unit_label: item.unit_label,
        unit_price_sar: item.unit_price_sar ?? '0.00',
        minimum_quantity: item.minimum_quantity,
        maximum_quantity: item.maximum_quantity ?? null,
        vat_rate: item.vat_rate,
        prices_include_vat: item.prices_include_vat,
        applies_to_text: item.applies_to.join(', '),
        is_active: item.is_active,
    };
}

function defaultFormData(): ServiceForm {
    if (props.service) {
        return {
            title: props.service.title,
            category: props.service.category,
            description: props.service.description ?? '',
            pricing_type: props.service.pricing_type,
            base_price_sar: props.service.base_price_sar ?? '0.00',
            vat_rate: props.service.vat_rate,
            prices_include_vat: props.service.prices_include_vat,
            materials_included: props.service.materials_included,
            minimum_billable_minutes: props.service.minimum_billable_minutes ?? 60,
            default_workers: props.service.default_workers ?? 1,
            default_duration_minutes: props.service.default_duration_minutes ?? 120,
            default_material_cost_sar: props.service.default_material_cost_sar ?? '0.00',
            material_policy: props.service.material_policy ?? 'company_supplied_included',
            included_materials_text: (props.service.included_materials ?? []).join('\n'),
            extra_hour_rate_sar: props.service.extra_hour_rate_sar ?? '0.00',
            overtime_policy: props.service.overtime_policy ?? 'none',
            allowed_frequencies: props.service.allowed_frequencies ?? [],
            required_certificates: props.service.required_certificates ?? [],
            checklist_text: checklistText(props.service.checklist_template),
            sla_kpi_text: slaKpiText(props.service.sla_kpi_template),
            is_active: props.service.is_active,
            packages: props.service.packages?.length ? props.service.packages.map(packageToForm) : [blankPackage()],
            pricing_rules: props.service.pricing_rules?.length ? props.service.pricing_rules.map(ruleToForm) : [blankRule()],
        };
    }

    return {
        title: '',
        category: 'cleaning',
        description: '',
        pricing_type: 'fixed',
        base_price_sar: '0.00',
        vat_rate: 15,
        prices_include_vat: false,
        materials_included: true,
        minimum_billable_minutes: 60,
        default_workers: 1,
        default_duration_minutes: 120,
        default_material_cost_sar: '0.00',
        material_policy: 'company_supplied_included',
        included_materials_text: '',
        extra_hour_rate_sar: '0.00',
        overtime_policy: 'none',
        allowed_frequencies: ['once'],
        required_certificates: [],
        checklist_text: '',
        sla_kpi_text: '',
        is_active: true,
        packages: [blankPackage()],
        pricing_rules: [blankRule()],
    };
}

const form = useForm<ServiceForm>(defaultFormData());
const pageTitle = computed(() => props.mode === 'edit' ? t('servicesAdmin.updateService', 'Update service') : t('servicesAdmin.newService', 'New service'));
const currentStepKey = computed(() => props.steps[currentStep.value]?.key ?? 'basics');
const isFirstStep = computed(() => currentStep.value === 0);
const isLastStep = computed(() => currentStep.value === props.steps.length - 1);

function stepLabel(step: Step): string {
    return t(`servicesAdmin.steps.${step.key}`, step.label);
}

function catalogLabel(group: keyof Catalog, key: string): string {
    const option = props.catalog[group].find((item) => item.key === key);

    return t(`servicesAdmin.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
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

function addPackage(): void {
    form.packages.push(blankPackage());
}

function removePackage(index: number): void {
    form.packages.splice(index, 1);

    if (form.packages.length === 0) {
        form.packages.push(blankPackage());
    }
}

function recalculatePackageLabor(index: number): void {
    const item = form.packages[index];

    if (! item) {
        return;
    }

    item.expected_labor_minutes = Math.round(Number(item.worker_count || 0) * Number(item.visits_per_week || 0) * Number(item.hours_per_visit || 0) * 60);
}

function addRule(): void {
    form.pricing_rules.push(blankRule());
}

function removeRule(index: number): void {
    form.pricing_rules.splice(index, 1);

    if (form.pricing_rules.length === 0) {
        form.pricing_rules.push(blankRule());
    }
}

function buildPayload(data: ServiceForm) {
    return {
        title: data.title,
        category: data.category,
        description: data.description,
        pricing_type: data.pricing_type,
        base_price_sar: data.base_price_sar,
        vat_rate: Number(data.vat_rate || 0),
        prices_include_vat: data.prices_include_vat,
        materials_included: data.materials_included,
        minimum_billable_minutes: Number(data.minimum_billable_minutes || 0),
        default_workers: Number(data.default_workers || 1),
        default_duration_minutes: Number(data.default_duration_minutes || 60),
        default_material_cost_sar: data.default_material_cost_sar,
        material_policy: data.material_policy,
        included_materials: splitList(data.included_materials_text),
        extra_hour_rate_sar: data.extra_hour_rate_sar,
        overtime_policy: data.overtime_policy,
        allowed_frequencies: data.allowed_frequencies,
        required_certificates: data.required_certificates,
        checklist_template: checklistFromText(data.checklist_text),
        sla_kpi_template: slaKpiFromText(data.sla_kpi_text),
        is_active: data.is_active,
        packages: data.packages
            .filter((item) => item.name.trim().length > 0)
            .map((item) => ({
                name: item.name,
                description: item.description,
                billing_cycle: item.billing_cycle,
                visit_frequency: item.visit_frequency,
                visits_per_week: Number(item.visits_per_week || 1),
                hours_per_visit: item.hours_per_visit,
                worker_count: Number(item.worker_count || 1),
                duration_minutes: Number(item.duration_minutes || 60),
                expected_labor_minutes: Number(item.expected_labor_minutes || 0),
                material_cost_sar: item.material_cost_sar,
                price_sar: item.price_sar,
                vat_rate: Number(item.vat_rate || 0),
                prices_include_vat: item.prices_include_vat,
                checklist_template: checklistFromText(item.checklist_text),
                sla_kpi_template: slaKpiFromText(item.sla_kpi_text),
                is_active: item.is_active,
            })),
        pricing_rules: data.pricing_rules
            .filter((item) => item.name.trim().length > 0)
            .map((item) => ({
                name: item.name,
                pricing_type: item.pricing_type,
                unit_label: item.unit_label,
                unit_price_sar: item.unit_price_sar,
                minimum_quantity: Number(item.minimum_quantity || 0),
                maximum_quantity: item.maximum_quantity === null || item.maximum_quantity === undefined || Number.isNaN(Number(item.maximum_quantity))
                    ? null
                    : Number(item.maximum_quantity),
                vat_rate: Number(item.vat_rate || 0),
                prices_include_vat: item.prices_include_vat,
                applies_to: splitList(item.applies_to_text),
                is_active: item.is_active,
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
                {{ t('servicesAdmin.backToServices', 'Back to services') }}
            </Link>
            <h1 class="ta-page-title">{{ pageTitle }}</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                {{ t('servicesAdmin.formSubtitle', 'Core service setup with package and pricing defaults.') }}
            </p>
        </div>

        <div class="ta-card overflow-hidden p-3">
            <div class="grid gap-2 md:grid-cols-4">
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
                    <Layers v-if="currentStepKey === 'basics'" class="h-6 w-6" />
                    <CircleDollarSign v-else-if="currentStepKey === 'pricing'" class="h-6 w-6" />
                    <Package v-else-if="currentStepKey === 'packages'" class="h-6 w-6" />
                    <ClipboardList v-else class="h-6 w-6" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ stepLabel(steps[currentStep]) }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('servicesAdmin.stepCount', 'Step :current of :total', { current: currentStep + 1, total: steps.length }) }}
                    </p>
                </div>
            </div>

            <div v-if="currentStepKey === 'basics'" class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.serviceName', 'Service name') }}</span>
                        <input v-model="form.title" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        <span v-if="form.errors.title" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.title }}</span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.category', 'Category') }}</span>
                        <select v-model="form.category" class="ta-input h-11 w-full px-4 text-sm">
                            <option v-for="option in catalog.categories" :key="option.key" :value="option.key">
                                {{ catalogLabel('categories', option.key) }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.pricingType', 'Pricing type') }}</span>
                        <select v-model="form.pricing_type" class="ta-input h-11 w-full px-4 text-sm">
                            <option v-for="option in catalog.pricingTypes" :key="option.key" :value="option.key">
                                {{ catalogLabel('pricingTypes', option.key) }}
                            </option>
                        </select>
                    </label>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.description', 'Description') }}</span>
                    <textarea v-model="form.description" class="ta-input min-h-24 w-full px-4 py-3 text-sm" />
                </label>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                        <span class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.materials', 'Materials') }}</span>
                        <input v-model="form.materials_included" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                        <span class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.vatInclusive', 'VAT inclusive') }}</span>
                        <input v-model="form.prices_include_vat" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                        <span class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.active', 'Active') }}</span>
                        <input v-model="form.is_active" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500">
                    </label>
                </div>
            </div>

            <div v-else-if="currentStepKey === 'pricing'" class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.basePrice', 'Base price (SAR)') }}</span>
                        <input v-model="form.base_price_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" placeholder="750.25">
                        <span v-if="form.errors.base_price_sar" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.base_price_sar }}</span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.vatRate', 'VAT rate') }}</span>
                        <input v-model.number="form.vat_rate" type="number" min="0" max="100" class="ta-input h-11 w-full px-4 text-sm">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.minimumBillableMinutes', 'Minimum billable minutes') }}</span>
                        <input v-model.number="form.minimum_billable_minutes" type="number" min="0" step="15" class="ta-input h-11 w-full px-4 text-sm">
                        <span v-if="form.errors.minimum_billable_minutes" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.minimum_billable_minutes }}</span>
                    </label>
                </div>

                <section class="rounded-xl border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.workloadDefaults', 'Workload defaults') }}</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.defaultWorkers', 'Default workers') }}</span>
                            <input v-model.number="form.default_workers" type="number" min="1" class="ta-input h-11 w-full px-4 text-sm">
                            <span v-if="form.errors.default_workers" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.default_workers }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.defaultDurationMinutes', 'Default duration minutes') }}</span>
                            <input v-model.number="form.default_duration_minutes" type="number" min="15" step="15" class="ta-input h-11 w-full px-4 text-sm">
                            <span v-if="form.errors.default_duration_minutes" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.default_duration_minutes }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.extraHourRate', 'Extra hour rate (SAR)') }}</span>
                            <input v-model="form.extra_hour_rate_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" placeholder="95.50">
                            <span v-if="form.errors.extra_hour_rate_sar" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.extra_hour_rate_sar }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.overtimePolicy', 'Overtime policy') }}</span>
                            <select v-model="form.overtime_policy" class="ta-input h-11 w-full px-4 text-sm">
                                <option v-for="option in catalog.overtimePolicies" :key="option.key" :value="option.key">
                                    {{ catalogLabel('overtimePolicies', option.key) }}
                                </option>
                            </select>
                        </label>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.materialDefaults', 'Material defaults') }}</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.defaultMaterialCost', 'Default material cost (SAR)') }}</span>
                            <input v-model="form.default_material_cost_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" placeholder="120.25">
                            <span v-if="form.errors.default_material_cost_sar" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.default_material_cost_sar }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.materialPolicy', 'Material policy') }}</span>
                            <select v-model="form.material_policy" class="ta-input h-11 w-full px-4 text-sm">
                                <option v-for="option in catalog.materialPolicies" :key="option.key" :value="option.key">
                                    {{ catalogLabel('materialPolicies', option.key) }}
                                </option>
                            </select>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.includedMaterials', 'Included materials') }}</span>
                            <textarea v-model="form.included_materials_text" class="ta-input min-h-20 w-full px-4 py-3 text-sm" :placeholder="t('servicesAdmin.includedMaterialsPlaceholder', 'Glass cleaner, garbage bags, disinfectant')" />
                        </label>
                    </div>
                </section>

                <section>
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">{{ t('servicesAdmin.frequencies', 'Allowed frequencies') }}</h3>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <label v-for="option in catalog.frequencies" :key="option.key" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <input v-model="form.allowed_frequencies" type="checkbox" :value="option.key" class="h-4 w-4 rounded border-gray-300 text-brand-500">
                            <span>{{ catalogLabel('frequencies', option.key) }}</span>
                        </label>
                    </div>
                </section>

                <section>
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">{{ t('servicesAdmin.certificates', 'Required certificates') }}</h3>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <label v-for="option in catalog.certificates" :key="option.key" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <input v-model="form.required_certificates" type="checkbox" :value="option.key" class="h-4 w-4 rounded border-gray-300 text-brand-500">
                            <span>{{ catalogLabel('certificates', option.key) }}</span>
                        </label>
                    </div>
                </section>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.checklist', 'Checklist') }}</span>
                    <textarea v-model="form.checklist_text" class="ta-input min-h-28 w-full px-4 py-3 text-sm" />
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.slaKpiTemplate', 'SLA/KPI template') }}</span>
                    <textarea v-model="form.sla_kpi_text" class="ta-input min-h-28 w-full px-4 py-3 text-sm" placeholder="Attendance | 95 | percent | 40 | at_least | attendance" />
                </label>
            </div>

            <div v-else-if="currentStepKey === 'packages'" class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.packageDefaults', 'Package defaults') }}</h3>
                    <button type="button" class="ta-btn ta-btn-secondary px-3" @click="addPackage">
                        <Plus class="h-4 w-4" />
                    </button>
                </div>

                <fieldset v-for="(item, index) in form.packages" :key="`package-${index}`" class="space-y-3 rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <legend class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.package', 'Package') }} {{ index + 1 }}</legend>
                        <button type="button" class="ta-btn ta-btn-secondary h-9 w-9 p-0" @click="removePackage(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="block sm:col-span-2 xl:col-span-3">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.packageName', 'Package name') }}</span>
                            <input v-model="item.name" type="text" class="ta-input h-11 w-full px-4 text-sm">
                            <span v-if="errorFor(`packages.${index}.name`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`packages.${index}.name`) }}</span>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.packagePrice', 'Package price (SAR)') }}</span>
                            <input v-model="item.price_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" placeholder="850.75">
                            <span v-if="errorFor(`packages.${index}.price_sar`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`packages.${index}.price_sar`) }}</span>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.billingCycle', 'Billing cycle') }}</span>
                            <select v-model="item.billing_cycle" class="ta-input h-11 w-full px-4 text-sm">
                                <option v-for="option in catalog.billingCycles" :key="option.key" :value="option.key">
                                    {{ catalogLabel('billingCycles', option.key) }}
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.visitFrequency', 'Visit frequency') }}</span>
                            <select v-model="item.visit_frequency" class="ta-input h-11 w-full px-4 text-sm">
                                <option v-for="option in catalog.frequencies" :key="option.key" :value="option.key">
                                    {{ catalogLabel('frequencies', option.key) }}
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.visitsPerWeek', 'Visits per week') }}</span>
                            <input v-model.number="item.visits_per_week" type="number" min="1" max="7" class="ta-input h-11 w-full px-4 text-sm" @change="recalculatePackageLabor(index)">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.hoursPerVisit', 'Hours per visit') }}</span>
                            <input v-model="item.hours_per_visit" type="number" min="0.25" max="24" step="0.25" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" @change="recalculatePackageLabor(index)">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.workerCount', 'Workers') }}</span>
                            <input v-model.number="item.worker_count" type="number" min="1" class="ta-input h-11 w-full px-4 text-sm" @change="recalculatePackageLabor(index)">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.duration', 'Duration minutes') }}</span>
                            <input v-model.number="item.duration_minutes" type="number" min="15" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.expectedLaborMinutes', 'Expected labor minutes') }}</span>
                            <input v-model.number="item.expected_labor_minutes" type="number" min="0" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.materialCost', 'Material cost (SAR)') }}</span>
                            <input v-model="item.material_cost_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" placeholder="45.50">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.vatRate', 'VAT rate') }}</span>
                            <input v-model.number="item.vat_rate" type="number" min="0" max="100" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.packageDescription', 'Package description') }}</span>
                        <textarea v-model="item.description" class="ta-input min-h-20 w-full px-4 py-3 text-sm" />
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.packageChecklist', 'Package checklist') }}</span>
                        <textarea v-model="item.checklist_text" class="ta-input min-h-20 w-full px-4 py-3 text-sm" />
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.packageSlaKpiTemplate', 'Package SLA/KPI template') }}</span>
                        <textarea v-model="item.sla_kpi_text" class="ta-input min-h-20 w-full px-4 py-3 text-sm" placeholder="Checklist completion | 90 | percent | 30 | at_least | checklist_completion" />
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                            <span class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.vatInclusive', 'VAT inclusive') }}</span>
                            <input v-model="item.prices_include_vat" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500">
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                            <span class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.active', 'Active') }}</span>
                            <input v-model="item.is_active" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500">
                        </label>
                    </div>
                </fieldset>
            </div>

            <div v-else class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.pricingRules', 'Pricing rules') }}</h3>
                    <button type="button" class="ta-btn ta-btn-secondary px-3" @click="addRule">
                        <Plus class="h-4 w-4" />
                    </button>
                </div>

                <fieldset v-for="(item, index) in form.pricing_rules" :key="`rule-${index}`" class="space-y-3 rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <legend class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.rule', 'Rule') }} {{ index + 1 }}</legend>
                        <button type="button" class="ta-btn ta-btn-secondary h-9 w-9 p-0" @click="removeRule(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="block sm:col-span-2 xl:col-span-3">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.ruleName', 'Rule name') }}</span>
                            <input v-model="item.name" type="text" class="ta-input h-11 w-full px-4 text-sm">
                            <span v-if="errorFor(`pricing_rules.${index}.name`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`pricing_rules.${index}.name`) }}</span>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.pricingType', 'Pricing type') }}</span>
                            <select v-model="item.pricing_type" class="ta-input h-11 w-full px-4 text-sm">
                                <option v-for="option in catalog.pricingTypes" :key="option.key" :value="option.key">
                                    {{ catalogLabel('pricingTypes', option.key) }}
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.unitLabel', 'Unit label') }}</span>
                            <input v-model="item.unit_label" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.unitPrice', 'Unit price (SAR)') }}</span>
                            <input v-model="item.unit_price_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" placeholder="15.25">
                            <span v-if="errorFor(`pricing_rules.${index}.unit_price_sar`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`pricing_rules.${index}.unit_price_sar`) }}</span>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.minimumQuantity', 'Minimum quantity') }}</span>
                            <input v-model.number="item.minimum_quantity" type="number" min="0" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.maximumQuantity', 'Maximum quantity') }}</span>
                            <input v-model.number="item.maximum_quantity" type="number" min="1" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.vatRate', 'VAT rate') }}</span>
                            <input v-model.number="item.vat_rate" type="number" min="0" max="100" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('servicesAdmin.appliesTo', 'Applies to') }}</span>
                        <input v-model="item.applies_to_text" type="text" class="ta-input h-11 w-full px-4 text-sm">
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                            <span class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.vatInclusive', 'VAT inclusive') }}</span>
                            <input v-model="item.prices_include_vat" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500">
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                            <span class="text-sm font-semibold text-gray-800">{{ t('servicesAdmin.active', 'Active') }}</span>
                            <input v-model="item.is_active" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500">
                        </label>
                    </div>
                </fieldset>
            </div>

            <div class="mt-6 flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" class="ta-btn ta-btn-secondary" :disabled="isFirstStep" @click="goToStep(currentStep - 1)">
                    <ChevronLeft class="h-4 w-4 rtl:rotate-180" />
                    {{ t('servicesAdmin.previousStep', 'Previous') }}
                </button>

                <button v-if="! isLastStep" type="button" class="ta-btn ta-btn-primary sm:ms-auto" @click="goToStep(currentStep + 1)">
                    {{ t('servicesAdmin.nextStep', 'Next') }}
                    <ChevronRight class="h-4 w-4 rtl:rotate-180" />
                </button>

                <button v-else type="submit" class="ta-btn ta-btn-primary sm:ms-auto" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('servicesAdmin.saveService', 'Save service') }}
                </button>
            </div>
        </form>
    </section>
</template>
