<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LockKeyhole, ShieldCheck } from '@lucide/vue';
import { useI18n } from '../../lib/i18n';

const form = useForm({
    email: 'owner@nofacast.test',
    password: 'password',
    remember: true,
});

const { t } = useI18n();

function submit(): void {
    form.post('/login');
}
</script>

<template>
    <Head :title="t('auth.headTitle', 'Login')" />

    <main class="grid min-h-screen bg-gray-50 lg:grid-cols-[1fr_0.88fr]">
        <section class="flex items-center justify-center px-6 py-12">
            <form class="ta-card w-full max-w-md p-8" @submit.prevent="submit">
                <div class="mb-8 flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-500 text-white shadow-theme-sm">
                        <ShieldCheck class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">{{ t('auth.productName', 'CleanOps') }}</h1>
                        <p class="text-sm text-gray-500">{{ t('auth.staffLogin', 'Staff operations login') }}</p>
                    </div>
                </div>

                <label class="block text-sm font-medium text-gray-700" for="email">{{ t('auth.email', 'Email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="ta-input focus-ring mt-2 w-full px-3 py-2.5 text-sm focus:border-brand-300"
                    autocomplete="email"
                >
                <p v-if="form.errors.email" class="mt-2 text-sm font-semibold text-error-600">{{ form.errors.email }}</p>

                <label class="mt-5 block text-sm font-medium text-gray-700" for="password">{{ t('auth.password', 'Password') }}</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="ta-input focus-ring mt-2 w-full px-3 py-2.5 text-sm focus:border-brand-300"
                    autocomplete="current-password"
                >
                <p v-if="form.errors.password" class="mt-2 text-sm font-semibold text-error-600">{{ form.errors.password }}</p>

                <label class="mt-5 flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-500">
                    {{ t('auth.remember', 'Keep me signed in') }}
                </label>

                <button type="submit" class="ta-btn ta-btn-primary mt-6 w-full" :disabled="form.processing">
                    <LockKeyhole class="h-4 w-4" />
                    {{ t('auth.enterDashboard', 'Enter dashboard') }}
                </button>

                <p class="mt-5 text-xs leading-5 text-gray-500">
                    {{ t('auth.seedLogin', 'Seed login: owner@nofacast.test / password. Public registration is disabled for Phase 1.') }}
                </p>
            </form>
        </section>

        <section class="hidden bg-gray-900 px-10 py-12 text-white lg:flex lg:flex-col lg:justify-end">
            <div class="max-w-lg">
                <div class="inline-flex rounded-full bg-white/10 px-3 py-1 text-sm font-medium text-brand-100">{{ t('auth.phaseBadge', 'Operate and Get Paid') }}</div>
                <h2 class="mt-5 text-4xl font-bold leading-tight">{{ t('auth.heroTitle', 'Contracts, schedules, attendance, and Saudi invoices in one admin system.') }}</h2>
                <p class="mt-5 text-base leading-7 text-gray-300">
                    {{ t('auth.heroBody', 'Phase 1 focuses on the operational proof needed every day: who is assigned, who arrived, what was completed, and what needs collection.') }}
                </p>
            </div>
        </section>
    </main>
</template>
