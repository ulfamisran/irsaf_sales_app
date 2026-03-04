<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Override Filesystem untuk hosting yang menonaktifkan exec()
        $this->app->singleton(\Illuminate\Filesystem\Filesystem::class, \App\Filesystem\Filesystem::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'warehouse' => Warehouse::class,
            'branch' => Branch::class,
            'gudang' => Warehouse::class,    // alias: beberapa data mungkin pakai 'gudang'
            'cabang' => Branch::class,       // alias: beberapa data mungkin pakai 'cabang'
        ]);
    }
}
