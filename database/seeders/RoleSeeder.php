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
                'name' => Role::ADMIN_PUSAT,
                'display_name' => 'Admin Pusat',
                'description' => 'Central admin access without settings menu',
            ],
            [
                'name' => Role::ADMIN_CABANG,
                'display_name' => 'Admin Cabang',
                'description' => 'Branch administrator',
            ],
            [
                'name' => Role::ADMIN_GUDANG,
                'display_name' => 'Admin Gudang',
                'description' => 'Warehouse administrator',
            ],
            [
                'name' => Role::KASIR,
                'display_name' => 'Kasir',
                'description' => 'Cashier',
            ],
            [
                'name' => Role::KEPALA_CABANG,
                'display_name' => 'Kepala Cabang',
                'description' => 'Verifikasi dan akses admin cabang serta kasir pada cabangnya',
            ],
            [
                'name' => Role::KEPALA_GUDANG,
                'display_name' => 'Kepala Gudang',
                'description' => 'Verifikasi dan akses admin gudang serta kasir pada gudangnya',
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
