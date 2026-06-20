<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BellRing, CreditCard, Download, Languages, LogOut, ReceiptText, Send, Sparkles, UploadCloud } from '@lucide/vue';
import { reactive } from 'vue';
import { useI18n } from '../../lib/i18n';

type PortalCustomer = {
    id: number;
    name: string;
};

type PortalInvoice = {
    id: number;
    number: string;
    status: string;
    issue_date?: string | null;
    due_date?: string | null;
    gross_total_sar: string;
    paid_total_sar: string;
    balance_sar: string;
    net_total_sar: string;
    vat_total_sar: string;
    vat_rate: number;
    contract?: {
        id: number;
        reference: string;
        detail_url: string;
        service?: string | null;
        site?: string | null;
    } | null;
    line_items: Array<{
        id: number;
        line_type: string;
        description: string;
        quantity: number;
        unit_label?: string | null;
        unit_price_sar: string;
        net_total_sar: string;
        vat_total_sar: string;
        gross_total_sar: string;
        visit_date?: string | null;
    }>;
    payments: Array<{
        id: number;
        amount_sar: string;
        method: string;
        reference?: string | null;
        received_at?: string | null;
    }>;
    payment_proofs: Array<{
        id: number;
        amount_sar: string;
        method: string;
        reference?: string | null;
        paid_on?: string | null;
        proof_url?: string | null;
        customer_note?: string | null;
        status: string;
        admin_note?: string | null;
        reviewed_at?: string | null;
    }>;
    credit_notes: Array<{
        id: number;
        number: string;
        amount_sar: string;
        status: string;
        reason: string;
    }>;
    print_url: string;
};

const props = defineProps<{
    customer: PortalCustomer;
    invoice: PortalInvoice;
    backUrl: string;
}>();

const { locale, t } = useI18n();
const proofForm = reactive({
    amount_sar: props.invoice.balance_sar,
    method: 'bank_transfer',
    reference: '',
    paid_on: today(),
    customer_note: '',
    proof_file: null as File | null,
    processing: false,
});

function changeLocale(): void {
    router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveScroll: true });
}

function logout(): void {
    router.post('/logout');
}

function statusTone(status: string): string {
    if (['paid', 'approved'].includes(status)) {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (['overdue', 'void', 'cancelled', 'rejected'].includes(status)) {
        return 'bg-red-50 text-red-700';
    }

    return 'bg-slate-100 text-slate-700';
}

function statusLabel(status: string): string {
    return t(`statuses.${status}`, status.replaceAll('_', ' '));
}

function setProofFile(event: Event): void {
    const target = event.target as HTMLInputElement;
    proofForm.proof_file = target.files?.[0] ?? null;
}

function submitPaymentProof(): void {
    proofForm.processing = true;

    router.post(`/app/customer/invoices/${props.invoice.id}/payment-proofs`, {
        amount_sar: proofForm.amount_sar,
        method: proofForm.method,
        reference: proofForm.reference,
        paid_on: proofForm.paid_on,
        customer_note: proofForm.customer_note,
        proof_file: proofForm.proof_file,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            proofForm.reference = '';
            proofForm.customer_note = '';
            proofForm.proof_file = null;
        },
        onFinish: () => {
            proofForm.processing = false;
        },
    });
}

function today(): string {
    const value = new Date();
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}
</script>

