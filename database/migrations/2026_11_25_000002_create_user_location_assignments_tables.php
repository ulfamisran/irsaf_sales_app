<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'branch_id']);
        });

        Schema::create('user_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'warehouse_id']);
        });

        $roleId = DB::table('roles')->where('name', Role::ADMIN_PUSAT)->value('id');
        if (! $roleId) {
            return;
        }

        $userIds = DB::table('role_user')->where('role_id', $roleId)->pluck('user_id');
        $now = now();
        foreach ($userIds as $userId) {
            $user = DB::table('users')->where('id', $userId)->first();
            if (! $user) {
                continue;
            }
            if (! empty($user->branch_id)) {
                DB::table('user_branches')->updateOrInsert(
                    ['user_id' => $userId, 'branch_id' => $user->branch_id],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
            if (! empty($user->warehouse_id)) {
                DB::table('user_warehouses')->updateOrInsert(
                    ['user_id' => $userId, 'warehouse_id' => $user->warehouse_id],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_warehouses');
        Schema::dropIfExists('user_branches');
    }
};
