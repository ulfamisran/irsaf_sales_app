<?php

namespace App\Support;

class DocumentStatusPresenter
{
    /**
     * Opsi filter status dokumen (nilai = DB / query param).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function filterOptions(string $module, bool $includePaymentFilters = false): array
    {
        $lifecycle = match ($module) {
            'purchases', 'stock-mutations' => [
                ['value' => 'open', 'label' => __('Open')],
                ['value' => 'active', 'label' => __('Release')],
                ['value' => 'cancelled', 'label' => __('Dibatalkan')],
            ],
            'sales', 'rentals' => [
                ['value' => 'open', 'label' => __('Open')],
                ['value' => 'released', 'label' => __('Release')],
                ['value' => 'cancel', 'label' => __('Dibatalkan')],
            ],
            'services' => [
                ['value' => 'open', 'label' => __('Open')],
                ['value' => 'completed', 'label' => __('Release')],
                ['value' => 'cancel', 'label' => __('Dibatalkan')],
            ],
            'unit-exchanges' => [
                ['value' => 'open', 'label' => __('Open')],
                ['value' => 'released', 'label' => __('Release')],
                ['value' => 'cancel', 'label' => __('Dibatalkan')],
            ],
            default => [
                ['value' => 'open', 'label' => __('Open')],
                ['value' => 'released', 'label' => __('Release')],
                ['value' => 'cancel', 'label' => __('Dibatalkan')],
            ],
        };

        if (! $includePaymentFilters) {
            return $lifecycle;
        }

        return array_merge($lifecycle, [
            ['value' => 'paid_off', 'label' => __('Lunas')],
            ['value' => 'unpaid', 'label' => __('Belum Lunas')],
        ]);
    }

    /**
     * Radio create/edit: value yang dikirim form.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function radioOptions(string $module): array
    {
        return match ($module) {
            'services' => [
                ['value' => 'open', 'label' => __('Open')],
                ['value' => 'release', 'label' => __('Release')],
            ],
            'sales' => [
                ['value' => 'open', 'label' => __('Open')],
                ['value' => 'released', 'label' => __('Release')],
            ],
            default => [
                ['value' => 'open', 'label' => __('Open')],
                ['value' => 'released', 'label' => __('Release')],
            ],
        };
    }

    public static function label(?string $status): string
    {
        return match (self::canonical($status)) {
            'open' => __('Open'),
            'released' => __('Release'),
            'cancelled' => __('Dibatalkan'),
            default => $status ? (string) $status : '-',
        };
    }

    public static function canonical(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return match ($status) {
            'open', 'draft' => 'open',
            'released', 'release', 'active', 'completed', 'selesai' => 'released',
            'cancel', 'cancelled', 'canceled' => 'cancelled',
            default => $status !== '' ? $status : 'unknown',
        };
    }

    public static function badgeClasses(?string $status): string
    {
        return match (self::canonical($status)) {
            'open' => 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-100 text-blue-800',
            'released' => 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-800',
            'cancelled' => 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-rose-100 text-rose-800',
            default => 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700',
        };
    }
}
