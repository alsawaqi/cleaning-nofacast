<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Bell,
    BriefcaseBusiness,
    CalendarDays,
    ChevronDown,
    ClipboardCheck,
    HardHat,
    LayoutDashboard,
    LogOut,
    Menu,
    Languages,
    ReceiptText,
    Search,
    Settings,
    ShieldCheck,
    Target,
    UserRound,
    UserCog,
    UsersRound,
    Wrench,
    X,
} from '@lucide/vue';
import { computed, ref, type Component } from 'vue';
import { useI18n } from '../lib/i18n';
import type { AppSharedProps, User } from '../types';

type NavItem = {
    labelKey: string;
    href: string;
    icon: Component;
    badgeKey?: string;
    permission?: string;
};

type NavGroup = {
    labelKey: string;
    items: NavItem[];
};

const page = usePage();
const mobileOpen = ref(false);
const userMenuOpen = ref(false);
const notificationsOpen = ref(false);
const { t } = useI18n();

const props = computed(() => page.props as AppSharedProps);
const user = computed<User | null | undefined>(() => props.value.auth?.user);
const flash = computed(() => props.value.flash?.success);
const app = computed(() => props.value.app);
const currentPath = computed(() => page.url.split('?')[0]);

const navGroups: NavGroup[] = [
    {
        labelKey: 'layout.operationsGroup',
        items: [
            { labelKey: 'layout.dashboard', href: '/app/dashboard', icon: LayoutDashboard, permission: 'access_back_office' },
            { labelKey: 'layout.operationsBoard', href: '/app/operations', icon: CalendarDays, permission: 'manage_operations' },
            { labelKey: 'layout.workers', href: '/app/workers', icon: HardHat, permission: 'manage_workers' },
            { labelKey: 'layout.workerMobile', href: '/app/worker/today', icon: ClipboardCheck, permission: 'access_worker_portal' },
        ],
    },
    {
        labelKey: 'layout.commercialGroup',
        items: [
            { labelKey: 'layout.services', href: '/app/services', icon: Wrench, permission: 'manage_services' },
            { labelKey: 'layout.customers', href: '/app/customers', icon: UsersRound, permission: 'manage_customers' },
            { labelKey: 'layout.contracts', href: '/app/contracts', icon: BriefcaseBusiness, permission: 'manage_contracts' },
        ],
    },
    {
        labelKey: 'layout.financeGroup',
        items: [
            { labelKey: 'layout.billing', href: '/app/finance', icon: Banknote, permission: 'manage_finance' },
            { labelKey: 'layout.expenses', href: '/app/expenses', icon: ReceiptText, permission: 'manage_expenses' },
            { labelKey: 'layout.reports', href: '/app/reports', icon: Target, permission: 'view_dashboard' },
        ],
    },
    {
        labelKey: 'layout.administrationGroup',
        items: [
            { labelKey: 'layout.usersAccess', href: '/app/users', icon: UserCog, permission: 'manage_users' },
            { labelKey: 'layout.auditSettings', href: '/app/settings', icon: Settings, permission: 'manage_settings' },
        ],
    },
];

const notifications = computed(() => [
    {
        title: t('layout.missedCheckIn'),
        body: t('layout.missedCheckInBody'),
        time: 'Now',
        timeKey: 'layout.now',
        tone: 'bg-warning-500',
    },
    {
        title: t('layout.collectionsFollowUp'),
        body: t('layout.collectionsFollowUpBody'),
        time: 'Today',
        timeKey: 'layout.today',
        tone: 'bg-error-500',
    },
    {
        title: t('layout.zatcaReady'),
        body: t('layout.zatcaReadyBody'),
        time: 'Phase 1',
        timeKey: 'layout.phaseOne',
        tone: 'bg-success-500',
    },
]);

function isActive(href: string): boolean {
    return currentPath.value === href || currentPath.value.startsWith(`${href}/`);
}

function hasPermission(permission?: string): boolean {
    if (! permission) {
        return true;
    }

    const permissions = user.value?.permissions ?? [];

    return permissions.includes('*') || permissions.includes(permission);
}

const visibleNavGroups = computed(() => navGroups
    .map((group) => ({
        label: t(group.labelKey),
        items: group.items
            .filter((item) => hasPermission(item.permission))
            .map((item) => ({
                ...item,
                badge: item.badgeKey ? t(item.badgeKey) : undefined,
                label: t(item.labelKey),
            })),
    }))
    .filter((group) => group.items.length > 0));

const nextLocale = computed(() => app.value?.locale === 'ar' ? 'en' : 'ar');
const nextLocaleLabel = computed(() => nextLocale.value.toUpperCase());

function closeMenus(): void {
    mobileOpen.value = false;
    userMenuOpen.value = false;
    notificationsOpen.value = false;
}

function switchLocale(): void {
    router.post('/app/locale', { locale: nextLocale.value }, {
        preserveScroll: true,
    });
}

