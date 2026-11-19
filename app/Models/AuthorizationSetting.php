<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationSetting extends Model
{
    protected $fillable = [
        'module',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
