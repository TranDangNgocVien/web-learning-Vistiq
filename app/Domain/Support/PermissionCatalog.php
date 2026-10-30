<?php

namespace VistiqCore\Support;

final class PermissionCatalog
{
    /** @return array<string, array{label: string, description: string, permissions: array<string, array{name: string, description: string}>}> */
    public static function groups(): array
    {
        return config('admin_permissions.groups', []);
    }

    /** @return array<string, array{name: string, description: string, group: string}> */
    public static function definitions(): array
    {
        $definitions = [];

        foreach (self::groups() as $group) {
            foreach ($group['permissions'] ?? [] as $code => $permission) {
                $definitions[$code] = [
                    ...$permission,
                    'group' => $group['label'],
                ];
            }
        }

        return $definitions;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::definitions());
    }

    /** @return array<string, array{name: string, description: string, permissions: list<string>|'*'}> */
    public static function defaultRoles(): array
    {
        return config('admin_permissions.default_roles', []);
    }
}
