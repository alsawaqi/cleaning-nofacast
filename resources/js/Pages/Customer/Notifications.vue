<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BellRing, CalendarDays, CheckCircle2, Clock, CreditCard, FileSignature, Languages, LogOut, MessageSquareText, Sparkles, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from '../../lib/i18n';

type PortalCustomer = {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
};

type NotificationTone = 'red' | 'amber' | 'blue' | 'emerald' | 'slate' | string;

type CustomerNotification = {
    id: string;
    category: 'contracts' | 'finance' | 'schedule' | 'requests' | string;
    type: string;
    title: string;
    body: string;
    status: string;
    tone: NotificationTone;
    occurred_at?: string | null;
    due_on?: string | null;
    href: string;
    requires_action: boolean;
    meta: Record<string, string | number | null | undefined>;
};

type NotificationGroups = {
    action_required: CustomerNotification[];
    contracts: CustomerNotification[];
    finance: CustomerNotification[];
    schedule: CustomerNotification[];
    requests: CustomerNotification[];
};

type NotificationTab = keyof NotificationGroups;

const props = defineProps<{
    customer: PortalCustomer;
    summary: {
        total_count: number;
        action_count: number;
        contract_count: number;
        finance_count: number;
        schedule_count: number;
        request_count: number;
    };
    groups: NotificationGroups;
    backUrl: string;
}>();

const { locale, t } = useI18n();
const activeTab = ref<NotificationTab>(props.summary.action_count > 0 ? 'action_required' : 'schedule');

const notificationTabs = computed(() => [
    {
        key: 'action_required' as NotificationTab,
        label: t('customerPortal.notifications.actionRequired', 'Action needed'),
        count: props.groups.action_required.length,
        icon: BellRing,
    },
    {
        key: 'schedule' as NotificationTab,
        label: t('customerPortal.notifications.schedule', 'Schedule'),
        count: props.summary.schedule_count,
        icon: CalendarDays,
    },
    {
        key: 'finance' as NotificationTab,
        label: t('customerPortal.notifications.finance', 'Finance'),
        count: props.summary.finance_count,
        icon: CreditCard,
    },
    {
        key: 'requests' as NotificationTab,
        label: t('customerPortal.notifications.requests', 'Requests'),
        count: props.summary.request_count,
        icon: MessageSquareText,
    },
    {
        key: 'contracts' as NotificationTab,
        label: t('customerPortal.notifications.contracts', 'Contracts'),
        count: props.summary.contract_count,
        icon: FileSignature,
    },
]);
const currentItems = computed(() => props.groups[activeTab.value] ?? []);

function changeLocale(): void {
    router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveScroll: true });
}

function logout(): void {
    router.post('/logout');
}

