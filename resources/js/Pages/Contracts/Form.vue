<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BadgePercent,
    BriefcaseBusiness,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    FilePlus2,
    PackageCheck,
    PencilLine,
    Plus,
    RefreshCw,
    ReceiptText,
    Save,
    Trash2,
    WandSparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { money } from '../../lib/format';
import { useI18n } from '../../lib/i18n';
import type {
    Contract,
    ContractAddendum,
    ContractCustomerOption,
    PaymentPlanInstallment,
    Service,
    ServicePackageOption,
} from '../../types';

type CatalogOption = {
    key: string;
    label: string;
};

type Catalog = {
    statuses: CatalogOption[];
    billingCycles: CatalogOption[];
    pricingModels: CatalogOption[];
    materialPolicies: CatalogOption[];
    overtimePolicies: CatalogOption[];
};

type Step = {
    key: 'customer_service' | 'terms' | 'pricing_scope' | 'payment_plan' | 'addendums' | 'review';
    label: string;
};

type PaymentPlanForm = {
    label: string;
    day: number;
    percent: number;
};

type AddendumForm = {
    id: number | null;
    number: number;
    title: string;
    summary: string;
    effective_on: string;
};

type ScopeForm = {
    area: string;
    tasks: string;
};

type AgreementSource = 'package' | 'custom';

type ReviewLine = {
    label: string;
    value: string;
    note?: string;
};

