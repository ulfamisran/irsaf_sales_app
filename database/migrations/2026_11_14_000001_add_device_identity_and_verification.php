<?php

use App\Models\Role;
use App\Support\ModuleAccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales', 'purchases', 'services', 'rentals', 'cash_flows'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'client_ip')) {
                    $blueprint->string('client_ip', 45)->nullable()->after('user_id');
                }
                if (! Schema::hasColumn($table, 'client_user_agent')) {
                    $blueprint->string('client_user_agent', 500)->nullable()->after('client_ip');
                }
            });
        }

        Schema::table('cash_flows', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('cash_flows', 'cancelled_at')) {
                $blueprint->timestamp('cancelled_at')->nullable()->after('client_user_agent');
            }
            if (! Schema::hasColumn('cash_flows', 'cancel_user_id')) {
                $blueprint->foreignId('cancel_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_flows', 'cancel_reason')) {
                $blueprint->string('cancel_reason', 500)->nullable()->after('cancel_user_id');
            }
        });

        Schema::table('purchases', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('purchases', 'cancel_reason')) {
                $blueprint->string('cancel_reason', 500)->nullable();
            }
            if (! Schema::hasColumn('purchases', 'cancel_user_id')) {
                $blueprint->foreignId('cancel_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('transaction_verifications')) {
            Schema::create('transaction_verifications', function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('verification_type', 40);
                $blueprint->string('document_number', 100);
                $blueprint->date('document_transaction_date')->nullable();
                $blueprint->timestamp('document_recorded_at')->nullable();
                $blueprint->timestamp('verified_at');
                $blueprint->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $blueprint->boolean('flag_accepted')->default(false);
                $blueprint->boolean('flag_rejected')->default(false);
                $blueprint->string('notes', 500)->nullable();
                $blueprint->string('reference_type', 40);
                $blueprint->unsignedBigInteger('reference_id');
                $blueprint->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $blueprint->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $blueprint->string('client_ip', 45)->nullable();
                $blueprint->timestamps();
                $blueprint->index(['verification_type', 'reference_id']);
            });
        }

        $now = now();
        foreach ([
            Role::KEPALA_CABANG => ['Kepala Cabang', 'Verifikasi dan akses admin cabang serta kasir pada cabangnya'],
            Role::KEPALA_GUDANG => ['Kepala Gudang', 'Verifikasi dan akses admin gudang serta kasir pada gudangnya'],
        ] as $name => [$display, $description]) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name],
                ['display_name' => $display, 'description' => $description, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        if (Schema::hasTable('app_modules')) {
            ModuleAccessCatalog::syncModules();
            $this->grantNewRolePermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_verifications');
    }

    private function grantNewRolePermissions(): void
    {
        if (! Schema::hasTable('role_module_permissions')) {
            return;
        }

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $moduleIds = DB::table('app_modules')->pluck('id', 'slug');
        $now = now();
        $rows = [];

        foreach ([Role::KEPALA_CABANG, Role::KEPALA_GUDANG] as $roleName) {
            $roleId = $roleIds[$roleName] ?? null;
            if (! $roleId) {
                continue;
            }
            foreach (ModuleAccessCatalog::grantsForRole($roleName) as $slug => $actions) {
                $moduleId = $moduleIds[$slug] ?? null;
                if (! $moduleId) {
                    continue;
                }
                foreach ($actions as $action) {
                    $exists = DB::table('role_module_permissions')
                        ->where('role_id', $roleId)
                        ->where('module_id', $moduleId)
                        ->where('action', $action)
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    $rows[] = [
                        'role_id' => $roleId,
                        'module_id' => $moduleId,
                        'action' => $action,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $verificationId = $moduleIds['verification'] ?? null;
        if ($verificationId) {
            foreach ([Role::SUPER_ADMIN, Role::ADMIN_PUSAT, Role::KEPALA_CABANG, Role::KEPALA_GUDANG] as $roleName) {
                $roleId = $roleIds[$roleName] ?? null;
                if (! $roleId) {
                    continue;
                }
                foreach (ModuleAccessCatalog::actionKeys() as $action) {
                    $exists = DB::table('role_module_permissions')
                        ->where('role_id', $roleId)
                        ->where('module_id', $verificationId)
                        ->where('action', $action)
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    $rows[] = [
                        'role_id' => $roleId,
                        'module_id' => $verificationId,
                        'action' => $action,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $unique = [];
        foreach ($rows as $row) {
            $unique[$row['role_id'].'-'.$row['module_id'].'-'.$row['action']] = $row;
        }
        foreach (array_chunk(array_values($unique), 400) as $chunk) {
            DB::table('role_module_permissions')->insert($chunk);
        }
    }
};
