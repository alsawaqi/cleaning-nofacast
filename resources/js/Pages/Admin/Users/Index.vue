<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    BadgeCheck,
    Filter,
    KeyRound,
    Pencil,
    Plus,
    RotateCcw,
    Save,
    Search,
    ShieldCheck,
    UserCheck,
    UserCog,
    UserRound,
    UserX,
    UsersRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useI18n } from '../../../lib/i18n';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    role: string;
    locale: string;
    is_active: boolean;
    worker?: {
        id: number;
        employee_code: string;
        name: string;
    } | null;
};

type RoleDefinition = {
    label: string;
    permissions: string[];
};

type WorkerOption = {
    id: number;
    employee_code: string;
    name: string;
    status: string;
    user_id?: number | null;
    user_name?: string | null;
};

type AuditRow = {
    id: number;
    action: string;
    actor: string;
    auditable_type?: string | null;
    auditable_id?: number | null;
    changes: Record<string, unknown>;
    created_at?: string | null;
};

type PaginationLink = {
    url?: string | null;
    label: string;
    active: boolean;
};

type Paginated<T> = {
    data: T[];
    links?: PaginationLink[];
};

const props = defineProps<{
    users: Paginated<AdminUser>;
    roles: Record<string, RoleDefinition>;
    workers: WorkerOption[];
    auditLogs: AuditRow[];
    filters: {
        q?: string;
        role?: string;
        status?: string;
    };
    metrics: {
        total: number;
        active: number;
        inactive: number;
        workerLinked: number;
    };
}>();

const { t } = useI18n();
const selectedUser = ref<AdminUser | null>(null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'operations',
    locale: 'ar',
    password: '',
    password_confirmation: '',
    is_active: true,
    worker_id: null as number | null,
});

const filtersForm = useForm({
    q: props.filters.q ?? '',
    role: props.filters.role ?? '',
    status: props.filters.status ?? '',
});

const roleOptions = computed(() => Object.entries(props.roles));
const availableWorkers = computed(() => props.workers);
const isEditing = computed(() => selectedUser.value !== null);
const formTitle = computed(() => isEditing.value ? t('adminUsers.updateUser', 'Update user') : t('adminUsers.newUser', 'New user'));

const metricCards = computed(() => [
    { label: t('adminUsers.totalUsers', 'Total users'), value: props.metrics.total, icon: UsersRound, tone: 'bg-brand-50 text-brand-500' },
    { label: t('adminUsers.activeUsers', 'Active users'), value: props.metrics.active, icon: UserCheck, tone: 'bg-success-50 text-success-600' },
    { label: t('adminUsers.inactiveUsers', 'Inactive users'), value: props.metrics.inactive, icon: UserX, tone: 'bg-error-50 text-error-600' },
    { label: t('adminUsers.linkedWorkers', 'Linked workers'), value: props.metrics.workerLinked, icon: BadgeCheck, tone: 'bg-warning-50 text-warning-600' },
]);

function resetForm(): void {
    selectedUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'operations';
    form.locale = 'ar';
    form.is_active = true;
    form.worker_id = null;
}

function editUser(user: AdminUser): void {
    selectedUser.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.phone = user.phone ?? '';
    form.role = user.role;
    form.locale = user.locale;
    form.password = '';
    form.password_confirmation = '';
    form.is_active = user.is_active;
    form.worker_id = user.worker?.id ?? null;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    };

    if (selectedUser.value) {
        form.patch(`/app/users/${selectedUser.value.id}`, options);
        return;
    }

    form.post('/app/users', options);
}

function applyFilters(): void {
    router.get('/app/users', {
        q: filtersForm.q,
        role: filtersForm.role,
        status: filtersForm.status,
    }, {
        preserveState: true,
        replace: true,
    });
}

function clearFilters(): void {
    filtersForm.q = '';
    filtersForm.role = '';
    filtersForm.status = '';
    applyFilters();
}

function workerLabel(worker: WorkerOption): string {
    const linked = worker.user_name ? ` - ${t('adminUsers.linkedTo', 'Linked to')} ${worker.user_name}` : '';

    return `${worker.employee_code} - ${worker.name}${linked}`;
}

function roleLabel(role: string): string {
    return t(`adminUsers.roles.${role}`, props.roles[role]?.label ?? role);
}

function permissionLabel(permission: string): string {
    return permission === '*'
        ? t('adminUsers.fullAccess', 'Full access')
        : t(`adminUsers.permissionLabels.${permission}`, permission.replaceAll('_', ' '));
}

function auditSummary(log: AuditRow): string {
    return Object.keys(log.changes ?? {}).slice(0, 3).join(', ') || log.auditable_type || 'system';
}
</script>

