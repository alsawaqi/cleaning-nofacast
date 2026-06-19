<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(array_keys(config('cleanops.role_permissions', [])))],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()
            ->with('worker')
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByRaw("case when role = 'owner' then 0 else 1 end")
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $user): array => $this->serializeUser($user));

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $this->roles(),
            'workers' => Worker::query()
                ->with('user')
                ->orderBy('name')
                ->get()
                ->map(fn (Worker $worker): array => [
                    'id' => $worker->id,
                    'employee_code' => $worker->employee_code,
                    'name' => $worker->name,
                    'status' => $worker->status,
                    'user_id' => $worker->user_id,
                    'user_name' => $worker->user?->name,
                ]),
            'auditLogs' => AuditLog::query()
                ->with('user:id,name')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $log->user?->name ?? 'System',
                    'auditable_type' => $log->auditable_type,
                    'auditable_id' => $log->auditable_id,
                    'changes' => $log->changes ?? [],
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'metrics' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
                'inactive' => User::query()->where('is_active', false)->count(),
                'workerLinked' => Worker::query()->whereNotNull('user_id')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request, null);

        $workerId = Arr::pull($validated, 'worker_id');
        $validated['password'] = $request->string('password')->toString();

        $user = User::create($validated);
        $this->syncWorkerLink($user, $workerId);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'user.created',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => [
                'created' => [
                    'old' => null,
                    'new' => $this->serializeUser($user->fresh('worker')),
                ],
            ],
        ]);

        return redirect('/app/users')->with('success', __('User account created.'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);

        if ($request->user()?->is($user) && array_key_exists('is_active', $validated) && ! $validated['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => __('You cannot deactivate your own account.'),
            ]);
        }

        $workerId = Arr::pull($validated, 'worker_id');
        $before = $user->fresh('worker');
        $oldValues = $before->only(['name', 'email', 'phone', 'role', 'locale', 'is_active']);
        $oldWorkerId = $before->worker?->id;

        if ($request->filled('password')) {
            $validated['password'] = $request->string('password')->toString();
        } else {
            unset($validated['password']);
        }

        $user->fill($validated)->save();
        $this->syncWorkerLink($user, $workerId);

        $user->refresh()->load('worker');
        $changes = $this->changes($oldValues, $user->only(['name', 'email', 'phone', 'role', 'locale', 'is_active']));

        if ($oldWorkerId !== $user->worker?->id) {
            $changes['worker_id'] = [
                'old' => $oldWorkerId,
                'new' => $user->worker?->id,
            ];
        }

        if ($request->filled('password')) {
            $changes['password'] = [
                'old' => false,
                'new' => true,
            ];
        }

        if ($changes !== []) {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'user.updated',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'changes' => $changes,
            ]);
        }

        return redirect('/app/users')->with('success', __('User account updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUser(Request $request, ?User $user): array
    {
        $roleKeys = array_keys(config('cleanops.role_permissions', []));

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in($roleKeys)],
            'locale' => ['required', Rule::in(config('cleanops.supported_locales', ['ar', 'en']))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', 'min:8'],
            'is_active' => ['required', 'boolean'],
            'worker_id' => ['nullable', 'integer', 'exists:workers,id'],
        ]);
    }

    private function syncWorkerLink(User $user, mixed $workerId): void
    {
        Worker::query()
            ->where('user_id', $user->id)
            ->when($workerId, fn ($query) => $query->where('id', '!=', $workerId))
            ->update(['user_id' => null]);

        if ($workerId) {
            Worker::query()
                ->whereKey($workerId)
                ->update(['user_id' => $user->id]);
        }
    }

    /**
     * @return array<string, array{label: string, permissions: array<int, string>}>
     */
    private function roles(): array
    {
        return collect(config('cleanops.role_permissions', []))
            ->mapWithKeys(fn (array $permissions, string $role): array => [
                $role => [
                    'label' => Roles::labels()[$role] ?? ucfirst($role),
                    'permissions' => $permissions,
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'locale' => $user->locale,
            'is_active' => $user->is_active,
            'worker' => $user->worker ? [
                'id' => $user->worker->id,
                'employee_code' => $user->worker->employee_code,
                'name' => $user->worker->name,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function changes(array $oldValues, array $newValues): array
    {
        $changes = [];

        foreach ($newValues as $key => $newValue) {
            if (($oldValues[$key] ?? null) !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValues[$key] ?? null,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }
}
