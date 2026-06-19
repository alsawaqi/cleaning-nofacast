<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Camera, CheckCircle2, ClipboardCheck, Clock, MapPin, Package, Play, Square, TimerReset } from '@lucide/vue';
import { reactive } from 'vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from '../../lib/i18n';
import type { ChecklistItem, Visit } from '../../types';

const props = defineProps<{
    worker: { id: number; name: string; employee_code: string; status: string } | null;
    visits: Visit[];
}>();

const { t } = useI18n();

type VisitForm = {
    completedItems: number[];
    materialLines: string;
    photosText: string;
    executionNotes: string;
    issueNote: string;
    issuePhotosText: string;
    processing: boolean;
};
type WorkerPayload = Parameters<typeof router.post>[1];

const forms = reactive<Record<number, VisitForm>>({});

function checklist(visit: Visit): ChecklistItem[] {
    return visit.checklist_items ?? visit.checklistItems ?? [];
}

function formFor(visit: Visit): VisitForm {
    if (!forms[visit.id]) {
        forms[visit.id] = {
            completedItems: checklist(visit)
                .filter((item) => item.status === 'done')
                .map((item) => item.id),
            materialLines: (visit.materials_used ?? [])
                .map((item) => [item.name, item.quantity, item.cost_sar ?? '0.00'].filter(Boolean).join(' | '))
                .join('\n'),
            photosText: (visit.photos ?? []).join('\n'),
            executionNotes: visit.execution_notes ?? '',
            issueNote: visit.issue_note ?? '',
            issuePhotosText: '',
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
    const planned = Number(visit.planned_minutes ?? 0);
    const elapsed = Number(visit.elapsed_minutes ?? visit.actual_minutes ?? 0);

    if (planned <= 0 || elapsed <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((elapsed / planned) * 100));
}

function overtimeLabel(visit: Visit): string {
    const overtime = Number(visit.overtime_minutes ?? 0);
    const elapsed = Number(visit.elapsed_minutes ?? visit.actual_minutes ?? 0);
    const planned = Number(visit.planned_minutes ?? 0);
    const liveOvertime = Math.max(0, elapsed - planned);

    return formatMinutes(Math.max(overtime, liveOvertime));
}

function isSelected(visit: Visit, item: ChecklistItem): boolean {
    return formFor(visit).completedItems.includes(item.id);
}

function toggleItem(visit: Visit, item: ChecklistItem): void {
    const form = formFor(visit);

    if (form.completedItems.includes(item.id)) {
        form.completedItems = form.completedItems.filter((id) => id !== item.id);
        return;
    }

    form.completedItems = [...form.completedItems, item.id];
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

function postVisit(visit: Visit, url: string, payload: WorkerPayload = {}): void {
    const form = formFor(visit);
    form.processing = true;

    router.post(url, payload, {
        preserveScroll: true,
        onFinish: () => {
            form.processing = false;
        },
    });
}

function startVisit(visit: Visit): void {
    postVisit(visit, `/app/worker/visits/${visit.id}/start`);
}

function saveChecklist(visit: Visit): void {
    postVisit(visit, `/app/worker/visits/${visit.id}/checklist`, {
        items: formFor(visit).completedItems,
    });
}

function finishVisit(visit: Visit): void {
    const form = formFor(visit);

    postVisit(visit, `/app/worker/visits/${visit.id}/finish`, {
        completed_items: form.completedItems,
        materials_used: parseMaterials(form.materialLines),
        photos: parsePhotos(form.photosText),
        execution_notes: form.executionNotes,
    });
}

function reportIssue(visit: Visit): void {
    const form = formFor(visit);

    postVisit(visit, `/app/worker/visits/${visit.id}/issue`, {
        issue_note: form.issueNote,
        photos: parsePhotos(form.issuePhotosText),
    });
}
</script>

<template>
    <Head :title="t('workerPortal.headTitle', 'Worker today')" />

    <section class="mx-auto max-w-4xl space-y-4 px-2 pb-8 sm:px-4">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="grid gap-4 bg-gradient-to-r from-brand-600 via-emerald-500 to-amber-400 p-5 text-white sm:grid-cols-[1fr_auto] sm:items-center">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/80">{{ t('workerPortal.today', 'Today') }}</p>
                    <h1 class="mt-1 break-words text-2xl font-bold">{{ worker?.name ?? t('workerPortal.noWorkerAssigned', 'No worker assigned') }}</h1>
                    <p v-if="worker" class="mt-1 text-sm font-medium text-white/85">{{ worker.employee_code }} / {{ t(`statuses.${worker.status}`, worker.status) }}</p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-center sm:min-w-56">
                    <div class="rounded-xl bg-white/20 px-3 py-2 backdrop-blur">
                        <div class="text-2xl font-bold">{{ props.visits.length }}</div>
                        <div class="text-xs font-semibold text-white/80">{{ t('workerPortal.visits', 'Visits') }}</div>
                    </div>
                    <div class="rounded-xl bg-white/20 px-3 py-2 backdrop-blur">
                        <div class="text-2xl font-bold">
                            {{ props.visits.filter((visit) => visit.status === 'completed').length }}
                        </div>
                        <div class="text-xs font-semibold text-white/80">{{ t('workerPortal.done', 'Done') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <article v-for="visit in visits" :key="visit.id" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="space-y-4 p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge :status="visit.status" />
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="visit.timer_state === 'running' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-gray-100 text-gray-600 ring-1 ring-gray-200'"
                            >
                                {{ timerStateLabel(visit.timer_state) }}
                            </span>
                        </div>
                        <h2 class="mt-2 break-words text-xl font-bold text-gray-900">{{ siteName(visit) }}</h2>
                        <p class="mt-1 break-words text-sm font-medium text-gray-500">{{ customerName(visit) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-bold text-gray-700">
                        {{ formatTime(visit.starts_at) }} - {{ formatTime(visit.ends_at) }}
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-brand-100 bg-brand-50 p-3">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase text-brand-600">
                            <Clock class="h-4 w-4" />
                            {{ t('workerPortal.planned', 'Planned') }}
                        </div>
                        <div class="mt-1 text-lg font-bold text-gray-900">{{ formatMinutes(visit.planned_minutes) }}</div>
                    </div>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase text-emerald-700">
                            <TimerReset class="h-4 w-4" />
                            {{ t('workerPortal.worked', 'Worked') }}
                        </div>
                        <div class="mt-1 text-lg font-bold text-gray-900">
                            {{ formatMinutes(visit.elapsed_minutes ?? visit.actual_minutes) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50 p-3">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase text-amber-700">
                            <AlertTriangle class="h-4 w-4" />
                            {{ t('workerPortal.overtime', 'Overtime') }}
                        </div>
                        <div class="mt-1 text-lg font-bold text-gray-900">{{ overtimeLabel(visit) }}</div>
                    </div>
                </div>

                <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="progressPercent(visit) >= 100 ? 'bg-amber-500' : 'bg-emerald-500'"
                        :style="{ width: `${progressPercent(visit)}%` }"
                    />
                </div>

                <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">
                    <MapPin class="h-4 w-4 shrink-0 text-brand-500" />
                    <span class="min-w-0 break-words">
                        {{ serviceTitle(visit) }}
                        <template v-if="typeof visit.site !== 'string' && (visit.site?.district || visit.site?.city)">
                            / {{ [visit.site?.district, visit.site?.city].filter(Boolean).join(', ') }}
                        </template>
                    </span>
                </div>

                <div class="grid gap-2 sm:grid-cols-3">
                    <button
                        class="ta-btn ta-btn-secondary min-h-12 justify-center"
                        type="button"
                        :disabled="!visit.can_start || formFor(visit).processing"
                        @click="startVisit(visit)"
                    >
                        <Play class="h-4 w-4" />
                        {{ t('workerPortal.startWork', 'Start') }}
                    </button>
                    <button
                        class="ta-btn ta-btn-secondary min-h-12 justify-center"
                        type="button"
                        :disabled="formFor(visit).processing"
                        @click="saveChecklist(visit)"
                    >
                        <ClipboardCheck class="h-4 w-4" />
                        {{ t('workerPortal.saveTasks', 'Save Tasks') }}
                    </button>
                    <button
                        class="ta-btn ta-btn-primary min-h-12 justify-center"
                        type="button"
                        :disabled="!visit.can_finish || formFor(visit).processing"
                        @click="finishVisit(visit)"
                    >
                        <Square class="h-4 w-4" />
                        {{ t('workerPortal.finish', 'Finish') }}
                    </button>
                </div>
            </div>

            <div class="border-t border-gray-100 bg-gray-50/70 p-4 sm:p-5">
                <div class="grid gap-4 lg:grid-cols-[1fr_1fr]">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-700">{{ t('workerPortal.tasks', 'Tasks') }}</h3>
                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-gray-500 ring-1 ring-gray-200">
                                {{ formFor(visit).completedItems.length }}/{{ checklist(visit).length }}
                            </span>
                        </div>
                        <button
                            v-for="item in checklist(visit)"
                            :key="item.id"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border bg-white p-3 text-start transition"
                            :class="isSelected(visit, item) ? 'border-emerald-200 ring-2 ring-emerald-100' : 'border-gray-200 hover:border-brand-200'"
                            type="button"
                            @click="toggleItem(visit, item)"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <CheckCircle2 class="h-5 w-5 shrink-0" :class="isSelected(visit, item) ? 'text-emerald-500' : 'text-gray-300'" />
                                <span class="min-w-0 break-words font-semibold text-gray-800">{{ item.label }}</span>
                            </span>
                            <StatusBadge :status="isSelected(visit, item) ? 'done' : item.status" />
                        </button>
                        <div v-if="checklist(visit).length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white p-4 text-sm font-medium text-gray-500">
                            {{ t('workerPortal.noChecklistItems', 'No checklist items.') }}
                        </div>
                    </div>

                    <div class="space-y-3">
                        <label class="block">
                            <span class="mb-1 flex items-center gap-2 text-sm font-bold text-gray-700">
                                <Package class="h-4 w-4 text-brand-500" />
                                {{ t('workerPortal.materials', 'Materials') }}
                            </span>
                            <textarea
                                v-model="formFor(visit).materialLines"
                                class="min-h-24 w-full resize-y rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 shadow-sm outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100"
                                :placeholder="t('workerPortal.materialsPlaceholder', 'Disinfectant | 1 bottle | 30.00')"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 flex items-center gap-2 text-sm font-bold text-gray-700">
                                <Camera class="h-4 w-4 text-brand-500" />
                                {{ t('workerPortal.photos', 'Photos') }}
                            </span>
                            <textarea
                                v-model="formFor(visit).photosText"
                                class="min-h-20 w-full resize-y rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 shadow-sm outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100"
                                :placeholder="t('workerPortal.photosPlaceholder', 'worker-photos/site-after.jpg')"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 text-sm font-bold text-gray-700">{{ t('workerPortal.notes', 'Notes') }}</span>
                            <textarea
                                v-model="formFor(visit).executionNotes"
                                class="min-h-20 w-full resize-y rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 shadow-sm outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100"
                                :placeholder="t('workerPortal.notesPlaceholder', 'Work notes')"
                            />
                        </label>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
                        <label class="block">
                            <span class="mb-1 flex items-center gap-2 text-sm font-bold text-amber-800">
                                <AlertTriangle class="h-4 w-4" />
                                {{ t('workerPortal.issue', 'Issue') }}
                            </span>
                            <input
                                v-model="formFor(visit).issueNote"
                                class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm text-gray-800 shadow-sm outline-none transition focus:border-amber-300 focus:ring-4 focus:ring-amber-100"
                                :placeholder="t('workerPortal.issuePlaceholder', 'Blocked room, customer request, missing access')"
                                type="text"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 text-sm font-bold text-amber-800">{{ t('workerPortal.issuePhotos', 'Issue photos') }}</span>
                            <input
                                v-model="formFor(visit).issuePhotosText"
                                class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm text-gray-800 shadow-sm outline-none transition focus:border-amber-300 focus:ring-4 focus:ring-amber-100"
                                :placeholder="t('workerPortal.issuePhotosPlaceholder', 'worker-photos/issue.jpg')"
                                type="text"
                            />
                        </label>
                        <button
                            class="ta-btn justify-center border-amber-300 bg-white text-amber-800 hover:bg-amber-100 md:min-w-32"
                            type="button"
                            :disabled="!formFor(visit).issueNote || formFor(visit).processing"
                            @click="reportIssue(visit)"
                        >
                            {{ t('workerPortal.report', 'Report') }}
                        </button>
                    </div>
                </div>
            </div>
        </article>

        <div v-if="visits.length === 0" class="ta-card px-5 py-10 text-center text-gray-500">
            {{ t('workerPortal.noVisitsAssigned', 'No visits assigned today.') }}
        </div>
    </section>
</template>