type ContractForm = {
    customer_id: number | null;
    customer_site_id: number | null;
    service_id: number | null;
    service_package_id: number | null;
    pricing_source: AgreementSource;
    reference: string;
    status: string;
    starts_on: string;
    ends_on: string;
    monthly_fee_sar: string;
    vat_rate: number;
    prices_include_vat: boolean;
    pricing_model: string;
    agreed_workers: number;
    visits_per_week: number | null;
    hours_per_visit: string;
    planned_weekly_minutes: number;
    included_materials: boolean;
    material_policy: string;
    estimated_material_cost_sar: string;
    extra_hour_rate_sar: string;
    overtime_policy: string;
    service_scope: ScopeForm[];
    terms_and_conditions: string;
    payment_plan: PaymentPlanForm[];
    notice_days: number;
    auto_renews: boolean;
    billing_cycle: string;
    special_terms: string;
    addendums: AddendumForm[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    contract: Contract | null;
    customers: ContractCustomerOption[];
    serviceOptions: Service[];
    servicePackages: ServicePackageOption[];
    catalog: Catalog;
    steps: Step[];
    submitUrl: string;
    backUrl: string;
}>();

const { t } = useI18n();
const currentStep = ref(0);

const stepFields: Record<Step['key'], string[]> = {
    customer_service: ['customer_id', 'customer_site_id', 'service_id', 'service_package_id', 'reference', 'status'],
    terms: ['starts_on', 'ends_on', 'monthly_fee_sar', 'vat_rate', 'prices_include_vat', 'notice_days', 'auto_renews', 'billing_cycle', 'special_terms'],
    pricing_scope: [
        'pricing_model',
        'agreed_workers',
        'visits_per_week',
        'hours_per_visit',
        'planned_weekly_minutes',
        'included_materials',
        'material_policy',
        'estimated_material_cost_sar',
        'extra_hour_rate_sar',
        'overtime_policy',
        'service_scope',
        'terms_and_conditions',
    ],
    payment_plan: ['payment_plan'],
    addendums: ['addendums'],
    review: [],
};

function blankPaymentPlan(): PaymentPlanForm {
    return {
        label: '',
        day: 1,
        percent: 100,
    };
}

function blankAddendum(nextNumber = 1): AddendumForm {
    return {
        id: null,
        number: nextNumber,
        title: '',
        summary: '',
        effective_on: '',
    };
}

function blankScope(): ScopeForm {
    return {
        area: '',
        tasks: '',
    };
}

function paymentPlanToForm(item: PaymentPlanInstallment): PaymentPlanForm {
    return {
        label: item.label,
        day: item.day,
        percent: item.percent,
    };
}

function addendumToForm(item: ContractAddendum): AddendumForm {
    return {
        id: item.id,
        number: item.number,
        title: item.title,
        summary: item.summary,
        effective_on: item.effective_on,
    };
}

function scopeToForm(item: { area: string; tasks?: string | null }): ScopeForm {
    return {
        area: item.area,
        tasks: item.tasks ?? '',
    };
}

function packageHours(item: ServicePackageOption | null | undefined): string {
    if (item?.hours_per_visit !== null && item?.hours_per_visit !== undefined) {
        return String(item.hours_per_visit);
    }

    const visits = Number(item?.visits_per_week || 1);
    const workers = Number(item?.worker_count || 1);
    const minutes = Number(item?.expected_labor_minutes || 0);

    if (minutes > 0 && visits > 0 && workers > 0) {
        return (minutes / workers / visits / 60).toFixed(2);
    }

    return '2.00';
}

function packagePlannedWeeklyMinutes(item: ServicePackageOption | null | undefined): number {
    if (Number(item?.expected_labor_minutes || 0) > 0) {
        return Number(item?.expected_labor_minutes);
    }

    return Math.round(Number(item?.worker_count || 1) * Number(item?.visits_per_week || 1) * Number(packageHours(item)) * 60);
}

function serviceDefaultVisitsPerWeek(item: Service | null | undefined): number {
    if (item?.allowed_frequencies?.includes('daily')) {
        return 7;
    }

    return 1;
}

function serviceHours(item: Service | null | undefined): string {
    const minutes = Number(item?.default_duration_minutes || 0);

    if (minutes > 0) {
        return (minutes / 60).toFixed(2);
    }

    return '2.00';
}

function servicePlannedWeeklyMinutes(item: Service | null | undefined): number {
    return Math.round(Number(item?.default_workers || 1) * serviceDefaultVisitsPerWeek(item) * Number(serviceHours(item)) * 60);
}

function pricingModelFromService(item: Service | null | undefined): string {
    const mapping: Record<string, string> = {
        per_hour: 'hourly',
        per_day: 'daily',
        per_week: 'weekly',
        per_month: 'monthly',
        per_worker_month: 'monthly',
    };

    return mapping[item?.pricing_type ?? ''] ?? 'custom_quote';
}

function scopeFromService(item: Service | null | undefined): ScopeForm[] {
    const tasks = (item?.checklist_template ?? [])
        .map((task) => task.label)
        .filter((label) => label.trim().length > 0);

    if (tasks.length === 0) {
        return [blankScope()];
    }

    return [{
        area: t('contractsAdmin.defaultScopeArea', 'General service'),
        tasks: tasks.join(', '),
    }];
}

function sarToHalalas(value: string | number | null | undefined): number {
    const parsed = Number(String(value ?? '0').replace(/[^\d.]/g, ''));

    if (! Number.isFinite(parsed)) {
        return 0;
    }

    return Math.max(0, Math.round(parsed * 100));
}

function defaultFormData(): ContractForm {
    const firstPackage = props.servicePackages[0] ?? null;
    const firstService = firstPackage
        ? (props.serviceOptions.find((service) => service.id === firstPackage.service_id) ?? props.serviceOptions[0] ?? null)
        : (props.serviceOptions[0] ?? null);
    const usePackage = firstPackage !== null;
    const defaultService = usePackage ? firstService : firstService;

    if (props.contract) {
        return {
            customer_id: props.contract.customer_id,
            customer_site_id: props.contract.customer_site_id,
            service_id: props.contract.service_id ?? null,
            service_package_id: props.contract.service_package_id ?? null,
            pricing_source: props.contract.service_package_id ? 'package' : 'custom',
            reference: props.contract.reference,
            status: props.contract.status,
            starts_on: props.contract.starts_on,
            ends_on: props.contract.ends_on ?? '',
            monthly_fee_sar: props.contract.monthly_fee_sar ?? '0.00',
            vat_rate: props.contract.vat_rate,
            prices_include_vat: props.contract.prices_include_vat,
            pricing_model: props.contract.pricing_model ?? 'package',
            agreed_workers: props.contract.agreed_workers ?? 1,
            visits_per_week: props.contract.visits_per_week ?? 1,
            hours_per_visit: String(props.contract.hours_per_visit ?? '2.00'),
            planned_weekly_minutes: props.contract.planned_weekly_minutes ?? 0,
            included_materials: props.contract.included_materials ?? true,
            material_policy: props.contract.material_policy ?? 'company_supplied_included',
            estimated_material_cost_sar: props.contract.estimated_material_cost_sar ?? '0.00',
            extra_hour_rate_sar: props.contract.extra_hour_rate_sar ?? '0.00',
            overtime_policy: props.contract.overtime_policy ?? 'none',
            service_scope: props.contract.service_scope?.length ? props.contract.service_scope.map(scopeToForm) : [blankScope()],
            terms_and_conditions: props.contract.terms_and_conditions ?? '',
            payment_plan: props.contract.payment_plan.length ? props.contract.payment_plan.map(paymentPlanToForm) : [blankPaymentPlan()],
            notice_days: props.contract.notice_days,
            auto_renews: props.contract.auto_renews,
            billing_cycle: props.contract.billing_cycle,
            special_terms: props.contract.special_terms ?? '',
            addendums: props.contract.addendums.length ? props.contract.addendums.map(addendumToForm) : [],
        };
    }

    return {
        customer_id: props.customers[0]?.id ?? null,
        customer_site_id: props.customers[0]?.sites[0]?.id ?? null,
        service_id: firstPackage?.service_id ?? defaultService?.id ?? null,
        service_package_id: firstPackage?.id ?? null,
        pricing_source: usePackage ? 'package' : 'custom',
        reference: '',
        status: 'draft',
        starts_on: '',
        ends_on: '',
        monthly_fee_sar: firstPackage?.price_sar ?? defaultService?.base_price_sar ?? '0.00',
        vat_rate: firstPackage?.vat_rate ?? defaultService?.vat_rate ?? 15,
        prices_include_vat: firstPackage?.prices_include_vat ?? defaultService?.prices_include_vat ?? false,
        pricing_model: usePackage ? 'package' : pricingModelFromService(defaultService),
        agreed_workers: firstPackage?.worker_count ?? defaultService?.default_workers ?? 1,
        visits_per_week: firstPackage?.visits_per_week ?? serviceDefaultVisitsPerWeek(defaultService),
        hours_per_visit: firstPackage ? packageHours(firstPackage) : serviceHours(defaultService),
        planned_weekly_minutes: firstPackage ? packagePlannedWeeklyMinutes(firstPackage) : servicePlannedWeeklyMinutes(defaultService),
        included_materials: defaultService?.materials_included ?? true,
        material_policy: defaultService?.material_policy ?? 'company_supplied_included',
        estimated_material_cost_sar: firstPackage?.material_cost_sar ?? defaultService?.default_material_cost_sar ?? '0.00',
        extra_hour_rate_sar: defaultService?.extra_hour_rate_sar ?? '0.00',
        overtime_policy: defaultService?.overtime_policy ?? 'none',
        service_scope: scopeFromService(defaultService),
        terms_and_conditions: '',
        payment_plan: [
            { label: 'Advance', day: 1, percent: 50 },
            { label: 'Completion', day: 20, percent: 50 },
        ],
        notice_days: 30,
        auto_renews: true,
        billing_cycle: firstPackage?.billing_cycle ?? 'monthly',
        special_terms: '',
        addendums: [],
    };
}

const form = useForm<ContractForm>(defaultFormData());
const customTermsOpen = ref(form.pricing_source === 'custom' || form.pricing_model !== 'package');
const pageTitle = computed(() => props.mode === 'edit' ? t('contractsAdmin.updateContract', 'Update contract') : t('contractsAdmin.newContract', 'New contract'));
const currentStepKey = computed(() => props.steps[currentStep.value]?.key ?? 'customer_service');
const isFirstStep = computed(() => currentStep.value === 0);
const isLastStep = computed(() => currentStep.value === props.steps.length - 1);
const selectedCustomer = computed(() => props.customers.find((customer) => customer.id === form.customer_id) ?? null);
const availableSites = computed(() => selectedCustomer.value?.sites ?? []);
const selectedSite = computed(() => availableSites.value.find((site) => site.id === form.customer_site_id) ?? null);
const selectedService = computed(() => props.serviceOptions.find((item) => item.id === form.service_id) ?? null);
const selectedServicePackage = computed(() => props.servicePackages.find((item) => item.id === form.service_package_id) ?? null);
const selectedPackageText = computed(() => selectedServicePackage.value ? packageLabel(selectedServicePackage.value) : '');
const selectedServiceText = computed(() => selectedService.value?.title ?? '');
const selectedSiteText = computed(() => selectedSite.value ? `${selectedSite.value.name} / ${selectedSite.value.city}` : '');
const paymentTotal = computed(() => form.payment_plan.reduce((total, item) => total + Number(item.percent || 0), 0));
const isCustomAgreement = computed(() => form.pricing_source === 'custom' || form.pricing_model !== 'package');
const agreementTermsLocked = computed(() => form.pricing_source === 'package' && !customTermsOpen.value);
const agreementBadgeLabel = computed(() => isCustomAgreement.value
    ? t('contractsAdmin.customAgreement', 'Custom agreement')
    : t('contractsAdmin.packageAgreement', 'Package agreement'));
const agreementSourceLabel = computed(() => form.pricing_source === 'package'
    ? t('contractsAdmin.usePackage', 'Use package')
    : t('contractsAdmin.customAgreementWithoutPackage', 'Custom agreement without package'));
const agreementSummaryItems = computed(() => [
    {
        label: t('contractsAdmin.summaryService', 'Service'),
        value: selectedService.value?.title ?? selectedServicePackage.value?.service_title ?? '-',
    },
    {
        label: t('contractsAdmin.summaryPackage', 'Package'),
        value: form.pricing_source === 'package'
            ? selectedServicePackage.value?.name ?? '-'
            : t('contractsAdmin.noPackage', 'No package'),
    },
    {
        label: t('contractsAdmin.monthlyFee', 'Monthly fee (SAR)'),
        value: `${form.monthly_fee_sar || '0.00'} SAR`,
    },
    {
        label: t('contractsAdmin.workload', 'Workload'),
        value: `${form.agreed_workers || 0} ${t('contractsAdmin.workersShort', 'workers')} / ${form.visits_per_week || 0} ${t('contractsAdmin.visitsShort', 'visits')} / ${form.hours_per_visit || 0}h`,
    },
    {
        label: t('contractsAdmin.materialPolicy', 'Material policy'),
        value: catalogLabel('materialPolicies', form.material_policy),
    },
    {
        label: t('contractsAdmin.extraHourRate', 'Extra hour rate'),
        value: `${form.extra_hour_rate_sar || '0.00'} SAR`,
    },
]);
const monthlyFeeHalalas = computed(() => sarToHalalas(form.monthly_fee_sar));
const materialCostHalalas = computed(() => sarToHalalas(form.estimated_material_cost_sar));
const extraHourRateHalalas = computed(() => sarToHalalas(form.extra_hour_rate_sar));
const materialIsChargeable = computed(() => form.material_policy === 'company_supplied_billable');
const chargeableMaterialHalalas = computed(() => materialIsChargeable.value ? materialCostHalalas.value : 0);
const fixedChargeSubtotalHalalas = computed(() => monthlyFeeHalalas.value + chargeableMaterialHalalas.value);
const priceBreakdown = computed(() => {
    const vatRate = Math.max(0, Number(form.vat_rate || 0));
    const amount = fixedChargeSubtotalHalalas.value;

    if (form.prices_include_vat) {
        const vatHalalas = vatRate > 0 ? Math.round(amount * vatRate / (100 + vatRate)) : 0;

        return {
            netHalalas: amount - vatHalalas,
            vatHalalas,
            grossHalalas: amount,
            vatMode: t('contractsAdmin.vatInclusive', 'VAT inclusive'),
        };
    }

    const vatHalalas = Math.round(amount * vatRate / 100);

    return {
        netHalalas: amount,
        vatHalalas,
        grossHalalas: amount + vatHalalas,
        vatMode: t('contractsAdmin.vatExclusive', 'VAT exclusive'),
    };
});
const reviewPriceLines = computed<ReviewLine[]>(() => [
    {
        label: t('contractsAdmin.baseAgreementAmount', 'Base agreement amount'),
        value: money(monthlyFeeHalalas.value),
        note: t('contractsAdmin.includedInTotal', 'Included in total'),
    },
    {
        label: t('contractsAdmin.chargeableMaterialEstimate', 'Material estimate'),
        value: money(materialCostHalalas.value),
        note: materialIsChargeable.value
            ? t('contractsAdmin.includedInTotal', 'Included in total')
            : t('contractsAdmin.notIncludedInTotal', 'Shown for cost tracking, not charged to customer'),
    },
    {
        label: t('contractsAdmin.variableExtraCharges', 'Variable extra charges'),
        value: `${money(extraHourRateHalalas.value)} / ${t('contractsAdmin.hourShort', 'hour')}`,
        note: t('contractsAdmin.billedIfUsed', 'Billed only if approved/used'),
    },
]);
const paymentPlanPreview = computed(() => form.payment_plan
    .filter((item) => Number(item.percent || 0) > 0)
    .map((item, index) => ({
        label: item.label || `${t('contractsAdmin.installment', 'Installment')} ${index + 1}`,
        day: Number(item.day || 1),
        percent: Number(item.percent || 0),
        amountHalalas: Math.round(priceBreakdown.value.grossHalalas * Number(item.percent || 0) / 100),
    })));
const reviewCommercialLines = computed<ReviewLine[]>(() => [
    { label: t('contractsAdmin.summarySource', 'Source'), value: agreementSourceLabel.value },
    { label: t('contractsAdmin.customer', 'Customer'), value: selectedCustomer.value?.name ?? '-' },
    { label: t('contractsAdmin.site', 'Site'), value: selectedSiteText.value || '-' },
    { label: t('contractsAdmin.summaryService', 'Service'), value: selectedService.value?.title ?? selectedServicePackage.value?.service_title ?? '-' },
    {
        label: t('contractsAdmin.summaryPackage', 'Package'),
        value: form.pricing_source === 'package'
            ? selectedServicePackage.value?.name ?? '-'
            : t('contractsAdmin.noPackage', 'No package'),
    },
    { label: t('contractsAdmin.billingCycle', 'Billing cycle'), value: catalogLabel('billingCycles', form.billing_cycle) },
    { label: t('contractsAdmin.status', 'Status'), value: catalogLabel('statuses', form.status) },
]);
const reviewWorkloadLines = computed<ReviewLine[]>(() => [
    { label: t('contractsAdmin.agreedWorkers', 'Agreed workers'), value: String(form.agreed_workers || 0) },
    { label: t('contractsAdmin.visitsPerWeek', 'Visits per week'), value: String(form.visits_per_week || 0) },
    { label: t('contractsAdmin.hoursPerVisit', 'Hours per visit'), value: `${form.hours_per_visit || 0}h` },
    { label: t('contractsAdmin.plannedWeeklyMinutes', 'Planned weekly minutes'), value: String(form.planned_weekly_minutes || 0) },
    { label: t('contractsAdmin.materialPolicy', 'Material policy'), value: catalogLabel('materialPolicies', form.material_policy) },
    { label: t('contractsAdmin.estimatedMaterialCost', 'Estimated material cost'), value: money(materialCostHalalas.value) },
    { label: t('contractsAdmin.extraHourRate', 'Extra hour rate'), value: money(extraHourRateHalalas.value) },
    { label: t('contractsAdmin.overtimePolicy', 'Overtime policy'), value: catalogLabel('overtimePolicies', form.overtime_policy) },
]);

function stepLabel(step: Step): string {
    return t(`contractsAdmin.steps.${step.key}`, step.label);
}

function catalogLabel(group: keyof Catalog, key: string): string {
    const option = props.catalog[group].find((item) => item.key === key);

    return t(`contractsAdmin.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
}

function packageLabel(item: ServicePackageOption): string {
    return `${item.service_title ?? t('contractsAdmin.service', 'Service')} / ${item.name}`;
}

function textDirection(value?: string | null): 'ltr' | 'rtl' {
    return /[\u0600-\u06FF]/.test(value ?? '') ? 'rtl' : 'ltr';
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

function syncSiteForCustomer(): void {
    form.customer_site_id = availableSites.value[0]?.id ?? null;
}

function applyPackageDefaults(): void {
    if (! selectedServicePackage.value) {
        return;
    }

    const service = props.serviceOptions.find((item) => item.id === selectedServicePackage.value?.service_id) ?? null;

    form.pricing_source = 'package';
    customTermsOpen.value = false;
    form.service_id = selectedServicePackage.value.service_id;
    form.monthly_fee_sar = selectedServicePackage.value.price_sar ?? '0.00';
    form.vat_rate = selectedServicePackage.value.vat_rate;
    form.prices_include_vat = selectedServicePackage.value.prices_include_vat;
    form.billing_cycle = selectedServicePackage.value.billing_cycle;
    form.pricing_model = 'package';
    form.agreed_workers = selectedServicePackage.value.worker_count;
    form.visits_per_week = selectedServicePackage.value.visits_per_week ?? 1;
    form.hours_per_visit = packageHours(selectedServicePackage.value);
    form.planned_weekly_minutes = packagePlannedWeeklyMinutes(selectedServicePackage.value);
    form.included_materials = service?.materials_included ?? true;
    form.material_policy = service?.material_policy ?? 'company_supplied_included';
    form.estimated_material_cost_sar = selectedServicePackage.value.material_cost_sar ?? '0.00';
    form.extra_hour_rate_sar = service?.extra_hour_rate_sar ?? '0.00';
    form.overtime_policy = service?.overtime_policy ?? 'none';
    form.service_scope = scopeFromService(service);
}

function applyServiceDefaults(): void {
    const service = selectedService.value ?? props.serviceOptions[0] ?? null;

    if (! service) {
        return;
    }

    form.pricing_source = 'custom';
    form.service_id = service.id;
    form.service_package_id = null;
    customTermsOpen.value = true;
    form.monthly_fee_sar = service.base_price_sar ?? '0.00';
    form.vat_rate = service.vat_rate;
    form.prices_include_vat = service.prices_include_vat;
    form.pricing_model = pricingModelFromService(service);
    form.agreed_workers = service.default_workers ?? 1;
    form.visits_per_week = serviceDefaultVisitsPerWeek(service);
    form.hours_per_visit = serviceHours(service);
    form.planned_weekly_minutes = servicePlannedWeeklyMinutes(service);
    form.included_materials = service.materials_included ?? true;
    form.material_policy = service.material_policy ?? 'company_supplied_included';
    form.estimated_material_cost_sar = service.default_material_cost_sar ?? '0.00';
    form.extra_hour_rate_sar = service.extra_hour_rate_sar ?? '0.00';
    form.overtime_policy = service.overtime_policy ?? 'none';
    form.service_scope = scopeFromService(service);
}

function setAgreementSource(source: AgreementSource): void {
    form.pricing_source = source;

    if (source === 'package') {
        form.service_package_id = selectedServicePackage.value?.id ?? props.servicePackages[0]?.id ?? null;
        applyPackageDefaults();
        return;
    }

    if (! selectedService.value) {
        form.service_id = props.serviceOptions[0]?.id ?? null;
    }

    applyServiceDefaults();
}

function customizeAgreementTerms(): void {
    customTermsOpen.value = true;

    if (form.pricing_source === 'package' && form.pricing_model === 'package') {
        form.pricing_model = 'custom_package';
        return;
    }

    if (form.pricing_source === 'custom' && form.pricing_model === 'package') {
        form.pricing_model = pricingModelFromService(selectedService.value);
    }
}

function resetPackageTerms(): void {
    if (form.pricing_source !== 'package') {
        return;
    }

    applyPackageDefaults();
}

function recalculatePlannedWeeklyMinutes(): void {
    form.planned_weekly_minutes = Math.round(Number(form.agreed_workers || 0) * Number(form.visits_per_week || 0) * Number(form.hours_per_visit || 0) * 60);
}

function addPaymentPlan(): void {
    form.payment_plan.push({
        label: '',
        day: 1,
        percent: 0,
    });
}

function removePaymentPlan(index: number): void {
    form.payment_plan.splice(index, 1);

    if (form.payment_plan.length === 0) {
        form.payment_plan.push(blankPaymentPlan());
    }
}

function addScope(): void {
    form.service_scope.push(blankScope());
}

function removeScope(index: number): void {
    form.service_scope.splice(index, 1);

    if (form.service_scope.length === 0) {
        form.service_scope.push(blankScope());
    }
}

function addAddendum(): void {
    const nextNumber = Math.max(0, ...form.addendums.map((item) => Number(item.number || 0))) + 1;
    form.addendums.push(blankAddendum(nextNumber));
}

function removeAddendum(index: number): void {
    form.addendums.splice(index, 1);
}

function buildPayload(data: ContractForm) {
    return {
        customer_id: data.customer_id,
        customer_site_id: data.customer_site_id,
        service_id: data.service_id,
        service_package_id: data.pricing_source === 'package' ? data.service_package_id : null,
        reference: data.reference,
        status: data.status,
        starts_on: data.starts_on,
        ends_on: data.ends_on || null,
        monthly_fee_sar: data.monthly_fee_sar,
        vat_rate: Number(data.vat_rate || 0),
        prices_include_vat: data.prices_include_vat,
        pricing_model: data.pricing_model,
        agreed_workers: Number(data.agreed_workers || 1),
        visits_per_week: data.visits_per_week === null ? null : Number(data.visits_per_week || 0),
        hours_per_visit: data.hours_per_visit || null,
        planned_weekly_minutes: Number(data.planned_weekly_minutes || 0),
        included_materials: data.included_materials,
        material_policy: data.material_policy,
        estimated_material_cost_sar: data.estimated_material_cost_sar,
        extra_hour_rate_sar: data.extra_hour_rate_sar,
        overtime_policy: data.overtime_policy,
        service_scope: data.service_scope
            .filter((item) => item.area.trim().length > 0)
            .map((item) => ({
                area: item.area,
                tasks: item.tasks,
            })),
        terms_and_conditions: data.terms_and_conditions,
        payment_plan: data.payment_plan
            .filter((item) => Number(item.percent || 0) > 0)
            .map((item) => ({
                label: item.label,
                day: Number(item.day || 1),
                percent: Number(item.percent || 0),
            })),
        notice_days: Number(data.notice_days || 0),
        auto_renews: data.auto_renews,
        billing_cycle: data.billing_cycle,
        special_terms: data.special_terms,
        addendums: data.addendums
            .filter((item) => item.title.trim().length > 0)
            .map((item) => ({
                id: item.id,
                number: Number(item.number || 1),
                title: item.title,
                summary: item.summary,
                effective_on: item.effective_on,
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
                {{ t('contractsAdmin.backToContracts', 'Back to contracts') }}
            </Link>
            <h1 class="ta-page-title">{{ pageTitle }}</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                {{ t('contractsAdmin.formSubtitle', 'Commercial terms, payment cadence, and scope addendums.') }}
            </p>
        </div>

        <div class="ta-card overflow-hidden p-3">
            <div class="grid gap-2 md:grid-cols-3 xl:grid-cols-6">
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
                    <BriefcaseBusiness v-if="currentStepKey === 'customer_service'" class="h-6 w-6" />
                    <CalendarDays v-else-if="currentStepKey === 'terms'" class="h-6 w-6" />
                    <ClipboardList v-else-if="currentStepKey === 'pricing_scope'" class="h-6 w-6" />
                    <BadgePercent v-else-if="currentStepKey === 'payment_plan'" class="h-6 w-6" />
                    <FilePlus2 v-else-if="currentStepKey === 'addendums'" class="h-6 w-6" />
                    <ReceiptText v-else class="h-6 w-6" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ stepLabel(steps[currentStep]) }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('contractsAdmin.stepCount', 'Step :current of :total', { current: currentStep + 1, total: steps.length }) }}
                    </p>
                </div>
            </div>

            <div v-if="currentStepKey === 'customer_service'" class="grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.reference', 'Reference') }}</span>
                    <input v-model="form.reference" type="text" dir="ltr" class="ta-input h-11 w-full px-4 text-sm" :placeholder="t('contractsAdmin.autoReference', 'Auto-generated when empty')">
                    <span v-if="form.errors.reference" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.reference }}</span>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.customer', 'Customer') }}</span>
                    <select v-model.number="form.customer_id" class="ta-input h-11 w-full px-4 text-sm" :dir="textDirection(selectedCustomer?.name)" @change="syncSiteForCustomer">
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id" :dir="textDirection(customer.name)">
                            {{ customer.name }}
                        </option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.site', 'Site') }}</span>
                    <select v-model.number="form.customer_site_id" class="ta-input h-11 w-full px-4 text-sm" :dir="textDirection(selectedSiteText)">
                        <option v-for="site in availableSites" :key="site.id" :value="site.id" :dir="textDirection(`${site.name} / ${site.city}`)">
                            {{ site.name }} / {{ site.city }}
                        </option>
                    </select>
                    <span v-if="form.errors.customer_site_id" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.customer_site_id }}</span>
                </label>

                <div class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.agreementType', 'Agreement type') }}</span>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            class="flex min-h-16 items-center gap-3 rounded-xl border px-4 py-3 text-start transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="form.pricing_source === 'package' ? 'border-brand-500 bg-brand-50 text-brand-700 shadow-theme-xs' : 'border-gray-200 bg-white text-gray-700 hover:border-brand-200'"
                            :disabled="servicePackages.length === 0"
                            @click="setAgreementSource('package')"
                        >
                            <PackageCheck class="h-5 w-5 shrink-0" />
                            <span>
                                <span class="block text-sm font-bold">{{ t('contractsAdmin.usePackage', 'Use package') }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500">{{ t('contractsAdmin.packageSourceHint', 'Auto-fill price, workload, VAT, and material rules from a prepared package.') }}</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            class="flex min-h-16 items-center gap-3 rounded-xl border px-4 py-3 text-start transition"
                            :class="form.pricing_source === 'custom' ? 'border-warning-500 bg-warning-50 text-warning-700 shadow-theme-xs' : 'border-gray-200 bg-white text-gray-700 hover:border-warning-200'"
                            @click="setAgreementSource('custom')"
                        >
                            <WandSparkles class="h-5 w-5 shrink-0" />
                            <span>
                                <span class="block text-sm font-bold">{{ t('contractsAdmin.customAgreementWithoutPackage', 'Custom agreement without package') }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500">{{ t('contractsAdmin.customSourceHint', 'Start from service defaults, then agree special terms for this customer.') }}</span>
                            </span>
                        </button>
                    </div>
                </div>

                <label v-if="form.pricing_source === 'package'" class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.package', 'Package') }}</span>
                    <select v-model.number="form.service_package_id" class="ta-input h-11 w-full px-4 text-sm" :dir="textDirection(selectedPackageText)" @change="applyPackageDefaults">
                        <option v-for="item in servicePackages" :key="item.id" :value="item.id" :dir="textDirection(packageLabel(item))">
                            {{ packageLabel(item) }}
                        </option>
                    </select>
                    <span v-if="form.errors.service_package_id" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.service_package_id }}</span>
                </label>

                <label v-else class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.service', 'Service') }}</span>
                    <select v-model.number="form.service_id" class="ta-input h-11 w-full px-4 text-sm" :dir="textDirection(selectedServiceText)" @change="applyServiceDefaults">
                        <option v-for="item in serviceOptions" :key="item.id" :value="item.id" :dir="textDirection(item.title)">
                            {{ item.title }}
                        </option>
                    </select>
                    <span v-if="form.errors.service_id" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.service_id }}</span>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.status', 'Status') }}</span>
                    <select v-model="form.status" class="ta-input h-11 w-full px-4 text-sm">
                        <option v-for="option in catalog.statuses" :key="option.key" :value="option.key">
                            {{ catalogLabel('statuses', option.key) }}
                        </option>
                    </select>
                </label>
            </div>

            <div v-else-if="currentStepKey === 'terms'" class="space-y-5">
                <div v-if="agreementTermsLocked" class="flex flex-col gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-bold text-brand-700">{{ t('contractsAdmin.inheritedTermsLocked', 'Package terms are auto-filled') }}</p>
                        <p class="mt-0.5 text-xs text-brand-600">{{ t('contractsAdmin.inheritedTermsLockedHint', 'Price, VAT, billing, workload, materials, and overtime stay locked until you customize this agreement.') }}</p>
                    </div>
                    <button type="button" class="ta-btn ta-btn-secondary shrink-0 px-3" @click="customizeAgreementTerms">
                        <PencilLine class="h-4 w-4" />
                        {{ t('contractsAdmin.customizeTerms', 'Customize terms') }}
                    </button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.startsOn', 'Starts on') }}</span>
                        <input v-model="form.starts_on" type="date" class="ta-input h-11 w-full px-4 text-sm">
                        <span v-if="form.errors.starts_on" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.starts_on }}</span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.endsOn', 'Ends on') }}</span>
                        <input v-model="form.ends_on" type="date" class="ta-input h-11 w-full px-4 text-sm">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.billingCycle', 'Billing cycle') }}</span>
                        <select v-model="form.billing_cycle" class="ta-input h-11 w-full px-4 text-sm disabled:bg-gray-50 disabled:text-gray-500" :disabled="agreementTermsLocked">
                            <option v-for="option in catalog.billingCycles" :key="option.key" :value="option.key">
                                {{ catalogLabel('billingCycles', option.key) }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.monthlyFee', 'Monthly fee (SAR)') }}</span>
                        <input v-model="form.monthly_fee_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm disabled:bg-gray-50 disabled:text-gray-500" placeholder="15000.75" :disabled="agreementTermsLocked">
                        <span v-if="form.errors.monthly_fee_sar" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.monthly_fee_sar }}</span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.vatRate', 'VAT rate') }}</span>
                        <input v-model.number="form.vat_rate" type="number" min="0" max="100" class="ta-input h-11 w-full px-4 text-sm disabled:bg-gray-50 disabled:text-gray-500" :disabled="agreementTermsLocked">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.noticeDays', 'Notice days') }}</span>
                        <input v-model.number="form.notice_days" type="number" min="0" class="ta-input h-11 w-full px-4 text-sm">
                    </label>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                        <span class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.vatInclusive', 'VAT inclusive') }}</span>
                        <input v-model="form.prices_include_vat" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500 disabled:opacity-50" :disabled="agreementTermsLocked">
                    </label>

                    <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                        <span class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.autoRenews', 'Auto renews') }}</span>
                        <input v-model="form.auto_renews" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500">
                    </label>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.specialTerms', 'Special terms') }}</span>
                    <textarea v-model="form.special_terms" class="ta-input min-h-24 w-full px-4 py-3 text-sm" />
                </label>
            </div>

            <div v-else-if="currentStepKey === 'pricing_scope'" class="space-y-5">
                <section class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.agreementSummary', 'Agreement summary') }}</h3>
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold"
                                    :class="isCustomAgreement ? 'border-warning-500/20 bg-warning-50 text-warning-600' : 'border-success-500/20 bg-success-50 text-success-600'"
                                >
                                    {{ agreementBadgeLabel }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ t('contractsAdmin.autoFilledSummaryHint', 'Auto-filled from the selected package or service. Open customization only when this customer needs special terms.') }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="agreementTermsLocked" type="button" class="ta-btn ta-btn-primary px-3" @click="customizeAgreementTerms">
                                <PencilLine class="h-4 w-4" />
                                {{ t('contractsAdmin.customizeTerms', 'Customize terms') }}
                            </button>
                            <button v-else-if="form.pricing_source === 'package'" type="button" class="ta-btn ta-btn-secondary px-3" @click="resetPackageTerms">
                                <RefreshCw class="h-4 w-4" />
                                {{ t('contractsAdmin.resetPackageTerms', 'Use package terms') }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                            <p class="text-xs font-semibold text-gray-500">{{ t('contractsAdmin.summarySource', 'Source') }}</p>
                            <p class="mt-1 text-sm font-bold text-gray-900">{{ agreementSourceLabel }}</p>
                        </div>
                        <div v-for="item in agreementSummaryItems" :key="item.label" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                            <p class="text-xs font-semibold text-gray-500">{{ item.label }}</p>
                            <p class="mt-1 text-sm font-bold text-gray-900">{{ item.value }}</p>
                        </div>
                    </div>
                </section>

                <section v-if="! agreementTermsLocked" class="rounded-xl border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.pricingScope', 'Agreement pricing') }}</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.pricingModel', 'Pricing model') }}</span>
                            <select v-model="form.pricing_model" class="ta-input h-11 w-full px-4 text-sm">
                                <option v-for="option in catalog.pricingModels" :key="option.key" :value="option.key">
                                    {{ catalogLabel('pricingModels', option.key) }}
                                </option>
                            </select>
                            <span v-if="form.errors.pricing_model" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.pricing_model }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.agreedWorkers', 'Agreed workers') }}</span>
                            <input v-model.number="form.agreed_workers" type="number" min="1" class="ta-input h-11 w-full px-4 text-sm" @change="recalculatePlannedWeeklyMinutes">
                            <span v-if="form.errors.agreed_workers" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.agreed_workers }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.visitsPerWeek', 'Visits per week') }}</span>
                            <input v-model.number="form.visits_per_week" type="number" min="1" max="7" class="ta-input h-11 w-full px-4 text-sm" @change="recalculatePlannedWeeklyMinutes">
                            <span v-if="form.errors.visits_per_week" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.visits_per_week }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.hoursPerVisit', 'Hours per visit') }}</span>
                            <input v-model="form.hours_per_visit" type="number" min="0.25" max="24" step="0.25" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" @change="recalculatePlannedWeeklyMinutes">
                            <span v-if="form.errors.hours_per_visit" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.hours_per_visit }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.plannedWeeklyMinutes', 'Planned weekly minutes') }}</span>
                            <input v-model.number="form.planned_weekly_minutes" type="number" min="0" class="ta-input h-11 w-full px-4 text-sm">
                            <span v-if="form.errors.planned_weekly_minutes" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.planned_weekly_minutes }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.extraHourRate', 'Extra hour rate (SAR)') }}</span>
                            <input v-model="form.extra_hour_rate_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" placeholder="110.25">
                            <span v-if="form.errors.extra_hour_rate_sar" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.extra_hour_rate_sar }}</span>
                        </label>
                    </div>
                </section>

                <section v-if="! agreementTermsLocked" class="rounded-xl border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.materialAgreement', 'Material agreement') }}</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="flex min-h-11 items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                            <span class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.includedMaterials', 'Included materials') }}</span>
                            <input v-model="form.included_materials" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500">
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.materialPolicy', 'Material policy') }}</span>
                            <select v-model="form.material_policy" class="ta-input h-11 w-full px-4 text-sm">
                                <option v-for="option in catalog.materialPolicies" :key="option.key" :value="option.key">
                                    {{ catalogLabel('materialPolicies', option.key) }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.estimatedMaterialCost', 'Estimated material cost (SAR)') }}</span>
                            <input v-model="form.estimated_material_cost_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" placeholder="275.50">
                            <span v-if="form.errors.estimated_material_cost_sar" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.estimated_material_cost_sar }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.overtimePolicy', 'Overtime policy') }}</span>
                            <select v-model="form.overtime_policy" class="ta-input h-11 w-full px-4 text-sm">
                                <option v-for="option in catalog.overtimePolicies" :key="option.key" :value="option.key">
                                    {{ catalogLabel('overtimePolicies', option.key) }}
                                </option>
                            </select>
                        </label>
                    </div>
                </section>

                <section v-if="! agreementTermsLocked" class="space-y-3 rounded-xl border border-gray-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.serviceScope', 'Service scope') }}</h3>
                        <button type="button" class="ta-btn ta-btn-secondary px-3" @click="addScope">
                            <Plus class="h-4 w-4" />
                        </button>
                    </div>

                    <fieldset v-for="(item, index) in form.service_scope" :key="`scope-${index}`" class="space-y-3 rounded-lg border border-gray-100 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <legend class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.scopeArea', 'Area') }} {{ index + 1 }}</legend>
                            <button type="button" class="ta-btn ta-btn-secondary h-9 w-9 p-0" @click="removeScope(index)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.areaName', 'Area name') }}</span>
                                <input v-model="item.area" type="text" class="ta-input h-11 w-full px-4 text-sm" placeholder="Reception">
                                <span v-if="errorFor(`service_scope.${index}.area`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`service_scope.${index}.area`) }}</span>
                            </label>

                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.areaTasks', 'Tasks') }}</span>
                                <input v-model="item.tasks" type="text" class="ta-input h-11 w-full px-4 text-sm" placeholder="Sweep, mop, sanitize counters">
                            </label>
                        </div>
                    </fieldset>
                </section>

                <label v-if="! agreementTermsLocked" class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.termsAndConditions', 'Terms and conditions') }}</span>
                    <textarea v-model="form.terms_and_conditions" class="ta-input min-h-28 w-full px-4 py-3 text-sm" />
                </label>
            </div>

            <div v-else-if="currentStepKey === 'payment_plan'" class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.paymentPlan', 'Payment plan') }}</h3>
                        <p class="mt-0.5 text-xs font-semibold" :class="paymentTotal === 100 ? 'text-success-600' : 'text-error-600'">
                            {{ t('contractsAdmin.paymentTotal', 'Total') }} {{ paymentTotal }}%
                        </p>
                    </div>
                    <button type="button" class="ta-btn ta-btn-secondary px-3" @click="addPaymentPlan">
                        <Plus class="h-4 w-4" />
                    </button>
                </div>
                <span v-if="form.errors.payment_plan" class="block text-xs font-semibold text-error-600">{{ form.errors.payment_plan }}</span>

                <fieldset v-for="(item, index) in form.payment_plan" :key="`payment-${index}`" class="space-y-3 rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <legend class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.installment', 'Installment') }} {{ index + 1 }}</legend>
                        <button type="button" class="ta-btn ta-btn-secondary h-9 w-9 p-0" @click="removePaymentPlan(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.label', 'Label') }}</span>
                            <input v-model="item.label" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.day', 'Day') }}</span>
                            <input v-model.number="item.day" type="number" min="1" max="31" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.percent', 'Percent') }}</span>
                            <input v-model.number="item.percent" type="number" min="1" max="100" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                    </div>
                </fieldset>
            </div>

            <div v-else-if="currentStepKey === 'addendums'" class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.addendums', 'Addendums') }}</h3>
                    <button type="button" class="ta-btn ta-btn-secondary px-3" @click="addAddendum">
                        <Plus class="h-4 w-4" />
                    </button>
                </div>

                <fieldset v-for="(item, index) in form.addendums" :key="`addendum-${index}-${item.id ?? 'new'}`" class="space-y-3 rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <legend class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.addendum', 'Addendum') }} {{ item.number }}</legend>
                        <button type="button" class="ta-btn ta-btn-secondary h-9 w-9 p-0" @click="removeAddendum(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.number', 'Number') }}</span>
                            <input v-model.number="item.number" type="number" min="1" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.effectiveOn', 'Effective on') }}</span>
                            <input v-model="item.effective_on" type="date" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.titleField', 'Title') }}</span>
                            <input v-model="item.title" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('contractsAdmin.summary', 'Summary') }}</span>
                            <textarea v-model="item.summary" class="ta-input min-h-20 w-full px-4 py-3 text-sm" />
                        </label>
                    </div>
                </fieldset>

                <p v-if="form.addendums.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                    {{ t('contractsAdmin.noAddendums', 'No addendums yet.') }}
                </p>
            </div>

            <div v-else class="space-y-5">
                <section class="rounded-xl border border-brand-500/20 bg-gradient-to-br from-brand-50 via-white to-success-50 p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-bold text-gray-900">{{ t('contractsAdmin.reviewBeforeSave', 'Review before saving') }}</h3>
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold"
                                    :class="isCustomAgreement ? 'border-warning-500/20 bg-warning-50 text-warning-600' : 'border-success-500/20 bg-success-50 text-success-600'"
                                >
                                    {{ agreementBadgeLabel }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ selectedCustomer?.name ?? '-' }} / {{ selectedSiteText || '-' }}
                            </p>
                        </div>
                        <div class="text-start lg:text-end">
                            <p class="text-xs font-bold uppercase text-gray-500">{{ t('contractsAdmin.totalCustomerPrice', 'Total customer price') }}</p>
                            <p class="mt-1 text-3xl font-bold text-gray-900">{{ money(priceBreakdown.grossHalalas) }}</p>
                            <p class="mt-1 text-xs font-semibold text-gray-500">
                                {{ catalogLabel('billingCycles', form.billing_cycle) }} / {{ priceBreakdown.vatMode }}
                            </p>
                        </div>
                    </div>
                </section>

                <div class="grid gap-5 lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="rounded-xl border border-gray-200 p-4">
                        <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.priceBreakdown', 'Price breakdown') }}</h3>
                        <div class="mt-4 space-y-3 text-sm">
                            <div v-for="line in reviewPriceLines" :key="line.label" class="flex items-start justify-between gap-4">
                                <span class="text-gray-500">
                                    {{ line.label }}
                                    <span v-if="line.note" class="mt-0.5 block text-xs text-gray-400">{{ line.note }}</span>
                                </span>
                                <span class="shrink-0 font-bold text-gray-900">{{ line.value }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-gray-100 pt-3">
                                <span class="font-semibold text-gray-700">{{ t('contractsAdmin.fixedChargeSubtotal', 'Fixed charge subtotal') }}</span>
                                <span class="font-bold text-gray-900">{{ money(fixedChargeSubtotalHalalas) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-gray-500">{{ t('contractsAdmin.netBeforeVat', 'Net before VAT') }}</span>
                                <span class="font-bold text-gray-900">{{ money(priceBreakdown.netHalalas) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-gray-500">{{ t('contractsAdmin.vatAmount', 'VAT amount') }} ({{ form.vat_rate }}%)</span>
                                <span class="font-bold text-gray-900">{{ money(priceBreakdown.vatHalalas) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 rounded-lg bg-gray-50 px-3 py-3">
                                <span class="font-bold text-gray-900">{{ t('contractsAdmin.totalWithVat', 'Total with VAT') }}</span>
                                <span class="text-lg font-bold text-brand-600">{{ money(priceBreakdown.grossHalalas) }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 p-4">
                        <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.contractSnapshot', 'Contract snapshot') }}</h3>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div v-for="line in reviewCommercialLines" :key="line.label" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                <p class="text-xs font-semibold text-gray-500">{{ line.label }}</p>
                                <p class="mt-1 text-sm font-bold text-gray-900">{{ line.value }}</p>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.paymentPlanBreakdown', 'Payment plan breakdown') }}</h3>
                        <p class="text-xs font-bold" :class="paymentTotal === 100 ? 'text-success-600' : 'text-error-600'">
                            {{ t('contractsAdmin.paymentTotal', 'Total') }} {{ paymentTotal }}%
                        </p>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="(item, index) in paymentPlanPreview" :key="`${item.label}-${index}`" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ item.label }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('contractsAdmin.day', 'Day') }} {{ item.day }} / {{ item.percent }}%</p>
                                </div>
                                <p class="text-sm font-bold text-brand-600">{{ money(item.amountHalalas) }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.workloadAndCostAssumptions', 'Workload and cost assumptions') }}</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div v-for="line in reviewWorkloadLines" :key="line.label" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                            <p class="text-xs font-semibold text-gray-500">{{ line.label }}</p>
                            <p class="mt-1 text-sm font-bold text-gray-900">{{ line.value }}</p>
                        </div>
                    </div>
                </section>

                <div class="grid gap-5 lg:grid-cols-2">
                    <section class="rounded-xl border border-gray-200 p-4">
                        <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.serviceScope', 'Service scope') }}</h3>
                        <div class="mt-3 space-y-2">
                            <div v-for="(item, index) in form.service_scope.filter((scope) => scope.area.trim().length > 0)" :key="`review-scope-${index}`" class="rounded-lg bg-gray-50 px-3 py-2">
                                <p class="text-sm font-bold text-gray-900">{{ item.area }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ item.tasks || t('contractsAdmin.noTasksListed', 'No tasks listed') }}</p>
                            </div>
                            <p v-if="form.service_scope.filter((scope) => scope.area.trim().length > 0).length === 0" class="text-sm text-gray-500">
                                {{ t('contractsAdmin.noScopeItems', 'No scope items listed.') }}
                            </p>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 p-4">
                        <h3 class="text-sm font-semibold text-gray-800">{{ t('contractsAdmin.addendums', 'Addendums') }}</h3>
                        <div class="mt-3 space-y-2">
                            <div v-for="item in form.addendums.filter((addendum) => addendum.title.trim().length > 0)" :key="`review-addendum-${item.number}-${item.title}`" class="rounded-lg bg-gray-50 px-3 py-2">
                                <p class="text-sm font-bold text-gray-900">{{ item.number }}. {{ item.title }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ item.effective_on || '-' }}</p>
                            </div>
                            <p v-if="form.addendums.filter((addendum) => addendum.title.trim().length > 0).length === 0" class="text-sm text-gray-500">
                                {{ t('contractsAdmin.noAddendums', 'No addendums yet.') }}
                            </p>
                        </div>
                    </section>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" class="ta-btn ta-btn-secondary" :disabled="isFirstStep" @click="goToStep(currentStep - 1)">
                    <ChevronLeft class="h-4 w-4 rtl:rotate-180" />
                    {{ t('contractsAdmin.previousStep', 'Previous') }}
                </button>

                <button v-if="! isLastStep" type="button" class="ta-btn ta-btn-primary sm:ms-auto" @click="goToStep(currentStep + 1)">
                    {{ t('contractsAdmin.nextStep', 'Next') }}
                    <ChevronRight class="h-4 w-4 rtl:rotate-180" />
                </button>

                <button v-else type="submit" class="ta-btn ta-btn-primary sm:ms-auto" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('contractsAdmin.saveContract', 'Save contract') }}
                </button>
            </div>
        </form>
    </section>
</template>
