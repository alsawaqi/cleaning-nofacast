<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    BarChart3,
    BriefcaseBusiness,
    Eye,
    FileText,
    GraduationCap,
    HardHat,
    Pencil,
    Plus,
    ShieldAlert,
    UserCheck,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import MoneyText from '../../Components/MoneyText.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type { Worker } from '../../types';

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

const props = defineProps<{
    workers: Worker[];
    catalog: Catalog;
    metrics: {
        total: number;
        available: number;
        assigned: number;
        expiringDocuments: number;
    };
    createUrl: string;
    statusUrl: string;
}>();

const { t } = useI18n();

const metricCards = computed(() => [
    { label: t('workersAdmin.totalWorkers', 'Total workers'), value: props.metrics.total, icon: UsersRound, tone: 'bg-brand-50 text-brand-600' },
    { label: t('workersAdmin.availableWorkers', 'Available'), value: props.metrics.available, icon: UserCheck, tone: 'bg-success-50 text-success-600' },
    { label: t('workersAdmin.assignedWorkers', 'Assigned'), value: props.metrics.assigned, icon: BriefcaseBusiness, tone: 'bg-warning-50 text-warning-600' },
    { label: t('workersAdmin.expiringDocuments', 'Expiring documents'), value: props.metrics.expiringDocuments, icon: ShieldAlert, tone: 'bg-error-50 text-error-600' },
]);

function catalogLabel(group: keyof Catalog, key: string): string {
    const option = props.catalog[group].find((item) => item.key === key);

    return t(`workersAdmin.catalog.${group}.${key}`, option?.label ?? key.replaceAll('_', ' '));
}

function expiryTone(status: string): string {
    const tones: Record<string, string> = {
        valid: 'bg-success-50 text-success-600',
        expiring: 'bg-warning-50 text-warning-600',
        expired: 'bg-error-50 text-error-600',
        not_tracked: 'bg-gray-100 text-gray-500',
    };

    return tones[status] ?? tones.not_tracked;
}
</script>

<template>
    <Head :title="t('layout.workers', 'Workers')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('workersAdmin.title', 'Workers & compliance') }}</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ t('workersAdmin.subtitle', 'Manage worker profiles, service skills, document expiry, and training certificates before assignments.') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link :href="statusUrl" class="ta-btn ta-btn-secondary">
                    <BarChart3 class="h-4 w-4" />
                    {{ t('workersAdmin.statusReport', 'Status report') }}
                </Link>
                <Link :href="createUrl" class="ta-btn ta-btn-primary">
                    <Plus class="h-4 w-4" />
                    {{ t('workersAdmin.newWorker', 'New worker') }}
                </Link>
            </div>
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
            <article v-for="worker in workers" :key="worker.id" class="ta-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-gray-900">{{ worker.name }}</h2>
                            <StatusBadge :status="worker.status" />
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ worker.employee_code }} / {{ catalogLabel('jobRoles', worker.job_role) }}
                        </p>
                        <p class="mt-2 text-sm text-gray-500">{{ worker.phone || t('workersAdmin.noPhone', 'No phone') }}</p>
                    </div>

                    <div class="flex gap-2">
                        <Link :href="worker.detail_url ?? `/app/workers/${worker.id}`" class="ta-btn ta-btn-secondary h-10 w-10 p-0" :aria-label="t('workersAdmin.viewDetails', 'View details')">
                            <Eye class="h-4 w-4" />
                        </Link>
                        <Link :href="worker.edit_url ?? `/app/workers/${worker.id}/edit`" class="ta-btn ta-btn-secondary h-10 w-10 p-0" :aria-label="t('workersAdmin.edit', 'Edit')">
                            <Pencil class="h-4 w-4" />
                        </Link>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.assignments', 'Assignments') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ worker.assignments_count ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.visits', 'Visits') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ worker.visits_count ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.docsShort', 'Docs') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ worker.compliance.documents }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-400">{{ t('workersAdmin.costRate', 'Cost rate (SAR)') }}</p>
                        <p class="mt-2 text-sm font-bold text-gray-900"><MoneyText :value="worker.cost_rate_halalas" /></p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <section class="rounded-xl bg-gray-50 p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <BadgeCheck class="h-4 w-4 text-brand-500" />
                            {{ t('workersAdmin.skills', 'Skills') }}
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="skill in worker.skills.slice(0, 4)" :key="skill" class="rounded-md bg-white px-2 py-1 text-xs font-medium text-gray-600">
                                {{ catalogLabel('skills', skill) }}
                            </span>
                            <span v-if="worker.skills.length === 0" class="rounded-md bg-white px-2 py-1 text-xs font-medium text-gray-500">
                                {{ t('workersAdmin.none', 'None') }}
                            </span>
                        </div>
                    </section>

                    <section class="rounded-xl bg-gray-50 p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <FileText class="h-4 w-4 text-brand-500" />
                            {{ t('workersAdmin.documents', 'Documents') }}
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="rounded-md bg-white px-2 py-1 text-xs font-medium text-gray-600">
                                {{ worker.compliance.documents }} {{ t('workersAdmin.docsShort', 'Docs') }}
                            </span>
                            <span v-if="worker.compliance.expiring_documents" class="rounded-md px-2 py-1 text-xs font-semibold" :class="expiryTone('expiring')">
                                {{ worker.compliance.expiring_documents }} {{ t('workersAdmin.expiry.expiring', 'Expiring') }}
                            </span>
                            <span v-if="worker.compliance.expired_documents" class="rounded-md px-2 py-1 text-xs font-semibold" :class="expiryTone('expired')">
                                {{ worker.compliance.expired_documents }} {{ t('workersAdmin.expiry.expired', 'Expired') }}
                            </span>
                        </div>
                    </section>

                    <section class="rounded-xl bg-gray-50 p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <GraduationCap class="h-4 w-4 text-brand-500" />
                            {{ t('workersAdmin.training', 'Training') }}
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="rounded-md bg-white px-2 py-1 text-xs font-medium text-gray-600">
                                {{ worker.compliance.training_records }} {{ t('workersAdmin.training', 'Training') }}
                            </span>
                            <span v-if="worker.compliance.certifications" class="rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-600">
                                {{ worker.compliance.certifications }} {{ t('workersAdmin.certifications', 'Certifications') }}
                            </span>
                        </div>
                    </section>
                </div>
            </article>

            <article v-if="workers.length === 0" class="ta-card px-6 py-14 text-center xl:col-span-2">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-500">
                    <HardHat class="h-6 w-6" />
                </div>
                <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ t('workersAdmin.emptyTitle', 'No workers yet') }}</h2>
            </article>
        </div>
    </section>
</template>