function logout(): void {
    router.post('/logout');
}
</script>

<template>
    <div class="flex min-h-screen overflow-hidden bg-gray-50 text-gray-900">
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-40 bg-gray-900/45 lg:hidden"
            aria-hidden="true"
            @click="mobileOpen = false"
        />

        <aside
            class="fixed inset-y-0 start-0 z-50 flex h-screen w-[290px] flex-col overflow-y-hidden border-e border-gray-200 bg-white px-5 transition-transform duration-200 lg:static lg:translate-x-0"
            :class="mobileOpen ? 'translate-x-0' : 'ta-sidebar-closed'"
        >
            <div class="flex items-center justify-between gap-3 pb-7 pt-8">
                <Link href="/app/dashboard" class="flex items-center gap-3" @click="mobileOpen = false">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-500 text-white shadow-theme-sm">
                        <ShieldCheck class="h-5 w-5" />
                    </span>
                        <span>
                            <span class="block text-base font-bold text-gray-900">CleanOps</span>
                        <span class="block text-xs font-medium text-gray-500">{{ t('layout.companySubtitle') }}</span>
                    </span>
                </Link>

                <button
                    type="button"
                    class="focus-ring flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 lg:hidden"
                    aria-label="Close sidebar"
                    @click="mobileOpen = false"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <nav class="no-scrollbar flex-1 overflow-y-auto">
                <div v-for="group in visibleNavGroups" :key="group.label" class="mb-6">
                    <h3 class="mb-4 px-3 text-xs font-medium uppercase leading-5 text-gray-400">
                        {{ group.label }}
                    </h3>
                    <ul class="flex flex-col gap-1">
                        <li v-for="item in group.items" :key="`${group.label}-${item.label}`">
                            <Link
                                :href="item.href"
                                class="ta-menu-item group"
                                :class="isActive(item.href) && !item.badge ? 'ta-menu-item-active' : ''"
                                @click="mobileOpen = false"
                            >
                                <component :is="item.icon" class="h-5 w-5" />
                                <span class="flex-1">{{ item.label }}</span>
                                <span
                                    v-if="item.badge"
                                    class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium uppercase text-brand-500"
                                >
                                    {{ item.badge }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="mb-6 rounded-2xl bg-gray-50 px-4 py-5">
                <h3 class="text-sm font-semibold text-gray-900">{{ t('layout.phaseFocus') }}</h3>
                <p class="mt-2 text-sm leading-5 text-gray-500">{{ t('layout.phaseFocusBody') }}</p>
                <Link href="/app/operations" class="ta-btn ta-btn-primary mt-4 w-full" @click="mobileOpen = false">
                    {{ t('layout.openToday') }}
                </Link>
            </div>
        </aside>

        <div class="relative flex min-w-0 flex-1 flex-col overflow-x-hidden overflow-y-auto">
            <header class="sticky top-0 z-30 flex w-full border-b border-gray-200 bg-white/95 backdrop-blur">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-500 via-success-500 to-warning-500" />

                <div class="flex grow flex-col items-center justify-between lg:flex-row lg:gap-4 lg:px-6">
                    <div class="flex w-full items-center justify-between gap-3 border-b border-gray-200 px-3 py-3 lg:flex-1 lg:justify-normal lg:border-b-0 lg:px-0 lg:py-4">
                        <button
                            type="button"
                            class="focus-ring flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 shadow-theme-xs hover:bg-gray-50 lg:hidden"
                            aria-label="Open sidebar"
                            @click.stop="mobileOpen = !mobileOpen"
                        >
                            <Menu class="h-5 w-5" />
                        </button>

                        <Link href="/app/dashboard" class="flex items-center gap-2 lg:hidden" @click="closeMenus">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-500 text-white">
                                <ShieldCheck class="h-4 w-4" />
                            </span>
                            <span class="text-sm font-bold text-gray-900">CleanOps</span>
                        </Link>

                        <div class="hidden min-w-0 flex-1 lg:block">
                            <div class="relative max-w-[520px]">
                                <span class="absolute top-1/2 start-4 -translate-y-1/2 text-gray-500">
                                    <Search class="h-5 w-5" />
                                </span>
                                <input
                                    type="text"
                                    :placeholder="t('layout.searchPlaceholder')"
                                    class="ta-input h-11 w-[430px] bg-transparent py-2.5 pe-24 ps-12 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10"
                                >
                                <span class="absolute top-1/2 end-2.5 -translate-y-1/2 rounded-lg border border-gray-200 bg-gray-50 px-2 py-1 text-xs font-medium text-gray-500">
                                    Ctrl K
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex w-full items-center justify-end gap-3 px-4 py-3 shadow-theme-md lg:w-auto lg:px-0 lg:py-0 lg:shadow-none">
                        <div class="flex max-w-full items-center gap-2 rounded-2xl border border-gray-200/80 bg-gradient-to-r from-brand-50 via-white to-success-50 p-1.5 shadow-theme-xs">
                        <button
                            type="button"
                                class="focus-ring inline-flex h-10 min-w-16 items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 px-3 text-sm font-bold text-white shadow-theme-sm transition-transform hover:-translate-y-0.5 hover:shadow-theme-md"
                            :aria-label="t('layout.switchLanguage')"
                            @click="switchLocale"
                        >
                                <Languages class="h-4 w-4" />
                            {{ nextLocaleLabel }}
                        </button>

                        <div class="relative">
                            <button
                                type="button"
                                    class="focus-ring relative flex h-10 w-10 items-center justify-center rounded-xl border border-warning-500/20 bg-warning-50 text-warning-600 shadow-theme-xs transition-transform hover:-translate-y-0.5 hover:bg-warning-500 hover:text-white"
                                aria-label="Notifications"
                                @click.stop="notificationsOpen = !notificationsOpen; userMenuOpen = false"
                            >
                                    <span class="absolute -right-0.5 -top-0.5 z-10 h-3 w-3 rounded-full border-2 border-white bg-warning-500">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-warning-500 opacity-75" />
                                </span>
                                <Bell class="h-5 w-5" />
                            </button>

                            <div
                                v-if="notificationsOpen"
                                class="absolute end-0 mt-4 w-[340px] max-w-[calc(100vw-2rem)] rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg"
                            >
                                <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-3">
                                    <h5 class="text-lg font-semibold text-gray-900">{{ t('layout.notifications') }}</h5>
                                    <button type="button" class="text-gray-500" @click="notificationsOpen = false">
                                        <X class="h-5 w-5" />
                                    </button>
                                </div>

                                <ul class="max-h-[360px] overflow-y-auto">
                                    <li v-for="notification in notifications" :key="notification.title">
                                        <button type="button" class="flex w-full gap-3 rounded-lg px-4 py-3 text-start hover:bg-gray-50">
                                            <span class="mt-1 h-2.5 w-2.5 rounded-full" :class="notification.tone" />
                                            <span>
                                                <span class="block text-sm font-medium text-gray-800">{{ notification.title }}</span>
                                                <span class="mt-1 block text-sm leading-5 text-gray-500">{{ notification.body }}</span>
                                                <span class="mt-2 block text-xs font-medium text-gray-400">{{ t(notification.timeKey, notification.time) }}</span>
                                            </span>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="relative">
                            <button
                                type="button"
                                    class="focus-ring flex min-w-0 items-center rounded-xl bg-white px-1.5 py-1 text-gray-700 shadow-theme-xs ring-1 ring-gray-200 transition-transform hover:-translate-y-0.5 hover:ring-brand-300"
                                @click.stop="userMenuOpen = !userMenuOpen; notificationsOpen = false"
                            >
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-brand-500 via-brand-600 to-success-500 text-sm font-bold text-white shadow-theme-xs">
                                    {{ user?.name?.slice(0, 1) ?? 'S' }}
                                </span>
                                    <span class="ms-2 me-1 hidden max-w-36 truncate text-sm font-semibold text-gray-800 xl:block">{{ user?.name ?? t('layout.staffUser') }}</span>
                                    <ChevronDown class="hidden h-4 w-4 text-gray-500 sm:block" :class="userMenuOpen ? 'rotate-180' : ''" />
                            </button>

                            <div
                                v-if="userMenuOpen"
                                class="absolute end-0 mt-4 flex w-[260px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg"
                            >
                                <div class="px-3 py-2">
                                    <span class="block text-sm font-medium text-gray-700">{{ user?.name ?? t('layout.staffUser') }}</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">{{ user?.email ?? user?.role ?? 'staff' }}</span>
                                </div>

                                <ul class="flex flex-col gap-1 border-y border-gray-200 py-3">
                                    <li>
                                        <button type="button" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                                            <UserRound class="h-5 w-5 text-gray-500" />
                                            {{ t('layout.profile') }}
                                        </button>
                                    </li>
                                    <li>
                                        <Link
                                            v-if="hasPermission('manage_settings')"
                                            href="/app/settings"
                                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                                            @click="closeMenus"
                                        >
                                            <Settings class="h-5 w-5 text-gray-500" />
                                            {{ t('layout.accountSettings') }}
                                        </Link>
                                        <button
                                            v-else
                                            type="button"
                                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-400"
                                        >
                                            <Settings class="h-5 w-5 text-gray-400" />
                                            {{ t('layout.accountSettings') }}
                                        </button>
                                    </li>
                                </ul>

                                <button
                                    type="button"
                                    class="mt-3 flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                                    @click="logout"
                                >
                                    <LogOut class="h-5 w-5 text-gray-500" />
                                    {{ t('layout.signOut') }}
                                </button>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1536px] p-4 md:p-6">
                <div v-if="flash" class="mb-4 rounded-xl border border-success-500/20 bg-success-50 px-4 py-3 text-sm font-semibold text-success-600">
                    {{ flash }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
