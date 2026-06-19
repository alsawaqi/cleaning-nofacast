<?php

namespace App\Support;

class Roles
{
    /**
     * @return array<int, string>
     */
    public static function permissionsFor(?string $role): array
    {
        return config("cleanops.role_permissions.{$role}", []);
    }

    public static function hasPermission(?string $role, string $permission): bool
    {
        $permissions = self::permissionsFor($role);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'owner' => 'Owner',
            'operations' => 'Operations',
            'accountant' => 'Accountant',
            'supervisor' => 'Supervisor',
            'worker' => 'Worker',
            'sales' => 'Sales',
            'customer' => 'Customer',
        ];
    }
}
