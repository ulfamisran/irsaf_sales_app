<x-app-layout>
    <x-slot name="title">{{ __('Tambah Penjualan') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tambah Penjualan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('sales.store') }}" id="sale-form">
                        @csrf
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    @if($branches->count() === 1)
                                        <x-locked-location label="{{ __('Branch') }}" :value="__('Cabang') . ': ' . $branches->first()->name" />
                                        <input type="hidden" id="branch_id" name="branch_id" value="{{ $branches->first()->id }}">
                                    @else
                                        <x-input-label for="branch_id" :value="__('Branch')" />
                                        <select id="branch_id" name="branch_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                            <option value="">{{ __('Select Branch') }}</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <p id="products_status" class="mt-1 text-xs text-slate-500"></p>
                                    <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="sale_date" :value="__('Sale Date')" />
                                    <x-text-input id="sale_date" class="block mt-1 w-full" type="date" name="sale_date" :value="old('sale_date', date('Y-m-d'))" required />
                                    <x-input-error :messages="$errors->get('sale_date')" class="mt-2" />
                                </div>
                            </div>

                            <div class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4">
                                <x-input-label :value="__('Status')" class="font-semibold" />
                                <div class="mt-2 flex gap-6">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="status" value="open" {{ old('status', 'open') === 'open' ? 'checked' : '' }} id="status_open" class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Open') }}</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="status" value="released" {{ old('status') === 'released' ? 'checked' : '' }} id="status_released" class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Release') }}</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <x-input-label for="customer_id" :value="__('Pelanggan')" />
                                <select id="customer_id" name="customer_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Pilih Pelanggan (atau isi pelanggan baru)') }}</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                                            {{ $c->name }}{{ $c->phone ? ' - '.$c->phone : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                            </div>

                            <div id="new-customer-fields" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-1">
                                    <x-input-label for="customer_new_name" :value="__('Nama Pelanggan Baru')" />
                                    <x-text-input id="customer_new_name" class="block mt-1 w-full" type="text" name="customer_new_name" :value="old('customer_new_name')" placeholder="Nama pelanggan" />
                                    <x-input-error :messages="$errors->get('customer_new_name')" class="mt-2" />
                                </div>
                                <div class="md:col-span-1">
                                    <x-input-label for="customer_new_phone" :value="__('No. HP')" />
                                    <x-text-input id="customer_new_phone" class="block mt-1 w-full" type="text" name="customer_new_phone" :value="old('customer_new_phone')" placeholder="08xxxxxxxxxx" />
                                    <x-input-error :messages="$errors->get('customer_new_phone')" class="mt-2" />
                                </div>
                                <div class="md:col-span-1">
                                    <x-input-label for="customer_new_address" :value="__('Alamat')" />
                                    <x-text-input id="customer_new_address" class="block mt-1 w-full" type="text" name="customer_new_address" :value="old('customer_new_address')" placeholder="Alamat singkat" />
                                    <x-input-error :messages="$errors->get('customer_new_address')" class="mt-2" />
                                </div>
                            </div>
                            <div>
                                <x-input-label :value="__('Items')" />
                                <p class="mt-1 mb-3 text-xs text-slate-500">{{ __('Produk akan tampil sesuai stok cabang yang dipilih.') }}</p>
                                <div id="sale-items" class="space-y-4">
                                    <div class="sale-item relative rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                        <div class="space-y-4">
                                            <div class="product-selector-block">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                                    <div>
                                                        <x-input-label :value="__('Brand')" class="mb-1" />
                                                        <select class="brand-filter block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></select>
                                                    </div>
                                                    <div>
                                                        <x-input-label :value="__('Series')" class="mb-1" />
                                                        <select class="series-filter block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></select>
                                                    </div>
                                                </div>
                                                <div>
                                                    <x-input-label :value="__('Produk')" class="mb-1" />
                                                    <input type="hidden" name="items[0][product_id]" class="product-id-input" value="" required>
                                                    <div class="product-dropdown-wrapper relative">
                                                        <button type="button" class="product-select-trigger w-full flex items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                            <span class="product-select-label text-slate-500">{{ __('Pilih Produk') }}</span>
                                                            <svg class="h-5 w-5 text-slate-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                                                        </button>
                                                        <div class="product-dropdown hidden absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">
                                                            <div class="p-2 border-b border-gray-100">
                                                                <input type="text" class="product-search w-full rounded-md border border-gray-300 py-2 px-3 text-sm" placeholder="{{ __('Cari SKU, brand, series, atau warna...') }}">
                                                            </div>
                                                            <div class="product-dropdown-list max-h-60 overflow-auto py-1"></div>
                                                            <div class="product-dropdown-empty hidden px-3 py-4 text-sm text-slate-500 text-center">{{ __('Tidak ada produk yang cocok.') }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <x-input-label :value="__('Nomor Serial')" class="mb-1" />
                                                <input type="text" class="serial-search block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-2" placeholder="{{ __('Search serial...') }}" disabled>
                                                <input type="text" class="serial-scan block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-2" placeholder="{{ __('Scan barcode/QR serial + Enter') }}" disabled>
                                                <x-input-label :value="__('Daftar serial dipilih')" class="mb-1 text-xs" />
                                                <select name="items[0][serial_numbers][]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 serial-select text-sm" multiple size="3" disabled></select>
                                                <p class="mt-1 text-xs text-gray-500">{{ __('Pilih serial jika stok serial-based.') }}</p>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label :value="__('Quantity')" class="mb-1" />
                                                    <x-text-input type="number" name="items[0][quantity]" min="1" value="1" placeholder="Qty" required />
                                                </div>
                                                <div>
                                                    <x-input-label :value="__('Harga Jual')" class="mb-1" />
                                                    <x-text-input type="text" name="items[0][price]" data-rupiah="true" placeholder="Harga" required />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="absolute top-3 right-3">
                                            <button type="button" class="remove-item inline-flex items-center px-3 py-2 rounded-md text-sm bg-red-100 text-red-700 hover:bg-red-200" style="display:none">{{ __('Hapus Item') }}</button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="add-item" class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">+ {{ __('Add Item') }}</button>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                    <div>
                                        <x-input-label for="discount_amount" :value="__('Diskon')" />
                                        <x-text-input id="discount_amount" class="block mt-1 w-full" type="text" name="discount_amount" data-rupiah="true" :value="old('discount_amount', 0)" />
                                        <x-input-error :messages="$errors->get('discount_amount')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="tax_amount" :value="__('Pajak')" />
                                        <x-text-input id="tax_amount" class="block mt-1 w-full" type="text" name="tax_amount" data-rupiah="true" :value="old('tax_amount', 0)" />
                                        <x-input-error :messages="$errors->get('tax_amount')" class="mt-2" />
                                    </div>
                                    <div class="md:col-span-2 rounded-md bg-white border border-slate-200 p-3">
                                        <div class="flex justify-between text-sm text-slate-600">
                                            <span>{{ __('Subtotal') }}</span>
                                            <span id="subtotalText">0</span>
                                        </div>
                                        <div class="flex justify-between text-sm text-slate-800 mt-1 font-semibold">
                                            <span>{{ __('Total') }}</span>
                                            <span id="totalText">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="trade-in-section" class="rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <div>
                                        <p class="font-semibold text-slate-800">{{ __('Tukar Tambah') }}</p>
                                        <p class="text-xs text-slate-500 mt-1">{{ __('Laptop bekas yang ditukar akan menjadi produk baru. Nilai tukar = HPP. Dana keluar jenis TT.') }}</p>
                                    </div>
                                    <button type="button" id="add-trade-in" class="inline-flex items-center px-3 py-2 rounded-md bg-amber-100 text-amber-800 border border-amber-200 text-sm hover:bg-amber-200 font-medium">
                                        + {{ __('Tambah Tukar Tambah') }}
                                    </button>
                                </div>
                                <div id="trade-in-rows" class="space-y-4"></div>
                                <div class="mt-3 text-sm text-slate-700">
                                    <span>{{ __('Total nilai tukar tambah') }}: </span><span id="tradeInSumText" class="font-semibold">0</span>
                                </div>
                            </div>

                            <div>
                                <x-input-label for="description" :value="__('Deskripsi (Opsional)')" />
                                <textarea id="description" name="description" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2">{{ old('description') }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <div id="payments-section" class="border rounded-lg p-4 bg-slate-50">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-800">{{ __('Metode Pembayaran') }}</p>
                                        <p id="paymentsReleasedHint" class="text-xs text-slate-500 hidden">
                                            {{ __('Bisa lebih dari 1 metode. Total pembayaran boleh sama (lunas) atau kurang (belum lunas).') }}
                                        </p>
                                        <p id="paymentsDraftHint" class="text-xs text-amber-700 mt-1">
                                            {{ __('Draft wajib uang muka. Boleh kurang dari total penjualan.') }}
                                        </p>
                                    </div>
                                    <button type="button" id="add-payment" class="inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">
                                        + {{ __('Tambah') }}
                                    </button>
                                </div>

                                <div id="payment-rows" class="mt-3 space-y-2"></div>
                                <div class="mt-3 text-sm text-slate-700">
                                    <span>{{ __('Tunai') }}: </span><span id="paymentSumText" class="font-semibold">0</span>
                                    <span class="ml-2">+ {{ __('Tukar Tambah') }}: <span id="tradeInInPaymentText" class="font-semibold">0</span></span>
                                    <span class="ml-2 text-slate-500">({{ __('selisih') }} <span id="paymentDiffText">0</span>)</span>
                                </div>
                                <x-input-error :messages="$errors->get('payments')" class="mt-2" />
                                <x-input-error :messages="$errors->get('payments.*.payment_method_id')" class="mt-2" />
                                <x-input-error :messages="$errors->get('payments.*.amount')" class="mt-2" />
                            </div>
                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Create Sale') }}</x-primary-button>
                                <a href="{{ route('sales.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let products = @json($productsForJs);
        let paymentMethods = @json($paymentMethods->map(fn($m) => [
            'id' => $m->id,
            'label' => $m->display_label,
        ])->values());
        const appBaseUrl = @json(request()->getBaseUrl());
        const formDataPath = @json(route('data-by-location.form-data', [], false));
        const availableProductsPath = @json(route('sales.available-products', [], false));
        const availableSerialsPath = @json(route('sales.available-serials', [], false));
        const formDataUrl = appBaseUrl + formDataPath;
        const availableProductsUrl = appBaseUrl + availableProductsPath;
        const availableSerialsUrl = appBaseUrl + availableSerialsPath;
        const categories = @json($categories ?? []);
        @php
            $i18n = [
                'brand' => __('Brand'),
                'series' => __('Series'),
                'produk' => __('Produk'),
                'pilihProduk' => __('Pilih Produk'),
                'cariProduk' => __('Cari SKU, brand, series, atau warna...'),
                'tidakAdaProduk' => __('Tidak ada produk yang cocok.'),
                'nomorSerial' => __('Nomor Serial'),
                'searchSerial' => __('Search serial...'),
                'scanSerial' => __('Scan barcode/QR serial + Enter'),
                'daftarSerial' => __('Daftar serial dipilih'),
                'pilihSerialInfo' => __('Pilih serial jika stok serial-based.'),
                'quantity' => __('Quantity'),
                'hargaJual' => __('Harga Jual'),
                'hapusItem' => __('Hapus Item'),
                'pilihCabangDulu' => __('Pilih cabang dulu'),
                'pilihPelanggan' => __('Pilih Pelanggan (atau isi pelanggan baru)'),
            ];
        @endphp
        const i18n = @json($i18n);
        const laptopCategoryId = (() => {
            const byCode = (categories || []).find(c => String(c.code || '').toUpperCase() === 'LAP');
            if (byCode?.id) return byCode.id;
            const byName = (categories || []).find(c => String(c.name || '').toLowerCase() === 'laptop');
            return byName?.id || null;
        })();

        function setProductsStatus(text, type = 'info') {
            const el = document.getElementById('products_status');
            if (!el) return;
            el.textContent = text || '';
            el.className = 'mt-1 text-xs ' + (type === 'error'
                ? 'text-red-600'
                : (type === 'ok' ? 'text-emerald-600' : 'text-slate-500'));
        }

        function productOptionDivHtml(p) {
            const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            const sku = esc(p.sku);
            const brand = esc(p.brand);
            const series = esc(p.series || '');
            const color = esc(p.color || '');
            const price = Number(p.price || 0).toLocaleString('id-ID');
            let colorPart = '';
            if (color) colorPart = ' <span class="text-slate-400">-</span> <span class="text-xs text-slate-600">' + color + '</span>';
            return '<div class="product-option px-3 py-2 cursor-pointer hover:bg-indigo-50 text-sm" data-id="' + p.id + '" data-brand="' + esc(p.brand) + '" data-series="' + esc(p.series || '') + '" data-sku="' + esc(p.sku) + '" data-color="' + esc(p.color || '') + '" data-price="' + (p.price || 0) + '">' +
                '<div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">' +
                '<span class="text-xs text-slate-500">' + sku + '</span>' +
                '<span class="text-slate-400">-</span>' +
                '<span class="text-slate-800">' + brand + ' ' + series + '</span>' + colorPart +
                '<span class="text-slate-400">-</span>' +
                '<span class="text-emerald-600 font-medium ml-auto">' + price + '</span>' +
                '</div></div>';
        }

        function productSelectorHtml(itemIndex) {
            return '<div class="product-selector-block">' +
                '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">' +
                '<div><label class="block text-sm font-medium text-gray-700 mb-1">' + i18n.brand + '</label>' +
                '<select class="brand-filter block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></select></div>' +
                '<div><label class="block text-sm font-medium text-gray-700 mb-1">' + i18n.series + '</label>' +
                '<select class="series-filter block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></select></div>' +
                '</div>' +
                '<div><label class="block text-sm font-medium text-gray-700 mb-1">' + i18n.produk + '</label>' +
                '<input type="hidden" name="items[' + itemIndex + '][product_id]" class="product-id-input" value="" required>' +
                '<div class="product-dropdown-wrapper relative">' +
                '<button type="button" class="product-select-trigger w-full flex items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">' +
                '<span class="product-select-label text-slate-500">' + i18n.pilihProduk + '</span>' +
                '<svg class="h-5 w-5 text-slate-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>' +
                '</button>' +
                '<div class="product-dropdown hidden absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">' +
                '<div class="p-2 border-b border-gray-100">' +
                '<input type="text" class="product-search w-full rounded-md border border-gray-300 py-2 px-3 text-sm" placeholder="' + i18n.cariProduk + '">' +
                '</div>' +
                '<div class="product-dropdown-list max-h-60 overflow-auto py-1"></div>' +
                '<div class="product-dropdown-empty hidden px-3 py-4 text-sm text-slate-500 text-center">' + i18n.tidakAdaProduk + '</div>' +
                '</div></div></div></div>';
        }

        function createSerialSelectHtml(name) {
            return '<label class="block text-sm font-medium text-gray-700 mb-1">' + i18n.nomorSerial + '</label>' +
                '<input type="text" class="serial-search block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-2" placeholder="' + i18n.searchSerial + '" disabled>' +
                '<input type="text" class="serial-scan block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-2" placeholder="' + i18n.scanSerial + '" disabled>' +
                '<label class="block text-xs font-medium text-gray-600 mb-1">' + i18n.daftarSerial + '</label>' +
                '<select name="' + name + '" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 serial-select text-sm" multiple size="3" disabled></select>' +
                '<p class="mt-1 text-xs text-gray-500">' + i18n.pilihSerialInfo + '</p>';
        }

        let itemIndex = 1;
        document.getElementById('add-item')?.addEventListener('click', function() {
            const container = document.getElementById('sale-items');
            if (!container) return;
            const div = document.createElement('div');
            div.className = 'sale-item relative rounded-lg border border-slate-200 bg-slate-50/50 p-4';
            div.innerHTML =
                '<div class="space-y-4">' +
                productSelectorHtml(itemIndex) +
                '<div>' + createSerialSelectHtml('items[' + itemIndex + '][serial_numbers][]') + '</div>' +
                '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">' +
                '<div><label class="block text-sm font-medium text-gray-700 mb-1">' + i18n.quantity + '</label><input type="number" name="items[' + itemIndex + '][quantity]" min="1" value="1" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Qty" required></div>' +
                '<div><label class="block text-sm font-medium text-gray-700 mb-1">' + i18n.hargaJual + '</label><input type="text" name="items[' + itemIndex + '][price]" data-rupiah="true" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Harga" required></div>' +
                '</div>' +
                '</div>' +
                '<div class="absolute top-3 right-3"><button type="button" class="remove-item inline-flex items-center px-3 py-2 rounded-md text-sm bg-red-100 text-red-700 hover:bg-red-200">' + i18n.hapusItem + '</button></div>';
            container.appendChild(div);
            populateRowProductDropdown(div);
            itemIndex++;
            toggleRemoveButtons();
        });

        document.getElementById('sale-items')?.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item')) {
                e.target.closest('.sale-item').remove();
                toggleRemoveButtons();
            }
        });

        const rowSerials = new WeakMap(); // row -> full serial array

        function getBrandsFromProducts() {
            const set = new Set();
            products.forEach(p => { if (p.brand) set.add(p.brand); });
            return Array.from(set).sort();
        }
        function getSeriesFromProducts(brandVal) {
            const set = new Set();
            products.forEach(p => {
                if ((!brandVal || (p.brand || '') === brandVal) && p.series) set.add(p.series);
            });
            return Array.from(set).sort();
        }
        function updateRowSeriesFilter(row) {
            const brandVal = row.querySelector('.brand-filter')?.value || '';
            const seriesSel = row.querySelector('.series-filter');
            if (!seriesSel) return;
            const list = getSeriesFromProducts(brandVal);
            seriesSel.innerHTML = '<option value="">Semua Series</option>' + list.map(s => '<option value="' + s + '">' + s + '</option>').join('');
        }
        function filterRowProductOptions(row) {
            const brandVal = row.querySelector('.brand-filter')?.value || '';
            const seriesVal = row.querySelector('.series-filter')?.value || '';
            const searchVal = (row.querySelector('.product-search')?.value || '').trim().toLowerCase();
            const options = row.querySelectorAll('.product-option');
            const listEl = row.querySelector('.product-dropdown-list');
            const emptyEl = row.querySelector('.product-dropdown-empty');
            let visibleCount = 0;
            let firstId = '';
            options.forEach(opt => {
                const matchBrand = !brandVal || (opt.getAttribute('data-brand') || '') === brandVal;
                const matchSeries = !seriesVal || (opt.getAttribute('data-series') || '') === seriesVal;
                const searchStr = ((opt.getAttribute('data-sku') || '') + ' ' + (opt.getAttribute('data-brand') || '') + ' ' + (opt.getAttribute('data-series') || '') + ' ' + (opt.getAttribute('data-color') || '')).toLowerCase();
                const matchSearch = !searchVal || searchStr.includes(searchVal);
                const visible = matchBrand && matchSeries && matchSearch;
                opt.classList.toggle('hidden', !visible);
                if (visible) { visibleCount++; if (!firstId) firstId = opt.getAttribute('data-id'); }
            });
            if (listEl) listEl.classList.toggle('hidden', visibleCount === 0);
            if (emptyEl) emptyEl.classList.toggle('hidden', visibleCount > 0);
        }
        function populateRowProductDropdown(row) {
            const listEl = row.querySelector('.product-dropdown-list');
            if (!listEl) return;
            listEl.innerHTML = products.map(p => productOptionDivHtml(p)).join('');
            const brandSel = row.querySelector('.brand-filter');
            const brands = getBrandsFromProducts();
            if (brandSel) brandSel.innerHTML = '<option value="">Semua Brand</option>' + brands.map(b => '<option value="' + b + '">' + b + '</option>').join('');
            updateRowSeriesFilter(row);
            filterRowProductOptions(row);
            attachProductOptionHandlers(row);
        }
        function attachProductOptionHandlers(row) {
            const trigger = row.querySelector('.product-select-trigger');
            const dropdown = row.querySelector('.product-dropdown');
            const searchInput = row.querySelector('.product-search');
            const productIdInput = row.querySelector('.product-id-input');
            if (!trigger || !dropdown) return;
            dropdown.addEventListener('click', e => e.stopPropagation());
            trigger.onclick = (e) => {
                e.stopPropagation();
                const wasHidden = dropdown.classList.contains('hidden');
                document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden'));
                dropdown.classList.toggle('hidden');
                if (wasHidden && searchInput) { searchInput.focus(); searchInput.value = ''; filterRowProductOptions(row); }
            };
            if (searchInput) searchInput.oninput = () => filterRowProductOptions(row);
            if (searchInput) searchInput.onkeydown = (e) => { e.stopPropagation(); if (e.key === 'Escape') dropdown.classList.add('hidden'); };
            row.querySelectorAll('.product-option').forEach(opt => {
                opt.onclick = (e) => {
                    e.stopPropagation();
                    const id = opt.getAttribute('data-id');
                    const price = opt.getAttribute('data-price');
                    if (productIdInput) { productIdInput.value = id; productIdInput.dispatchEvent(new Event('change', { bubbles: true })); }
                    const label = row.querySelector('.product-select-label');
                    if (label) {
                        const p = products.find(x => String(x.id) === String(id));
                        if (p) label.innerHTML = '<span class="text-xs text-slate-500">' + (p.sku || '') + '</span> <span class="text-slate-800">' + (p.brand || '') + ' ' + (p.series || '') + '</span>';
                        label.classList.remove('text-slate-500');
                    }
                    const priceInput = row.querySelector('input[name*="[price]"]');
                    if (priceInput && price) {
                        priceInput.value = price;
                        if (window.attachRupiahFormatter) window.attachRupiahFormatter();
                        if (typeof refreshTotals === 'function') refreshTotals();
                    }
                    dropdown.classList.add('hidden');
                    loadSerialsForRow(row);
                };
            });
            const brandEl = row.querySelector('.brand-filter');
            const seriesEl = row.querySelector('.series-filter');
            if (brandEl) brandEl.onchange = () => { updateRowSeriesFilter(row); filterRowProductOptions(row); };
            if (seriesEl) seriesEl.onchange = () => filterRowProductOptions(row);
        }
        document.addEventListener('click', () => document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden')));
        document.querySelectorAll('.sale-item').forEach(row => row.querySelector('.product-dropdown')?.addEventListener('click', e => e.stopPropagation()));

        function updateAllProductSelectors() {
            document.querySelectorAll('.sale-item').forEach(row => {
                const block = row.querySelector('.product-selector-block');
                if (block) populateRowProductDropdown(row);
            });
        }

        async function loadProductsForBranch() {
            const branchId = document.getElementById('branch_id')?.value;
            if (!branchId) {
                products = [];
                updateAllProductSelectors();
                setProductsStatus('');
                return;
            }

            try {
                setProductsStatus('Memuat produk...');
                const url = new URL(availableProductsUrl, window.location.origin);
                url.searchParams.set('branch_id', branchId);
                const res = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error(`available-products ${res.status}`);
                const data = await res.json();
                products = Array.isArray(data.products) ? data.products : [];

                updateAllProductSelectors();
                setProductsStatus(
                    products.length > 0 ? `Produk tersedia: ${products.length}` : 'Tidak ada produk in stock untuk cabang ini.',
                    products.length > 0 ? 'ok' : 'info'
                );

                document.querySelectorAll('.sale-item').forEach(row => {
                    const productIdInput = row.querySelector('.product-id-input');
                    const before = productIdInput?.value;

                    // If selected product is no longer available, clear serials and qty readonly state.
                    const stillAvailable = before && products.some(p => String(p.id) === String(before));
                    if (before && !stillAvailable) {
                        const serialSelect = row.querySelector('.serial-select');
                        const searchInput = row.querySelector('.serial-search');
                        const scanInput = row.querySelector('.serial-scan');
                        if (serialSelect) serialSelect.innerHTML = '';
                        if (searchInput) searchInput.value = '';
                        if (scanInput) scanInput.value = '';
                        if (productIdInput) productIdInput.value = '';
                        const label = row.querySelector('.product-select-label');
                        if (label) { label.textContent = 'Pilih Produk'; label.classList.add('text-slate-500'); }
                        rowSerials.set(row, []);
                        setSerialInputsEnabled(row, false);
                        syncQtyFromSerials(row);
                    }
                });
            } catch (e) {
                console.error('Failed to load products for branch', e);
                setProductsStatus('Gagal memuat produk. Cek koneksi/login atau endpoint sales/available-products.', 'error');
            }
        }

        function setSerialInputsEnabled(row, enabled) {
            const serialSelect = row.querySelector('.serial-select');
            const searchInput = row.querySelector('.serial-search');
            const scanInput = row.querySelector('.serial-scan');
            if (serialSelect) serialSelect.disabled = !enabled;
            if (searchInput) searchInput.disabled = !enabled;
            if (scanInput) scanInput.disabled = !enabled;
        }

        function renderSerialOptions(row) {
            const serialSelect = row.querySelector('.serial-select');
            const searchInput = row.querySelector('.serial-search');
            if (!serialSelect) return;

            const all = rowSerials.get(row) || [];
            const q = (searchInput?.value || '').trim().toLowerCase();

            const selected = new Set(Array.from(serialSelect.selectedOptions || []).map(o => o.value));
            const filtered = q ? all.filter(sn => String(sn).toLowerCase().includes(q)) : all;

            // Always keep selected options visible (even when filtered out)
            const merged = Array.from(new Set([...filtered, ...selected]));

            serialSelect.innerHTML = merged.map(sn => `<option value="${sn}">${sn}</option>`).join('');
            Array.from(serialSelect.options).forEach(opt => {
                if (selected.has(opt.value)) opt.selected = true;
            });
        }

        async function loadSerialsForRow(row) {
            const branchId = document.getElementById('branch_id')?.value;
            const productIdInput = row.querySelector('.product-id-input');
            const serialSelect = row.querySelector('.serial-select');
            if (!serialSelect) return;

            const productId = productIdInput?.value;
            if (!branchId || !productId) {
                rowSerials.set(row, []);
                serialSelect.innerHTML = '';
                setSerialInputsEnabled(row, false);
                return;
            }

            try {
                const url = new URL(availableSerialsUrl, window.location.origin);
                url.searchParams.set('branch_id', branchId);
                url.searchParams.set('product_id', productId);
                const res = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error(`available-serials ${res.status}`);
                const data = await res.json();
                const serials = Array.isArray(data.serial_numbers) ? data.serial_numbers : [];
                const isTracked = !!data.is_serial_tracked;

                rowSerials.set(row, serials);
                // Enable inputs when product is serial-tracked (even if zero available, allow manual typing/scanning)
                setSerialInputsEnabled(row, isTracked);
                renderSerialOptions(row);
            } catch (e) {
                rowSerials.set(row, []);
                serialSelect.innerHTML = '';
                setSerialInputsEnabled(row, false);
                console.error('Failed to load serials', e);
            }
        }

        function syncQtyFromSerials(row) {
            const serialSelect = row.querySelector('.serial-select');
            const qtyInput = row.querySelector('input[name*="[quantity]"]');
            if (!serialSelect || !qtyInput) return;

            const selectedCount = Array.from(serialSelect.selectedOptions || []).length;
            if (selectedCount > 0) {
                qtyInput.value = selectedCount;
                qtyInput.setAttribute('readonly', 'readonly');
            } else {
                qtyInput.removeAttribute('readonly');
            }
        }

        async function loadFormDataForBranch() {
            const branchId = document.getElementById('branch_id')?.value;
            if (!branchId) {
                paymentMethods = [];
                const custSel = document.getElementById('customer_id');
                if (custSel) { custSel.innerHTML = '<option value="">' + i18n.pilihCabangDulu + '</option>'; }
                document.querySelectorAll('#payment-rows select[name*="payment_method_id"]').forEach(sel => { sel.innerHTML = '<option value="">Pilih metode</option>'; });
                return;
            }
            try {
                const url = new URL(formDataUrl, window.location.origin);
                url.searchParams.set('location_type', 'branch');
                url.searchParams.set('location_id', branchId);
                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Fetch failed');
                const data = await res.json();
                paymentMethods = (data.payment_methods || []).map(m => ({ id: m.id, label: m.label }));
                const customers = data.customers || [];
                const custSel = document.getElementById('customer_id');
                if (custSel) {
                    custSel.innerHTML = '<option value="">' + i18n.pilihPelanggan + '</option>' +
                        customers.map(c => '<option value="' + c.id + '">' + (c.name || '') + (c.phone ? ' - ' + c.phone : '') + '</option>').join('');
                }
                document.querySelectorAll('#payment-rows select[name*="payment_method_id"]').forEach(sel => {
                    const oldVal = sel.value;
                    sel.innerHTML = '<option value="">Pilih metode</option>' + paymentMethods.map(m => '<option value="' + m.id + '">' + (m.label || '') + '</option>').join('');
                    if (oldVal && paymentMethods.some(m => m.id == oldVal)) sel.value = oldVal;
                });
            } catch (e) {
                console.error('loadFormDataForBranch failed', e);
            }
        }

        document.getElementById('branch_id')?.addEventListener('change', async function() {
            await loadProductsForBranch();
            await loadFormDataForBranch();
            document.querySelectorAll('.sale-item').forEach(row => loadSerialsForRow(row));
        });

        document.getElementById('sale-items')?.addEventListener('change', function(e) {
            const row = e.target.closest('.sale-item');
            if (!row) return;

            if (e.target.classList.contains('product-id-input')) {
                loadSerialsForRow(row);
            }

            if (e.target.classList.contains('serial-select')) {
                // prevent selecting same serial across different rows
                const currentSelect = e.target;
                const otherSelected = new Set();
                document.querySelectorAll('.sale-item').forEach(r => {
                    if (r === row) return;
                    const s = r.querySelector('.serial-select');
                    if (!s) return;
                    Array.from(s.selectedOptions || []).forEach(opt => otherSelected.add(opt.value));
                });

                let changed = false;
                Array.from(currentSelect.selectedOptions || []).forEach(opt => {
                    if (otherSelected.has(opt.value)) {
                        opt.selected = false;
                        changed = true;
                    }
                });
                if (changed) {
                    alert('Serial sudah dipilih di item lain.');
                }
                syncQtyFromSerials(row);
            }
        });

        // Some browsers only fire 'change' after focus leaves multi-select.
        // Make qty update feel instant.
        document.getElementById('sale-items')?.addEventListener('mouseup', function(e) {
            const row = e.target.closest('.sale-item');
            if (!row) return;
            if (e.target.classList.contains('serial-select')) {
                setTimeout(() => syncQtyFromSerials(row), 0);
            }
        });
        document.getElementById('sale-items')?.addEventListener('keyup', function(e) {
            const row = e.target.closest('.sale-item');
            if (!row) return;
            if (e.target.classList.contains('serial-select')) {
                syncQtyFromSerials(row);
            }
        });

        document.getElementById('sale-items')?.addEventListener('input', function(e) {
            const row = e.target.closest('.sale-item');
            if (!row) return;
            if (e.target.classList.contains('serial-search')) {
                renderSerialOptions(row);
            }
        });

        document.getElementById('sale-items')?.addEventListener('keydown', function(e) {
            const row = e.target.closest('.sale-item');
            if (!row) return;
            if (!e.target.classList.contains('serial-scan')) return;

            if (e.key === 'Enter') {
                e.preventDefault();
                const serial = (e.target.value || '').trim();
                if (!serial) return;

                const all = rowSerials.get(row) || [];
                const serialSelect = row.querySelector('.serial-select');
                if (!serialSelect) return;

                const exists = all.includes(serial);

                // clear search so option is visible
                const searchInput = row.querySelector('.serial-search');
                if (searchInput) searchInput.value = '';
                renderSerialOptions(row);

                const otherSelected = new Set();
                document.querySelectorAll('.sale-item').forEach(r => {
                    if (r === row) return;
                    const s = r.querySelector('.serial-select');
                    if (!s) return;
                    Array.from(s.selectedOptions || []).forEach(opt => otherSelected.add(opt.value));
                });
                if (otherSelected.has(serial)) {
                    alert('Serial sudah dipilih di item lain.');
                    return;
                }

                // Only allow serials that are currently available (in_stock) from server list.
                // This prevents selecting reserved/invalid units for OPEN sales.
                if (!exists) {
                    alert('Serial tidak tersedia (bukan IN STOCK). Silakan pilih dari daftar.');
                    return;
                }

                const opt = Array.from(serialSelect.options).find(o => o.value === serial);
                if (opt) {
                    opt.selected = true;
                    serialSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
                e.target.value = '';
                e.target.focus();
            }
        });

        // initial load (ensure runs even if page uses partial navigation)
        function initSalesCreate() {
            loadProductsForBranch().then(() => {
                document.querySelectorAll('.sale-item').forEach(row => loadSerialsForRow(row));
            });
            loadFormDataForBranch();
            document.querySelectorAll('.sale-item').forEach(row => syncQtyFromSerials(row));
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSalesCreate);
        } else {
            initSalesCreate();
        }

        function toggleRemoveButtons() {
            const items = document.querySelectorAll('.sale-item');
            items.forEach((item, i) => {
                const btn = item.querySelector('.remove-item');
                if (btn) btn.style.display = items.length > 1 ? 'inline-block' : 'none';
            });
        }
        toggleRemoveButtons();

        // Customer form toggle
        const customerSelect = document.getElementById('customer_id');
        const newCustomerFields = document.getElementById('new-customer-fields');
        const newName = document.getElementById('customer_new_name');
        const toggleCustomerFields = () => {
            const hasCustomer = !!(customerSelect && customerSelect.value);
            if (newCustomerFields) newCustomerFields.style.display = hasCustomer ? 'none' : '';
            if (hasCustomer) {
                if (newName) newName.value = '';
                const p = document.getElementById('customer_new_phone'); if (p) p.value = '';
                const a = document.getElementById('customer_new_address'); if (a) a.value = '';
            }
        };
        customerSelect?.addEventListener('change', toggleCustomerFields);
        toggleCustomerFields();

        // Totals calc
        function toNumber(val) {
            if (typeof window.parseRupiahToNumber === 'function') {
                return window.parseRupiahToNumber(val);
            }
            const str = String(val ?? '').trim();
            if (!str) return 0;
            // Handle plain decimal like 8200000.00 correctly (treat as 8,200,000)
            const decimalMatch = str.match(/^(\d+)\.(\d{1,2})$/);
            if (decimalMatch) {
                return Math.round(parseFloat(str));
            }
            const raw = str.replace(/[^\d]/g, '');
            return raw ? parseFloat(raw) : 0;
        }

        function calcSubtotal() {
            let subtotal = 0;
            document.querySelectorAll('.sale-item').forEach(row => {
                const qty = parseFloat(row.querySelector('input[name*=\"[quantity]\"]')?.value || '0');
                const price = toNumber(row.querySelector('input[name*=\"[price]\"]')?.value || '0');
                if (qty > 0 && price >= 0) subtotal += qty * price;
            });
            return subtotal;
        }
        function fmtNumber(n) {
            return Number(n || 0).toLocaleString('id-ID');
        }
        function refreshTotals() {
            const subtotal = calcSubtotal();
            const disc = toNumber(document.getElementById('discount_amount')?.value || '0') || 0;
            const tax = toNumber(document.getElementById('tax_amount')?.value || '0') || 0;
            const total = Math.max(0, subtotal - disc + tax);
            const subtotalText = document.getElementById('subtotalText');
            const totalText = document.getElementById('totalText');
            if (subtotalText) subtotalText.textContent = fmtNumber(subtotal);
            if (totalText) totalText.textContent = fmtNumber(total);
            refreshTradeInSum();
            refreshPaymentSum();
        }
        document.getElementById('sale-items').addEventListener('input', refreshTotals);
        document.getElementById('discount_amount')?.addEventListener('input', refreshTotals);
        document.getElementById('tax_amount')?.addEventListener('input', refreshTotals);

        // Payments
        const getStatusValue = () => document.querySelector('input[name="status"]:checked')?.value || 'open';
        const paymentsSection = document.getElementById('payments-section');
        const paymentRows = document.getElementById('payment-rows');
        let paymentIndex = 0;

        function paymentOptionsHtml() {
            return '<option value=\"\">Pilih metode</option>' + paymentMethods.map(m => `<option value=\"${m.id}\">${m.label}</option>`).join('');
        }

        function addPaymentRow(pref = {}) {
            if (!paymentRows) return;
            const idx = paymentIndex++;
            const div = document.createElement('div');
            div.className = 'flex flex-col md:flex-row gap-2 items-end';
            div.innerHTML = `
                <div class="flex-1">
                    <select name="payments[${idx}][payment_method_id]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        ${paymentOptionsHtml()}
                    </select>
                </div>
                <div class="w-full md:w-48">
                    <input type="text" name="payments[${idx}][amount]" data-rupiah="true" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nominal" required>
                </div>
                <div class="w-full md:w-64">
                    <input type="text" name="payments[${idx}][notes]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Catatan (opsional)">
                </div>
                <button type="button" class="remove-payment px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200">-</button>
            `;
            paymentRows.appendChild(div);

            if (pref.payment_method_id) {
                const sel = div.querySelector('select');
                if (sel) sel.value = String(pref.payment_method_id);
            }
            if (pref.amount) {
                const inp = div.querySelector('input[name*=\"[amount]\"]');
                if (inp) inp.value = String(pref.amount);
            }
            if (pref.notes) {
                const inp = div.querySelector('input[name*=\"[notes]\"]');
                if (inp) inp.value = String(pref.notes);
            }

            div.querySelectorAll('select,input').forEach(el => el.addEventListener('input', refreshPaymentSum));
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            div.querySelector('.remove-payment')?.addEventListener('click', () => { div.remove(); refreshPaymentSum(); });
        }

        function calcTradeInSum() {
            let sum = 0;
            document.querySelectorAll('.trade-in-value-input').forEach(inp => {
                const v = toNumber(inp.value || '0');
                if (v > 0) sum += v;
            });
            return sum;
        }

        function refreshTradeInSum() {
            const sum = calcTradeInSum();
            const el = document.getElementById('tradeInSumText');
            const el2 = document.getElementById('tradeInInPaymentText');
            if (el) el.textContent = fmtNumber(sum);
            if (el2) el2.textContent = fmtNumber(sum);
        }

        function refreshPaymentSum() {
            const subtotal = calcSubtotal();
            const disc = toNumber(document.getElementById('discount_amount')?.value || '0') || 0;
            const tax = toNumber(document.getElementById('tax_amount')?.value || '0') || 0;
            const total = Math.max(0, subtotal - disc + tax);
            const tradeInSum = calcTradeInSum();

            let sum = 0;
            document.querySelectorAll('#payment-rows input[name*=\"[amount]\"]').forEach(inp => {
                const v = toNumber(inp.value || '0');
                if (v > 0) sum += v;
            });
            const totalPaid = sum + tradeInSum;
            const sumEl = document.getElementById('paymentSumText');
            const diffEl = document.getElementById('paymentDiffText');
            if (sumEl) sumEl.textContent = fmtNumber(sum);
            if (diffEl) diffEl.textContent = fmtNumber(total - totalPaid);
        }

        let tradeInIndex = 0;

        const sanitizeSkuValue = (value) => {
            return String(value || '')
                .trim()
                .toUpperCase()
                .replace(/\s+/g, '')
                .replace(/[^A-Z0-9]/g, '');
        };

        const skuBrandSegment = (value) => {
            const cleaned = sanitizeSkuValue(value).replace(/[AEIOU]/g, '');
            return cleaned !== '' ? cleaned : 'NA';
        };

        const skuSegment = (value) => {
            const cleaned = sanitizeSkuValue(value);
            return cleaned !== '' ? cleaned : 'NA';
        };

        const randomSkuSuffix = () => {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let out = '';
            for (let i = 0; i < 3; i++) {
                out += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return out;
        };

        function addTradeInRow(pref = {}) {
            const container = document.getElementById('trade-in-rows');
            if (!container) return;
            const idx = tradeInIndex++;
            const esc = (s) => String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            const div = document.createElement('div');
            div.className = 'trade-in-row rounded-lg border border-amber-200 bg-white p-3 space-y-3';
            div.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">` + @json(__('Merek')) + `</label>
                        <input type="text" name="trade_ins[${idx}][brand]" value="${esc(pref.brand)}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">` + @json(__('Seri')) + `</label>
                        <input type="text" name="trade_ins[${idx}][series]" value="${esc(pref.series)}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">` + @json(__('Processor')) + `</label>
                        <input type="text" name="trade_ins[${idx}][processor]" value="${esc(pref.processor)}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">` + @json(__('RAM')) + `</label>
                        <input type="text" name="trade_ins[${idx}][ram]" value="${esc(pref.ram)}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">` + @json(__('Kapasitas Penyimpanan')) + `</label>
                        <input type="text" name="trade_ins[${idx}][storage]" value="${esc(pref.storage)}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">` + @json(__('Warna')) + `</label>
                        <input type="text" name="trade_ins[${idx}][color]" value="${esc(pref.color)}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">` + @json(__('Spesifikasi')) + `</label>
                        <input type="text" name="trade_ins[${idx}][specs]" value="${esc(pref.specs)}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <input type="hidden" name="trade_ins[${idx}][category_id]" value="${esc(pref.category_id || laptopCategoryId || '')}">
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="generate-trade-in-sku inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700">
                            ` + @json(__('Generate SKU')) + `
                        </button>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">` + @json(__('SKU')) + `</label>
                        <input type="hidden" name="trade_ins[${idx}][sku]" value="${esc(pref.sku)}">
                        <input type="text" class="trade-in-sku-display block w-full rounded-md border-gray-300 shadow-sm bg-slate-100 text-sm" value="${esc(pref.sku)}" disabled>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">` + @json(__('Nomor Serial')) + `</label>
                        <input type="text" name="trade_ins[${idx}][serial_number]" value="${esc(pref.serial_number)}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">` + @json(__('Nilai Tukar (HPP)')) + `</label>
                        <input type="text" name="trade_ins[${idx}][trade_in_value]" data-rupiah="true" class="trade-in-value-input block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" value="${esc(pref.trade_in_value)}" required>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="remove-trade-in px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm">` + @json(__('Hapus')) + `</button>
                    </div>
                </div>
            `;
            container.appendChild(div);
            if (pref.category_id) {
                const sel = div.querySelector('select[name*="[category_id]"]');
                if (sel) sel.value = String(pref.category_id);
            }
            div.querySelectorAll('input').forEach(el => {
                el.addEventListener('input', () => { refreshTradeInSum(); refreshPaymentSum(); });
            });
            div.querySelector('.remove-trade-in')?.addEventListener('click', () => {
                div.remove();
                refreshTradeInSum();
                refreshPaymentSum();
            });
            div.querySelector('.generate-trade-in-sku')?.addEventListener('click', () => {
                const brand = div.querySelector('input[name*="[brand]"]')?.value;
                const series = div.querySelector('input[name*="[series]"]')?.value;
                const processor = div.querySelector('input[name*="[processor]"]')?.value;
                const ram = div.querySelector('input[name*="[ram]"]')?.value;
                const storage = div.querySelector('input[name*="[storage]"]')?.value;
                const sku = [
                    'LP',
                    'TT',
                    skuBrandSegment(brand),
                    skuSegment(series),
                    skuSegment(processor),
                    skuSegment(ram),
                    skuSegment(storage),
                    randomSkuSuffix()
                ].join('-');

                const skuInput = div.querySelector('input[name*="[sku]"]');
                const skuDisplay = div.querySelector('.trade-in-sku-display');
                if (skuInput) skuInput.value = sku;
                if (skuDisplay) skuDisplay.value = sku;
            });
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
        }

        document.getElementById('add-trade-in')?.addEventListener('click', () => addTradeInRow());

        const oldTradeIns = @json(old('trade_ins', []));
        if (Array.isArray(oldTradeIns) && oldTradeIns.length > 0) {
            oldTradeIns.forEach(t => addTradeInRow(t));
        }

        document.getElementById('add-payment')?.addEventListener('click', () => addPaymentRow());

        function togglePayments() {
            const released = (getStatusValue() === 'released');
            const draftHint = document.getElementById('paymentsDraftHint');
            const releasedHint = document.getElementById('paymentsReleasedHint');
            if (draftHint) draftHint.classList.toggle('hidden', released);
            if (releasedHint) releasedHint.classList.toggle('hidden', !released);

            // Pembayaran selalu aktif (draft = uang muka, released = pembayaran penuh/partial)
            document.querySelectorAll('#payment-rows select, #payment-rows input').forEach(el => {
                el.disabled = false;
            });
            const addBtn = document.getElementById('add-payment');
            if (addBtn) addBtn.disabled = false;
            if (addBtn) {
                addBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            if (paymentsSection) {
                paymentsSection.classList.remove('opacity-60');
            }
            if (paymentRows && paymentRows.children.length === 0) {
                addPaymentRow();
            }
        }
        document.querySelectorAll('input[name="status"]').forEach(el => el.addEventListener('change', togglePayments));

        // Restore old payments if validation failed
        const oldPayments = @json(old('payments', []));
        if (Array.isArray(oldPayments) && oldPayments.length > 0) {
            oldPayments.forEach(p => addPaymentRow(p));
        } else {
            addPaymentRow();
        }

        togglePayments();
        refreshTotals();
    </script>
</x-app-layout>
