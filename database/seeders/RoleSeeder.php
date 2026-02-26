<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => Role::SUPER_ADMIN,
                'display_name' => 'Super Admin',
                'description' => 'Full system access',
            ],
            [
                'name' => Role::ADMIN_CABANG,
                'display_name' => 'Admin Cabang',
                'description' => 'Branch administrator',
            ],
            [
                'name' => Role::STAFF_GUDANG,
                'display_name' => 'Staff Gudang',
                'description' => 'Warehouse staff',
            ],
            [
                'name' => Role::KASIR,
                'display_name' => 'Kasir',
                'description' => 'Cashier',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}
