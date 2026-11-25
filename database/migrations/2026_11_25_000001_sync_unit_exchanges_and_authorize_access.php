<?php

use App\Support\ModuleAccessCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ModuleAccessCatalog::syncModules();
    }

    public function down(): void
    {
        // Keep module/permission rows; removing them would revoke live access.
    }
};
