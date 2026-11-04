<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

class CatatRange
{
    public const TZ = 'Asia/Makassar';

    public static function apply(EloquentBuilder|QueryBuilder $query, Request $request, string $column = 'created_at'): void
    {
        self::applyBetween(
            $query,
            $request->filled('catat_from') ? (string) $request->input('catat_from') : null,
            $request->filled('catat_to') ? (string) $request->input('catat_to') : null,
            $column
        );
    }

    public static function applyBetween(EloquentBuilder|QueryBuilder $query, ?string $from, ?string $to, string $column = 'created_at'): void
    {
        $start = self::bound($from, true);
        if ($start) {
            $query->where($column, '>=', $start);
        }
        $end = self::bound($to, false);
        if ($end) {
            $query->where($column, '<=', $end);
        }
    }

    private static function bound(?string $date, bool $startOfDay): ?Carbon
    {
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $date, self::TZ);
        if (! $parsed) {
            return null;
        }

        return ($startOfDay ? $parsed->startOfDay() : $parsed->endOfDay())->utc();
    }
}
