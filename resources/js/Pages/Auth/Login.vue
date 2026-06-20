<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LockKeyhole, ShieldCheck, UserPlus } from '@lucide/vue';
import PublicHeader from '../../Components/PublicHeader.vue';
import { useI18n } from '../../lib/i18n';

const { t } = useI18n();

const form = useForm({
    email: 'owner@nofacast.test',
    password: 'password',
    remember: true,
});

function submit(): void {
    form.post('/login');
}
</script>

<template>
    <Head :title="t('auth.headTitle', 'Login')" />

    <main class="min-h-screen bg-[#f7f9fd]">
        <PublicHeader />

        <div class="grid min-h-[calc(100vh-80px)] lg:grid-cols-[1fr_0.9fr]">
        <section class="flex items-center justify-center px-6 py-12">
            <form class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-8 shadow-xl shadow-slate-200/70" @submit.prevent="submit">
                <div class="mb-8 flex items-center gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded-lg bg-[#1040b6] text-white shadow-lg shadow-blue-900/15">
                        <ShieldCheck class="h-6 w-6" />
                    </span>
                    <div>
                        <h1 class="text-xl font-extrabold text-[#071b3d]">{{ t('auth.headTitle', 'Login') }}</h1>
                        <p class="text-sm text-slate-500">{{ t('auth.loginSubtitle', 'Staff and customer login') }}</p>
                    </div>
                </div>

                <label class="block text-sm font-bold text-slate-700" for="email">{{ t('auth.email', 'Email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100"
                    autocomplete="email"
                >
                <p v-if="form.errors.email" class="mt-2 text-sm font-bold text-red-600">{{ form.errors.email }}</p>

                <label class="mt-5 block text-sm font-bold text-slate-700" for="password">{{ t('auth.password', 'Password') }}</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100"
                    autocomplete="current-password"
                >
                <p v-if="form.errors.password" class="mt-2 text-sm font-bold text-red-600">{{ form.errors.password }}</p>

                <label class="mt-5 flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#1040b6]">
                    {{ t('auth.remember', 'Keep me signed in') }}
                </label>

                <button type="submit" class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-md bg-[#1040b6] px-5 py-3 text-sm font-extrabold text-white hover:bg-[#0a2f95]" :disabled="form.processing">
                    <LockKeyhole class="h-4 w-4" />
                    {{ t('auth.enterDashboard', 'Enter dashboard') }}
                </button>

                <div class="mt-6 rounded-lg bg-[#f7f9fd] p-4 text-xs leading-5 text-slate-600">
                    {{ t('auth.seedLogin', 'Demo staff: owner@nofacast.test / password. Demo customer: customer@nofacast.test / password.') }}
                </div>

                <Link href="/register" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md border border-slate-200 px-5 py-3 text-sm font-extrabold text-[#071b3d] hover:bg-slate-50">
                    <UserPlus class="h-4 w-4" />
                    {{ t('auth.createCustomerAccount', 'Create customer account') }}
                </Link>
            </form>
        </section>

        <section class="hidden bg-[#071b3d] px-10 py-12 text-white lg:flex lg:flex-col lg:justify-end">
            <div class="max-w-xl">
                <div class="inline-flex items-center gap-2 rounded-md bg-white/10 px-3 py-1 text-sm font-bold text-[#ffdd72]">
                    <ShieldCheck class="h-4 w-4" />
                    {{ t('auth.phaseBadge', 'Role-based access') }}
                </div>
                <h2 class="mt-5 text-4xl font-extrabold leading-tight">{{ t('auth.heroTitle', 'One login for operations, workers, and customers.') }}</h2>
                <p class="mt-5 text-base leading-8 text-slate-300">
                    {{ t('auth.heroBody', 'Staff go to the admin dashboard, workers go to their mobile workday, and customers go to their booking dashboard.') }}
                </p>
            </div>
        </section>
        </div>
    </main>
</template>
