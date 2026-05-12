<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'invoices.view', 'invoices.create', 'invoices.delete',
            'ledgers.view', 'ledgers.create', 'ledgers.edit', 'ledgers.delete',
            'vouchers.view', 'vouchers.sync',
            'reports.view',
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'settings.view', 'settings.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        $accountant = Role::firstOrCreate(['name' => 'accountant']);
        $accountant->syncPermissions([
            'invoices.view', 'invoices.create',
            'ledgers.view', 'ledgers.create', 'ledgers.edit',
            'vouchers.view', 'vouchers.sync',
            'reports.view',
        ]);

        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->syncPermissions([
            'invoices.view', 'invoices.create',
            'ledgers.view',
            'vouchers.view',
        ]);
    }
}
