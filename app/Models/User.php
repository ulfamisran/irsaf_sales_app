<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\ModuleAccessService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    public const PLACEMENT_MULTI = 'multi';

    /** @var list<int>|null */
    private ?array $cachedAccessibleBranchIds = null;

    /** @var list<int>|null */
    private ?array $cachedAccessibleWarehouseIds = null;

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

    public function assignedBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches')->withTimestamps();
    }

    public function assignedWarehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouses')->withTimestamps();
    }

    /**
     * Get placement display name (Cabang/Gudang + name).
     */
    public function getPlacementDisplayAttribute(): ?string
    {
        if ($this->isAdminPusat()) {
            $parts = [];
            $branchNames = $this->relationLoaded('assignedBranches')
                ? $this->assignedBranches->pluck('name')
                : $this->assignedBranches()->orderBy('name')->pluck('name');
            $warehouseNames = $this->relationLoaded('assignedWarehouses')
                ? $this->assignedWarehouses->pluck('name')
                : $this->assignedWarehouses()->orderBy('name')->pluck('name');
            if ($branchNames->isNotEmpty()) {
                $parts[] = __('Cabang').': '.$branchNames->implode(', ');
            }
            if ($warehouseNames->isNotEmpty()) {
                $parts[] = __('Gudang').': '.$warehouseNames->implode(', ');
            }

            return $parts !== [] ? implode(' · ', $parts) : '-';
        }
        if ($this->placement_type === self::PLACEMENT_GUDANG && $this->warehouse_id) {
            return __('Gudang') . ': ' . ($this->warehouse?->name ?? '-');
        }
        if (($this->placement_type === self::PLACEMENT_CABANG || $this->branch_id) && $this->branch_id) {
            return __('Cabang') . ': ' . ($this->branch?->name ?? '-');
        }
        return '-';
    }

    public function hasUnrestrictedLocationAccess(): bool
    {
        return $this->isStrictSuperAdmin();
    }

    /**
     * @return list<int>
     */
    public function accessibleBranchIds(): array
    {
        if ($this->cachedAccessibleBranchIds !== null) {
            return $this->cachedAccessibleBranchIds;
        }

        if ($this->hasUnrestrictedLocationAccess()) {
            return $this->cachedAccessibleBranchIds = Branch::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($this->isAdminPusat()) {
            return $this->cachedAccessibleBranchIds = $this->assignedBranches()
                ->pluck('branches.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $this->cachedAccessibleBranchIds = $this->branch_id ? [(int) $this->branch_id] : [];
    }

    /**
     * @return list<int>
     */
    public function accessibleWarehouseIds(): array
    {
        if ($this->cachedAccessibleWarehouseIds !== null) {
            return $this->cachedAccessibleWarehouseIds;
        }

        if ($this->hasUnrestrictedLocationAccess()) {
            return $this->cachedAccessibleWarehouseIds = Warehouse::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($this->isAdminPusat()) {
            return $this->cachedAccessibleWarehouseIds = $this->assignedWarehouses()
                ->pluck('warehouses.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $this->cachedAccessibleWarehouseIds = $this->warehouse_id ? [(int) $this->warehouse_id] : [];
    }

    public function canAccessBranch(?int $branchId): bool
    {
        if (! $branchId) {
            return false;
        }
        if ($this->hasUnrestrictedLocationAccess()) {
            return true;
        }

        return in_array((int) $branchId, $this->accessibleBranchIds(), true);
    }

    public function canAccessWarehouse(?int $warehouseId): bool
    {
        if (! $warehouseId) {
            return false;
        }
        if ($this->hasUnrestrictedLocationAccess()) {
            return true;
        }

        return in_array((int) $warehouseId, $this->accessibleWarehouseIds(), true);
    }

    public function canAccessLocation(?int $branchId, ?int $warehouseId): bool
    {
        if ($this->hasUnrestrictedLocationAccess()) {
            return true;
        }
        if ($warehouseId) {
            return $this->canAccessWarehouse((int) $warehouseId);
        }
        if ($branchId) {
            return $this->canAccessBranch((int) $branchId);
        }

        return false;
    }

    /**
     * @param  list<int|string>|null  $branchIds
     * @param  list<int|string>|null  $warehouseIds
     */
    public function syncLocationAssignments(?array $branchIds, ?array $warehouseIds): void
    {
        $branches = collect($branchIds ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $warehouses = collect($warehouseIds ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->assignedBranches()->sync($branches);
        $this->assignedWarehouses()->sync($warehouses);
        $this->cachedAccessibleBranchIds = null;
        $this->cachedAccessibleWarehouseIds = null;
    }

    /**
     * Get the roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function publicIps(): HasMany
    {
        return $this->hasMany(UserPublicIp::class)->orderBy('ip_address');
    }

    public function publicIpSummary(): string
    {
        $ips = $this->publicIps;
        if ($ips->isEmpty()) {
            return __('Belum ada');
        }
        if ($ips->contains(fn (UserPublicIp $row) => $row->isAnyDevice())) {
            return __('Semua perangkat');
        }

        $addresses = $ips->pluck('ip_address');
        $shown = $addresses->take(2)->implode(', ');
        $extra = $addresses->count() - 2;

        return $extra > 0 ? $shown.' +'.$extra : $shown;
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if ($user->publicIps()->exists()) {
                return;
            }

            $user->publicIps()->create([
                'ip_address' => UserPublicIp::ANY,
            ]);
        });
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

    public function canModule(string $slug, string $action = 'select'): bool
    {
        return app(ModuleAccessService::class)->allows($this, $slug, $action);
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
