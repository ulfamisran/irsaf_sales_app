<x-app-layout>
    <x-slot name="title">{{ __('Edit Penyewaan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Penyewaan') }}: {{ $rental->invoice_number }}</h2>
            <x-icon-btn-back :href="route('rentals.show', $rental)" :label="__('Kembali')" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('rentals.update', $rental) }}" id="rental-form">
                        @csrf
                        @method('PATCH')
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="location_type" :value="__('Tipe Lokasi')" />
                                    <select id="location_type" name="location_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="warehouse" {{ old('location_type', $rental->location_type ?? ($rental->warehouse_id ? 'warehouse' : 'branch')) == 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                        <option value="branch" {{ old('location_type', $rental->location_type ?? '') == 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="location_id" :value="__('Lokasi')" />
                                    <select id="location_id" name="location_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="">{{ __('Pilih lokasi') }}</option>
                                    </select>
                                    <p id="products_status" class="mt-1 text-xs text-slate-500"></p>
                                    <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="pickup_date" :value="__('Tanggal Pengambilan')" />
                                    <x-text-input id="pickup_date" class="block mt-1 w-full" type="date" name="pickup_date" :value="old('pickup_date', $rental->pickup_date?->toDateString())" required />
                                    <x-input-error :messages="$errors->get('pickup_date')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="return_date" :value="__('Tanggal Pengembalian')" />
                                    <x-text-input id="return_date" class="block mt-1 w-full" type="date" name="return_date" :value="old('return_date', $rental->return_date?->toDateString())" required />
                                    <x-input-error :messages="$errors->get('return_date')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label :value="__('Jumlah Hari')" />
                                    <x-text-input id="total_days" class="block mt-1 w-full bg-slate-50" type="text" value="{{ $rental->total_days }}" readonly />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="customer_id" :value="__('Penyewa')" />
                                <select id="customer_id" name="customer_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Pilih Penyewa (atau isi penyewa baru)') }}</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" {{ old('customer_id', $rental->customer_id) == $c->id ? 'selected' : '' }}>
                                            {{ $c->name }}{{ $c->phone ? ' - '.$c->phone : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                            </div>

                            <div id="new-customer-fields" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="customer_new_name" :value="__('Nama Penyewa')" />
                                    <x-text-input id="customer_new_name" class="block mt-1 w-full" type="text" name="customer_new_name" :value="old('customer_new_name')" placeholder="Nama penyewa" />
                                    <x-input-error :messages="$errors->get('customer_new_name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="customer_new_phone" :value="__('No. HP')" />
                                    <x-text-input id="customer_new_phone" class="block mt-1 w-full" type="text" name="customer_new_phone" :value="old('customer_new_phone')" placeholder="08xxxxxxxxxx" />
                                </div>
                                <div>
                                    <x-input-label for="customer_new_address" :value="__('Alamat')" />
                                    <x-text-input id="customer_new_address" class="block mt-1 w-full" type="text" name="customer_new_address" :value="old('customer_new_address')" placeholder="Alamat" />
                                </div>
                            </div>

                            <div id="product-selector-block">
                                <x-input-label :value="__('Laptop yang Disewa')" class="font-semibold" />
                                <p class="mt-1 mb-2 text-xs text-slate-500">{{ __('Pilih lokasi terlebih dahulu. Produk: Laptop, bekas, aktif. Unit in_stock sesuai cabang/gudang.') }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                    <div>
                                        <x-input-label for="rental_brand_filter" :value="__('Merk')" class="text-sm" />
                                        <select id="rental_brand_filter" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">{{ __('Semua Merk') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="rental_series_filter" :value="__('Series')" class="text-sm" />
                                        <select id="rental_series_filter" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">{{ __('Semua Series') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="overflow-x-auto border border-slate-200 rounded-lg">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-slate-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left">{{ __('Produk') }}</th>
                                                <th class="px-3 py-2 text-left">{{ __('Nomor Serial') }}</th>
                                                <th class="px-3 py-2 text-right">{{ __('Harga Sewa / Hari') }}</th>
                                                <th class="px-3 py-2 text-right">{{ __('Total') }}</th>
                                                <th class="px-3 py-2 text-right"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="rental-items"></tbody>
                                    </table>
                                </div>
                                <button type="button" id="add-item" class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">+ {{ __('Tambah Item') }}</button>
                                <div id="items-hidden"></div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                    <div>
                                        <x-input-label for="tax_amount" :value="__('Pajak')" />
                                        <x-text-input id="tax_amount" class="block mt-1 w-full" type="text" name="tax_amount" data-rupiah="true" :value="old('tax_amount', $rental->tax_amount)" />
                                        <x-input-error :messages="$errors->get('tax_amount')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="penalty_amount" :value="__('Denda')" />
                                        <x-text-input id="penalty_amount" class="block mt-1 w-full" type="text" name="penalty_amount" data-rupiah="true" :value="old('penalty_amount', $rental->penalty_amount)" />
                                        <x-input-error :messages="$errors->get('penalty_amount')" class="mt-2" />
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

                            <div>
                                <x-input-label for="description" :value="__('Keterangan (Opsional)')" />
                                <textarea id="description" name="description" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2">{{ old('description', $rental->description) }}</textarea>
                            </div>

                            <div id="payments-section" class="border rounded-lg p-4 bg-slate-50">
                                <p class="font-semibold text-slate-800">{{ __('Pembayaran DP (Wajib)') }}</p>
                                <p class="text-xs text-amber-700 mt-1">{{ __('Penyewaan wajib DP. Boleh kurang dari total sewa.') }}</p>
                                <div class="mt-3 flex justify-between items-center">
                                    <button type="button" id="add-payment" class="inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah') }}</button>
                                </div>
                                <div id="payment-rows" class="mt-3 space-y-2"></div>
                                <div class="mt-3 text-sm text-slate-700">
                                    <span>{{ __('Total pembayaran') }}: </span><span id="paymentSumText" class="font-semibold">0</span>
                                    <span class="ml-2 text-slate-500">({{ __('selisih') }} <span id="paymentDiffText">0</span>)</span>
                                </div>
                                <x-input-error :messages="$errors->get('payments')" class="mt-2" />
                            </div>

                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Update Penyewaan') }}</x-primary-button>
                                <a href="{{ route('rentals.show', $rental) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @php
        $editPaymentMethods = ($paymentMethods ?? collect())->map(fn ($m) => ['id' => $m->id, 'label' => $m->display_label])->values()->toArray();
        $editPayments = old('payments', ($rental->payments ?? collect())->map(fn ($p) => ['payment_method_id' => $p->payment_method_id, 'amount' => (float)$p->amount, 'notes' => $p->notes])->toArray());
        $editItems = old('items', ($rental->items ?? collect())->map(fn ($i) => ['product_id' => $i->product_id, 'serial_number' => $i->serial_number, 'rental_price' => (float)$i->rental_price])->toArray());
    @endphp
    <script>
        let paymentMethods = @json($editPaymentMethods);
        const editPayments = @json($editPayments);
        const editItems = @json($editItems);
        const rentalId = @json($rental->id);
        const appBaseUrl = @json(request()->getBaseUrl());
        const availableProductsPath = @json(route('rentals.available-products', [], false));
        const availableSerialsPath = @json(route('rentals.available-serials', [], false));
        const formDataUrl = @json(route('data-by-location.form-data', [], false));
        const availableProductsUrl = appBaseUrl + availableProductsPath;
        const availableSerialsUrl = appBaseUrl + availableSerialsPath;
        const warehouses = @json($warehouses ?? []);
        const branches = @json($branches ?? []);
        const editLocationType = @json(old('location_type', $rental->location_type ?? ($rental->warehouse_id ? 'warehouse' : 'branch')));
        const editLocationId = @json(old('location_id', $rental->warehouse_id ?? $rental->branch_id));
        let products = [];

        function toNumber(val) {
            if (typeof window.parseRupiahToNumber === 'function') {
                return window.parseRupiahToNumber(val);
            }
            const raw = String(val ?? '').replace(/[^\d]/g, '');
            return raw ? parseFloat(raw) : 0;
        }
        function fmtNumber(n) {
            return Number(n || 0).toLocaleString('id-ID');
        }

        function setProductsStatus(text, type = 'info') {
            const el = document.getElementById('products_status');
            if (!el) return;
            el.textContent = text || '';
            el.className = 'mt-1 text-xs ' + (type === 'error'
                ? 'text-red-600'
                : (type === 'ok' ? 'text-emerald-600' : 'text-slate-500'));
        }

        function getProductsByBrandSeries() {
            const brandVal = document.getElementById('rental_brand_filter')?.value || '';
            const seriesVal = document.getElementById('rental_series_filter')?.value || '';
            return products.filter(p => {
                const matchBrand = !brandVal || (p.brand || '') === brandVal;
                const matchSeries = !seriesVal || (p.series || '') === seriesVal;
                return matchBrand && matchSeries;
            });
        }

        function getProductsForDropdown(baseList, searchVal) {
            const q = (searchVal || '').trim().toLowerCase();
            if (!q) return baseList;
            return baseList.filter(p => {
                const s = ((p.sku || '') + ' ' + (p.brand || '') + ' ' + (p.series || '') + ' ' + (p.color || '')).toLowerCase();
                return s.includes(q);
            });
        }

        function productOptionHtml(p) {
            const label = (p.sku || '') + ' - ' + (p.brand || '') + ' ' + (p.series || '') + (p.in_stock_count != null ? ' (' + p.in_stock_count + ' unit)' : '');
            return '<div class="product-option px-3 py-2 cursor-pointer hover:bg-indigo-50 text-sm" data-id="' + p.id + '" data-sku="' + (p.sku || '') + '" data-brand="' + (p.brand || '') + '" data-series="' + (p.series || '') + '">' + label + '</div>';
        }

        function renderProductList(dropdownEl, searchVal) {
            const baseList = getProductsByBrandSeries();
            const list = getProductsForDropdown(baseList, searchVal);
            const listEl = dropdownEl.querySelector('.product-list');
            const emptyEl = dropdownEl.querySelector('.product-empty');
            if (!listEl) return;
            listEl.innerHTML = list.map(p => productOptionHtml(p)).join('');
            listEl.classList.toggle('hidden', list.length === 0);
            if (emptyEl) emptyEl.classList.toggle('hidden', list.length > 0);
            attachProductOptionHandlers(dropdownEl);
        }

        function attachProductOptionHandlers(dropdownEl) {
            const wrapper = dropdownEl.closest('.product-dropdown-wrapper');
            const idInput = wrapper?.querySelector('.product-id-input');
            const labelEl = wrapper?.querySelector('.product-label');
            dropdownEl.querySelectorAll('.product-option').forEach(opt => {
                opt.onclick = function(e) {
                    e.stopPropagation();
                    const id = this.getAttribute('data-id');
                    const p = products.find(x => String(x.id) === id);
                    if (idInput) idInput.value = id;
                    if (idInput) idInput.setAttribute('required', 'required');
                    if (labelEl) {
                        labelEl.textContent = (p?.sku || '') + ' - ' + (p?.brand || '') + ' ' + (p?.series || '');
                        labelEl.classList.remove('text-slate-500');
                    }
                    dropdownEl.classList.add('hidden');
                    const row = wrapper?.closest('.rental-item');
                    if (row) loadSerialsForRow(row);
                };
            });
        }

        function updateAllProductDropdowns() {
            document.querySelectorAll('.product-dropdown').forEach(dd => {
                const searchInput = dd.querySelector('.product-search-input');
                renderProductList(dd, searchInput?.value || '');
            });
        }

        function getProductDropdownHtml(idx) {
            return `
                <div class="product-dropdown-wrapper relative">
                    <input type="hidden" class="product-id-input" value="">
                    <button type="button" class="product-trigger w-full flex items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm hover:bg-slate-50">
                        <span class="product-label text-slate-500">{{ __('Pilih Produk') }}</span>
                        <svg class="h-4 w-4 text-slate-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </button>
                    <div class="product-dropdown hidden absolute z-30 left-0 right-0 mt-1 min-w-[280px] rounded-md border border-gray-200 bg-white shadow-lg">
                        <div class="p-2 border-b border-gray-100">
                            <input type="text" class="product-search-input w-full rounded-md border border-gray-300 py-1.5 px-2 text-sm" placeholder="{{ __('Cari SKU, merk, series...') }}">
                        </div>
                        <div class="product-list max-h-48 overflow-auto py-1"></div>
                        <div class="product-empty hidden px-3 py-3 text-sm text-slate-500 text-center">{{ __('Tidak ada produk.') }}</div>
                    </div>
                </div>
            `;
        }

        function initProductDropdown(wrapper) {
            if (!wrapper) return;
            const trigger = wrapper.querySelector('.product-trigger');
            const dropdown = wrapper.querySelector('.product-dropdown');
            const searchInput = wrapper.querySelector('.product-search-input');
            trigger?.addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden'));
                dropdown?.classList.toggle('hidden');
                if (!dropdown?.classList.contains('hidden') && searchInput) {
                    searchInput.value = '';
                    renderProductList(dropdown, '');
                    searchInput.focus();
                }
            });
            searchInput?.addEventListener('input', () => renderProductList(dropdown, searchInput.value));
            searchInput?.addEventListener('keydown', (e) => { if (e.key === 'Escape') dropdown?.classList.add('hidden'); });
            dropdown?.addEventListener('click', (e) => e.stopPropagation());
        }

        function updateBrandSeriesFilters() {
            const brands = [...new Set(products.map(p => p.brand).filter(Boolean))].sort();
            const brandSel = document.getElementById('rental_brand_filter');
            if (brandSel) brandSel.innerHTML = '<option value="">Semua Merk</option>' + brands.map(b => '<option value="' + b + '">' + b + '</option>').join('');
            const seriesSel = document.getElementById('rental_series_filter');
            if (seriesSel) {
                const brandVal = brandSel?.value || '';
                const series = [...new Set(products.filter(p => !brandVal || p.brand === brandVal).map(p => p.series).filter(Boolean))].sort();
                seriesSel.innerHTML = '<option value="">Semua Series</option>' + series.map(s => '<option value="' + s + '">' + s + '</option>').join('');
            }
        }

        function updateLocationSelect() {
            const type = document.getElementById('location_type')?.value;
            const locSel = document.getElementById('location_id');
            if (!locSel) return;
            const options = type === 'warehouse' ? warehouses : branches;
            locSel.innerHTML = '<option value="">Pilih lokasi</option>' + options.map(o => '<option value="' + o.id + '">' + o.name + '</option>').join('');
            if (editLocationId && type === editLocationType) locSel.value = String(editLocationId);
        }

        async function loadProductsForLocation() {
            const locationType = document.getElementById('location_type')?.value;
            const locationId = document.getElementById('location_id')?.value;
            if (!locationId) {
                products = [];
                updateBrandSeriesFilters();
                updateAllProductDropdowns();
                setProductsStatus('');
                return;
            }
            try {
                setProductsStatus('Memuat produk...');
                const url = new URL(availableProductsUrl, window.location.origin);
                url.searchParams.set('location_type', locationType);
                url.searchParams.set('location_id', locationId);
                url.searchParams.set('rental_id', rentalId);
                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`available-products ${res.status}`);
                const data = await res.json();
                products = Array.isArray(data.products) ? data.products : [];
                updateBrandSeriesFilters();
                updateAllProductDropdowns();
                setProductsStatus(products.length > 0 ? 'Produk tersedia: ' + products.length : 'Tidak ada laptop bekas/aktif di lokasi ini.', products.length > 0 ? 'ok' : 'info');
            } catch (e) {
                console.error('Failed to load products', e);
                setProductsStatus('Gagal memuat produk.', 'error');
            }
        }

        async function loadSerialsForRow(row) {
            const locationType = document.getElementById('location_type')?.value;
            const locationId = document.getElementById('location_id')?.value;
            const productIdInput = row.querySelector('.product-id-input');
            const serialBox = row.querySelector('.serial-box');
            if (!serialBox) return;

            const productId = productIdInput?.value;
            if (!locationId || !productId) {
                serialBox.innerHTML = '<p class="text-xs text-slate-500">{{ __('Pilih produk dulu') }}</p>';
                return;
            }

            try {
                const url = new URL(availableSerialsUrl, window.location.origin);
                url.searchParams.set('location_type', locationType);
                url.searchParams.set('location_id', locationId);
                url.searchParams.set('product_id', productId);
                url.searchParams.set('rental_id', rentalId);
                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`available-serials ${res.status}`);
                const data = await res.json();
                const serials = Array.isArray(data.serial_numbers) ? data.serial_numbers : [];
                if (serials.length === 0) {
                    serialBox.innerHTML = '<p class="text-xs text-amber-600">{{ __('Tidak ada serial tersedia') }}</p>';
                    return;
                }
                serialBox.innerHTML = serials.map((sn, idx) => `
                    <label class="flex items-center gap-2 py-0.5">
                        <input type="checkbox" class="serial-check" value="${sn}">
                        <span class="font-mono text-xs">${sn}</span>
                    </label>
                `).join('');
            } catch (e) {
                serialBox.innerHTML = '<p class="text-xs text-red-600">{{ __('Gagal memuat serial') }}</p>';
            }
        }

        function calcDays() {
            const pick = document.getElementById('pickup_date')?.value;
            const ret = document.getElementById('return_date')?.value;
            if (!pick || !ret) return 1;
            const d1 = new Date(pick);
            const d2 = new Date(ret);
            const diff = Math.max(0, Math.round((d2 - d1) / 86400000));
            return Math.max(1, diff + 1);
        }

        function refreshDays() {
            const days = calcDays();
            const el = document.getElementById('total_days');
            if (el) el.value = days;
            refreshTotals();
        }

        function refreshTotals() {
            const days = calcDays();
            let subtotal = 0;
            document.querySelectorAll('.rental-item').forEach(row => {
                const price = toNumber(row.querySelector('.rental-price')?.value || '0');
                const qty = row.querySelectorAll('.serial-check:checked').length || 0;
                const line = Math.max(0, price * days * qty);
                const lineEl = row.querySelector('.line-total');
                if (lineEl) lineEl.textContent = fmtNumber(line);
                subtotal += line;
            });
            const tax = toNumber(document.getElementById('tax_amount')?.value || '0');
            const penalty = toNumber(document.getElementById('penalty_amount')?.value || '0');
            const total = Math.max(0, subtotal + tax + penalty);
            document.getElementById('subtotalText').textContent = fmtNumber(subtotal);
            document.getElementById('totalText').textContent = fmtNumber(total);
            refreshPaymentSum();
        }

        function refreshPaymentSum() {
            const subtotalText = document.getElementById('subtotalText')?.textContent || '0';
            const subtotal = toNumber(subtotalText);
            const tax = toNumber(document.getElementById('tax_amount')?.value || '0');
            const penalty = toNumber(document.getElementById('penalty_amount')?.value || '0');
            const total = Math.max(0, subtotal + tax + penalty);
            let sum = 0;
            document.querySelectorAll('#payment-rows input[name*="[amount]"]').forEach(inp => {
                const v = toNumber(inp.value || '0');
                if (v > 0) sum += v;
            });
            document.getElementById('paymentSumText').textContent = fmtNumber(sum);
            document.getElementById('paymentDiffText').textContent = fmtNumber(total - sum);
        }

        function createItemRow(index) {
            const body = document.getElementById('rental-items');
            const tr = document.createElement('tr');
            tr.className = 'rental-item';
            tr.innerHTML = `
                <td class="px-3 py-2">
                    ${getProductDropdownHtml(index)}
                </td>
                <td class="px-3 py-2">
                    <div class="serial-box text-sm text-slate-600">
                        <p class="text-xs text-slate-500">{{ __('Pilih produk dulu') }}</p>
                    </div>
                </td>
                <td class="px-3 py-2">
                    <input type="text" name="items[${index}][rental_price]" data-rupiah="true" class="rental-price block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right" placeholder="Harga" required>
                </td>
                <td class="px-3 py-2 text-right">
                    <span class="line-total">0</span>
                </td>
                <td class="px-3 py-2 text-right">
                    <button type="button" class="remove-item px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 text-xs">{{ __('Hapus') }}</button>
                </td>
            `;
            body.appendChild(tr);
            initProductDropdown(tr.querySelector('.product-dropdown-wrapper'));
            updateAllProductDropdowns();
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            return tr;
        }

        function groupItems(items) {
            const map = new Map();
            (items || []).forEach(it => {
                const key = `${it.product_id}|${it.rental_price}`;
                if (!map.has(key)) {
                    map.set(key, { product_id: it.product_id, rental_price: it.rental_price, serials: [] });
                }
                map.get(key).serials.push(it.serial_number);
            });
            return Array.from(map.values());
        }

        function toggleRemoveButtons() {
            const items = document.querySelectorAll('.rental-item');
            items.forEach((item) => {
                const btn = item.querySelector('.remove-item');
                if (btn) btn.style.display = items.length > 1 ? 'inline-block' : 'none';
            });
        }

        document.getElementById('add-item')?.addEventListener('click', () => {
            const idx = document.querySelectorAll('.rental-item').length;
            createItemRow(idx);
            toggleRemoveButtons();
        });

        document.getElementById('rental-items')?.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-item')) {
                e.target.closest('tr').remove();
                toggleRemoveButtons();
                refreshTotals();
            }
        });

        document.addEventListener('click', () => document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden')));

        document.getElementById('rental-items')?.addEventListener('change', (e) => {
            const row = e.target.closest('.rental-item');
            if (!row) return;
            if (e.target.classList.contains('serial-check')) {
                const current = e.target;
                const otherSelected = new Set();
                document.querySelectorAll('.serial-check').forEach(chk => {
                    if (chk === current) return;
                    if (chk.checked) otherSelected.add(chk.value);
                });
                if (current.checked && otherSelected.has(current.value)) {
                    alert('Serial sudah dipilih di item lain.');
                    current.checked = false;
                }
                refreshTotals();
            }
        });

        document.getElementById('rental-items')?.addEventListener('input', (e) => {
            if (e.target.classList.contains('rental-price')) {
                refreshTotals();
            }
        });

        // Payments
        const paymentRows = document.getElementById('payment-rows');
        let paymentIndex = 0;
        function paymentOptionsHtml() {
            return '<option value="">{{ __('Pilih metode') }}</option>' + paymentMethods.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
        }
        function addPaymentRow(pref = {}) {
            const div = document.createElement('div');
            const idx = paymentIndex++;
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
        document.getElementById('add-payment')?.addEventListener('click', () => addPaymentRow());
        if (Array.isArray(editPayments) && editPayments.length > 0) {
            editPayments.forEach(p => addPaymentRow(p));
        } else {
            addPaymentRow();
        }

        // Customer toggle
        const customerSelect = document.getElementById('customer_id');
        const newCustomerFields = document.getElementById('new-customer-fields');
        const toggleCustomerFields = () => {
            const hasCustomer = !!(customerSelect && customerSelect.value);
            if (newCustomerFields) newCustomerFields.style.display = hasCustomer ? 'none' : '';
        };
        customerSelect?.addEventListener('change', toggleCustomerFields);
        toggleCustomerFields();

        async function loadFormDataForLocation() {
            const locationType = document.getElementById('location_type')?.value;
            const locationId = document.getElementById('location_id')?.value;
            if (!locationId) {
                paymentMethods = [];
                document.querySelectorAll('#payment-rows select[name*="payment_method_id"]').forEach(sel => {
                    sel.innerHTML = '<option value="">{{ __('Pilih metode') }}</option>';
                });
                return;
            }
            try {
                const url = new URL(appBaseUrl + formDataUrl, window.location.origin);
                url.searchParams.set('location_type', locationType);
                url.searchParams.set('location_id', locationId);
                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Fetch failed');
                const data = await res.json();
                paymentMethods = (data.payment_methods || []).map(m => ({ id: m.id, label: m.label }));
                document.querySelectorAll('#payment-rows select[name*="payment_method_id"]').forEach(sel => {
                    const oldVal = sel.value;
                    sel.innerHTML = paymentOptionsHtml();
                    if (oldVal && paymentMethods.some(m => m.id == oldVal)) sel.value = oldVal;
                });
            } catch (e) { console.error('loadFormDataForLocation failed', e); }
        }

        document.getElementById('location_type')?.addEventListener('change', function() {
            updateLocationSelect();
            loadFormDataForLocation();
            loadProductsForLocation();
            document.querySelectorAll('.rental-item').forEach(row => loadSerialsForRow(row));
        });
        document.getElementById('location_id')?.addEventListener('change', async function() {
            await loadFormDataForLocation();
            await loadProductsForLocation();
            document.querySelectorAll('.rental-item').forEach(row => loadSerialsForRow(row));
        });
        document.getElementById('rental_brand_filter')?.addEventListener('change', function() {
            const brandVal = this.value;
            const seriesSel = document.getElementById('rental_series_filter');
            if (seriesSel) {
                const series = [...new Set(products.filter(p => !brandVal || p.brand === brandVal).map(p => p.series).filter(Boolean))].sort();
                seriesSel.innerHTML = '<option value="">Semua Series</option>' + series.map(s => '<option value="' + s + '">' + s + '</option>').join('');
            }
            updateAllProductDropdowns();
        });
        document.getElementById('rental_series_filter')?.addEventListener('change', updateAllProductDropdowns);
        document.getElementById('pickup_date')?.addEventListener('change', refreshDays);
        document.getElementById('return_date')?.addEventListener('change', refreshDays);
        document.getElementById('tax_amount')?.addEventListener('input', refreshTotals);
        document.getElementById('penalty_amount')?.addEventListener('input', refreshTotals);

        function rebuildHiddenItems() {
            const container = document.getElementById('items-hidden');
            if (!container) return;
            container.innerHTML = '';
            let idx = 0;
            document.querySelectorAll('.rental-item').forEach(row => {
                const productId = row.querySelector('.product-id-input')?.value;
                const price = row.querySelector('.rental-price')?.value || '';
                const selectedSerials = Array.from(row.querySelectorAll('.serial-check'))
                    .filter(chk => chk.checked)
                    .map(chk => chk.value);
                selectedSerials.forEach(sn => {
                    const fields = `
                        <input type="hidden" name="items[${idx}][product_id]" value="${productId}">
                        <input type="hidden" name="items[${idx}][serial_number]" value="${sn}">
                        <input type="hidden" name="items[${idx}][rental_price]" value="${price}">
                    `;
                    container.insertAdjacentHTML('beforeend', fields);
                    idx++;
                });
            });
        }

        document.getElementById('rental-form')?.addEventListener('submit', () => {
            rebuildHiddenItems();
            document.querySelectorAll('.rental-item .product-trigger').forEach(el => el.setAttribute('disabled', 'disabled'));
            document.querySelectorAll('.rental-item .rental-price').forEach(el => el.setAttribute('disabled', 'disabled'));
        });

        async function initRentalEdit() {
            updateLocationSelect();
            await loadProductsForLocation();
            const body = document.getElementById('rental-items');
            body.innerHTML = '';
            const groups = groupItems(editItems);
            if (groups.length === 0) {
                createItemRow(0);
            } else {
                for (let i = 0; i < groups.length; i++) {
                    const g = groups[i];
                    const row = createItemRow(i);
                    const idInput = row.querySelector('.product-id-input');
                    const labelEl = row.querySelector('.product-label');
                    if (idInput) idInput.value = String(g.product_id);
                    const p = products.find(x => String(x.id) === String(g.product_id));
                    if (labelEl && p) {
                        labelEl.textContent = (p.sku || '') + ' - ' + (p.brand || '') + ' ' + (p.series || '');
                        labelEl.classList.remove('text-slate-500');
                    }
                    await loadSerialsForRow(row);
                    const priceInput = row.querySelector('.rental-price');
                    if (priceInput) priceInput.value = String(g.rental_price);
                    const serialSet = new Set((g.serials || []).map(String));
                    row.querySelectorAll('.serial-check').forEach(chk => {
                        if (serialSet.has(chk.value)) chk.checked = true;
                    });
                }
            }
            toggleRemoveButtons();
            refreshDays();
            refreshTotals();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initRentalEdit);
        } else {
            initRentalEdit();
        }
    </script>
</x-app-layout>
