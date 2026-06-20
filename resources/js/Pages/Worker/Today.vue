<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Camera, CheckCircle2, ChevronRight, ClipboardCheck, Clock, FileText, Languages, MapPin, Navigation, Package, Phone, Play, Square, TimerReset } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type { AppSharedProps, ChecklistItem, Visit } from '../../types';

type WorkerTodaySummary = {
    total_visits: number;
    scheduled_visits: number;
    in_progress_visits: number;
    completed_visits: number;
    issue_visits: number;
    required_tasks: number;
    completed_required_tasks: number;
    open_required_tasks: number;
    total_tasks: number;
    completed_tasks: number;
};

const props = defineProps<{
    worker: { id: number; name: string; employee_code: string; status: string } | null;
    summary: WorkerTodaySummary;
    activeVisitId?: number | null;
    nextVisitId?: number | null;
    visits: Visit[];
}>();

const { locale, t } = useI18n();
const page = usePage<AppSharedProps>();
const selectedVisitId = ref<number | null>(props.activeVisitId ?? props.nextVisitId ?? props.visits[0]?.id ?? null);
const activePanel = ref<'overview' | 'tasks' | 'evidence' | 'issue'>('overview');

type VisitForm = {
    completedItems: number[];
    skippedItems: number[];
    taskNotes: Record<number, string>;
    taskPhotos: Record<number, string>;
    taskPhotoFiles: Record<number, File | null>;
    materialLines: string;
    photosText: string;
    photoFiles: File[];
    executionNotes: string;
    issueNote: string;
    issuePhotosText: string;
    issuePhotoFiles: File[];
    locationMessage: string;
    processing: boolean;
};
type WorkerPayload = Parameters<typeof router.post>[1];

const forms = reactive<Record<number, VisitForm>>({});

const selectedVisit = computed(() => props.visits.find((visit) => visit.id === selectedVisitId.value) ?? props.visits[0]);
const panels = computed(() => [
    { key: 'overview' as const, label: t('workerPortal.panels.overview', 'Overview'), icon: Clock },
    { key: 'tasks' as const, label: t('workerPortal.panels.tasks', 'Tasks'), icon: ClipboardCheck },
    { key: 'evidence' as const, label: t('workerPortal.panels.evidence', 'Evidence'), icon: Camera },
    { key: 'issue' as const, label: t('workerPortal.panels.issue', 'Issue'), icon: AlertTriangle },
]);
const success = computed(() => page.props.flash?.success);

watch(selectedVisitId, () => {
    activePanel.value = selectedVisit.value?.timer_state === 'running' ? 'tasks' : 'overview';
});

function checklist(visit: Visit): ChecklistItem[] {
    return visit.checklist_items ?? visit.checklistItems ?? [];
}

function formFor(visit: Visit): VisitForm {
    if (!forms[visit.id]) {
        forms[visit.id] = {
            completedItems: checklist(visit)
                .filter((item) => item.status === 'done')
                .map((item) => item.id),
            skippedItems: checklist(visit)
                .filter((item) => item.status === 'skipped')
                .map((item) => item.id),
            taskNotes: Object.fromEntries(checklist(visit).map((item) => [item.id, item.notes ?? ''])),
            taskPhotos: Object.fromEntries(checklist(visit).map((item) => [item.id, item.photo_path ?? ''])),
            taskPhotoFiles: Object.fromEntries(checklist(visit).map((item) => [item.id, null])),
            materialLines: (visit.materials_used ?? [])
                .map((item) => [item.name, item.quantity, item.cost_sar ?? '0.00'].filter(Boolean).join(' | '))
                .join('\n'),
            photosText: (visit.photos ?? []).join('\n'),
            photoFiles: [],
            executionNotes: visit.execution_notes ?? '',
            issueNote: visit.issue_note ?? '',
            issuePhotosText: '',
            issuePhotoFiles: [],
            locationMessage: '',
            processing: false,
        };
    }

    return forms[visit.id];
}

function siteName(visit: Visit): string {
    return typeof visit.site === 'string' ? visit.site : (visit.site?.name ?? t('workerPortal.site', 'Site'));
}

