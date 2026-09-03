<?php
return [
    'models' => ['permission' => Spatie\Permission\Models\Permission::class, 'role' => Spatie\Permission\Models\Role::class],
    'table_names' => [
        'roles' => 'roles', 'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions', 'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],
    'column_names' => ['role_pivot_key' => null, 'permission_pivot_key' => null,
        'model_morph_key' => 'model_id', 'team_foreign_key' => 'company_id'],
    'register_permission_check_method' => true,
    // Per-company roles: each role row carries a company_id (team_foreign_key), so every tenant
    // owns its own role catalogue. Permission *names* stay a global vocabulary.
    'teams' => true,
    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,
    // Wildcards are off: every permission is an explicit module.action and every check uses an
    // exact name. (Wildcard mode's per-record index is not team-aware, so it must not be mixed
    // with teams.)
    'enable_wildcard_permission' => false,
    'cache' => ['expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache', 'store' => 'default'],
];
