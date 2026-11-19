<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class DocumentSearch
{
    public static function applySale(Builder $query, ?string $search): void
    {
        $like = self::like($search);
        if ($like === null) {
            return;
        }

        $query->where(function (Builder $q) use ($like) {
            $q->where('invoice_number', 'like', $like)
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', $like))
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like))
                ->orWhereHas('saleDetails', function (Builder $detail) use ($like) {
                    $detail->where('serial_numbers', 'like', $like)
                        ->orWhereHas('product', fn (Builder $product) => $product->where('sku', 'like', $like));
                })
                ->orWhereHas('tradeIns', function (Builder $tradeIn) use ($like) {
                    $tradeIn->where('serial_number', 'like', $like)
                        ->orWhere('sku', 'like', $like);
                });
        });
    }

    public static function applyService(Builder $query, ?string $search): void
    {
        $like = self::like($search);
        if ($like === null) {
            return;
        }

        $query->where(function (Builder $q) use ($like) {
            $q->where('invoice_number', 'like', $like)
                ->orWhere('laptop_detail', 'like', $like)
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', $like))
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like))
                ->orWhereHas('serviceMaterials', function (Builder $material) use ($like) {
                    $material->whereHas('product', fn (Builder $product) => $product->where('sku', 'like', $like));
                });
        });
    }

    public static function applyRental(Builder $query, ?string $search): void
    {
        $like = self::like($search);
        if ($like === null) {
            return;
        }

        $query->where(function (Builder $q) use ($like) {
            $q->where('invoice_number', 'like', $like)
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', $like))
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like))
                ->orWhereHas('items', function (Builder $item) use ($like) {
                    $item->where('serial_number', 'like', $like)
                        ->orWhereHas('product', fn (Builder $product) => $product->where('sku', 'like', $like));
                });
        });
    }

    private static function like(?string $search): ?string
    {
        $search = trim((string) $search);
        if ($search === '') {
            return null;
        }

        return '%'.addcslashes($search, '%_\\').'%';
    }
}