function customerName(visit: Visit): string {
    return typeof visit.site === 'string' ? (visit.customer ?? '') : (visit.site?.customer?.name ?? visit.customer ?? '');
}

function serviceTitle(visit: Visit): string {
    return typeof visit.service === 'string' ? visit.service : (visit.service?.title ?? t('workerPortal.service', 'Service'));
}

function siteAddress(visit: Visit): string {
    if (typeof visit.site === 'string') {
        return visit.site;
    }

    return visit.site?.formatted_address
        ?? [visit.site?.address, visit.site?.district, visit.site?.city].filter(Boolean).join(', ')
        ?? '';
}

function formatTime(time?: string | null): string {
    return time?.slice(0, 5) ?? '--:--';
}

function formatMinutes(minutes?: number | null): string {
    const value = Number(minutes ?? 0);
    const hours = Math.floor(value / 60);
    const mins = value % 60;

    if (hours === 0) {
        return t('workerPortal.minutesOnly', ':minutesm', { minutes: mins });
    }

    return t('workerPortal.hoursMinutes', ':hoursh :minutesm', { hours, minutes: mins });
}

function timerStateLabel(state?: string | null): string {
    if (state === 'running') {
        return t('workerPortal.timerRunning', 'Timer running');
    }

    if (state === 'finished') {
        return t('workerPortal.finished', 'Finished');
    }

    return t('workerPortal.ready', 'Ready');
}

function progressPercent(visit: Visit): number {
    return visit.checklist_summary?.progress_percent ?? 0;
}

function overtimeLabel(visit: Visit): string {
    const overtime = Number(visit.overtime_minutes ?? 0);
    const elapsed = Number(visit.elapsed_minutes ?? visit.actual_minutes ?? 0);
    const planned = Number(visit.planned_minutes ?? 0);
    const liveOvertime = Math.max(0, elapsed - planned);

    return formatMinutes(Math.max(overtime, liveOvertime));
}

function taskStatus(visit: Visit, item: ChecklistItem): 'pending' | 'done' | 'skipped' {
    const form = formFor(visit);

    if (form.completedItems.includes(item.id)) {
        return 'done';
    }

    if (form.skippedItems.includes(item.id)) {
        return 'skipped';
    }

    return 'pending';
}

function setTaskStatus(visit: Visit, item: ChecklistItem, status: 'pending' | 'done' | 'skipped'): void {
    const form = formFor(visit);
    form.completedItems = form.completedItems.filter((id) => id !== item.id);
    form.skippedItems = form.skippedItems.filter((id) => id !== item.id);

    if (status === 'done') {
        form.completedItems = [...form.completedItems, item.id];
    }

    if (status === 'skipped') {
        form.skippedItems = [...form.skippedItems, item.id];
    }
}

function parsePhotos(value: string): string[] {
    return value
        .split(/[\n,]+/)
        .map((line) => line.trim())
        .filter(Boolean);
}

function parseMaterials(value: string): Array<{ name: string; quantity: string | null; cost_sar: string }> {
    return value
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const [name = '', quantity = '', cost = '0.00'] = line.split('|').map((part) => part.trim());
            const cleanCost = cost.replace(/[^\d.]/g, '') || '0.00';

            return {
                name,
                quantity: quantity || null,
                cost_sar: cleanCost,
            };
        })
        .filter((item) => item.name !== '');
}

function checklistPayload(visit: Visit): Array<{ id: number; status: string; notes: string | null; photo_path: string | null; photo_file?: File | null }> {
    const form = formFor(visit);

    return checklist(visit).map((item) => ({
        id: item.id,
        status: taskStatus(visit, item),
        notes: form.taskNotes[item.id] || null,
        photo_path: form.taskPhotos[item.id] || null,
        photo_file: form.taskPhotoFiles[item.id] ?? null,
    }));
}

function postVisit(visit: Visit, url: string, payload: WorkerPayload = {}): void {
    const form = formFor(visit);
    form.processing = true;

    router.post(url, payload, {
        forceFormData: payloadHasFile(payload),
        preserveScroll: true,
        onFinish: () => {
            form.processing = false;
        },
    });
}

async function startVisit(visit: Visit): Promise<void> {
    postVisit(visit, `/app/worker/visits/${visit.id}/start`, await gpsPayload(visit, 'check_in'));
}

