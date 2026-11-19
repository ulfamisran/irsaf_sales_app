<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('authorization_picker_roles')) {
            Schema::create('authorization_picker_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->timestamps();
                $table->unique('role_id');
            });
        }

        // Seed default roles (sama dengan daftar hardcode sebelumnya).
        $defaultNames = [
            Role::SUPER_ADMIN,
            Role::ADMIN_PUSAT,
            Role::KEPALA_GUDANG,
            Role::KEPALA_CABANG,
            'spv_toko',
        ];
        $defaultLabels = [
            'super admin',
            'admin pusat',
            'kepala gudang',
            'kepala cabang',
            'kepala toko',
            'supervisor toko',
        ];

        $roleIds = Role::query()
            ->where(function ($query) use ($defaultNames, $defaultLabels) {
                $query->whereIn('name', $defaultNames)
                    ->orWhereIn(DB::raw('LOWER(display_name)'), $defaultLabels);
            })
            ->pluck('id');

        $now = now();
        foreach ($roleIds as $roleId) {
            DB::table('authorization_picker_roles')->updateOrInsert(
                ['role_id' => $roleId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_picker_roles');
    }
};
