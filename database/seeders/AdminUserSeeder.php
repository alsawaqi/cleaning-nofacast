<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed or update the production owner/admin account.
     */
    public function run(): void
    {
        $admin = config('cleanops.admin_seed', []);

        $email = trim((string) ($admin['email'] ?? ''));
        $password = (string) ($admin['password'] ?? '');
        $role = (string) ($admin['role'] ?? 'owner');
        $locale = (string) ($admin['locale'] ?? 'ar');

        if ($email === '') {
            throw new RuntimeException('ADMIN_EMAIL must be set before running AdminUserSeeder.');
        }

        if (trim($password) === '') {
            throw new RuntimeException('ADMIN_PASSWORD must be set before running AdminUserSeeder.');
        }

        if (! in_array($role, array_keys(config('cleanops.role_permissions', [])), true)) {
            throw new RuntimeException("ADMIN_ROLE [{$role}] is not a valid role.");
        }

        if (! in_array($locale, config('cleanops.supported_locales', ['ar', 'en']), true)) {
            throw new RuntimeException("ADMIN_LOCALE [{$locale}] is not supported.");
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) ($admin['name'] ?? 'Nofa Clean Owner'),
                'phone' => $admin['phone'] ?: null,
                'password' => Hash::make($password),
                'role' => $role,
                'locale' => $locale,
                'is_active' => true,
            ],
        );

        $this->command?->info("Admin user ready: {$user->email}");
    }
}