function saveChecklist(visit: Visit): void {
    postVisit(visit, `/app/worker/visits/${visit.id}/checklist`, {
        items: checklistPayload(visit),
    });
}

async function finishVisit(visit: Visit): Promise<void> {
    const form = formFor(visit);

    postVisit(visit, `/app/worker/visits/${visit.id}/finish`, {
        completed_items: form.completedItems,
        materials_used: parseMaterials(form.materialLines),
        photos: parsePhotos(form.photosText),
        photo_files: form.photoFiles,
        execution_notes: form.executionNotes,
        ...(await gpsPayload(visit, 'check_out')),
    });
}

function reportIssue(visit: Visit): void {
    const form = formFor(visit);

    postVisit(visit, `/app/worker/visits/${visit.id}/issue`, {
        issue_note: form.issueNote,
        photos: parsePhotos(form.issuePhotosText),
        photo_files: form.issuePhotoFiles,
    });
}

function runPrimaryAction(visit: Visit): void {
    if (visit.workflow?.primary_action === 'start') {
        void startVisit(visit);
        return;
    }

    if (visit.workflow?.primary_action === 'save_tasks') {
        if (activePanel.value !== 'tasks') {
            activePanel.value = 'tasks';
            return;
        }

        saveChecklist(visit);
        return;
    }

    if (visit.workflow?.primary_action === 'finish') {
        if (activePanel.value !== 'evidence') {
            activePanel.value = 'evidence';
            return;
        }

        void finishVisit(visit);
    }
}

function primaryActionLabel(visit: Visit): string {
    if (visit.workflow?.primary_action === 'start') {
        return t('workerPortal.startWork', 'Start');
    }

    if (visit.workflow?.primary_action === 'save_tasks') {
        return activePanel.value === 'tasks'
            ? t('workerPortal.saveTasks', 'Save Tasks')
            : t('workerPortal.panels.tasks', 'Tasks');
    }

    if (visit.workflow?.primary_action === 'finish') {
        return activePanel.value === 'evidence'
            ? t('workerPortal.finish', 'Finish')
            : t('workerPortal.panels.evidence', 'Evidence');
    }

    return t('workerPortal.viewDetails', 'View details');
}

function canRunPrimaryAction(visit: Visit): boolean {
    return ['start', 'save_tasks', 'finish'].includes(String(visit.workflow?.primary_action))
        && !formFor(visit).processing;
}

function changeLocale(): void {
    router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveScroll: true });
}

function setTaskPhotoFile(visit: Visit, item: ChecklistItem, event: Event): void {
    const target = event.target as HTMLInputElement;
    formFor(visit).taskPhotoFiles[item.id] = target.files?.[0] ?? null;
}

function setVisitPhotoFiles(visit: Visit, event: Event): void {
    const target = event.target as HTMLInputElement;
    formFor(visit).photoFiles = Array.from(target.files ?? []);
}

function setIssuePhotoFiles(visit: Visit, event: Event): void {
    const target = event.target as HTMLInputElement;
    formFor(visit).issuePhotoFiles = Array.from(target.files ?? []);
}

function fileCountLabel(files: File[]): string {
    return t('workerPortal.filesSelected', ':count selected', { count: files.length });
}

function payloadHasFile(value: unknown): boolean {
    if (value instanceof File || value instanceof Blob) {
        return true;
    }

    if (Array.isArray(value)) {
        return value.some((item) => payloadHasFile(item));
    }

    if (value && typeof value === 'object') {
        return Object.values(value as Record<string, unknown>).some((item) => payloadHasFile(item));
    }

    return false;
}

async function gpsPayload(visit: Visit, prefix: 'check_in' | 'check_out'): Promise<Record<string, string | number>> {
    const form = formFor(visit);

    if (typeof navigator === 'undefined' || !navigator.geolocation) {
        form.locationMessage = t('workerPortal.locationUnavailable', 'Location is unavailable on this device.');

        return {};
    }

    form.locationMessage = t('workerPortal.locationCapturing', 'Capturing location...');

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                form.locationMessage = t('workerPortal.locationCaptured', 'Location captured.');
                resolve({
                    [`${prefix}_latitude`]: position.coords.latitude.toFixed(7),
                    [`${prefix}_longitude`]: position.coords.longitude.toFixed(7),
                    [`${prefix}_accuracy_meters`]: Math.round(position.coords.accuracy),
                });
            },
            () => {
                form.locationMessage = t('workerPortal.locationSkipped', 'Location was not captured.');
                resolve({});
            },
            {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 60000,
            },
        );
    });
}
</script>

