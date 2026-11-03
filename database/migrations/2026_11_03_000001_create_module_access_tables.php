<?php

use App\Support\ModuleAccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('group_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('role_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('app_modules')->cascadeOnDelete();
            $table->string('action', 32);
            $table->timestamps();

            $table->unique(['role_id', 'module_id', 'action']);
        });

        ModuleAccessCatalog::install();
    }

    public function down(): void
    {
        Schema::dropIfExists('role_module_permissions');
        Schema::dropIfExists('app_modules');
    }
};
