<x-app-layout>
    <x-slot name="title">{{ __('Edit Penjualan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Draft Penjualan') }}: {{ $sale->invoice_number }}
            </h2>
            <x-icon-btn-back :href="route('sales.show', $sale)" :label="__('Kembali')" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('sales.update', $sale) }}" id="sale-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="confirm_reuse_sold_serials" id="confirm_reuse_sold_serials" value="{{ old('confirm_reuse_sold_serials') ? 1 : 0 }}">
                        <input type="hidden" name="status" value="open">
                        <input type="hidden" name="branch_id" value="{{ $sale->branch_id }}">

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="branch_id_display" :value="__('Branch')" />
                                    <x-text-input id="branch_id_display" class="block mt-1 w-full" type="text" :value="$sale->branch?->name" disabled />
                                </div>
                                <div>
                                    <x-input-label for="sale_date" :value="__('Sale Date')" />
                                    <x-text-input id="sale_date" class="block mt-1 w-full" type="date" name="sale_date" :value="old('sale_date', $sale->sale_date->toDateString())" required />
                                    <x-input-error :messages="$errors->get('sale_date')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="customer_id" :value="__('Pelanggan')" />
                                <select id="customer_id" name="customer_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Pilih Pelanggan (atau isi pelanggan baru)') }}</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" {{ old('customer_id', $sale->customer_id) == $c->id ? 'selected' : '' }}>
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
                                    @php
                                        $details = old('items') ?? null;
                                    @endphp

                                    @if (is_array($details))
                                        @foreach ($details as $i => $it)
                                            <div class="sale-item relative rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                                <div class="space-y-4">
                                                    <div>
                                                        <x-input-label :value="__('Produk')" class="mb-1" />
                                                        <select name="items[{{ $i }}][product_id]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 product-select" required>
                                                            <option value="{{ $it['product_id'] ?? '' }}" selected>{{ __('Loading...') }}</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <x-input-label :value="__('Nomor Serial')" class="mb-1" />
                                                        <input type="text" class="serial-search block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-2" placeholder="{{ __('Search serial...') }}" disabled>
                                                        <input type="text" class="serial-scan block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-2" placeholder="{{ __('Scan barcode/QR serial + Enter') }}" disabled>
                                                        <x-input-label :value="__('Daftar serial dipilih')" class="mb-1 text-xs" />
                                                        <select name="items[{{ $i }}][serial_numbers][]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 serial-select text-sm" multiple size="3" disabled>
                                                            @foreach (($it['serial_numbers'] ?? []) as $sn)
                                                                <option value="{{ $sn }}" selected>{{ $sn }}</option>
                                                            @endforeach
                                                        </select>
                                                        <p class="mt-1 text-xs text-gray-500">{{ __('Pilih serial jika stok serial-based.') }}</p>
                                                    </div>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <x-input-label :value="__('Quantity')" class="mb-1" />
                                                            <x-text-input type="number" name="items[{{ $i }}][quantity]" min="1" :value="$it['quantity'] ?? 1" placeholder="Qty" required />
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Harga Jual')" class="mb-1" />
                                                            <x-text-input type="text" name="items[{{ $i }}][price]" data-rupiah="true" :value="$it['price'] ?? 0" placeholder="Harga" required />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="absolute top-3 right-3">
                                                    <button type="button" class="remove-item inline-flex items-center px-3 py-2 rounded-md text-sm bg-red-100 text-red-700 hover:bg-red-200" style="display:none">{{ __('Hapus Item') }}</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        @foreach ($sale->saleDetails as $i => $d)
                                            @php
                                                $serials = $d->serial_numbers ? preg_split('/[\r\n,]+/', (string) $d->serial_numbers) : [];
                                                $serials = array_values(array_unique(array_filter(array_map('trim', $serials ?: []))));
                                            @endphp
                                            <div class="sale-item relative rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                                <div class="space-y-4">
                                                    <div>
                                                        <x-input-label :value="__('Produk')" class="mb-1" />
                                                        <select name="items[{{ $i }}][product_id]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 product-select" required>
                                                            <option value="{{ $d->product_id }}" selected>{{ $d->product?->sku ?? '-' }} - {{ $d->product?->brand ?? '' }} {{ $d->product?->series ?? '' }} ({{ number_format((float)$d->price, 0, ',', '.') }})</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <x-input-label :value="__('Nomor Serial')" class="mb-1" />
                                                        <input type="text" class="serial-search block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-2" placeholder="{{ __('Search serial...') }}" disabled>
                                                        <input type="text" class="serial-scan block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-2" placeholder="{{ __('Scan barcode/QR serial + Enter') }}" disabled>
                                                        <x-input-label :value="__('Daftar serial dipilih')" class="mb-1 text-xs" />
                                                        <select name="items[{{ $i }}][serial_numbers][]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 serial-select text-sm" multiple size="3" disabled>
                                                            @foreach ($serials as $sn)
                                                                <option value="{{ $sn }}" selected>{{ $sn }}</option>
                                                            @endforeach
                                                        </select>
                                                        <p class="mt-1 text-xs text-gray-500">{{ __('Pilih serial jika stok serial-based.') }}</p>
                                                    </div>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <x-input-label :value="__('Quantity')" class="mb-1" />
                                                            <x-text-input type="number" name="items[{{ $i }}][quantity]" min="1" :value="old('items.'.$i.'.quantity', $d->quantity)" placeholder="Qty" required />
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Harga Jual')" class="mb-1" />
                                                            <x-text-input type="text" name="items[{{ $i }}][price]" data-rupiah="true" :value="old('items.'.$i.'.price', $d->price)" placeholder="Harga" required />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="absolute top-3 right-3">
                                                    <button type="button" class="remove-item inline-flex items-center px-3 py-2 rounded-md text-sm bg-red-100 text-red-700 hover:bg-red-200" style="display:none">{{ __('Hapus Item') }}</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                                <button type="button" id="add-item" class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">+ {{ __('Add Item') }}</button>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                    <div>
                                        <x-input-label for="discount_amount" :value="__('Diskon')" />
                                        <x-text-input id="discount_amount" class="block mt-1 w-full" type="text" name="discount_amount" data-rupiah="true" :value="old('discount_amount', $sale->discount_amount ?? 0)" />
                                        <x-input-error :messages="$errors->get('discount_amount')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="tax_amount" :value="__('Pajak')" />
                                        <x-text-input id="tax_amount" class="block mt-1 w-full" type="text" name="tax_amount" data-rupiah="true" :value="old('tax_amount', $sale->tax_amount ?? 0)" />
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
                                <textarea id="description" name="description" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2">{{ old('description', $sale->description) }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <div id="payments-section" class="border rounded-lg p-4 bg-slate-50">
                                <p class="font-semibold text-slate-800">{{ __('Uang Muka') }}</p>
                                <p class="text-xs text-amber-700 mt-1">{{ __('Draft wajib uang muka. Boleh kurang dari total penjualan.') }}</p>
                                <div id="payment-rows" class="mt-3 space-y-2"></div>
                                <button type="button" id="add-payment" class="mt-2 inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah') }}</button>
                                <div class="mt-3 text-sm text-slate-700">
                                    <span>{{ __('Tunai') }}: </span><span id="paymentSumText" class="font-semibold">0</span>
                                    <span class="ml-2">+ {{ __('Tukar Tambah') }}: <span id="tradeInInPaymentText" class="font-semibold">0</span></span>
                                    <span class="ml-2 text-slate-500">({{ __('selisih') }} <span id="paymentDiffText">0</span>)</span>
                                </div>
                                <x-input-error :messages="$errors->get('payments')" class="mt-2" />
                            </div>

                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Update Draft') }}</x-primary-button>
                                <a href="{{ route('sales.show', $sale) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @php
        $editOldTradeIns = old('trade_ins', ($sale->tradeIns ?? collect())->map(fn ($t) => [
            'sku' => $t->sku,
            'brand' => $t->brand,
            'series' => $t->series ?? '',
            'processor' => $t->processor ?? '',
            'ram' => $t->ram ?? '',
            'storage' => $t->storage ?? '',
            'color' => $t->color ?? '',
            'specs' => $t->specs ?? '',
            'category_id' => $t->category_id,
            'serial_number' => $t->serial_number,
            'trade_in_value' => (float) $t->trade_in_value,
        ])->toArray());
        $editPaymentMethods = ($paymentMethods ?? collect())->map(fn ($m) => [
            'id' => $m->id,
            'label' => $m->display_label,
        ])->values()->toArray();
        $editOldPayments = old('payments', ($sale->payments ?? collect())->map(fn ($p) => [
            'payment_method_id' => $p->payment_method_id,
            'amount' => (float) $p->amount,
            'notes' => $p->notes ?? '',
        ])->toArray());
        $editSaleProductsForJs = $sale->saleDetails->map(fn ($d) => [
            'id' => $d->product_id,
            'sku' => $d->product?->sku ?? '-',
            'brand' => $d->product?->brand ?? '',
            'series' => $d->product?->series ?? '',
            'price' => (float) $d->price,
        ])->unique('id')->values()->toArray();
    @endphp
    <script>
        let products = @json($productsForJs);
        const saleProductsForEdit = @json($editSaleProductsForJs);
        const categories = @json($categories ?? []);
        const laptopCategoryId = (() => {
            const byCode = (categories || []).find(c => String(c.code || '').toUpperCase() === 'LAP');
            if (byCode?.id) return byCode.id;
            const byName = (categories || []).find(c => String(c.name || '').toLowerCase() === 'laptop');
            return byName?.id || null;
        })();
        const appBaseUrl = @json(request()->getBaseUrl()); // supports subfolder installs
        const availableProductsPath = @json(route('sales.available-products', [], false));
        const availableSerialsPath = @json(route('sales.available-serials', [], false));
        const checkReusableTradeInSerialsPath = @json(route('sales.check-reusable-trade-in-serials', [], false));
        const tradeInSerialSearchPath = @json(route('sales.trade-in-search-serial', [], false));
        const availableProductsUrl = appBaseUrl + availableProductsPath;
        const availableSerialsUrl = appBaseUrl + availableSerialsPath;
        const checkReusableTradeInSerialsUrl = appBaseUrl + checkReusableTradeInSerialsPath;
        const tradeInSerialSearchUrl = appBaseUrl + tradeInSerialSearchPath;

        function productOptionsHtml() {
            return '<option value="">Select Product</option>' + products.map(p =>
                '<option value="' + p.id + '" data-price="' + p.price + '">' +
                p.sku + ' - ' + p.brand + ' ' + (p.series || '') + ' (' + Number(p.price).toLocaleString('id-ID') + ')</option>'
            ).join('');
        }

        function createSerialSelectHtml(name) {
            return '<label class="block text-sm font-medium text-gray-700 mb-1">' + @json(__('Nomor Serial')) + '</label>' +
                '<input type="text" class="serial-search block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-2" placeholder="' + @json(__('Search serial...')) + '" disabled>' +
                '<input type="text" class="serial-scan block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-2" placeholder="' + @json(__('Scan barcode/QR serial + Enter')) + '" disabled>' +
                '<label class="block text-xs font-medium text-gray-600 mb-1">' + @json(__('Daftar serial dipilih')) + '</label>' +
                '<select name="' + name + '" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 serial-select text-sm" multiple size="3" disabled></select>' +
                '<p class="mt-1 text-xs text-gray-500">' + @json(__('Pilih serial jika stok serial-based.')) + '</p>';
        }

        let itemIndex = @json(max(1, (is_array(old('items')) ? count(old('items')) : $sale->saleDetails->count())));

        document.getElementById('add-item').addEventListener('click', function() {
            const container = document.getElementById('sale-items');
            const div = document.createElement('div');
            div.className = 'sale-item relative rounded-lg border border-slate-200 bg-slate-50/50 p-4';
            div.innerHTML =
                '<div class="space-y-4">' +
                '<div><label class="block text-sm font-medium text-gray-700 mb-1">' + @json(__('Produk')) + '</label><select name="items[' + itemIndex + '][product_id]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 product-select">' + productOptionsHtml() + '</select></div>' +
                '<div>' + createSerialSelectHtml('items[' + itemIndex + '][serial_numbers][]') + '</div>' +
                '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">' +
                '<div><label class="block text-sm font-medium text-gray-700 mb-1">' + @json(__('Quantity')) + '</label><input type="number" name="items[' + itemIndex + '][quantity]" min="1" value="1" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Qty" required></div>' +
                '<div><label class="block text-sm font-medium text-gray-700 mb-1">' + @json(__('Harga Jual')) + '</label><input type="text" name="items[' + itemIndex + '][price]" data-rupiah="true" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Harga" required></div>' +
                '</div>' +
                '</div>' +
                '<div class="absolute top-3 right-3"><button type="button" class="remove-item inline-flex items-center px-3 py-2 rounded-md text-sm bg-red-100 text-red-700 hover:bg-red-200">' + @json(__('Hapus Item')) + '</button></div>';
            container.appendChild(div);
            itemIndex++;
            toggleRemoveButtons();
            refreshTotals();
        });

        document.getElementById('sale-items').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item')) {
                e.target.closest('.sale-item').remove();
                toggleRemoveButtons();
                refreshTotals();
            }
        });

        const rowSerials = new WeakMap(); // row -> full serial array
        const rowUnits = new WeakMap();   // row -> [{serial_number, harga_jual, harga_hpp}, ...]

        function updateProductSelectOptions(selectEl) {
            if (!selectEl) return;
            const old = selectEl.value;
            selectEl.innerHTML = productOptionsHtml();
            if (old && Array.from(selectEl.options).some(o => o.value === old)) {
                selectEl.value = old;
            } else {
                selectEl.value = '';
            }
        }

        async function loadProductsForBranch() {
            const branchId = @json((string) $sale->branch_id);
            if (!branchId) {
                products = [];
                document.querySelectorAll('.product-select').forEach(sel => updateProductSelectOptions(sel));
                return;
            }

            try {
                const url = new URL(availableProductsUrl, window.location.origin);
                url.searchParams.set('branch_id', branchId);
                const res = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error(`available-products ${res.status}`);
                const data = await res.json();
                const apiProducts = Array.isArray(data.products) ? data.products : [];
                const saleIds = (saleProductsForEdit || []).map(sp => String(sp.id));
                products = [
                    ...(saleProductsForEdit || []),
                    ...apiProducts.filter(ap => !saleIds.includes(String(ap.id)))
                ];

                document.querySelectorAll('.sale-item').forEach(row => {
                    const productSelect = row.querySelector('.product-select');
                    updateProductSelectOptions(productSelect);
                });
            } catch (e) {
                // keep previous products list if fetch fails
                console.error('Failed to load products for branch', e);
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
            const merged = Array.from(new Set([...filtered, ...selected]));

            serialSelect.innerHTML = merged.map(sn => `<option value="${sn}">${sn}</option>`).join('');
            Array.from(serialSelect.options).forEach(opt => {
                if (selected.has(opt.value)) opt.selected = true;
            });
        }

        async function loadSerialsForRow(row) {
            const branchId = @json((string) $sale->branch_id);
            const productSelect = row.querySelector('.product-select');
            const serialSelect = row.querySelector('.serial-select');
            if (!serialSelect) return;

            const productId = productSelect?.value;
            const existingSelected = Array.from(serialSelect.selectedOptions || []).map(o => o.value);

            if (!branchId || !productId) {
                rowSerials.set(row, existingSelected);
                rowUnits.set(row, []);
                setSerialInputsEnabled(row, false);
                renderSerialOptions(row);
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
                const apiSerials = Array.isArray(data.serial_numbers) ? data.serial_numbers : [];
                const units = Array.isArray(data.units) ? data.units : [];
                const isTracked = !!data.is_serial_tracked;
                const serials = [...new Set([...apiSerials, ...existingSelected])];

                rowSerials.set(row, serials);
                rowUnits.set(row, units);
                setSerialInputsEnabled(row, isTracked);
                renderSerialOptions(row);
            } catch (e) {
                rowSerials.set(row, []);
                rowUnits.set(row, []);
                serialSelect.innerHTML = '';
                setSerialInputsEnabled(row, false);
                console.error('Failed to load serials', e);
            }
        }

        function syncPriceFromSerials(row) {
            const serialSelect = row.querySelector('.serial-select');
            const priceInput = row.querySelector('input[name*="[price]"]');
            if (!serialSelect || !priceInput) return;

            const selectedSerials = Array.from(serialSelect.selectedOptions || []).map(o => o.value);
            const units = rowUnits.get(row) || [];
            if (selectedSerials.length === 0 || units.length === 0) return;

            const unitMap = Object.fromEntries(units.map(u => [String(u.serial_number || ''), u]));
            let totalHargaJual = 0;
            for (const sn of selectedSerials) {
                const u = unitMap[sn];
                if (u && typeof u.harga_jual === 'number') totalHargaJual += u.harga_jual;
                else if (u && typeof u.harga_jual === 'string') totalHargaJual += parseFloat(u.harga_jual) || 0;
            }
            const pricePerUnit = selectedSerials.length > 0 ? Math.round(totalHargaJual / selectedSerials.length * 100) / 100 : 0;
            priceInput.value = pricePerUnit;
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            if (typeof refreshTotals === 'function') refreshTotals();
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

        document.getElementById('sale-items').addEventListener('change', function(e) {
            const row = e.target.closest('.sale-item');
            if (!row) return;

            if (e.target.classList.contains('product-select')) {
                const opt = e.target.options[e.target.selectedIndex];
                const price = opt?.dataset?.price;
                const priceInput = row.querySelector('input[name*="[price]"]');
                if (price && priceInput) priceInput.value = price;
                loadSerialsForRow(row);
                refreshTotals();
            }

            if (e.target.classList.contains('serial-select')) {
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
                syncPriceFromSerials(row);
                refreshTotals();
            }
        });

        document.getElementById('sale-items').addEventListener('input', function(e) {
            const row = e.target.closest('.sale-item');
            if (!row) return;
            if (e.target.classList.contains('serial-search')) {
                renderSerialOptions(row);
            }
            if (e.target.name?.includes('[quantity]') || e.target.name?.includes('[price]')) {
                refreshTotals();
            }
        });

        document.getElementById('sale-items').addEventListener('keydown', function(e) {
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

        // Customer toggle
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

        // Totals
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
        document.getElementById('discount_amount')?.addEventListener('input', refreshTotals);
        document.getElementById('tax_amount')?.addEventListener('input', refreshTotals);

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

        function fillTradeInRowFromSerialResult(row, item) {
            const set = (partialName, v) => {
                const el = row.querySelector('input[name*="' + partialName + '"]');
                if (el) el.value = v == null || v === undefined ? '' : String(v);
            };
            set('[brand]', item.brand);
            set('[series]', item.series);
            set('[processor]', item.processor);
            set('[ram]', item.ram);
            set('[storage]', item.storage);
            set('[color]', item.color);
            set('[specs]', item.specs);
            set('[serial_number]', item.serial_number);
            const catEl = row.querySelector('input[name*="[category_id]"]');
            if (catEl) {
                const cid = item.category_id || laptopCategoryId || '';
                catEl.value = cid ? String(cid) : '';
            }
            const skuInput = row.querySelector('input[name*="[sku]"]');
            const skuDisplay = row.querySelector('.trade-in-sku-display');
            const skuVal = item.sku || '';
            if (skuInput) skuInput.value = skuVal;
            if (skuDisplay) skuDisplay.value = skuVal;
            const valInp = row.querySelector('.trade-in-value-input');
            if (valInp && item.harga_hpp != null && Number(item.harga_hpp) > 0) {
                valInp.value = String(Math.round(Number(item.harga_hpp)));
                if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            }
            const dd = row.querySelector('.trade-in-serial-dropdown');
            const si = row.querySelector('.trade-in-serial-search');
            if (dd) { dd.innerHTML = ''; dd.classList.add('hidden'); }
            if (si) si.value = '';
            refreshTradeInSum();
            refreshPaymentSum();
        }

        function attachTradeInSerialSearch(row) {
            const input = row.querySelector('.trade-in-serial-search');
            const dropdown = row.querySelector('.trade-in-serial-dropdown');
            if (!input || !dropdown) return;
            let debounce = null;
            let lastController = null;
            input.addEventListener('input', () => {
                clearTimeout(debounce);
                const q = input.value.trim();
                if (q.length < 2) {
                    dropdown.classList.add('hidden');
                    dropdown.innerHTML = '';
                    return;
                }
                debounce = setTimeout(async () => {
                    lastController?.abort();
                    lastController = new AbortController();
                    try {
                        const url = new URL(tradeInSerialSearchUrl, window.location.origin);
                        url.searchParams.set('q', q);
                        const res = await fetch(url.toString(), {
                            headers: { Accept: 'application/json' },
                            signal: lastController.signal,
                        });
                        if (!res.ok) throw new Error('fetch');
                        const data = await res.json();
                        const results = Array.isArray(data.results) ? data.results : [];
                        dropdown.innerHTML = '';
                        if (results.length === 0) {
                            const empty = document.createElement('div');
                            empty.className = 'px-3 py-2 text-xs text-slate-500';
                            empty.textContent = @json(__('Tidak ada data serial yang cocok.'));
                            dropdown.appendChild(empty);
                        } else {
                            results.forEach((item) => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'w-full text-left px-3 py-2 text-sm hover:bg-amber-100 border-b border-amber-100/80 last:border-0';
                                const st = item.status || '';
                                btn.textContent = (item.serial_number || '') + ' — ' + [item.brand, item.series].filter(Boolean).join(' ') + (st ? ' (' + st + ')' : '');
                                btn.addEventListener('mousedown', (ev) => {
                                    ev.preventDefault();
                                    fillTradeInRowFromSerialResult(row, item);
                                });
                                dropdown.appendChild(btn);
                            });
                        }
                        dropdown.classList.remove('hidden');
                    } catch (e) {
                        if (e.name === 'AbortError') return;
                        dropdown.innerHTML = '';
                        dropdown.classList.add('hidden');
                    }
                }, 300);
            });
            input.addEventListener('blur', () => {
                setTimeout(() => dropdown.classList.add('hidden'), 200);
            });
            input.addEventListener('focus', () => {
                if (dropdown.children.length) dropdown.classList.remove('hidden');
            });
        }

        function addTradeInRow(pref = {}) {
            const container = document.getElementById('trade-in-rows');
            if (!container) return;
            const idx = tradeInIndex++;
            const esc = (s) => String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            const div = document.createElement('div');
            div.className = 'trade-in-row rounded-lg border border-amber-200 bg-white p-3 space-y-3';
            div.innerHTML = `
                <div class="trade-in-serial-autocomplete relative rounded-lg border border-amber-100 bg-amber-50/60 p-3">
                    <label class="block text-xs font-medium text-amber-900 mb-1">` + @json(__('Cari nomor serial')) + `</label>
                    <input type="text" class="trade-in-serial-search block w-full rounded-md border border-amber-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="` + @json(__('Ketik minimal 2 karakter, lalu pilih dari daftar')) + `" autocomplete="off">
                    <div class="trade-in-serial-dropdown hidden absolute left-3 right-3 mt-1 max-h-52 overflow-auto rounded-md border border-amber-200 bg-white shadow-lg z-50"></div>
                    <p class="mt-1 text-[11px] text-amber-800/80">` + @json(__('Mengisi otomatis dari unit di seluruh cabang/gudang. Lokasi unit diperbarui saat transaksi disimpan.')) + `</p>
                </div>
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
            attachTradeInSerialSearch(div);
            if (pref.category_id) {
                const sel = div.querySelector('select[name*="[category_id]"]');
                if (sel) sel.value = String(pref.category_id);
            }
            div.querySelectorAll('input').forEach(el => {
                el.addEventListener('input', () => { refreshTradeInSum(); refreshPaymentSum(); });
            });
            div.querySelector('.remove-trade-in')?.addEventListener('click', () => { div.remove(); refreshTradeInSum(); refreshPaymentSum(); });
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

        const oldTradeIns = @json($editOldTradeIns ?? []);
        if (Array.isArray(oldTradeIns) && oldTradeIns.length > 0) {
            oldTradeIns.forEach(t => addTradeInRow(t));
        }

        // Payments (uang muka untuk draft)
        const paymentMethods = @json($editPaymentMethods);
        const paymentRows = document.getElementById('payment-rows');
        let paymentIndex = 0;
        function paymentOptionsHtml() {
            return '<option value="">Pilih metode</option>' + paymentMethods.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
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
            if (pref.payment_method_id) div.querySelector('select').value = String(pref.payment_method_id);
            if (pref.amount) div.querySelector('input[name*="[amount]"]').value = String(pref.amount);
            if (pref.notes) div.querySelector('input[name*="[notes]"]').value = String(pref.notes || '');
            div.querySelectorAll('select,input').forEach(el => el.addEventListener('input', refreshPaymentSum));
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            div.querySelector('.remove-payment')?.addEventListener('click', () => { div.remove(); refreshPaymentSum(); });
        }
        function refreshPaymentSum() {
            const subtotal = calcSubtotal();
            const disc = toNumber(document.getElementById('discount_amount')?.value || '0') || 0;
            const tax = toNumber(document.getElementById('tax_amount')?.value || '0') || 0;
            const total = Math.max(0, subtotal - disc + tax);
            const tradeInSum = calcTradeInSum();
            let sum = 0;
            document.querySelectorAll('#payment-rows input[name*="[amount]"]').forEach(inp => {
                const v = toNumber(inp.value || '0');
                if (v > 0) sum += v;
            });
            const totalPaid = sum + tradeInSum;
            const sumEl = document.getElementById('paymentSumText');
            const diffEl = document.getElementById('paymentDiffText');
            if (sumEl) sumEl.textContent = fmtNumber(sum);
            if (diffEl) diffEl.textContent = fmtNumber(total - totalPaid);
        }
        document.getElementById('add-payment')?.addEventListener('click', () => addPaymentRow());
        const oldPayments = @json($editOldPayments);
        if (Array.isArray(oldPayments) && oldPayments.length > 0) {
            oldPayments.forEach(p => addPaymentRow(p));
        } else {
            addPaymentRow();
        }

        // initial load
        loadProductsForBranch().then(async () => {
            for (const row of document.querySelectorAll('.sale-item')) {
                await loadSerialsForRow(row);
                syncQtyFromSerials(row);
                syncPriceFromSerials(row);
            }
            refreshTotals();
        });

        function toggleRemoveButtons() {
            const items = document.querySelectorAll('.sale-item');
            items.forEach((item, i) => {
                const btn = item.querySelector('.remove-item');
                if (btn) btn.style.display = items.length > 1 ? 'inline-block' : 'none';
            });
        }
        toggleRemoveButtons();

        const saleForm = document.getElementById('sale-form');
        const confirmReuseInput = document.getElementById('confirm_reuse_sold_serials');
        if (saleForm && confirmReuseInput) {
            saleForm.addEventListener('submit', async function(e) {
                if (saleForm.dataset.reuseConfirmed === '1' || confirmReuseInput.value === '1') {
                    return;
                }
                e.preventDefault();
                try {
                    const formData = new FormData(saleForm);
                    const res = await fetch(checkReusableTradeInSerialsUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });
                    if (!res.ok) {
                        saleForm.dataset.reuseConfirmed = '1';
                        saleForm.requestSubmit();
                        return;
                    }
                    const data = await res.json();
                    const blocked = Array.isArray(data.blocked_serials) ? data.blocked_serials : [];
                    if (blocked.length > 0) {
                        await Swal.fire({
                            icon: 'error',
                            title: 'Serial tukar tambah tidak valid',
                            text: 'Serial berikut tidak bisa dipakai ulang karena bukan status SOLD: ' + blocked.join(', '),
                        });
                        return;
                    }
                    const sold = Array.isArray(data.sold_serials) ? data.sold_serials : [];
                    if (sold.length > 0) {
                        const confirmResult = await Swal.fire({
                            icon: 'warning',
                            title: 'Serial tukar tambah sudah pernah ada',
                            html: 'Unit berikut sudah pernah ada dan statusnya <b>SOLD</b>:<br><b>' + sold.join(', ') + '</b><br><br>Lanjutkan update data unit/barang dengan data terbaru?',
                            showCancelButton: true,
                            confirmButtonText: 'Ya, update',
                            cancelButtonText: 'Batal',
                        });
                        if (!confirmResult.isConfirmed) {
                            return;
                        }
                        confirmReuseInput.value = '1';
                    }
                    saleForm.dataset.reuseConfirmed = '1';
                    saleForm.requestSubmit();
                } catch (err) {
                    saleForm.dataset.reuseConfirmed = '1';
                    saleForm.requestSubmit();
                }
            });
        }
    </script>
</x-app-layout>

