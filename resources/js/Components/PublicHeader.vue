<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Languages, LogIn, Sparkles, UserPlus } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '../lib/i18n';
import type { AppSharedProps } from '../types';

const page = usePage<AppSharedProps>();
const { locale, t } = useI18n();

const user = computed(() => page.props.auth?.user);
const dashboardHref = computed(() => {
    const permissions = user.value?.permissions ?? [];

    if (permissions.includes('*') || permissions.includes('access_back_office')) {
        return '/app/dashboard';
    }

    if (permissions.includes('access_worker_portal')) {
        return '/app/worker/today';
    }

    if (permissions.includes('access_customer_portal')) {
        return '/app/customer';
    }

    return '/';
});

function changeLocale(): void {
    router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveScroll: true });
}
</script>

<template>
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4 lg:px-8">
            <Link href="/" class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-lg bg-[#1040b6] text-[#ffb703] shadow-lg shadow-blue-900/15">
                    <Sparkles class="h-6 w-6" />
                </span>
                <span>
                    <span class="block text-xl font-extrabold leading-none text-[#071b3d]">{{ t('publicLead.brand', 'Nofa Clean') }}</span>
                    <span class="mt-1 block text-xs font-bold uppercase text-slate-500">{{ t('publicLead.brandTagline', 'Saudi facility care') }}</span>
                </span>
            </Link>

            <nav class="hidden items-center gap-7 text-sm font-bold text-slate-700 lg:flex">
                <a href="/#services" class="hover:text-[#1040b6]">{{ t('publicLead.navServices', 'Services') }}</a>
                <a href="/#quality" class="hover:text-[#1040b6]">{{ t('publicLead.navQuality', 'Quality') }}</a>
                <a href="/#process" class="hover:text-[#1040b6]">{{ t('publicLead.navProcess', 'Process') }}</a>
                <a href="/#quote" class="hover:text-[#1040b6]">{{ t('publicLead.navQuote', 'Quote') }}</a>
            </nav>

            <div class="flex items-center gap-2">
                <button type="button" class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50" @click="changeLocale">
                    <Languages class="h-4 w-4" />
                </button>
                <Link
                    v-if="user"
                    :href="dashboardHref"
                    class="inline-flex items-center gap-2 rounded-md bg-[#1040b6] px-3 py-2 text-sm font-bold text-white hover:bg-[#0a2f95]"
                >
                    {{ t('auth.enterDashboard', 'Enter dashboard') }}
                </Link>
                <template v-else>
                    <Link href="/login" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        <LogIn class="h-4 w-4" />
                        {{ t('publicLead.login', 'Login') }}
                    </Link>
                    <Link href="/register" class="hidden items-center gap-2 rounded-md bg-[#1040b6] px-3 py-2 text-sm font-bold text-white hover:bg-[#0a2f95] sm:inline-flex">
                        <UserPlus class="h-4 w-4" />
                        {{ t('publicLead.register', 'Register') }}
                    </Link>
                </template>
            </div>
        </div>
    </header>
</template>
