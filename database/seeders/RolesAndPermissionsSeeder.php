<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/** Production-safe and idempotent: creates the permission catalogue and default role matrix. */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::catalogue() as $name => $meta) {
            Permission::query()->updateOrCreate(['name' => $name], $meta);
        }

        foreach (Permissions::defaultRoles() as $name => $def) {
            $role = Role::query()->firstOrCreate(['name' => $name], [
                'label' => $def['label'],
                'description' => $def['description'],
                'is_system' => true,
            ]);
            // Only set permissions on first creation; later edits made through the UI are preserved.
            if ($role->wasRecentlyCreated) {
                $role->permissions()->sync(Permission::query()->whereIn('name', $def['permissions'])->pluck('id'));
            }
        }
    }
}
