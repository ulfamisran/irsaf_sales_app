<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('authorization_settings')) {
            Schema::create('authorization_settings', function (Blueprint $table) {
                $table->id();
                $table->string('module', 50)->unique();
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('authorization_assignees')) {
            Schema::create('authorization_assignees', function (Blueprint $table) {
                $table->id();
                $table->string('module', 50);
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['module', 'user_id']);
            });
        }

        if (! Schema::hasTable('authorization_requests')) {
            Schema::create('authorization_requests', function (Blueprint $table) {
                $table->id();
                $table->string('module', 50)->index();
                $table->string('reference_type');
                $table->unsignedBigInteger('reference_id');
                $table->string('document_number', 100)->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('requested_at')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->timestamps();
                $table->index(['reference_type', 'reference_id']);
            });
        }

        foreach (['sales', 'purchases', 'services', 'rentals', 'unit_exchanges', 'distributions'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'requires_authorization')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->boolean('requires_authorization')->default(false);
                });
            }
        }

        if (Schema::hasTable('rentals') && ! Schema::hasColumn('rentals', 'effects_pending')) {
            Schema::table('rentals', function (Blueprint $table) {
                $table->boolean('effects_pending')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_requests');
        Schema::dropIfExists('authorization_assignees');
        Schema::dropIfExists('authorization_settings');

        foreach (['sales', 'purchases', 'services', 'rentals', 'unit_exchanges', 'distributions'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'requires_authorization')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('requires_authorization');
                });
            }
        }

        if (Schema::hasTable('rentals') && Schema::hasColumn('rentals', 'effects_pending')) {
            Schema::table('rentals', function (Blueprint $table) {
                $table->dropColumn('effects_pending');
            });
        }
    }
};
