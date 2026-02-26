<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Laptop', 'code' => 'LAP', 'description' => 'Laptop/Notebook'],
            ['name' => 'Aksesoris', 'code' => 'AKS', 'description' => 'Aksesoris Laptop'],
        ];

        foreach ($categories as $c) {
            Category::updateOrCreate(['code' => $c['code']], $c);
        }

        $branches = [
            ['name' => 'Cabang Jakarta', 'address' => 'Jl. Sudirman No. 1', 'pic_name' => 'Budi'],
            ['name' => 'Cabang Bandung', 'address' => 'Jl. Dago No. 10', 'pic_name' => 'Ani'],
        ];

        foreach ($branches as $b) {
            Branch::updateOrCreate(['name' => $b['name']], $b);
        }

        $branchJakarta = Branch::where('name', 'Cabang Jakarta')->first();
        $branchBandung = Branch::where('name', 'Cabang Bandung')->first();

        $warehouse = Warehouse::updateOrCreate(
            ['name' => 'Gudang Utama'],
            ['address' => 'Jl. Industri No. 1', 'pic_name' => 'Admin Gudang']
        );

        $lapCategory = Category::where('code', 'LAP')->first();
        if ($lapCategory) {
            $products = [
                ['category_id' => $lapCategory->id, 'sku' => 'LP-ASUS-001', 'brand' => 'ASUS', 'series' => 'Vivobook 15', 'specs' => 'i5/8GB/256GB', 'purchase_price' => 7000000, 'selling_price' => 8500000],
                ['category_id' => $lapCategory->id, 'sku' => 'LP-ACER-001', 'brand' => 'Acer', 'series' => 'Aspire 5', 'specs' => 'i5/8GB/512GB', 'purchase_price' => 6500000, 'selling_price' => 7800000],
                ['category_id' => $lapCategory->id, 'sku' => 'LP-LENOVO-001', 'brand' => 'Lenovo', 'series' => 'IdeaPad 3', 'specs' => 'Ryzen 5/8GB/256GB', 'purchase_price' => 6800000, 'selling_price' => 8200000],
            ];
            foreach ($products as $p) {
                Product::updateOrCreate(['sku' => $p['sku']], $p);
            }
        }

        if ($lapCategory) {
            $product = Product::where('sku', 'LP-ASUS-001')->first();
            if ($product) {
                // Seed example serial-numbered units into Gudang Utama (5 units)
                for ($i = 1; $i <= 5; $i++) {
                    $sn = 'SN-ASUS-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
                    ProductUnit::firstOrCreate(
                        ['serial_number' => $sn],
                        [
                            'product_id' => $product->id,
                            'location_type' => Stock::LOCATION_WAREHOUSE,
                            'location_id' => $warehouse->id,
                            'status' => ProductUnit::STATUS_IN_STOCK,
                            'received_date' => now()->toDateString(),
                        ]
                    );
                }

                $qty = ProductUnit::where('product_id', $product->id)
                    ->where('location_type', Stock::LOCATION_WAREHOUSE)
                    ->where('location_id', $warehouse->id)
                    ->where('status', ProductUnit::STATUS_IN_STOCK)
                    ->count();

                Stock::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'location_type' => Stock::LOCATION_WAREHOUSE,
                        'location_id' => $warehouse->id,
                    ],
                    ['quantity' => $qty]
                );
            }
        }

        // Sample users per branch/role (password: password)
        $roleAdminCabangId = Role::where('name', Role::ADMIN_CABANG)->value('id');
        $roleKasirId = Role::where('name', Role::KASIR)->value('id');
        $roleStaffGudangId = Role::where('name', Role::STAFF_GUDANG)->value('id');

        if ($branchJakarta) {
            $adminCabangJakarta = User::updateOrCreate(
                ['email' => 'admin.jakarta@example.com'],
                ['name' => 'Admin Cabang Jakarta', 'password' => Hash::make('password'), 'branch_id' => $branchJakarta->id]
            );
            if ($roleAdminCabangId) {
                $adminCabangJakarta->roles()->syncWithoutDetaching([$roleAdminCabangId]);
            }

            $kasirJakarta = User::updateOrCreate(
                ['email' => 'kasir.jakarta@example.com'],
                ['name' => 'Kasir Jakarta', 'password' => Hash::make('password'), 'branch_id' => $branchJakarta->id]
            );
            if ($roleKasirId) {
                $kasirJakarta->roles()->syncWithoutDetaching([$roleKasirId]);
            }
        }

        if ($branchBandung) {
            $adminCabangBandung = User::updateOrCreate(
                ['email' => 'admin.bandung@example.com'],
                ['name' => 'Admin Cabang Bandung', 'password' => Hash::make('password'), 'branch_id' => $branchBandung->id]
            );
            if ($roleAdminCabangId) {
                $adminCabangBandung->roles()->syncWithoutDetaching([$roleAdminCabangId]);
            }
        }

        $staffGudang = User::updateOrCreate(
            ['email' => 'gudang@example.com'],
            ['name' => 'Staff Gudang', 'password' => Hash::make('password')]
        );
        if ($roleStaffGudangId) {
            $staffGudang->roles()->syncWithoutDetaching([$roleStaffGudangId]);
        }
    }
}
