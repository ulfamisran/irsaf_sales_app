<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory;

    public const PLACEMENT_CABANG = 'cabang';
    public const PLACEMENT_GUDANG = 'gudang';

    protected $fillable = [
        'placement_type',
        'branch_id',
        'warehouse_id',
        'jenis_pembayaran',
        'nama_bank',
        'atas_nama_bank',
        'no_rekening',
        'keterangan',
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
     * Filter metode pembayaran sesuai lokasi (cabang atau gudang).
     * Super admin / Admin pusat: bisa filter via branch_id/warehouse_id.
     * Role lain: hanya PM dari cabang/gudang user.
     */
    public function scopeForLocation($query, ?int $branchId, ?int $warehouseId)
    {
        if ($branchId) {
            return $query->where('branch_id', $branchId);
        }
        if ($warehouseId) {
            return $query->where('warehouse_id', $warehouseId);
        }

        return $query;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getDisplayLabelAttribute(): string
    {
        $jenis = trim($this->jenis_pembayaran ?? '');
        $label = $jenis ?: 'Tunai';

        $bank = trim((string) ($this->nama_bank ?? ''));
        if ($bank !== '') {
            $label .= ' - ' . $bank;
        }

        $atasNama = trim((string) ($this->atas_nama_bank ?? ''));
        if ($atasNama !== '') {
            $label .= ' - ' . $atasNama;
        }

        $noRek = trim((string) ($this->no_rekening ?? ''));
        if ($noRek !== '') {
            $label .= ' (' . $noRek . ')';
        }

        return $label;
    }
}

