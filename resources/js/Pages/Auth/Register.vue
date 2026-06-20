<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, UserPlus } from '@lucide/vue';
import PublicHeader from '../../Components/PublicHeader.vue';
import { useI18n } from '../../lib/i18n';

const { locale, t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    customer_type: 'individual',
    city: 'Riyadh',
    district: '',
    address: '',
    preferred_locale: locale.value,
});

function submit(): void {
    form.preferred_locale = locale.value;
    form.post('/register');
}
</script>

<template>
    <Head :title="t('auth.registerHeadTitle', 'Create customer account')" />

    <main class="min-h-screen bg-[#f7f9fd]">
        <PublicHeader />

        <div class="mx-auto grid min-h-[calc(100vh-80px)] max-w-7xl gap-8 px-5 py-8 lg:grid-cols-[0.82fr_1fr] lg:px-8">
            <section class="flex flex-col justify-between rounded-lg bg-[#071b3d] p-8 text-white">
                <div class="py-8">
                    <div class="inline-flex items-center gap-2 rounded-md bg-white/10 px-3 py-1 text-sm font-bold text-[#ffdd72]">
                        <Building2 class="h-4 w-4" />
                        {{ t('auth.customerPortalBadge', 'Customer portal') }}
                    </div>
                    <h1 class="mt-5 text-4xl font-extrabold leading-tight">{{ t('auth.registerHeroTitle', 'Book cleaning packages and manage your service from one place.') }}</h1>
                    <p class="mt-5 text-base leading-8 text-slate-300">
                        {{ t('auth.registerHeroBody', 'Create an account to choose Nofa Clean services, request preferred dates, and later follow contracts, visits, invoices, and payments.') }}
                    </p>
                </div>

                <Link href="/login" class="inline-flex w-fit rounded-md bg-white px-4 py-2 text-sm font-extrabold text-[#071b3d] hover:bg-slate-100">
                    {{ t('auth.alreadyHaveAccount', 'Already have an account? Login') }}
                </Link>
            </section>

            <section class="flex items-center">
                <form class="w-full rounded-lg border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/70 md:p-8" @submit.prevent="submit">
                    <h2 class="text-2xl font-extrabold text-[#071b3d]">{{ t('auth.registerTitle', 'Create customer account') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('auth.registerIntro', 'Tell us who you are and where your first service site is located.') }}</p>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('auth.fullName', 'Full name') }}
                            <input v-model="form.name" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="text" autocomplete="name">
                            <span v-if="form.errors.name" class="mt-1 block text-xs font-bold text-red-600">{{ form.errors.name }}</span>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('auth.customerType', 'Customer type') }}
                            <select v-model="form.customer_type" class="mt-2 w-full rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100">
                                <option value="individual">{{ t('auth.individual', 'Individual') }}</option>
                                <option value="company">{{ t('auth.company', 'Company') }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('auth.email', 'Email') }}
                            <input v-model="form.email" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="email" autocomplete="email">
                            <span v-if="form.errors.email" class="mt-1 block text-xs font-bold text-red-600">{{ form.errors.email }}</span>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('auth.phone', 'Mobile number') }}
                            <input v-model="form.phone" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="tel" autocomplete="tel">
                            <span v-if="form.errors.phone" class="mt-1 block text-xs font-bold text-red-600">{{ form.errors.phone }}</span>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('auth.password', 'Password') }}
                            <input v-model="form.password" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="password" autocomplete="new-password">
                            <span v-if="form.errors.password" class="mt-1 block text-xs font-bold text-red-600">{{ form.errors.password }}</span>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('auth.confirmPassword', 'Confirm password') }}
                            <input v-model="form.password_confirmation" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="password" autocomplete="new-password">
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('auth.city', 'City') }}
                            <input v-model="form.city" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="text">
                            <span v-if="form.errors.city" class="mt-1 block text-xs font-bold text-red-600">{{ form.errors.city }}</span>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            {{ t('auth.district', 'District') }}
                            <input v-model="form.district" class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" type="text">
                        </label>
                    </div>

                    <label class="mt-4 block text-sm font-bold text-slate-700">
                        {{ t('auth.address', 'Address') }}
                        <textarea v-model="form.address" class="mt-2 min-h-24 w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#1040b6] focus:ring-4 focus:ring-blue-100" />
                    </label>

                    <button type="submit" class="mt-6 inline-flex items-center gap-2 rounded-md bg-[#1040b6] px-5 py-3 text-sm font-extrabold text-white hover:bg-[#0a2f95]" :disabled="form.processing">
                        <UserPlus class="h-4 w-4" />
                        {{ t('auth.createAccount', 'Create account') }}
                    </button>
                </form>
            </section>
        </div>
    </main>
</template>