<template>
    <Head :title="t('workerPortal.headTitle', 'Worker today')" />

    <main class="min-h-screen bg-[#f7f9fd] text-slate-900">
        <section class="mx-auto max-w-5xl px-3 pb-28 pt-4 sm:px-5 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-[#071b3d] text-white shadow-sm">
                <div class="grid gap-5 p-5 sm:grid-cols-[1fr_auto] sm:items-center lg:p-6">
                    <div class="min-w-0">
                        <p class="text-xs font-extrabold uppercase text-[#ffdd72]">{{ t('workerPortal.today', 'Today') }}</p>
                        <h1 class="mt-2 break-words text-2xl font-extrabold">{{ worker?.name ?? t('workerPortal.noWorkerAssigned', 'No worker assigned') }}</h1>
                        <p v-if="worker" class="mt-1 text-sm font-bold text-slate-300">{{ worker.employee_code }} / {{ t(`statuses.${worker.status}`, worker.status) }}</p>
                    </div>
                    <button type="button" class="grid h-11 w-11 place-items-center rounded-md border border-white/15 text-white hover:bg-white/10" @click="changeLocale">
                        <Languages class="h-4 w-4" />
                    </button>
                </div>
                <div class="grid grid-cols-2 border-t border-white/10 sm:grid-cols-4">
                    <div class="p-4">
                        <p class="text-2xl font-extrabold">{{ summary.total_visits }}</p>
                        <p class="mt-1 text-xs font-bold uppercase text-slate-300">{{ t('workerPortal.visits', 'Visits') }}</p>
                    </div>
                    <div class="border-s border-white/10 p-4">
                        <p class="text-2xl font-extrabold">{{ summary.in_progress_visits }}</p>
                        <p class="mt-1 text-xs font-bold uppercase text-slate-300">{{ t('workerPortal.inProgress', 'In progress') }}</p>
                    </div>
                    <div class="border-t border-white/10 p-4 sm:border-s sm:border-t-0">
                        <p class="text-2xl font-extrabold">{{ summary.completed_visits }}</p>
                        <p class="mt-1 text-xs font-bold uppercase text-slate-300">{{ t('workerPortal.done', 'Done') }}</p>
                    </div>
                    <div class="border-s border-t border-white/10 p-4 sm:border-t-0">
                        <p class="text-2xl font-extrabold text-[#ffdd72]">{{ summary.open_required_tasks }}</p>
                        <p class="mt-1 text-xs font-bold uppercase text-slate-300">{{ t('workerPortal.openRequired', 'Open required') }}</p>
                    </div>
                </div>
            </div>

            <div v-if="success" class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ success }}
            </div>

            <div v-if="visits.length" class="mt-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-extrabold">{{ t('workerPortal.todayQueue', 'Today queue') }}</h2>
                    <span class="rounded-md bg-white px-3 py-1.5 text-xs font-extrabold text-slate-600 shadow-sm ring-1 ring-slate-200">
                        {{ summary.completed_tasks }}/{{ summary.total_tasks }} {{ t('workerPortal.tasksDone', 'tasks') }}
                    </span>
                </div>
                <div class="-mx-3 flex gap-3 overflow-x-auto px-3 pb-2">
                    <button
                        v-for="visit in visits"
                        :key="visit.id"
                        type="button"
                        class="min-w-64 rounded-lg border bg-white p-4 text-start shadow-sm transition"
                        :class="selectedVisit?.id === visit.id ? 'border-[#1040b6] ring-2 ring-[#1040b6]/10' : 'border-slate-200 hover:border-slate-300'"
                        @click="selectedVisitId = visit.id"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-extrabold text-[#1040b6]">{{ formatTime(visit.starts_at) }} - {{ formatTime(visit.ends_at) }}</span>
                            <StatusBadge :status="visit.status" />
                        </div>
                        <p class="mt-3 truncate text-base font-extrabold">{{ siteName(visit) }}</p>
                        <p class="mt-1 truncate text-sm font-bold text-slate-500">{{ serviceTitle(visit) }}</p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${progressPercent(visit)}%` }" />
                        </div>
                    </button>
                </div>
            </div>

            <article v-if="selectedVisit" class="mt-5 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <StatusBadge :status="selectedVisit.status" />
                                <span class="rounded-md px-2.5 py-1 text-xs font-extrabold" :class="selectedVisit.timer_state === 'running' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200'">
                                    {{ timerStateLabel(selectedVisit.timer_state) }}
                                </span>
                            </div>
                            <h2 class="mt-3 break-words text-2xl font-extrabold">{{ siteName(selectedVisit) }}</h2>
                            <p class="mt-1 text-sm font-bold text-slate-500">{{ customerName(selectedVisit) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-extrabold text-slate-700">
                            {{ formatTime(selectedVisit.starts_at) }} - {{ formatTime(selectedVisit.ends_at) }}
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                            <div class="flex items-center gap-2 text-xs font-extrabold uppercase text-[#1040b6]">
                                <Clock class="h-4 w-4" />
                                {{ t('workerPortal.planned', 'Planned') }}
                            </div>
                            <p class="mt-1 text-lg font-extrabold">{{ formatMinutes(selectedVisit.planned_minutes) }}</p>
                        </div>
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-4">
                            <div class="flex items-center gap-2 text-xs font-extrabold uppercase text-emerald-700">
                                <TimerReset class="h-4 w-4" />
                                {{ t('workerPortal.worked', 'Worked') }}
                            </div>
                            <p class="mt-1 text-lg font-extrabold">{{ formatMinutes(selectedVisit.elapsed_minutes ?? selectedVisit.actual_minutes) }}</p>
                        </div>
                        <div class="rounded-lg border border-amber-100 bg-amber-50 p-4">
                            <div class="flex items-center gap-2 text-xs font-extrabold uppercase text-amber-700">
                                <AlertTriangle class="h-4 w-4" />
                                {{ t('workerPortal.overtime', 'Overtime') }}
                            </div>
                            <p class="mt-1 text-lg font-extrabold">{{ overtimeLabel(selectedVisit) }}</p>
                        </div>
                    </div>

                    <div v-if="formFor(selectedVisit).locationMessage" class="mt-4 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm font-bold text-[#1040b6]">
                        {{ formFor(selectedVisit).locationMessage }}
                    </div>
                </div>

                <div class="border-y border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="flex gap-2 overflow-x-auto">
                        <button
                            v-for="panel in panels"
                            :key="panel.key"
                            type="button"
                            class="inline-flex min-h-10 items-center gap-2 rounded-md px-4 text-sm font-extrabold"
                            :class="activePanel === panel.key ? 'bg-[#1040b6] text-white' : 'text-slate-600 hover:bg-white'"
                            @click="activePanel = panel.key"
                        >
                            <component :is="panel.icon" class="h-4 w-4" />
                            {{ panel.label }}
                        </button>
                    </div>
                </div>

                <div class="p-5">
                    <section v-if="activePanel === 'overview'" class="grid gap-4 lg:grid-cols-[1fr_0.9fr]">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <h3 class="text-sm font-extrabold uppercase text-slate-500">{{ t('workerPortal.siteDetails', 'Site details') }}</h3>
                            <dl class="mt-4 grid gap-3 text-sm">
                                <div>
                                    <dt class="font-bold text-slate-500">{{ t('workerPortal.service', 'Service') }}</dt>
                                    <dd class="mt-1 font-extrabold">{{ serviceTitle(selectedVisit) }}</dd>
                                </div>
                                <div v-if="selectedVisit.contract">
                                    <dt class="font-bold text-slate-500">{{ t('workerPortal.contract', 'Contract') }}</dt>
                                    <dd class="mt-1 font-extrabold">{{ selectedVisit.contract.reference }}</dd>
                                </div>
                                <div v-if="siteAddress(selectedVisit)">
                                    <dt class="font-bold text-slate-500">{{ t('workerPortal.address', 'Address') }}</dt>
                                    <dd class="mt-1 font-extrabold">{{ siteAddress(selectedVisit) }}</dd>
                                </div>
                            </dl>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a v-if="typeof selectedVisit.site !== 'string' && selectedVisit.site?.contact_phone" :href="`tel:${selectedVisit.site.contact_phone}`" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-extrabold text-slate-700 hover:bg-slate-50">
                                    <Phone class="h-4 w-4" />
                                    {{ t('workerPortal.call', 'Call') }}
                                </a>
                                <a v-if="typeof selectedVisit.site !== 'string' && selectedVisit.site?.maps_url" :href="selectedVisit.site.maps_url" target="_blank" rel="noreferrer" class="inline-flex items-center gap-2 rounded-md bg-[#1040b6] px-3 py-2 text-sm font-extrabold text-white hover:bg-blue-800">
                                    <Navigation class="h-4 w-4" />
                                    {{ t('workerPortal.openMap', 'Open map') }}
                                </a>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4">
                            <h3 class="text-sm font-extrabold uppercase text-slate-500">{{ t('workerPortal.workflow', 'Workflow') }}</h3>
                            <div class="mt-4 grid gap-2">
                                <div v-for="step in selectedVisit.workflow?.steps ?? []" :key="step.key" class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 text-sm font-bold">
                                    <span>{{ t(`workerPortal.workflowSteps.${step.key}`, step.key.replaceAll('_', ' ')) }}</span>
                                    <StatusBadge :status="step.status" />
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4 lg:col-span-2">
                            <h3 class="text-sm font-extrabold uppercase text-slate-500">{{ t('workerPortal.gpsEvidence', 'GPS evidence') }}</h3>
                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <a
                                    v-if="selectedVisit.check_in_location"
                                    :href="selectedVisit.check_in_location.maps_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-bold text-emerald-800"
                                >
                                    <span class="block text-xs uppercase">{{ t('workerPortal.checkIn', 'Check-in') }}</span>
                                    <span class="mt-1 block">{{ selectedVisit.check_in_location.latitude }}, {{ selectedVisit.check_in_location.longitude }}</span>
                                    <span v-if="selectedVisit.check_in_location.accuracy_meters" class="mt-1 block text-xs text-emerald-700">
                                        {{ t('workerPortal.accuracyMeters', 'Accuracy :meters m', { meters: selectedVisit.check_in_location.accuracy_meters }) }}
                                    </span>
                                </a>
                                <a
                                    v-if="selectedVisit.check_out_location"
                                    :href="selectedVisit.check_out_location.maps_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm font-bold text-[#1040b6]"
                                >
                                    <span class="block text-xs uppercase">{{ t('workerPortal.checkOut', 'Check-out') }}</span>
                                    <span class="mt-1 block">{{ selectedVisit.check_out_location.latitude }}, {{ selectedVisit.check_out_location.longitude }}</span>
                                    <span v-if="selectedVisit.check_out_location.accuracy_meters" class="mt-1 block text-xs text-blue-700">
                                        {{ t('workerPortal.accuracyMeters', 'Accuracy :meters m', { meters: selectedVisit.check_out_location.accuracy_meters }) }}
                                    </span>
                                </a>
                                <div v-if="!selectedVisit.check_in_location && !selectedVisit.check_out_location" class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-3 text-sm font-bold text-slate-500">
                                    {{ t('workerPortal.noGpsEvidence', 'GPS evidence will appear after start or finish.') }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="activePanel === 'tasks'" class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-lg font-extrabold">{{ t('workerPortal.tasks', 'Tasks') }}</h3>
                            <span class="rounded-md bg-slate-100 px-3 py-1.5 text-sm font-extrabold text-slate-700">
                                {{ selectedVisit.checklist_summary?.completed_required ?? 0 }}/{{ selectedVisit.checklist_summary?.required ?? 0 }} {{ t('workerPortal.requiredTasks', 'required') }}
                            </span>
                        </div>

                        <div v-if="checklist(selectedVisit).length" class="grid gap-3">
                            <article v-for="item in checklist(selectedVisit)" :key="item.id" class="rounded-lg border border-slate-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="break-words font-extrabold">{{ item.label }}</h4>
                                            <span v-if="item.is_required" class="rounded-md bg-amber-100 px-2 py-1 text-xs font-extrabold text-amber-800">{{ t('workerPortal.required', 'Required') }}</span>
                                        </div>
                                        <StatusBadge class="mt-2" :status="taskStatus(selectedVisit, item)" />
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" class="rounded-md px-3 py-2 text-xs font-extrabold" :class="taskStatus(selectedVisit, item) === 'done' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700'" @click="setTaskStatus(selectedVisit, item, 'done')">
                                            {{ t('workerPortal.markDone', 'Done') }}
                                        </button>
                                        <button type="button" class="rounded-md px-3 py-2 text-xs font-extrabold" :class="taskStatus(selectedVisit, item) === 'skipped' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-700'" @click="setTaskStatus(selectedVisit, item, 'skipped')">
                                            {{ t('workerPortal.skip', 'Skip') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    <label class="block">
                                        <span class="mb-1 block text-sm font-bold text-slate-600">{{ t('workerPortal.taskNotes', 'Task notes') }}</span>
                                        <input v-model="formFor(selectedVisit).taskNotes[item.id]" class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="text" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1 block text-sm font-bold text-slate-600">{{ t('workerPortal.taskPhoto', 'Task photo') }}</span>
                                        <input class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="file" accept="image/*" capture="environment" @change="setTaskPhotoFile(selectedVisit, item, $event)" />
                                        <p v-if="formFor(selectedVisit).taskPhotoFiles[item.id]" class="mt-1 text-xs font-bold text-emerald-700">
                                            {{ formFor(selectedVisit).taskPhotoFiles[item.id]?.name }}
                                        </p>
                                        <a v-if="item.photo_url" :href="item.photo_url" target="_blank" rel="noreferrer" class="mt-1 inline-flex text-xs font-bold text-[#1040b6]">
                                            {{ t('workerPortal.existingPhoto', 'Existing photo') }}
                                        </a>
                                        <input v-model="formFor(selectedVisit).taskPhotos[item.id]" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" :placeholder="t('workerPortal.manualPhotoPath', 'Manual photo path')" type="text" />
                                    </label>
                                </div>
                            </article>
                        </div>
                        <div v-else class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">
                            {{ t('workerPortal.noChecklistItems', 'No checklist items.') }}
                        </div>

                        <button class="ta-btn ta-btn-secondary min-h-12 w-full justify-center" type="button" :disabled="!selectedVisit.can_save_checklist || formFor(selectedVisit).processing" @click="saveChecklist(selectedVisit)">
                            <ClipboardCheck class="h-4 w-4" />
                            {{ t('workerPortal.saveTasks', 'Save Tasks') }}
                        </button>
                    </section>

                    <section v-if="activePanel === 'evidence'" class="grid gap-4 lg:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 flex items-center gap-2 text-sm font-extrabold text-slate-700">
                                <Package class="h-4 w-4 text-[#1040b6]" />
                                {{ t('workerPortal.materials', 'Materials') }}
                            </span>
                            <textarea v-model="formFor(selectedVisit).materialLines" class="min-h-32 w-full resize-y rounded-md border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" :placeholder="t('workerPortal.materialsPlaceholder', 'Disinfectant | 1 bottle | 30.00')" />
                        </label>
                        <label class="block">
                            <span class="mb-1 flex items-center gap-2 text-sm font-extrabold text-slate-700">
                                <Camera class="h-4 w-4 text-[#1040b6]" />
                                {{ t('workerPortal.photos', 'Photos') }}
                            </span>
                            <input class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="file" accept="image/*" capture="environment" multiple @change="setVisitPhotoFiles(selectedVisit, $event)" />
                            <p v-if="formFor(selectedVisit).photoFiles.length" class="mt-1 text-xs font-bold text-emerald-700">
                                {{ fileCountLabel(formFor(selectedVisit).photoFiles) }}
                            </p>
                            <textarea v-model="formFor(selectedVisit).photosText" class="mt-2 min-h-24 w-full resize-y rounded-md border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" :placeholder="t('workerPortal.photosPlaceholder', 'worker-photos/site-after.jpg')" />
                        </label>
                        <label class="block lg:col-span-2">
                            <span class="mb-1 flex items-center gap-2 text-sm font-extrabold text-slate-700">
                                <FileText class="h-4 w-4 text-[#1040b6]" />
                                {{ t('workerPortal.notes', 'Notes') }}
                            </span>
                            <textarea v-model="formFor(selectedVisit).executionNotes" class="min-h-28 w-full resize-y rounded-md border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" :placeholder="t('workerPortal.notesPlaceholder', 'Work notes')" />
                        </label>
                        <button class="ta-btn ta-btn-primary min-h-12 justify-center lg:col-span-2" type="button" :disabled="!selectedVisit.can_finish || formFor(selectedVisit).processing" @click="void finishVisit(selectedVisit)">
                            <Square class="h-4 w-4" />
                            {{ t('workerPortal.finishVisit', 'Finish visit') }}
                        </button>
                    </section>

                    <section v-if="activePanel === 'issue'" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div class="grid gap-3">
                            <label class="block">
                                <span class="mb-1 flex items-center gap-2 text-sm font-extrabold text-amber-800">
                                    <AlertTriangle class="h-4 w-4" />
                                    {{ t('workerPortal.issue', 'Issue') }}
                                </span>
                                <input v-model="formFor(selectedVisit).issueNote" class="w-full rounded-md border border-amber-200 bg-white px-3 py-2 text-sm outline-none focus:border-amber-300 focus:ring-4 focus:ring-amber-100" :placeholder="t('workerPortal.issuePlaceholder', 'Blocked room, customer request, missing access')" type="text" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-extrabold text-amber-800">{{ t('workerPortal.issuePhotos', 'Issue photos') }}</span>
                                <input class="w-full rounded-md border border-amber-200 bg-white px-3 py-2 text-sm outline-none focus:border-amber-300 focus:ring-4 focus:ring-amber-100" type="file" accept="image/*" capture="environment" multiple @change="setIssuePhotoFiles(selectedVisit, $event)" />
                                <p v-if="formFor(selectedVisit).issuePhotoFiles.length" class="mt-1 text-xs font-bold text-amber-800">
                                    {{ fileCountLabel(formFor(selectedVisit).issuePhotoFiles) }}
                                </p>
                                <input v-model="formFor(selectedVisit).issuePhotosText" class="mt-2 w-full rounded-md border border-amber-200 bg-white px-3 py-2 text-sm outline-none focus:border-amber-300 focus:ring-4 focus:ring-amber-100" :placeholder="t('workerPortal.issuePhotosPlaceholder', 'worker-photos/issue.jpg')" type="text" />
                            </label>
                            <button class="ta-btn justify-center border-amber-300 bg-white text-amber-800 hover:bg-amber-100" type="button" :disabled="!formFor(selectedVisit).issueNote || formFor(selectedVisit).processing" @click="reportIssue(selectedVisit)">
                                {{ t('workerPortal.report', 'Report') }}
                            </button>
                        </div>
                    </section>
                </div>
            </article>

            <div v-if="visits.length === 0" class="mt-5 rounded-lg border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-slate-500">
                {{ t('workerPortal.noVisitsAssigned', 'No visits assigned today.') }}
            </div>
        </section>

        <div v-if="selectedVisit" class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 p-3 shadow-[0_-10px_30px_rgba(15,23,42,0.08)] backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center gap-3">
                <button class="ta-btn ta-btn-primary min-h-12 flex-1 justify-center" type="button" :disabled="!canRunPrimaryAction(selectedVisit)" @click="runPrimaryAction(selectedVisit)">
                    <Play v-if="selectedVisit.workflow?.primary_action === 'start'" class="h-4 w-4" />
                    <ClipboardCheck v-else-if="selectedVisit.workflow?.primary_action === 'save_tasks'" class="h-4 w-4" />
                    <Square v-else class="h-4 w-4" />
                    {{ primaryActionLabel(selectedVisit) }}
                </button>
                <button class="grid h-12 w-12 place-items-center rounded-md border border-slate-200 text-slate-700" type="button" @click="activePanel = activePanel === 'overview' ? 'tasks' : 'overview'">
                    <ChevronRight class="h-5 w-5 rtl:rotate-180" />
                </button>
            </div>
        </div>
    </main>
</template>
