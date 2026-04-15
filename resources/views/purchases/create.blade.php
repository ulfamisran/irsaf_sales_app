<x-app-layout>
    @php
        $isEdit = $isEdit ?? false;
        $editPurchase = $editPurchase ?? null;
    @endphp
    <x-slot name="title">{{ $isEdit ? __('Ubah Pembelian') : __('Catat Pembelian') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $isEdit ? __('Ubah Pembelian') : __('Catat Pembelian') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ $isEdit ? route('purchases.update', $editPurchase) : route('purchases.store') }}" id="purchase-form">
                        @csrf
                        @if ($isEdit)
                            @method('PUT')
                            <input type="hidden" name="location_type" value="{{ $editPurchase->warehouse_id ? 'warehouse' : 'branch' }}">
                            @if ($editPurchase->warehouse_id)
                                <input type="hidden" name="warehouse_id" value="{{ $editPurchase->warehouse_id }}">
                            @else
                                <input type="hidden" name="branch_id" value="{{ $editPurchase->branch_id }}">
                            @endif
                            <input type="hidden" name="jenis_pembelian" value="{{ $editPurchase->jenis_pembelian }}">
                            @if ($editPurchase->service_id)
                                <input type="hidden" name="service_id" value="{{ $editPurchase->service_id }}">
                            @endif
                        @endif
                        <input type="hidden" name="confirm_reuse_sold_serials" id="confirm_reuse_sold_serials" value="{{ old('confirm_reuse_sold_serials') ? 1 : 0 }}">
                        <div class="space-y-6">
                            @if ($isEdit)
                                <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-4 text-sm text-slate-700">
                                    <p><span class="font-medium text-slate-800">{{ __('Lokasi') }}:</span>
                                        @if ($editPurchase->warehouse_id)
                                            {{ __('Gudang') }} — {{ $editPurchase->warehouse?->name ?? '—' }}
                                        @else
                                            {{ __('Cabang') }} — {{ $editPurchase->branch?->name ?? '—' }}
                                        @endif
                                    </p>
                                    <p class="mt-1"><span class="font-medium text-slate-800">{{ __('Jenis pembelian') }}:</span> {{ $editPurchase->jenis_pembelian }}</p>
                                    @if ($editPurchase->service_id && $editPurchase->service)
                                        <p class="mt-1"><span class="font-medium text-slate-800">{{ __('Referensi service') }}:</span> {{ $editPurchase->service->invoice_number }}</p>
                                    @endif
                                    <p class="mt-2 text-xs text-amber-800">{{ __('Lokasi, jenis, dan referensi service tidak dapat diubah dari form ini. Hanya barang, distributor, tanggal, dan invoice yang dapat disesuaikan.') }}</p>
                                </div>
                            @endif
                            {{-- Lokasi --}}
                            @unless ($isEdit)
                            <div x-data="{ locationType: '{{ old('location_type', $defaultLocationType) }}' }" x-init="$nextTick(() => window.loadPurchaseDistributors?.())">
                                <x-input-label :value="__('Lokasi Pembelian (Gudang/Cabang)')" class="font-semibold" />
                                <div class="mt-2 flex gap-6">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="location_type" value="warehouse" x-model="locationType"
                                            {{ $isWarehouseUser && !$isBranchUser ? 'checked' : '' }}
                                            {{ $isBranchUser ? 'disabled' : '' }}
                                            class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700">Gudang</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="location_type" value="branch" x-model="locationType"
                                            {{ $isBranchUser ? 'checked' : '' }}
                                            {{ $isWarehouseUser && !$isBranchUser ? 'disabled' : '' }}
                                            class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700">Cabang</span>
                                    </label>
                                </div>
                                <div x-show="locationType === 'warehouse'" x-cloak x-transition class="mt-3">
                                    <x-input-label for="warehouse_id" :value="__('Gudang')" />
                                    <select id="warehouse_id" name="warehouse_id"
                                        class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :required="locationType === 'warehouse'" :disabled="locationType !== 'warehouse'"
                                        @change="window.loadPurchaseDistributors?.()">
                                        <option value="">Pilih Gudang</option>
                                        @foreach ($warehouses as $w)
                                            <option value="{{ $w->id }}" {{ old('warehouse_id', $isWarehouseUser ? $defaultLocationId : null) == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div x-show="locationType === 'branch'" x-cloak x-transition class="mt-3">
                                    <x-input-label for="branch_id" :value="__('Cabang')" />
                                    <select id="branch_id" name="branch_id"
                                        class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :required="locationType === 'branch'" :disabled="locationType !== 'branch'"
                                        @change="window.loadPurchaseDistributors?.()">
                                        <option value="">Pilih Cabang</option>
                                        @foreach ($branches as $b)
                                            <option value="{{ $b->id }}" {{ old('branch_id', $isBranchUser ? $defaultLocationId : null) == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @endunless

                            {{-- Jenis pembelian & referensi service --}}
                            @unless ($isEdit)
                            <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-4 space-y-4">
                                <div>
                                    <x-input-label for="jenis_pembelian" :value="__('Jenis Pembelian')" />
                                    <select id="jenis_pembelian" name="jenis_pembelian" required
                                        class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="{{ \App\Models\Purchase::JENIS_PEMBELIAN_UNIT }}" {{ old('jenis_pembelian', \App\Models\Purchase::JENIS_PEMBELIAN_UNIT) === \App\Models\Purchase::JENIS_PEMBELIAN_UNIT ? 'selected' : '' }}>
                                            {{ \App\Models\Purchase::JENIS_PEMBELIAN_UNIT }}
                                        </option>
                                        <option value="{{ \App\Models\Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE }}" {{ old('jenis_pembelian') === \App\Models\Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE ? 'selected' : '' }}>
                                            {{ \App\Models\Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE }}
                                        </option>
                                        <option value="{{ \App\Models\Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE_LAPTOP_TOKO }}" {{ old('jenis_pembelian') === \App\Models\Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE_LAPTOP_TOKO ? 'selected' : '' }}>
                                            {{ \App\Models\Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE_LAPTOP_TOKO }}
                                        </option>
                                    </select>
                                    <p class="mt-1 text-xs text-slate-600">{{ __('Untuk servis: buat invoice service (Open), lalu catat sparepart di sini dengan jenis ini dan pilih nomor invoice service sebagai referensi. Stok masuk ke cabang yang sama.') }}</p>
                                    <x-input-error :messages="$errors->get('jenis_pembelian')" class="mt-2" />
                                </div>
                                <div id="service-invoice-row" class="{{ old('jenis_pembelian') === \App\Models\Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE ? '' : 'hidden' }}">
                                    <x-input-label for="service_id" :value="__('Referensi invoice service (Open)')" />
                                    <select id="service_id" name="service_id"
                                        data-initial-value="{{ old('service_id') }}"
                                        class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ __('Pilih invoice service') }}</option>
                                        @foreach ($openServicesInitial ?? [] as $svc)
                                            <option value="{{ $svc->id }}" @selected(old('service_id') == $svc->id)>
                                                {{ $svc->invoice_number }} — {{ $svc->laptop_type }}{{ $svc->customer ? ' ('.$svc->customer->name.')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                                </div>
                            </div>
                            @endunless

                            {{-- No. Invoice --}}
                            <div>
                                <x-input-label for="invoice_number" :value="__('No. Invoice Pembelian')" />
                                <x-text-input id="invoice_number" name="invoice_number" type="text" :value="old('invoice_number', $isEdit ? $editPurchase->invoice_number : null)" :placeholder="$isEdit ? '' : 'Opsional - kosongkan untuk auto-generate (PBL-YYYYMMDD-0001)'" />
                                <p class="mt-1 text-xs text-slate-500">{{ $isEdit ? __('Nomor invoice saat ini. Kosongkan untuk mempertahankan nomor yang sama.') : __('Kosongkan untuk generate otomatis.') }}</p>
                            </div>

                            {{-- Distributor --}}
                            <div>
                                <x-input-label for="distributor_id" :value="__('Distributor')" />
                                <select id="distributor_id" name="distributor_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="">Pilih Distributor</option>
                                    @foreach ($distributors as $d)
                                        <option value="{{ $d->id }}" {{ (int) old('distributor_id', $isEdit ? $editPurchase->distributor_id : 0) === (int) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Tanggal, Termin, Jatuh Tempo --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="purchase_date" :value="__('Tanggal Pembelian')" />
                                    <x-text-input id="purchase_date" class="block mt-1 w-full" type="date" name="purchase_date" :value="old('purchase_date', $isEdit ? $editPurchase->purchase_date->format('Y-m-d') : date('Y-m-d'))" required />
                                </div>
                                <div>
                                    <x-input-label for="termin" :value="__('Termin')" />
                                    <x-text-input id="termin" class="block mt-1 w-full" type="text" name="termin" :value="old('termin', $isEdit ? $editPurchase->termin : null)" placeholder="NET 30, Tunai, dll" />
                                </div>
                                <div>
                                    <x-input-label for="due_date" :value="__('Jatuh Tempo')" />
                                    <x-text-input id="due_date" class="block mt-1 w-full" type="date" name="due_date" :value="old('due_date', $isEdit && $editPurchase->due_date ? $editPurchase->due_date->format('Y-m-d') : null)" />
                                </div>
                            </div>

                            {{-- Deskripsi --}}
                            <div>
                                <x-input-label for="description" :value="__('Deskripsi Transaksi')" />
                                <textarea id="description" name="description" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2" placeholder="Catatan pembelian...">{{ old('description', $isEdit ? $editPurchase->description : null) }}</textarea>
                            </div>

                            {{-- Barang --}}
                            <div>
                                <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
                                    <div class="flex-1 min-w-[200px] space-y-2">
                                        <x-input-label :value="__('Barang yang Dibeli')" class="font-semibold" />
                                        <p class="text-xs text-slate-500">{{ __('Pilih lokasi & kategori, lalu pilih produk. Filter merk & series untuk mempermudah pencarian.') }}</p>
                                        <div class="flex flex-wrap gap-3 mt-2">
                                            <div class="min-w-[160px]">
                                                <x-input-label for="purchase_category_id" :value="__('Kategori Barang')" class="text-xs" />
                                                <select id="purchase_category_id" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    <option value="">{{ __('Semua Kategori') }}</option>
                                                    @foreach ($categories as $cat)
                                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" id="refresh-products-btn" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-100 text-indigo-800 border border-indigo-200 text-sm font-medium hover:bg-indigo-200" title="{{ __('Muat ulang daftar produk tanpa kehilangan data yang sudah diinput') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            {{ __('Muat Ulang Produk') }}
                                        </button>
                                        <a href="{{ route('products.create') }}" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-100 text-amber-800 border border-amber-200 text-sm font-medium hover:bg-amber-200" title="{{ __('Buka di tab baru. Setelah menambah produk, klik Muat Ulang Produk.') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            {{ __('Tambah Produk') }}
                                        </a>
                                    </div>
                                </div>
                                <div id="purchase-items" class="space-y-4">
                                    @if ($isEdit && $editPurchase?->details?->isNotEmpty())
                                        @foreach ($editPurchase->details as $idx => $detail)
                                            @include('purchases._item_row', ['idx' => $idx, 'detail' => $detail, 'hideRemove' => $editPurchase->details->count() <= 1])
                                        @endforeach
                                    @else
                                        @include('purchases._item_row', ['idx' => 0, 'detail' => null, 'hideRemove' => true])
                                    @endif
                                </div>
                                <button type="button" id="add-item" class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">+ Tambah Barang</button>
                            </div>

                            {{-- Total Pembelian --}}
                            <div class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4">
                                <p class="text-sm font-semibold text-slate-800">{{ __('Total Pembelian') }}</p>
                                <p class="text-2xl font-bold text-indigo-600 mt-1" id="purchase-total-text">0</p>
                            </div>

                            @unless ($isEdit)
                            {{-- Pembayaran (opsional - bisa kosong untuk termin) --}}
                            <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                <x-input-label :value="__('Pembayaran (Opsional)')" class="font-semibold" />
                                <p class="mt-1 mb-3 text-xs text-slate-500">{{ __('Bisa langsung lunasi atau bayar sebagian. Kosongkan jika akan bayar nanti sesuai termin. Setiap pembayaran tercatat dari sumber kas yang dipilih.') }}</p>
                                <div id="payment-rows" class="space-y-2"></div>
                                <button type="button" id="add-payment" class="mt-2 inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ Tambah Pembayaran</button>
                                <div class="mt-3 space-y-1 text-sm text-slate-700">
                                    <div class="flex justify-between">
                                        <span>{{ __('Total Pembelian') }}:</span>
                                        <span id="payment-section-total-pembelian" class="font-medium">0</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>{{ __('Total Pembayaran') }}:</span>
                                        <span id="payment-sum-text" class="font-semibold">0</span>
                                    </div>
                                    <div class="flex justify-between pt-1 border-t border-slate-200">
                                        <span class="font-medium">{{ __('Selisih') }}:</span>
                                        <span id="payment-diff-text" class="font-bold">0</span>
                                    </div>
                                </div>
                            </div>
                            @endunless

                            <div class="flex gap-4">
                                <x-primary-button>{{ $isEdit ? __('Simpan Perubahan') : __('Simpan Pembelian') }}</x-primary-button>
                                <a href="{{ $isEdit ? route('purchases.show', $editPurchase) : route('purchases.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    @php
        $serviceInvoicePlaceholder = __('Pilih invoice service');
        $paymentMethodsJson = $paymentMethods->map(fn ($m) => [
            'id' => $m->id,
            'label' => $m->display_label,
            'saldo' => (float) ($saldoByPaymentMethod[$m->id] ?? 0),
        ])->values();
        $productsJson = $products->map(fn ($p) => ['id' => $p->id, 'sku' => $p->sku ?? '', 'brand' => $p->brand ?? '', 'series' => $p->series ?? '', 'purchase_price' => (float) ($p->purchase_price ?? 0), 'selling_price' => (float) ($p->selling_price ?? 0)])->values();
    @endphp
    <script>
        let paymentMethods = @json($paymentMethodsJson);
        let products = @json($productsJson);
        const formDataUrl = @json(route('purchases.form-data', [], false));
        const checkReusableSerialsUrl = @json(route('purchases.check-reusable-serials', [], false));
        const purchaseSerialSearchPath = @json(route('purchases.search-unit-by-serial', [], false));
        const baseUrl = '{{ url("") }}';
        const purchaseSerialSearchUrl = baseUrl + purchaseSerialSearchPath;
        const JENIS_SPAREPART_SERVICE = @json(\App\Models\Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE);
        const JENIS_SPAREPART_SERVICE_LAPTOP_TOKO = @json(\App\Models\Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE_LAPTOP_TOKO);
        const serviceInvoicePlaceholder = @json($serviceInvoicePlaceholder);
        const isEdit = @json($isEdit);
        let itemIdx = {{ ($isEdit && $editPurchase) ? $editPurchase->details->count() : 1 }};

        function productOptionHtml(p) {
            const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            const sku = esc(p.sku); const brand = esc(p.brand); const series = esc(p.series);
            const price = Number(p.purchase_price || 0).toLocaleString('id-ID');
            const sell = Number(p.selling_price || 0).toLocaleString('id-ID');
            return '<div class="product-option px-3 py-2 cursor-pointer hover:bg-indigo-50 text-sm" data-id="' + p.id + '" data-brand="' + esc(p.brand) + '" data-series="' + esc(p.series) + '" data-sku="' + esc(p.sku) + '" data-price="' + (p.purchase_price || 0) + '">' +
                '<span class="text-xs text-slate-500">' + sku + '</span> <span class="text-slate-400">-</span> <span class="text-slate-800">' + brand + ' ' + series + '</span> <span class="text-emerald-600 font-medium ml-1">Beli ' + price + '</span> <span class="text-indigo-600 text-xs ml-1">Jual ' + sell + '</span></div>';
        }

        function syncPurchaseJenisServiceUi() {
            if (isEdit) return;
            const locType = document.querySelector('input[name="location_type"]:checked')?.value;
            const jenisSel = document.getElementById('jenis_pembelian');
            const serviceRow = document.getElementById('service-invoice-row');
            const serviceSel = document.getElementById('service_id');
            if (!jenisSel || !serviceRow || !serviceSel) return;
            if (locType === 'warehouse') {
                if (jenisSel.value === JENIS_SPAREPART_SERVICE || jenisSel.value === JENIS_SPAREPART_SERVICE_LAPTOP_TOKO) {
                    jenisSel.value = @json(\App\Models\Purchase::JENIS_PEMBELIAN_UNIT);
                }
                serviceRow.classList.add('hidden');
                serviceSel.value = '';
                serviceSel.removeAttribute('required');
                return;
            }
            const isSparepartSvc = jenisSel.value === JENIS_SPAREPART_SERVICE;
            serviceRow.classList.toggle('hidden', !isSparepartSvc);
            if (isSparepartSvc) {
                serviceSel.setAttribute('required', 'required');
            } else {
                serviceSel.removeAttribute('required');
                serviceSel.value = '';
            }
        }

        function fillServiceInvoiceOptions(openServices) {
            const serviceSel = document.getElementById('service_id');
            if (!serviceSel) return;
            const initial = serviceSel.getAttribute('data-initial-value') || '';
            const prev = serviceSel.value || initial;
            const list = Array.isArray(openServices) ? openServices : [];
            const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            serviceSel.innerHTML = '<option value="">' + esc(serviceInvoicePlaceholder) + '</option>' +
                list.map(s => '<option value="' + s.id + '">' + esc(s.label || s.invoice_number || '') + '</option>').join('');
            if (prev && list.some(s => String(s.id) === String(prev))) {
                serviceSel.value = String(prev);
            }
        }
        function getBrands() { const s = new Set(); products.forEach(p => { if (p.brand) s.add(p.brand); }); return Array.from(s).sort(); }
        function getSeries(brandVal) { const s = new Set(); products.forEach(p => { if ((!brandVal || p.brand === brandVal) && p.series) s.add(p.series); }); return Array.from(s).sort(); }
        function populateProductDropdown(row) {
            const listEl = row.querySelector('.product-dropdown-list'); if (!listEl) return;
            listEl.innerHTML = products.map(p => productOptionHtml(p)).join('');
            const brandSel = row.querySelector('.brand-filter');
            if (brandSel) brandSel.innerHTML = '<option value="">Semua Merk</option>' + getBrands().map(b => '<option value="' + b + '">' + b + '</option>').join('');
            const seriesSel = row.querySelector('.series-filter');
            if (seriesSel) { const seriesList = getSeries(brandSel?.value || ''); seriesSel.innerHTML = '<option value="">Semua Series</option>' + seriesList.map(s => '<option value="' + s + '">' + s + '</option>').join(''); }
            filterProductOptions(row);
            attachProductHandlers(row);
        }
        function filterProductOptions(row) {
            const brandVal = row.querySelector('.brand-filter')?.value || '';
            const seriesVal = row.querySelector('.series-filter')?.value || '';
            const searchVal = (row.querySelector('.product-search')?.value || '').trim().toLowerCase();
            const opts = row.querySelectorAll('.product-option');
            const listEl = row.querySelector('.product-dropdown-list');
            const emptyEl = row.querySelector('.product-dropdown-empty');
            let visible = 0;
            opts.forEach(o => {
                const matchBrand = !brandVal || (o.getAttribute('data-brand') || '') === brandVal;
                const matchSeries = !seriesVal || (o.getAttribute('data-series') || '') === seriesVal;
                const searchStr = ((o.getAttribute('data-sku') || '') + ' ' + (o.getAttribute('data-brand') || '') + ' ' + (o.getAttribute('data-series') || '')).toLowerCase();
                const matchSearch = !searchVal || searchStr.includes(searchVal);
                const show = matchBrand && matchSeries && matchSearch;
                o.classList.toggle('hidden', !show); if (show) visible++;
            });
            if (listEl) listEl.classList.toggle('hidden', visible === 0);
            if (emptyEl) emptyEl.classList.toggle('hidden', visible > 0);
        }
        function attachProductHandlers(row) {
            const trigger = row.querySelector('.product-select-trigger'), dropdown = row.querySelector('.product-dropdown'), searchInput = row.querySelector('.product-search');
            const productIdInput = row.querySelector('.product-id-input'), brandEl = row.querySelector('.brand-filter'), seriesEl = row.querySelector('.series-filter');
            if (!trigger || !dropdown) return;
            dropdown.onclick = e => e.stopPropagation();
            trigger.onclick = (e) => {
                e.stopPropagation();
                document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden'));
                const wasHidden = dropdown.classList.contains('hidden');
                dropdown.classList.toggle('hidden');
                if (wasHidden && searchInput) { searchInput.focus(); searchInput.value = ''; filterProductOptions(row); }
            };
            if (searchInput) { searchInput.oninput = () => filterProductOptions(row); searchInput.onkeydown = e => { if (e.key === 'Escape') dropdown.classList.add('hidden'); }; }
            if (brandEl) brandEl.onchange = () => { const v = brandEl.value; seriesEl.innerHTML = '<option value="">Semua Series</option>' + getSeries(v).map(s => '<option value="' + s + '">' + s + '</option>').join(''); filterProductOptions(row); };
            if (seriesEl) seriesEl.onchange = () => filterProductOptions(row);
            row.querySelectorAll('.product-option').forEach(opt => {
                opt.onclick = (e) => {
                    e.stopPropagation();
                    const id = opt.getAttribute('data-id'), price = opt.getAttribute('data-price');
                    if (productIdInput) productIdInput.value = id;
                    const label = row.querySelector('.product-select-label');
                    if (label) { const p = products.find(x => String(x.id) === String(id)); if (p) label.innerHTML = (p.sku || '') + ' - ' + (p.brand || '') + ' ' + (p.series || ''); label.classList.remove('text-slate-500'); }
                    const priceInp = row.querySelector('.item-price'); if (priceInp && price) { priceInp.value = new Intl.NumberFormat('id-ID').format(price); updateAllTotals(); }
                    dropdown.classList.add('hidden');
                };
            });
        }
        document.addEventListener('click', () => document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden')));

        function getPurchaseLocationHint() {
            const hiddenLt = document.querySelector('#purchase-form input[name="location_type"][type="hidden"]');
            if (hiddenLt && hiddenLt.value) {
                const lt = hiddenLt.value;
                if (lt === 'warehouse') {
                    const w = document.querySelector('#purchase-form input[name="warehouse_id"][type="hidden"]')?.value;
                    return w ? ('{{ __("Gudang") }} (ID ' + w + ')') : '{{ __("Gudang") }}';
                }
                const b = document.querySelector('#purchase-form input[name="branch_id"][type="hidden"]')?.value;
                return b ? ('{{ __("Cabang") }} (ID ' + b + ')') : '{{ __("Cabang") }}';
            }
            const lt = document.querySelector('input[name="location_type"]:checked')?.value;
            if (lt === 'warehouse') {
                const sel = document.getElementById('warehouse_id');
                const t = sel?.options?.[sel.selectedIndex]?.text?.trim() || '';
                return t ? ('{{ __("Gudang") }}: ' + t) : '{{ __("Gudang (pilih lokasi)") }}';
            }
            if (lt === 'branch') {
                const sel = document.getElementById('branch_id');
                const t = sel?.options?.[sel.selectedIndex]?.text?.trim() || '';
                return t ? ('{{ __("Cabang") }}: ' + t) : '{{ __("Cabang (pilih lokasi)") }}';
            }
            return '-';
        }

        function clearPurchaseSerialDropdown(row) {
            const dd = row.querySelector('.purchase-serial-dropdown');
            const si = row.querySelector('.purchase-serial-search');
            if (dd) { dd.innerHTML = ''; dd.classList.add('hidden'); }
            if (si) si.value = '';
        }

        function applyPurchaseUnitSelection(row, item) {
            const pid = item.product_id;
            const purchasePrice = Number(item.purchase_price || 0);
            const hpp = item.harga_hpp != null ? Number(item.harga_hpp) : null;
            const priceToUse = (hpp != null && !Number.isNaN(hpp) && hpp > 0) ? hpp : purchasePrice;
            const prod = { id: pid, sku: item.sku || '', brand: item.brand || '', series: item.series || '', purchase_price: purchasePrice };
            if (!products.some(p => String(p.id) === String(pid))) products.push(prod);
            populateProductDropdown(row);
            const brandEl = row.querySelector('.brand-filter');
            const seriesEl = row.querySelector('.series-filter');
            if (brandEl) brandEl.value = '';
            if (seriesEl) seriesEl.innerHTML = '<option value="">{{ __("Semua Series") }}</option>';
            filterProductOptions(row);
            const productIdInput = row.querySelector('.product-id-input');
            if (productIdInput) productIdInput.value = String(pid);
            const label = row.querySelector('.product-select-label');
            if (label) {
                label.textContent = (item.sku || '') + ' - ' + (item.brand || '') + ' ' + (item.series || '');
                label.classList.remove('text-slate-500');
            }
            const priceInp = row.querySelector('.item-price');
            if (priceInp) {
                priceInp.value = new Intl.NumberFormat('id-ID').format(Math.round(priceToUse));
                updateAllTotals();
            }
            const ta = row.querySelector('.item-serials');
            if (ta) ta.value = item.serial_number || '';
            const qtyInp = row.querySelector('.item-qty');
            if (qtyInp) qtyInp.value = '1';
            clearPurchaseSerialDropdown(row);
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
        }

        function attachPurchaseSerialSearch(row) {
            if (row.dataset.purchaseSerialSearchBound === '1') return;
            row.dataset.purchaseSerialSearchBound = '1';
            const input = row.querySelector('.purchase-serial-search');
            const dropdown = row.querySelector('.purchase-serial-dropdown');
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
                        const url = new URL(purchaseSerialSearchUrl, window.location.origin);
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
                                btn.className = 'w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 border-b border-slate-100 last:border-0';
                                const st = item.status || '';
                                const loc = item.location_label || '';
                                btn.textContent = (item.serial_number || '') + ' — ' + [item.brand, item.series].filter(Boolean).join(' ') + (loc ? ' · ' + loc : '') + (st ? ' (' + st + ')' : '');
                                btn.addEventListener('mousedown', async (ev) => {
                                    ev.preventDefault();
                                    if (String(item.status) === 'sold') {
                                        const locPurchase = getPurchaseLocationHint();
                                        const r = await Swal.fire({
                                            icon: 'warning',
                                            title: @json(__('Serial pernah terjual (SOLD)')),
                                            html: @json(__('Unit ini sudah pernah tercatat terjual. Data unit dan produk akan diperbarui sesuai isian pembelian ini, dan <b>lokasi unit akan dipindahkan</b> ke lokasi pembelian:')) + '<br><br><b>' + locPurchase + '</b>',
                                            showCancelButton: true,
                                            confirmButtonText: @json(__('Lanjut isi baris')),
                                            cancelButtonText: @json(__('Batal')),
                                        });
                                        if (!r.isConfirmed) return;
                                    }
                                    applyPurchaseUnitSelection(row, item);
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

        function paymentMethodOptionLabel(method) {
            const saldo = Number(method?.saldo || 0);
            return `${method.label} (Saldo: Rp ${saldo.toLocaleString('id-ID')})`;
        }

        function paymentMethodOptionsHtml(selectedId = '') {
            return '<option value="">Pilih Kas</option>' + paymentMethods.map(m => {
                const selected = String(selectedId) === String(m.id) ? 'selected' : '';
                return `<option value="${m.id}" ${selected}>${paymentMethodOptionLabel(m)}</option>`;
            }).join('');
        }

        function refreshPaymentMethodSelects() {
            document.querySelectorAll('#payment-rows select[name*="[payment_method_id]"]').forEach(sel => {
                const oldVal = sel.value;
                sel.innerHTML = paymentMethodOptionsHtml(oldVal);
            });
        }

        function addPaymentRow(idx, pref = {}) {
            const html = `<div class="payment-row flex flex-wrap gap-2 items-end">
                <div class="flex-1 min-w-[200px]">
                    <select name="payments[${idx}][payment_method_id]" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        ${paymentMethodOptionsHtml(pref.payment_method_id || '')}
                    </select>
                </div>
                <div class="w-40">
                    <input type="text" name="payments[${idx}][amount]" data-rupiah="true" class="payment-amount block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Nominal" value="${pref.amount || ''}">
                </div>
                <button type="button" class="remove-payment px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm">-</button>
            </div>`;
            document.getElementById('payment-rows').insertAdjacentHTML('beforeend', html);
            const row = document.getElementById('payment-rows').lastElementChild;
            row.querySelector('.remove-payment')?.addEventListener('click', () => { row.remove(); updateAllTotals(); });
            if (document.querySelectorAll('[data-rupiah="true"]').length) initRupiahInputs();
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            row.querySelector('.payment-amount')?.addEventListener('input', updateAllTotals);
        }

        function getPurchaseTotal() {
            let total = 0;
            document.querySelectorAll('.purchase-item').forEach(row => {
                const qtyInp = row.querySelector('.item-qty');
                const priceInp = row.querySelector('.item-price');
                const qty = parseInt(qtyInp?.value || '0', 10) || 0;
                const price = parseInt((priceInp?.value || '').replace(/\D/g, ''), 10) || 0;
                total += qty * price;
            });
            return total;
        }

        function updateAllTotals() {
            const purchaseTotal = getPurchaseTotal();
            let paymentSum = 0;
            document.querySelectorAll('.payment-amount').forEach(inp => {
                const v = (inp.value || '').replace(/\D/g, '');
                if (v) paymentSum += parseInt(v, 10);
            });
            const selisih = purchaseTotal - paymentSum;
            const fmt = (n) => new Intl.NumberFormat('id-ID').format(n);

            const totalEl = document.getElementById('purchase-total-text');
            const sectionTotalEl = document.getElementById('payment-section-total-pembelian');
            const sumEl = document.getElementById('payment-sum-text');
            const diffEl = document.getElementById('payment-diff-text');

            if (totalEl) totalEl.textContent = fmt(purchaseTotal);
            if (sectionTotalEl) sectionTotalEl.textContent = fmt(purchaseTotal);
            if (sumEl) sumEl.textContent = fmt(paymentSum);
            if (diffEl) {
                diffEl.textContent = fmt(selisih);
                diffEl.className = 'font-bold ' + (selisih > 0 ? 'text-amber-600' : selisih < 0 ? 'text-emerald-600' : 'text-slate-700');
            }
        }

        let paymentIdx = 0;

        document.getElementById('add-item')?.addEventListener('click', function() {
            const tpl = document.querySelector('.purchase-item').cloneNode(true);
            tpl.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.name) el.name = el.name.replace(/items\[\d+\]/, 'items[' + itemIdx + ']');
                if (el.type !== 'hidden') el.value = '';
                if (el.classList.contains('item-qty')) el.value = '1';
                if (el.classList.contains('product-id-input')) el.value = '';
            });
            tpl.querySelector('.product-select-label')?.classList.add('text-slate-500');
            tpl.querySelector('.product-select-label').textContent = 'Pilih Produk';
            tpl.querySelector('.brand-filter').innerHTML = '<option value="">Semua Merk</option>';
            tpl.querySelector('.series-filter').innerHTML = '<option value="">Semua Series</option>';
            tpl.querySelector('.product-dropdown-list').innerHTML = '';
            tpl.removeAttribute('data-purchase-serial-search-bound');
            const ps = tpl.querySelector('.purchase-serial-search');
            const pd = tpl.querySelector('.purchase-serial-dropdown');
            if (ps) ps.value = '';
            if (pd) { pd.innerHTML = ''; pd.classList.add('hidden'); }
            tpl.querySelector('.remove-item').style.display = '';
            tpl.querySelector('.remove-item').addEventListener('click', function onRemove() {
                if (document.querySelectorAll('.purchase-item').length > 1) {
                    tpl.remove();
                    updateAllTotals();
                }
            });
            document.getElementById('purchase-items').appendChild(tpl);
            populateProductDropdown(tpl);
            attachPurchaseSerialSearch(tpl);
            attachItemTotalListeners(tpl);
            itemIdx++;
            if (document.querySelectorAll('[data-rupiah="true"]').length) initRupiahInputs();
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            updateAllTotals();
        });

        function attachItemTotalListeners(row) {
            row.querySelector('.item-qty')?.addEventListener('input', updateAllTotals);
            row.querySelector('.item-qty')?.addEventListener('change', updateAllTotals);
            row.querySelector('.item-price')?.addEventListener('input', updateAllTotals);
            row.querySelector('.item-price')?.addEventListener('blur', updateAllTotals);
        }


        document.querySelectorAll('.purchase-item .remove-item').forEach(btn => {
            btn.addEventListener('click', function() {
                if (document.querySelectorAll('.purchase-item').length > 1) {
                    this.closest('.purchase-item')?.remove();
                    updateAllTotals();
                }
            });
        });

        document.getElementById('add-payment')?.addEventListener('click', function() {
            addPaymentRow(paymentIdx++, {});
        });

        document.querySelectorAll('.purchase-item').forEach(row => {
            populateProductDropdown(row);
            attachPurchaseSerialSearch(row);
            attachItemTotalListeners(row);
        });
        updateAllTotals();

        function initRupiahInputs() {
            document.querySelectorAll('[data-rupiah="true"]').forEach(inp => {
                if (inp.dataset.rupiahInit) return;
                inp.dataset.rupiahInit = '1';
                inp.addEventListener('blur', function() {
                    const v = this.value.replace(/\D/g, '');
                    if (v) this.value = new Intl.NumberFormat('id-ID').format(v);
                });
                inp.addEventListener('focus', function() {
                    this.value = this.value.replace(/\D/g, '');
                });
            });
        }
        initRupiahInputs();

        document.getElementById('purchase-form')?.addEventListener('submit', function(e) {
            document.querySelectorAll('.item-serials').forEach(ta => {
                const names = ta.name;
                if (names && names.includes('serial_numbers_text')) {
                    const baseName = names.replace('serial_numbers_text', 'serial_numbers');
                    const lines = (ta.value || '').split(/[\r\n]+/).map(s => s.trim()).filter(Boolean);
                    ta.removeAttribute('name');
                    lines.forEach((line, i) => {
                        const hid = document.createElement('input');
                        hid.type = 'hidden';
                        hid.name = baseName + '[' + i + ']';
                        hid.value = line;
                        ta.parentNode.appendChild(hid);
                    });
                }
            });
        });

        if (!isEdit) {
            const oldPayments = @json(old('payments', []));
            const hasValidOld = Array.isArray(oldPayments) && oldPayments.length > 0 && oldPayments.some(p => p && (p.payment_method_id || (p.amount && parseInt(String(p.amount).replace(/\D/g,''), 10) > 0)));
            if (hasValidOld) {
                oldPayments.forEach(p => addPaymentRow(paymentIdx++, p));
            } else {
                addPaymentRow(paymentIdx++, {});
            }
            updateAllTotals();
        }

        window.loadPurchaseDistributors = async function() {
            let locType = document.querySelector('#purchase-form input[name="location_type"][type="hidden"]')?.value;
            let locId = null;
            if (locType === 'warehouse') {
                locId = document.querySelector('#purchase-form input[name="warehouse_id"][type="hidden"]')?.value;
            } else if (locType === 'branch') {
                locId = document.querySelector('#purchase-form input[name="branch_id"][type="hidden"]')?.value;
            }
            if (!locType || !locId) {
                locType = document.querySelector('input[name="location_type"]:checked')?.value;
                locId = locType === 'warehouse' ? document.getElementById('warehouse_id')?.value : (locType === 'branch' ? document.getElementById('branch_id')?.value : null);
            }
            if (!locType || !locId) {
                document.getElementById('distributor_id').innerHTML = '<option value="">Pilih lokasi terlebih dahulu</option>';
                document.getElementById('distributor_id').disabled = true;
                window.loadPurchaseProducts?.();
                fillServiceInvoiceOptions([]);
                syncPurchaseJenisServiceUi();
                return;
            }
            try {
                const catId = document.getElementById('purchase_category_id')?.value || '';
                const url = baseUrl + formDataUrl + '?location_type=' + encodeURIComponent(locType) + '&location_id=' + encodeURIComponent(locId) + (catId ? '&category_id=' + encodeURIComponent(catId) : '');
                const r = await fetch(url);
                const data = await r.json();
                const sel = document.getElementById('distributor_id');
                sel.disabled = false;
                sel.innerHTML = '<option value="">Pilih Distributor</option>' + (data.distributors || []).map(d => '<option value="' + d.id + '">' + (d.name || '') + '</option>').join('');
                if (Array.isArray(data.payment_methods)) {
                    paymentMethods = data.payment_methods;
                    refreshPaymentMethodSelects();
                }
                if (data.products) {
                    products = data.products;
                    repopulateProductDropdowns();
                }
                if (data.open_services) {
                    fillServiceInvoiceOptions(data.open_services);
                }
                syncPurchaseJenisServiceUi();
            } catch (e) {
                document.getElementById('distributor_id').innerHTML = '<option value="">Gagal memuat distributor</option>';
            }
        };

        window.loadPurchaseProducts = async function() {
            let locType = document.querySelector('#purchase-form input[name="location_type"][type="hidden"]')?.value;
            let locId = null;
            if (locType === 'warehouse') {
                locId = document.querySelector('#purchase-form input[name="warehouse_id"][type="hidden"]')?.value;
            } else if (locType === 'branch') {
                locId = document.querySelector('#purchase-form input[name="branch_id"][type="hidden"]')?.value;
            }
            if (!locType || !locId) {
                locType = document.querySelector('input[name="location_type"]:checked')?.value;
                locId = locType === 'warehouse' ? document.getElementById('warehouse_id')?.value : (locType === 'branch' ? document.getElementById('branch_id')?.value : null);
            }
            if (!locType || !locId) {
                products = [];
                repopulateProductDropdowns();
                return;
            }
            try {
                const catId = document.getElementById('purchase_category_id')?.value || '';
                const url = baseUrl + formDataUrl + '?location_type=' + encodeURIComponent(locType) + '&location_id=' + encodeURIComponent(locId) + (catId ? '&category_id=' + encodeURIComponent(catId) : '');
                const r = await fetch(url);
                const data = await r.json();
                if (data.products) {
                    products = data.products;
                    repopulateProductDropdowns();
                }
            } catch (e) {
                products = [];
                repopulateProductDropdowns();
            }
        };

        function repopulateProductDropdowns() {
            document.querySelectorAll('.purchase-item').forEach(row => {
                const prevProductId = row.querySelector('.product-id-input')?.value;
                populateProductDropdown(row);
                const productStillExists = prevProductId && products.some(p => String(p.id) === String(prevProductId));
                if (!productStillExists && prevProductId) {
                    row.querySelector('.product-id-input').value = '';
                    const label = row.querySelector('.product-select-label');
                    if (label) { label.textContent = 'Pilih Produk'; label.classList.add('text-slate-500'); }
                }
            });
        }

        document.getElementById('purchase_category_id')?.addEventListener('change', () => window.loadPurchaseProducts?.());
        document.getElementById('refresh-products-btn')?.addEventListener('click', function() {
            this.disabled = true;
            this.querySelector('svg')?.classList?.add('animate-spin');
            window.loadPurchaseProducts?.().finally(() => {
                this.disabled = false;
                this.querySelector('svg')?.classList?.remove('animate-spin');
            });
        });
        document.querySelectorAll('input[name="location_type"]').forEach(el => el.addEventListener('change', () => {
            window.loadPurchaseDistributors?.();
            syncPurchaseJenisServiceUi();
        }));
        document.getElementById('jenis_pembelian')?.addEventListener('change', () => syncPurchaseJenisServiceUi());
        document.querySelector('form')?.addEventListener('change', function(e) {
            if (e.target.matches('#warehouse_id, #branch_id')) window.loadPurchaseDistributors?.();
        });
        document.addEventListener('DOMContentLoaded', () => {
            syncPurchaseJenisServiceUi();
            setTimeout(() => window.loadPurchaseDistributors?.(), 200);
        });

        const purchaseForm = document.getElementById('purchase-form');
        const confirmReuseInput = document.getElementById('confirm_reuse_sold_serials');
        if (purchaseForm && confirmReuseInput) {
            purchaseForm.addEventListener('submit', async function(e) {
                if (purchaseForm.dataset.reuseConfirmed === '1' || confirmReuseInput.value === '1') {
                    return;
                }
                e.preventDefault();
                try {
                    const formData = new FormData(purchaseForm);
                    const res = await fetch(baseUrl + checkReusableSerialsUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });
                    if (!res.ok) {
                        purchaseForm.dataset.reuseConfirmed = '1';
                        purchaseForm.requestSubmit();
                        return;
                    }
                    const data = await res.json();
                    const blocked = Array.isArray(data.blocked_serials) ? data.blocked_serials : [];
                    if (blocked.length > 0) {
                        await Swal.fire({
                            icon: 'error',
                            title: 'Serial sudah dipakai',
                            text: 'Serial berikut tidak bisa dipakai ulang (masih aktif / dipesan, dll.): ' + blocked.join(', '),
                        });
                        return;
                    }
                    const sold = Array.isArray(data.sold_serials) ? data.sold_serials : [];
                    if (sold.length > 0) {
                        const confirmResult = await Swal.fire({
                            icon: 'warning',
                            title: 'Serial pernah terdaftar',
                            html: 'Unit berikut sudah pernah ada dan statusnya <b>SOLD</b>:<br><b>' + sold.join(', ') + '</b><br><br>Lanjutkan update data unit/barang dengan data terbaru?<br><br><span class="text-sm">Lokasi unit akan disesuaikan ke <b>' + getPurchaseLocationHint() + '</b> (lokasi pembelian).</span>',
                            showCancelButton: true,
                            confirmButtonText: 'Ya, update',
                            cancelButtonText: 'Batal',
                        });
                        if (!confirmResult.isConfirmed) {
                            return;
                        }
                        confirmReuseInput.value = '1';
                        purchaseForm.dataset.reuseConfirmed = '1';
                    }
                    purchaseForm.dataset.reuseConfirmed = '1';
                    purchaseForm.requestSubmit();
                } catch (err) {
                    purchaseForm.dataset.reuseConfirmed = '1';
                    purchaseForm.requestSubmit();
                }
            });
        }
    </script>
    @endpush
</x-app-layout>
