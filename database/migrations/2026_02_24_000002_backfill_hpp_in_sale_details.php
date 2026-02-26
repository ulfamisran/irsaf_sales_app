<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('sale_details')
            ->join('products', 'products.id', '=', 'sale_details.product_id')
            ->where(function ($q) {
                $q->where('sale_details.hpp', 0)->orWhereNull('sale_details.hpp');
            })
            ->select('sale_details.id', 'products.purchase_price')
            ->get();

        foreach ($rows as $row) {
            DB::table('sale_details')->where('id', $row->id)->update(['hpp' => $row->purchase_price]);
        }
    }

    public function down(): void
    {
        // Tidak ada reverse
    }
};
