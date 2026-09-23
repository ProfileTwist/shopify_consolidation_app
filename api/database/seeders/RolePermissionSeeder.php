<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure the cached permission map is fresh before we assign anything.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Permissions
        $permissions = ['view orders', 'manage connections', 'manage users'];
        foreach ($permissions as $name) {
            Permission::findOrCreate($name);
        }

        // Refresh the cache now that the permissions exist, so role assignment sees them.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Roles
        $admin = Role::findOrCreate('admin');
        $admin->syncPermissions($permissions);

        $manager = Role::findOrCreate('manager');
        $manager->syncPermissions(['view orders', 'manage connections']);

        $viewer = Role::findOrCreate('viewer');
        $viewer->syncPermissions(['view orders']);

        // Demo users
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@demo.test'],
            ['name' => 'Admin User', 'password' => Hash::make('password')]
        );
        $adminUser->syncRoles(['admin']);

        $viewerUser = User::updateOrCreate(
            ['email' => 'viewer@demo.test'],
            ['name' => 'Viewer User', 'password' => Hash::make('password')]
        );
        $viewerUser->syncRoles(['viewer']);

        // No demo stores are seeded — connect real Shopify stores via the app.
    }
}