function toneClass(tone: NotificationTone): string {
    if (tone === 'emerald') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (tone === 'red') {
        return 'border-red-200 bg-red-50 text-red-700';
    }

    if (tone === 'amber') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    if (tone === 'blue') {
        return 'border-blue-200 bg-blue-50 text-[#1040b6]';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700';
}

function statusLabel(status: string): string {
    return t(`statuses.${status}`, status.replaceAll('_', ' '));
}

function typeLabel(type: string): string {
    return t(`customerPortal.notificationTypes.${type}`, type.replaceAll('_', ' '));
}

function metaValues(item: CustomerNotification): Array<{ key: string; value: string | number }> {
    return Object.entries(item.meta)
        .filter((entry): entry is [string, string | number] => entry[1] !== null && entry[1] !== undefined && entry[1] !== '')
        .slice(0, 4)
        .map(([key, value]) => ({ key, value }));
}
</script>

<template>
    <Head :title="t('customerPortal.notifications.headTitle', 'Notification center')" />

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
                <div class="flex flex-wrap items-center gap-2">
                    <Link href="/app/customer/profile" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        <UserRound class="h-4 w-4" />
                        {{ t('customerPortal.profileSitesLink', 'Profile & sites') }}
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
                <div class="flex flex-wrap items-start justify-between gap-5">
                    <div>
                        <p class="text-sm font-extrabold uppercase text-[#ffdd72]">{{ t('customerPortal.notifications.kicker', 'Customer updates') }}</p>
                        <h1 class="mt-3 text-3xl font-extrabold md:text-4xl">
                            {{ t('customerPortal.notifications.title', 'Notification center') }}
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">
                            {{ t('customerPortal.notifications.intro', 'Follow contract actions, upcoming visits, invoice payments, and request status updates in one place.') }}
                        </p>
                    </div>
                    <div class="grid min-w-52 gap-3 rounded-lg border border-white/10 bg-white/5 p-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="font-bold text-slate-300">{{ t('customerPortal.notifications.total', 'Total') }}</span>
                            <span class="text-2xl font-extrabold">{{ summary.total_count }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="font-bold text-slate-300">{{ t('customerPortal.notifications.actions', 'Needs action') }}</span>
                            <span class="text-2xl font-extrabold text-[#ffdd72]">{{ summary.action_count }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-4">
                <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-extrabold uppercase text-slate-500">{{ t('customerPortal.notifications.schedule', 'Schedule') }}</p>
                    <p class="mt-2 text-3xl font-extrabold">{{ summary.schedule_count }}</p>
                </article>
                <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-extrabold uppercase text-slate-500">{{ t('customerPortal.notifications.finance', 'Finance') }}</p>
                    <p class="mt-2 text-3xl font-extrabold">{{ summary.finance_count }}</p>
                </article>
                <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-extrabold uppercase text-slate-500">{{ t('customerPortal.notifications.requests', 'Requests') }}</p>
                    <p class="mt-2 text-3xl font-extrabold">{{ summary.request_count }}</p>
                </article>
                <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-extrabold uppercase text-slate-500">{{ t('customerPortal.notifications.contracts', 'Contracts') }}</p>
                    <p class="mt-2 text-3xl font-extrabold">{{ summary.contract_count }}</p>
                </article>
            </div>

            <div class="mt-6 flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                <button
                    v-for="tab in notificationTabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-extrabold"
                    :class="activeTab === tab.key ? 'bg-[#1040b6] text-white' : 'text-slate-600 hover:bg-slate-50'"
                    @click="activeTab = tab.key"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    <span>{{ tab.label }}</span>
                    <span class="rounded-md px-2 py-0.5 text-xs" :class="activeTab === tab.key ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600'">{{ tab.count }}</span>
                </button>
            </div>

            <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-extrabold">{{ notificationTabs.find((tab) => tab.key === activeTab)?.label }}</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ t('customerPortal.notifications.groupIntro', 'Open any item to review the related contract, invoice, visit, or request.') }}
                        </p>
                    </div>
                    <span class="rounded-md bg-blue-50 px-3 py-1.5 text-sm font-extrabold text-[#1040b6]">{{ currentItems.length }}</span>
                </div>

                <div v-if="currentItems.length" class="mt-6 grid gap-3">
                    <Link
                        v-for="item in currentItems"
                        :key="item.id"
                        :href="item.href"
                        class="block rounded-lg border border-slate-200 p-4 transition hover:border-[#1040b6]/40 hover:bg-slate-50"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-extrabold" :class="toneClass(item.tone)">
                                        <CheckCircle2 v-if="item.tone === 'emerald'" class="h-3.5 w-3.5" />
                                        <Clock v-else class="h-3.5 w-3.5" />
                                        {{ typeLabel(item.type) }}
                                    </span>
                                    <span v-if="item.requires_action" class="rounded-md bg-amber-100 px-2.5 py-1 text-xs font-extrabold text-amber-800">
                                        {{ t('customerPortal.notifications.needsAction', 'Needs action') }}
                                    </span>
                                </div>
                                <h3 class="mt-3 text-lg font-extrabold">{{ item.title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ item.body }}</p>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold text-slate-500">
                                    <span v-if="item.due_on" class="rounded-md bg-slate-100 px-2.5 py-1">
                                        {{ t('customerPortal.notifications.date', 'Date') }}: {{ item.due_on }}
                                    </span>
                                    <span v-if="item.occurred_at" class="rounded-md bg-slate-100 px-2.5 py-1">
                                        {{ t('customerPortal.notifications.updated', 'Updated') }}: {{ item.occurred_at }}
                                    </span>
                                    <span class="rounded-md bg-slate-100 px-2.5 py-1">
                                        {{ statusLabel(item.status) }}
                                    </span>
                                </div>
                            </div>
                            <span class="rounded-md bg-[#1040b6] px-3 py-2 text-sm font-extrabold text-white">
                                {{ t('customerPortal.notifications.open', 'Open') }}
                            </span>
                        </div>

                        <div v-if="metaValues(item).length" class="mt-4 grid gap-2 border-t border-slate-100 pt-4 text-xs font-bold text-slate-500 sm:grid-cols-2 lg:grid-cols-4">
                            <div v-for="meta in metaValues(item)" :key="meta.key" class="min-w-0 rounded-md bg-slate-50 px-3 py-2">
                                <span class="block uppercase">{{ meta.key.replaceAll('_', ' ') }}</span>
                                <span class="mt-1 block truncate text-sm text-[#071b3d]">{{ meta.value }}</span>
                            </div>
                        </div>
                    </Link>
                </div>

                <div v-else class="mt-6 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <BellRing class="mx-auto h-10 w-10 text-slate-400" />
                    <h3 class="mt-3 text-lg font-extrabold">{{ t('customerPortal.notifications.emptyTitle', 'No notifications here') }}</h3>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ t('customerPortal.notifications.emptyBody', 'New visit, payment, contract, and request updates will appear here.') }}
                    </p>
                </div>
            </section>
        </section>
    </main>
</template>