<template>
    <Head :title="t('customerPortal.invoiceDetailTitle', 'Invoice details')" />

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
                <div class="flex items-center gap-2">
                    <Link href="/app/customer/notifications" :aria-label="t('customerPortal.notifications.link', 'Notifications')" :title="t('customerPortal.notifications.link', 'Notifications')" class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50">
                        <BellRing class="h-4 w-4" />
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
                {{ t('customerPortal.backToPortal', 'Back to portal') }}
            </Link>

            <div class="mt-5 rounded-lg bg-[#071b3d] p-6 text-white lg:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-extrabold uppercase text-[#ffdd72]">{{ t('customerPortal.invoice', 'Invoice') }}</p>
                        <h1 class="mt-3 text-3xl font-extrabold md:text-4xl">{{ invoice.number }}</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">
                            {{ invoice.contract?.reference ?? '-' }} / {{ invoice.contract?.service ?? '-' }} / {{ invoice.contract?.site ?? '-' }}
                        </p>
                    </div>
                    <span class="rounded-md bg-white/10 px-3 py-1.5 text-sm font-extrabold">{{ statusLabel(invoice.status) }}</span>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <ReceiptText class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ invoice.gross_total_sar }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.invoiceTotal', 'Invoice total') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <CreditCard class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ invoice.paid_total_sar }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.paid', 'Paid') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <CreditCard class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ invoice.balance_sar }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.balance', 'Balance') }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <ReceiptText class="h-6 w-6 text-[#1040b6]" />
                    <div class="mt-3 text-2xl font-extrabold">{{ invoice.due_date ?? '-' }}</div>
                    <div class="text-sm font-bold text-slate-500">{{ t('customerPortal.dueDate', 'Due date') }}</div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_0.8fr]">
                <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.invoiceLines', 'Invoice lines') }}</h2>
                        <a :href="invoice.print_url" class="inline-flex items-center gap-2 rounded-md bg-[#1040b6] px-4 py-2 text-sm font-extrabold text-white hover:bg-[#0a2f95]" target="_blank">
                            <Download class="h-4 w-4" />
                            {{ t('customerPortal.printInvoice', 'Print') }}
                        </a>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-lg border border-slate-200">
                        <div class="grid grid-cols-[1fr_90px_110px] bg-slate-50 px-4 py-3 text-xs font-extrabold uppercase text-slate-500">
                            <span>{{ t('customerPortal.description', 'Description') }}</span>
                            <span>{{ t('customerPortal.quantity', 'Qty') }}</span>
                            <span class="text-end">{{ t('customerPortal.total', 'Total') }}</span>
                        </div>
                        <div v-for="line in invoice.line_items" :key="line.id" class="grid grid-cols-[1fr_90px_110px] border-t border-slate-200 px-4 py-3 text-sm">
                            <span>
                                <span class="block font-bold text-slate-800">{{ line.description }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ line.visit_date ?? line.line_type }}</span>
                            </span>
                            <span class="font-bold text-slate-700">{{ line.quantity }} {{ line.unit_label ?? '' }}</span>
                            <span class="text-end font-extrabold text-[#1040b6]">{{ line.gross_total_sar }}</span>
                        </div>
                        <div v-if="invoice.line_items.length === 0" class="border-t border-slate-200 px-4 py-6 text-center text-sm font-bold text-slate-500">
                            {{ t('customerPortal.noInvoiceLines', 'No invoice lines available.') }}
                        </div>
                    </div>
                </section>

                <aside class="space-y-6">
                    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.totals', 'Totals') }}</h2>
                        <dl class="mt-5 grid gap-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="font-bold text-slate-500">{{ t('customerPortal.netTotal', 'Net') }}</dt>
                                <dd class="font-extrabold">{{ invoice.net_total_sar }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="font-bold text-slate-500">{{ t('customerPortal.vatTotal', 'VAT') }} {{ invoice.vat_rate }}%</dt>
                                <dd class="font-extrabold">{{ invoice.vat_total_sar }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 border-t border-slate-200 pt-3">
                                <dt class="font-bold text-slate-500">{{ t('customerPortal.grossTotal', 'Gross') }}</dt>
                                <dd class="font-extrabold">{{ invoice.gross_total_sar }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="font-bold text-slate-500">{{ t('customerPortal.paid', 'Paid') }}</dt>
                                <dd class="font-extrabold">{{ invoice.paid_total_sar }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="font-bold text-slate-500">{{ t('customerPortal.balance', 'Balance') }}</dt>
                                <dd class="font-extrabold text-[#1040b6]">{{ invoice.balance_sar }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-lg bg-[#1040b6]/10 text-[#1040b6]">
                                <UploadCloud class="h-5 w-5" />
                            </span>
                            <h2 class="text-xl font-extrabold">{{ t('customerPortal.submitPaymentProof', 'Submit payment proof') }}</h2>
                        </div>

                        <form class="mt-5 grid gap-4" @submit.prevent="submitPaymentProof">
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.amountSar', 'Amount (SAR)') }}
                                <input v-model="proofForm.amount_sar" type="number" min="0.01" step="0.01" inputmode="decimal" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="text-sm font-bold text-slate-600">
                                    {{ t('customerPortal.paymentMethod', 'Payment method') }}
                                    <select v-model="proofForm.method" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                        <option value="bank_transfer">{{ t('customerPortal.methods.bank_transfer', 'Bank transfer') }}</option>
                                        <option value="cash">{{ t('customerPortal.methods.cash', 'Cash') }}</option>
                                        <option value="card">{{ t('customerPortal.methods.card', 'Card') }}</option>
                                        <option value="cheque">{{ t('customerPortal.methods.cheque', 'Cheque') }}</option>
                                        <option value="online">{{ t('customerPortal.methods.online', 'Online') }}</option>
                                    </select>
                                </label>
                                <label class="text-sm font-bold text-slate-600">
                                    {{ t('customerPortal.paidOn', 'Paid on') }}
                                    <input v-model="proofForm.paid_on" type="date" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                                </label>
                            </div>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.reference', 'Reference') }}
                                <input v-model="proofForm.reference" type="text" class="mt-1 h-11 w-full rounded-md border border-slate-200 px-3 text-sm font-bold text-slate-800">
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.proofFile', 'Receipt or proof file') }}
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-800" @change="setProofFile">
                            </label>
                            <label class="text-sm font-bold text-slate-600">
                                {{ t('customerPortal.notes', 'Notes') }}
                                <textarea v-model="proofForm.customer_note" rows="3" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-800"></textarea>
                            </label>
                            <button type="submit" class="inline-flex w-fit items-center gap-2 rounded-md bg-[#1040b6] px-4 py-2 text-sm font-extrabold text-white disabled:opacity-60" :disabled="proofForm.processing">
                                <Send class="h-4 w-4" />
                                {{ t('customerPortal.sendPaymentProof', 'Send for review') }}
                            </button>
                        </form>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.payments', 'Payments') }}</h2>
                        <div class="mt-5 grid gap-3">
                            <article v-for="payment in invoice.payments" :key="payment.id" class="rounded-lg border border-slate-200 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-extrabold">{{ payment.amount_sar }}</span>
                                    <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-extrabold text-emerald-700">{{ payment.method }}</span>
                                </div>
                                <p class="mt-2 text-xs font-bold text-slate-500">{{ payment.received_at ?? '-' }} / {{ payment.reference ?? '-' }}</p>
                            </article>
                            <div v-if="invoice.payments.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500">{{ t('customerPortal.noPayments', 'No payments recorded yet.') }}</div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.paymentProofHistory', 'Payment proof history') }}</h2>
                        <div class="mt-5 grid gap-3">
                            <article v-for="proof in invoice.payment_proofs" :key="proof.id" class="rounded-lg border border-slate-200 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="font-extrabold">{{ proof.amount_sar }}</p>
                                        <p class="mt-1 text-xs font-bold text-slate-500">{{ proof.paid_on ?? '-' }} / {{ proof.reference ?? '-' }}</p>
                                    </div>
                                    <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(proof.status)">{{ statusLabel(proof.status) }}</span>
                                </div>
                                <p v-if="proof.customer_note" class="mt-3 text-sm leading-6 text-slate-600">{{ proof.customer_note }}</p>
                                <p v-if="proof.admin_note" class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-sm leading-6 text-slate-600">{{ proof.admin_note }}</p>
                                <a v-if="proof.proof_url" :href="proof.proof_url" target="_blank" class="mt-3 inline-flex items-center gap-2 text-sm font-extrabold text-[#1040b6]">
                                    <Download class="h-4 w-4" />
                                    {{ t('customerPortal.viewProof', 'View proof') }}
                                </a>
                            </article>
                            <div v-if="invoice.payment_proofs.length === 0" class="rounded-lg border border-slate-200 p-4 text-sm font-bold text-slate-500">{{ t('customerPortal.noPaymentProofs', 'No payment proofs submitted yet.') }}</div>
                        </div>
                    </section>

                    <section v-if="invoice.credit_notes.length" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-extrabold">{{ t('customerPortal.creditNotes', 'Credit notes') }}</h2>
                        <div class="mt-5 grid gap-3">
                            <article v-for="credit in invoice.credit_notes" :key="credit.id" class="rounded-lg border border-slate-200 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-extrabold">{{ credit.number }}</span>
                                    <span class="rounded-md px-2 py-1 text-xs font-extrabold" :class="statusTone(credit.status)">{{ statusLabel(credit.status) }}</span>
                                </div>
                                <p class="mt-2 text-sm font-extrabold text-[#1040b6]">{{ credit.amount_sar }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ credit.reason }}</p>
                            </article>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
    </main>
</template>
