<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN_CABANG = 'admin_cabang';
    public const STAFF_GUDANG = 'staff_gudang';
    public const KASIR = 'kasir';

    /**
     * Get the users that have this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    /**
     * Check if role is super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }
}
