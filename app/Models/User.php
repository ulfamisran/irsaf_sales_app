<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const PLACEMENT_CABANG = 'cabang';
    public const PLACEMENT_GUDANG = 'gudang';

    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'placement_type',
        'name',
        'email',
        'password',
        'is_active',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get placement display name (Cabang/Gudang + name).
     */
    public function getPlacementDisplayAttribute(): ?string
    {
        if ($this->placement_type === self::PLACEMENT_GUDANG && $this->warehouse_id) {
            return __('Gudang') . ': ' . ($this->warehouse?->name ?? '-');
        }
        if (($this->placement_type === self::PLACEMENT_CABANG || $this->branch_id) && $this->branch_id) {
            return __('Cabang') . ': ' . ($this->branch?->name ?? '-');
        }
        return '-';
    }

    /**
     * Get the roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    /**
     * Check if user is super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->roles()
            ->where(function ($query) {
                $query->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN_PUSAT, 'super admin', 'Super Admin', 'admin pusat', 'Admin Pusat'])
                    ->orWhereIn('display_name', ['Super Admin', 'super admin', 'super_admin', 'Admin Pusat', 'admin pusat', 'admin_pusat']);
            })
            ->exists();
    }

    /**
     * Check if user is strictly super admin.
     */
    public function isStrictSuperAdmin(): bool
    {
        return $this->roles()
            ->where(function ($query) {
                $query->whereIn('name', [Role::SUPER_ADMIN, 'super admin', 'Super Admin'])
                    ->orWhereIn('display_name', ['Super Admin', 'super admin', 'super_admin']);
            })
            ->exists();
    }

    /**
     * Check if user is admin pusat.
     */
    public function isAdminPusat(): bool
    {
        return $this->roles()
            ->where(function ($query) {
                $query->whereIn('name', [Role::ADMIN_PUSAT, 'admin pusat', 'Admin Pusat'])
                    ->orWhereIn('display_name', ['Admin Pusat', 'admin pusat', 'admin_pusat']);
            })
            ->exists();
    }

    /**
     * Check if user is super admin or admin pusat.
     */
    public function isSuperAdminOrAdminPusat(): bool
    {
        return $this->isSuperAdmin() || $this->isAdminPusat();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
