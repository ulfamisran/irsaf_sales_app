<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppModule extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'group_name',
        'sort_order',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(RoleModulePermission::class, 'module_id');
    }
}
