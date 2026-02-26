<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'jenis_pembayaran',
        'nama_bank',
        'atas_nama_bank',
        'no_rekening',
        'keterangan',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Label untuk dropdown pilihan metode pembayaran.
     * Untuk Tunai (cash) hanya tampilkan "Tunai", tanpa bank/rekening.
     */
    public function getDisplayLabelAttribute(): string
    {
        $jenis = trim($this->jenis_pembayaran ?? '');
        if (strtolower($jenis) === 'tunai') {
            return $jenis ?: 'Tunai';
        }
        $label = $jenis;
        if (! empty(trim((string) ($this->nama_bank ?? '')))) {
            $label .= ' - ' . trim($this->nama_bank);
        }
        if (! empty(trim((string) ($this->no_rekening ?? '')))) {
            $label .= ' (' . trim($this->no_rekening) . ')';
        }

        return trim($label);
    }
}