<template>
    <Head :title="t('layout.usersAccess', 'Users & Access')" />

    <section class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="ta-page-title">{{ t('adminUsers.title', 'Users & access') }}</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    {{ t('adminUsers.subtitle', 'Create staff accounts, assign roles, link worker profiles, and review recent admin actions.') }}
                </p>
            </div>

            <button type="button" class="ta-btn ta-btn-primary" @click="resetForm">
                <Plus class="h-4 w-4" />
                {{ t('adminUsers.newUser', 'New user') }}
            </button>
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

        <div class="grid grid-cols-12 gap-6">
            <article class="ta-card col-span-12 p-5 xl:col-span-4">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <div class="ta-icon-box">
                            <UserCog class="h-6 w-6" />
                        </div>
                        <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ formTitle }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('adminUsers.pageHint', 'Passwords are required for new users and optional when editing.') }}</p>
                    </div>
                    <button type="button" class="ta-btn ta-btn-secondary px-3" @click="resetForm">
                        <RotateCcw class="h-4 w-4" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('adminUsers.name', 'Name') }}</span>
                        <input v-model="form.name" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        <span v-if="form.errors.name" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.name }}</span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('adminUsers.email', 'Email') }}</span>
                        <input v-model="form.email" type="email" class="ta-input h-11 w-full px-4 text-sm">
                        <span v-if="form.errors.email" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.email }}</span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('adminUsers.phone', 'Phone') }}</span>
                        <input v-model="form.phone" type="text" class="ta-input h-11 w-full px-4 text-sm">
                        <span v-if="form.errors.phone" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.phone }}</span>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('adminUsers.role', 'Role') }}</span>
                            <select v-model="form.role" class="ta-input h-11 w-full px-4 text-sm">
                                <option v-for="[role, definition] in roleOptions" :key="role" :value="role">
                                    {{ roleLabel(role) }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('adminUsers.locale', 'Locale') }}</span>
                            <select v-model="form.locale" class="ta-input h-11 w-full px-4 text-sm">
                                <option value="ar">{{ t('adminUsers.localeArabic', 'Arabic') }}</option>
                                <option value="en">{{ t('adminUsers.localeEnglish', 'English') }}</option>
                            </select>
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('adminUsers.workerProfile', 'Worker profile') }}</span>
                        <select v-model.number="form.worker_id" class="ta-input h-11 w-full px-4 text-sm">
                            <option :value="null">{{ t('adminUsers.noWorker', 'No worker profile') }}</option>
                            <option v-for="worker in availableWorkers" :key="worker.id" :value="worker.id">
                                {{ workerLabel(worker) }}
                            </option>
                        </select>
                        <span v-if="form.errors.worker_id" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.worker_id }}</span>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('adminUsers.password', 'Password') }}</span>
                            <input v-model="form.password" type="password" class="ta-input h-11 w-full px-4 text-sm">
                            <span v-if="form.errors.password" class="mt-1 block text-xs font-semibold text-error-600">{{ form.errors.password }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700">{{ t('adminUsers.confirmPassword', 'Confirm password') }}</span>
                            <input v-model="form.password_confirmation" type="password" class="ta-input h-11 w-full px-4 text-sm">
                        </label>
                    </div>

                    <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
                        <span>
                            <span class="block text-sm font-semibold text-gray-800">{{ t('adminUsers.status', 'Status') }}</span>
                            <span class="text-xs text-gray-500">{{ form.is_active ? t('adminUsers.active', 'Active') : t('adminUsers.inactive', 'Inactive') }}</span>
                        </span>
                        <input v-model="form.is_active" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-brand-500">
                    </label>

                    <button type="submit" class="ta-btn ta-btn-primary w-full" :disabled="form.processing">
                        <Save class="h-4 w-4" />
                        {{ t('adminUsers.saveUser', 'Save user') }}
                    </button>
                </form>
            </article>

            <div class="col-span-12 space-y-6 xl:col-span-8">
                <article class="ta-card p-5">
                    <div class="mb-5 space-y-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('adminUsers.staffAccounts', 'Staff accounts') }}</h2>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-500">{{ t('adminUsers.subtitle', 'Create staff accounts, assign roles, link worker profiles, and review recent admin actions.') }}</p>
                        </div>

                        <form class="grid gap-2 sm:grid-cols-2 2xl:grid-cols-[minmax(220px,1fr)_150px_150px_auto_auto]" @submit.prevent="applyFilters">
                            <div class="relative sm:col-span-2 2xl:col-span-1">
                                <Search class="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    v-model="filtersForm.q"
                                    type="text"
                                    class="ta-input h-11 w-full px-4 ps-10 text-sm"
                                    :placeholder="t('adminUsers.searchPlaceholder', 'Search name, email, or phone')"
                                >
                            </div>

                            <select v-model="filtersForm.role" class="ta-input h-11 w-full px-4 text-sm">
                                <option value="">{{ t('adminUsers.allRoles', 'All roles') }}</option>
                                <option v-for="[role] in roleOptions" :key="role" :value="role">{{ roleLabel(role) }}</option>
                            </select>

                            <select v-model="filtersForm.status" class="ta-input h-11 w-full px-4 text-sm">
                                <option value="">{{ t('adminUsers.allStatuses', 'All statuses') }}</option>
                                <option value="active">{{ t('adminUsers.activeOnly', 'Active only') }}</option>
                                <option value="inactive">{{ t('adminUsers.inactiveOnly', 'Inactive only') }}</option>
                            </select>

                            <button type="submit" class="ta-btn ta-btn-secondary w-full 2xl:w-auto">
                                <Filter class="h-4 w-4" />
                                {{ t('adminUsers.applyFilters', 'Apply filters') }}
                            </button>
                            <button type="button" class="ta-btn ta-btn-secondary w-full 2xl:w-auto" @click="clearFilters">
                                {{ t('adminUsers.clear', 'Clear') }}
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ta-table min-w-[680px]">
                            <thead>
                                <tr>
                                    <th>{{ t('adminUsers.name', 'Name') }}</th>
                                    <th>{{ t('adminUsers.role', 'Role') }}</th>
                                    <th>{{ t('adminUsers.workerProfile', 'Worker profile') }}</th>
                                    <th>{{ t('adminUsers.status', 'Status') }}</th>
                                    <th>{{ t('adminUsers.locale', 'Locale') }}</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in users.data" :key="user.id">
                                    <td>
                                        <div class="font-semibold text-gray-900">{{ user.name }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ user.email }}</div>
                                        <div v-if="user.phone" class="mt-1 text-xs text-gray-400">{{ user.phone }}</div>
                                    </td>
                                    <td>
                                        <span class="rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-600">
                                            {{ roleLabel(user.role) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div v-if="user.worker" class="text-sm text-gray-700">
                                            {{ user.worker.employee_code }} - {{ user.worker.name }}
                                        </div>
                                        <span v-else class="text-sm text-gray-400">{{ t('adminUsers.noWorker', 'No worker profile') }}</span>
                                    </td>
                                    <td><StatusBadge :status="user.is_active ? 'active' : 'inactive'" /></td>
                                    <td class="uppercase">{{ user.locale }}</td>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="ta-btn ta-btn-secondary h-9 w-9 p-0"
                                            :aria-label="t('adminUsers.edit', 'Edit')"
                                            @click="editUser(user)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="users.links?.length" class="mt-4 flex flex-wrap gap-2">
                        <Link
                            v-for="link in users.links"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url ?? '#'"
                            class="rounded-lg border px-3 py-2 text-sm"
                            :class="[
                                link.active ? 'border-brand-500 bg-brand-50 text-brand-600' : 'border-gray-200 text-gray-600',
                                !link.url ? 'pointer-events-none opacity-50' : '',
                            ]"
                            preserve-scroll
                        >
                            <span v-html="link.label" />
                        </Link>
                    </div>
                </article>

                <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                    <article class="ta-card p-5">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="ta-icon-box">
                                <ShieldCheck class="h-6 w-6" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">{{ t('adminUsers.rolePermissions', 'Role permissions') }}</h2>
                                <p class="text-sm text-gray-500">{{ t('adminUsers.permissions', 'Permissions') }}</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div v-for="[role, definition] in roleOptions" :key="role" class="rounded-xl border border-gray-200 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="font-semibold text-gray-900">{{ roleLabel(role) }}</div>
                                    <KeyRound class="h-4 w-4 text-brand-500" />
                                </div>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="permission in definition.permissions"
                                        :key="`${role}-${permission}`"
                                        class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium capitalize text-gray-600"
                                    >
                                        {{ permissionLabel(permission) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="ta-card p-5">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="ta-icon-box">
                                <Activity class="h-6 w-6" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">{{ t('adminUsers.auditTrail', 'Audit trail') }}</h2>
                                <p class="text-sm text-gray-500">{{ t('adminUsers.latestActions', 'Latest actions') }}</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div v-for="log in auditLogs" :key="log.id" class="rounded-xl border border-gray-200 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ log.action.replaceAll('.', ' ') }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ log.actor }} / {{ auditSummary(log) }}</div>
                                    </div>
                                    <UserRound class="h-4 w-4 text-gray-400" />
                                </div>
                                <div class="mt-2 text-xs text-gray-400">{{ log.created_at }}</div>
                            </div>
                            <p v-if="auditLogs.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                                {{ t('adminUsers.noAudit', 'No audit activity yet.') }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>
