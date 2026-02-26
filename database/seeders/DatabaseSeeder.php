<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                // ensure a known password on first install
                'password' => Hash::make('password'),
            ]
        );
        $roleId = Role::where('name', Role::SUPER_ADMIN)->value('id');
        if ($roleId) {
            $superAdmin->roles()->syncWithoutDetaching([$roleId]);
        }

        $this->call([
            SampleDataSeeder::class,
        ]);
    }
}
