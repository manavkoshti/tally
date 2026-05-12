<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $company = Company::create([
            'name' => 'Demo Company Pvt Ltd',
            'gstin' => '27AABCD1234E1Z5',
            'pan' => 'AABCD1234E',
            'tally_company_name' => 'Demo Company Pvt Ltd',
            'tally_host' => 'localhost',
            'tally_port' => 9000,
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Admin User',
            'email' => 'admin@demo.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $admin->assignRole('admin');
        $admin->markEmailAsVerified();

        $accountant = User::create([
            'company_id' => $company->id,
            'name' => 'Accountant User',
            'email' => 'accountant@demo.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $accountant->assignRole('accountant');
        $accountant->markEmailAsVerified();
    }
}
