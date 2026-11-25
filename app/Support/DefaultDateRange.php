<?php

namespace App\Support;

use Illuminate\Http\Request;

class DefaultDateRange
{
    /**
     * Jika user belum mengisi filter tanggal, pakai bulan berjalan (tgl 1 s/d hari ini).
     * Jika salah satu date_from/date_to diisi, biarkan apa adanya (tidak memaksa default).
     */
    public static function applyMonthToDate(Request $request, string $fromKey = 'date_from', string $toKey = 'date_to'): Request
    {
        if ($request->filled($fromKey) || $request->filled($toKey)) {
            return $request;
        }

        $request->merge([
            $fromKey => now()->startOfMonth()->toDateString(),
            $toKey => now()->toDateString(),
        ]);

        return $request;
    }
}
