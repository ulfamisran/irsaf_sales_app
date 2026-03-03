<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $staffGudang = DB::table('roles')->where('name', 'staff_gudang')->first();
        $adminGudang = DB::table('roles')->where('name', 'admin_gudang')->first();

        if ($staffGudang && ! $adminGudang) {
            DB::table('roles')->where('id', $staffGudang->id)->update([
                'name' => 'admin_gudang',
                'display_name' => 'Admin Gudang',
                'description' => 'Warehouse administrator',
            ]);
        } elseif ($staffGudang && $adminGudang) {
            $userIdsWithStaffGudang = DB::table('role_user')->where('role_id', $staffGudang->id)->pluck('user_id');
            $userIdsWithAdminGudang = DB::table('role_user')->where('role_id', $adminGudang->id)->pluck('user_id');
            $userIdsToMigrate = $userIdsWithStaffGudang->diff($userIdsWithAdminGudang);
            foreach ($userIdsToMigrate as $userId) {
                DB::table('role_user')->insert([
                    'user_id' => $userId,
                    'role_id' => $adminGudang->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('role_user')->where('role_id', $staffGudang->id)->delete();
            DB::table('roles')->where('id', $staffGudang->id)->delete();
        }
    }

    public function down(): void
    {
        $adminGudang = DB::table('roles')->where('name', 'admin_gudang')->first();
        if ($adminGudang) {
            DB::table('roles')->where('id', $adminGudang->id)->update([
                'name' => 'staff_gudang',
                'display_name' => 'Staff Gudang',
                'description' => 'Warehouse staff',
            ]);
        }
    }
};
