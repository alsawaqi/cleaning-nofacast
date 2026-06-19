<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BadgeCheck,
    ChevronLeft,
    ChevronRight,
    FileText,
    GraduationCap,
    HardHat,
    Plus,
    Save,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from '../../lib/i18n';
import type { TrainingRecord, Worker, WorkerDocument } from '../../types';

type CatalogOption = {
    key: string;
    label: string;
};

type Catalog = {
    statuses: CatalogOption[];
    jobRoles: CatalogOption[];
    skills: CatalogOption[];
    certifications: CatalogOption[];
    documentTypes: CatalogOption[];
    languages: CatalogOption[];
};

type Step = {
    key: 'profile' | 'skills' | 'documents' | 'training';
    label: string;
};

type DocumentForm = {
    id: number | null;
    document_type: string;
    document_number: string;
    expires_on: string;
    file_path: string;
};

type TrainingForm = {
    id: number | null;
    course_name: string;
    certificate_code: string;
    completed_on: string;
    expires_on: string;
};

type WorkerForm = {
    user_id: number | null;
    employee_code: string;
    name: string;
    phone: string;
    hired_on: string;
    nationality: string;
    role_language: string;
    job_role: string;
    status: string;
    cost_rate_sar: string;
    skills: string[];
    certifications: string[];
    availability_notes: string;
    documents: DocumentForm[];
    training_records: TrainingForm[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    worker: Worker | null;
    catalog: Catalog;
    steps: Step[];
    submitUrl: string;
    backUrl: string;
}>();

const { t } = useI18n();
const currentStep = ref(0);

const stepFields: Record<Step['key'], string[]> = {
    profile: ['employee_code', 'name', 'phone', 'hired_on', 'nationality', 'role_language', 'job_role', 'status', 'cost_rate_sar', 'availability_notes'],
    skills: ['skills', 'certifications'],
    documents: ['documents'],
    training: ['training_records'],
};

function blankDocument(): DocumentForm {
    return {
        id: null,
        document_type: 'iqama',
        document_number: '',
        expires_on: '',
        file_path: '',
    };
}

function blankTraining(): TrainingForm {
    return {
        id: null,
        course_name: '',
        certificate_code: '',
        completed_on: '',
        expires_on: '',
    };
}

function documentToForm(document: WorkerDocument): DocumentForm {
    return {
        id: document.id,
        document_type: document.document_type,
        document_number: document.document_number ?? '',
        expires_on: document.expires_on ?? '',
        file_path: document.file_path ?? '',
    };
}

function trainingToForm(record: TrainingRecord): TrainingForm {
    return {
        id: record.id,
        course_name: record.course_name,
        certificate_code: record.certificate_code ?? '',
        completed_on: record.completed_on ?? '',
        expires_on: record.expires_on ?? '',
    };
}

function defaultFormData(): WorkerForm {
    return {
        user_id: props.worker?.user_id ?? null,
        employee_code: props.worker?.employee_code ?? '',
        name: props.worker?.name ?? '',
        phone: props.worker?.phone ?? '',
        hired_on: props.worker?.hired_on ?? '',
        nationality: props.worker?.nationality ?? '',
        role_language: props.worker?.role_language ?? 'ar',
        job_role: props.worker?.job_role ?? 'cleaner',
        status: props.worker?.status ?? 'available',
        cost_rate_sar: props.worker?.cost_rate_sar ?? '0.00',
        skills: props.worker?.skills ?? ['general_cleaning'],
        certifications: props.worker?.certifications ?? [],
        availability_notes: props.worker?.availability_notes ?? '',
        documents: props.worker?.documents.length ? props.worker.documents.map(documentToForm) : [blankDocument()],
        training_records: props.worker?.training_records.length ? props.worker.training_records.map(trainingToForm) : [blankTraining()],
    };
}

const form = useForm<WorkerForm>(defaultFormData());

const pageTitle = computed(() => props.mode === 'edit'
    ? t('workersAdmin.updateWorker', 'Update worker')
    : t('workersAdmin.newWorker', 'New worker'));

const currentStepKey = computed(() => props.steps[currentStep.value]?.key ?? 'profile');
const isFirstStep = computed(() => currentStep.value === 0);
const isLastStep = computed(() => currentStep.value === props.steps.length - 1);

function stepLabel(step: Step): string {
    return t(`workersAdmin.steps.${step.key}`, step.label);
}

function catalogLabel(group: keyof Catalog, key: string): string {
    const option = props.catalog[group].find((item) => item.key === key);

    return t(`workersAdmin.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
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

function nextStep(): void {
    goToStep(currentStep.value + 1);
}

function previousStep(): void {
    goToStep(currentStep.value - 1);
}

function addDocument(): void {
    form.documents.push(blankDocument());
}

function removeDocument(index: number): void {
    form.documents.splice(index, 1);

    if (form.documents.length === 0) {
        form.documents.push(blankDocument());
    }
}

function addTraining(): void {
    form.training_records.push(blankTraining());
}

function removeTraining(index: number): void {
    form.training_records.splice(index, 1);

    if (form.training_records.length === 0) {
        form.training_records.push(blankTraining());
    }
}

function buildPayload(data: WorkerForm) {
    return {
        user_id: data.user_id,
        employee_code: data.employee_code,
        name: data.name,
        phone: data.phone,
        hired_on: data.hired_on || null,
        nationality: data.nationality,
        role_language: data.role_language,
        job_role: data.job_role,
        status: data.status,
        cost_rate_sar: data.cost_rate_sar,
        skills: data.skills,
        certifications: data.certifications,
        availability_notes: data.availability_notes,
        documents: data.documents
            .filter((document) => document.document_number.trim() || document.expires_on || document.file_path.trim())
            .map((document) => ({
                id: document.id,
                document_type: document.document_type,
                document_number: document.document_number,
                expires_on: document.expires_on || null,
                file_path: document.file_path,
            })),
        training_records: data.training_records
            .filter((record) => record.course_name.trim())
            .map((record) => ({
                id: record.id,
                course_name: record.course_name,
                certificate_code: record.certificate_code,
                completed_on: record.completed_on || null,
                expires_on: record.expires_on || null,
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
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <Link :href="backUrl" class="mb-3 inline-flex items-center gap-2 text-sm font-semibold text-brand-600">
                    <ArrowLeft class="h-4 w-4 rtl:rotate-180" />
                    {{ t('workersAdmin.backToWorkers', 'Back to workers') }}
                </Link>
                <h1 class="ta-page-title">{{ pageTitle }}</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ t('workersAdmin.formSubtitle', 'Worker profile, eligibility, documents, and training records.') }}
                </p>
            </div>
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
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <div class="ta-icon-box">
                        <HardHat v-if="currentStepKey === 'profile'" class="h-6 w-6" />
                        <BadgeCheck v-else-if="currentStepKey === 'skills'" class="h-6 w-6" />
                        <FileText v-else-if="currentStepKey === 'documents'" class="h-6 w-6" />
                        <GraduationCap v-else class="h-6 w-6" />
                    </div>
                    <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ stepLabel(steps[currentStep]) }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('workersAdmin.stepCount', 'Step :current of :total', { current: currentStep + 1, total: steps.length }) }}
                    </p>
                </div>
            </div>

            <div v-if="currentStepKey === 'profile'" class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.employeeCode', 'Employee code') }}</span>
                        <input v-model="form.employee_code" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        <span v-if="form.errors.employee_code" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.employee_code }}</span>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.name', 'Name') }}</span>
                        <input v-model="form.name" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        <span v-if="form.errors.name" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.name }}</span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.phone', 'Phone') }}</span>
                        <input v-model="form.phone" type="text" class="ta-input h-11 w-full px-4 text-sm">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.status', 'Status') }}</span>
                        <select v-model="form.status" class="ta-input h-11 w-full px-4 text-sm">
                            <option v-for="option in catalog.statuses" :key="option.key" :value="option.key">
                                {{ catalogLabel('statuses', option.key) }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.jobRole', 'Job role') }}</span>
                        <select v-model="form.job_role" class="ta-input h-11 w-full px-4 text-sm">
                            <option v-for="option in catalog.jobRoles" :key="option.key" :value="option.key">
                                {{ catalogLabel('jobRoles', option.key) }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.roleLanguage', 'Role language') }}</span>
                        <select v-model="form.role_language" class="ta-input h-11 w-full px-4 text-sm">
                            <option v-for="option in catalog.languages" :key="option.key" :value="option.key">
                                {{ catalogLabel('languages', option.key) }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.costRate', 'Cost rate (SAR)') }}</span>
                        <input v-model="form.cost_rate_sar" type="number" min="0" step="0.01" inputmode="decimal" class="ta-input h-11 w-full px-4 text-sm" placeholder="1800.50">
                        <span v-if="form.errors.cost_rate_sar" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.cost_rate_sar }}</span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.hiredOn', 'Hired on') }}</span>
                        <input v-model="form.hired_on" type="date" class="ta-input h-11 w-full px-4 text-sm">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.nationality', 'Nationality') }}</span>
                        <input v-model="form.nationality" type="text" class="ta-input h-11 w-full px-4 text-sm">
                    </label>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.availabilityNotes', 'Availability notes') }}</span>
                    <textarea v-model="form.availability_notes" class="ta-input min-h-24 w-full px-4 py-3 text-sm" />
                </label>
            </div>

            <div v-else-if="currentStepKey === 'skills'" class="grid gap-6 xl:grid-cols-2">
                <section>
                    <h3 class="mb-3 text-sm font-semibold text-gray-800">{{ t('workersAdmin.skills', 'Skills') }}</h3>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="option in catalog.skills" :key="option.key" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <input v-model="form.skills" type="checkbox" :value="option.key" class="h-4 w-4 rounded border-gray-300 text-brand-500">
                            <span>{{ catalogLabel('skills', option.key) }}</span>
                        </label>
                    </div>
                </section>

                <section>
                    <h3 class="mb-3 text-sm font-semibold text-gray-800">{{ t('workersAdmin.certifications', 'Certifications') }}</h3>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="option in catalog.certifications" :key="option.key" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <input v-model="form.certifications" type="checkbox" :value="option.key" class="h-4 w-4 rounded border-gray-300 text-brand-500">
                            <span>{{ catalogLabel('certifications', option.key) }}</span>
                        </label>
                    </div>
                </section>
            </div>

            <div v-else-if="currentStepKey === 'documents'" class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('workersAdmin.documents', 'Documents') }}</h3>
                    <button type="button" class="ta-btn ta-btn-secondary px-3" @click="addDocument">
                        <Plus class="h-4 w-4" />
                    </button>
                </div>

                <fieldset v-for="(document, index) in form.documents" :key="`document-${index}-${document.id ?? 'new'}`" class="space-y-3 rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <legend class="text-sm font-semibold text-gray-800">{{ t('workersAdmin.document', 'Document') }} {{ index + 1 }}</legend>
                        <button type="button" class="ta-btn ta-btn-secondary h-9 w-9 p-0" @click="removeDocument(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.documentType', 'Document type') }}</span>
                            <select v-model="document.document_type" class="ta-input h-11 w-full px-4 text-sm">
                                <option v-for="option in catalog.documentTypes" :key="option.key" :value="option.key">
                                    {{ catalogLabel('documentTypes', option.key) }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.documentNumber', 'Document number') }}</span>
                            <input v-model="document.document_number" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.expiresOn', 'Expires on') }}</span>
                            <input v-model="document.expires_on" type="date" class="ta-input h-11 w-full px-4 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.filePath', 'File path') }}</span>
                            <input v-model="document.file_path" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                    </div>
                </fieldset>
            </div>

            <div v-else class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-800">{{ t('workersAdmin.training', 'Training') }}</h3>
                    <button type="button" class="ta-btn ta-btn-secondary px-3" @click="addTraining">
                        <Plus class="h-4 w-4" />
                    </button>
                </div>

                <fieldset v-for="(record, index) in form.training_records" :key="`training-${index}-${record.id ?? 'new'}`" class="space-y-3 rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <legend class="text-sm font-semibold text-gray-800">{{ t('workersAdmin.trainingRecord', 'Training record') }} {{ index + 1 }}</legend>
                        <button type="button" class="ta-btn ta-btn-secondary h-9 w-9 p-0" @click="removeTraining(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.courseName', 'Course name') }}</span>
                            <input v-model="record.course_name" type="text" class="ta-input h-11 w-full px-4 text-sm">
                            <span v-if="errorFor(`training_records.${index}.course_name`)" class="mt-1 block text-xs font-semibold text-error-600">{{ errorFor(`training_records.${index}.course_name`) }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.certificateCode', 'Certificate code') }}</span>
                            <input v-model="record.certificate_code" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.completedOn', 'Completed on') }}</span>
                            <input v-model="record.completed_on" type="date" class="ta-input h-11 w-full px-4 text-sm">
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('workersAdmin.expiresOn', 'Expires on') }}</span>
                            <input v-model="record.expires_on" type="date" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                    </div>
                </fieldset>
            </div>

            <div class="mt-6 flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" class="ta-btn ta-btn-secondary" :disabled="isFirstStep" @click="previousStep">
                    <ChevronLeft class="h-4 w-4 rtl:rotate-180" />
                    {{ t('workersAdmin.previousStep', 'Previous') }}
                </button>

                <button v-if="! isLastStep" type="button" class="ta-btn ta-btn-primary sm:ms-auto" @click="nextStep">
                    {{ t('workersAdmin.nextStep', 'Next') }}
                    <ChevronRight class="h-4 w-4 rtl:rotate-180" />
                </button>

                <button v-else type="submit" class="ta-btn ta-btn-primary sm:ms-auto" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('workersAdmin.saveWorker', 'Save worker') }}
                </button>
            </div>
        </form>
    </section>
</template>
