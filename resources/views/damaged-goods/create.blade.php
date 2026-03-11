<x-app-layout>
    <x-slot name="title">{{ __('Catat Barang Rusak') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Catat Barang Rusak Cadangan') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('damaged-goods.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @if ($filterLocked && $locationLabel)
                                    <div class="md:col-span-2">
                                        <x-input-label :value="__('Lokasi')" />
                                        <x-locked-location label="{{ __('Lokasi (sesuai user)') }}" :value="$locationLabel" />
                                        <input type="hidden" name="location_type" value="{{ $defaultLocationType }}">
                                        <input type="hidden" name="location_id" value="{{ $defaultLocationId }}">
                                    </div>
                                @else
                                    <div>
                                        <x-input-label for="location_type" :value="__('Tipe Lokasi')" />
                                        <select id="location_type" name="location_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                            <option value="warehouse" {{ $defaultLocationType === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                            <option value="branch" {{ $defaultLocationType === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="location_id" :value="__('Lokasi')" />
                                        <select id="location_id" name="location_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                            <option value="">{{ __('Pilih') }}</option>
                                            @if ($defaultLocationType === 'warehouse')
                                                @foreach ($warehouses as $w)
                                                    <option value="{{ $w->id }}" {{ (string)$defaultLocationId === (string)$w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                                @endforeach
                                            @else
                                                @foreach ($branches as $b)
                                                    <option value="{{ $b->id }}" {{ (string)$defaultLocationId === (string)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                @endif
                            </div>

                            <div id="product-selector-block" class="space-y-3">
                                <x-input-label :value="__('Pilih Produk')" class="font-semibold" />
                                <p class="text-xs text-slate-500">{{ __('Pilih lokasi terlebih dahulu, lalu filter kategori, merk, dan series.') }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <x-input-label for="dg_category_id" :value="__('Kategori Barang')" class="text-sm" />
                                        <select id="dg_category_id" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">{{ __('Semua Kategori') }}</option>
                                            @foreach ($categories as $cat)
                                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="dg_brand_filter" :value="__('Merk')" class="text-sm" />
                                        <select id="dg_brand_filter" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">{{ __('Semua Merk') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="dg_series_filter" :value="__('Series')" class="text-sm" />
                                        <select id="dg_series_filter" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">{{ __('Semua Series') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="dg_product_trigger" :value="__('Produk')" class="text-sm" />
                                    <input type="hidden" id="product_id" name="product_id" value="{{ old('product_id') }}">
                                    <div class="relative mt-0.5">
                                        <button type="button" id="dg_product_trigger" class="w-full flex items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <span id="dg_product_label" class="text-slate-500">{{ __('Pilih Produk') }}</span>
                                            <svg class="h-5 w-5 text-slate-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <div id="dg_product_dropdown" class="product-dropdown hidden absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">
                                            <div class="p-2 border-b border-gray-100">
                                                <input type="text" id="dg_product_search" placeholder="{{ __('Cari SKU, merk, series...') }}"
                                                    class="w-full rounded-md border border-gray-300 py-2 px-3 text-sm">
                                            </div>
                                            <div id="dg_product_list" class="max-h-60 overflow-auto py-1"></div>
                                            <div id="dg_product_empty" class="hidden px-3 py-4 text-sm text-slate-500 text-center">{{ __('Tidak ada produk yang cocok.') }}</div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1">{{ __('Angka dalam kurung = stok unit tersedia.') }}</p>
                                </div>
                            </div>

                            <div id="serial-section" class="rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                                <x-input-label :value="__('Nomor Serial (Unit)')" class="font-semibold" />
                                <p class="text-xs text-slate-600 mt-0.5 mb-2">{{ __('Yang dicatat sebagai barang rusak adalah unit produk (product_unit) yang diidentifikasi melalui nomor serial. Pilih produk terlebih dahulu, lalu centang satu atau lebih nomor serial.') }}</p>
                                <div id="serial-placeholder" class="text-sm text-slate-500 py-2">
                                    {{ __('Pilih produk terlebih dahulu.') }}
                                </div>
                                <div id="serial-checkboxes" class="hidden space-y-2 max-h-60 overflow-auto py-2 pr-2 border border-slate-200 rounded-md bg-white">
                                    <div class="flex items-center gap-2 pb-2 border-b border-slate-100 sticky top-0 bg-white">
                                        <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-indigo-600">
                                            <input type="checkbox" id="serial-select-all" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            {{ __('Pilih Semua') }}
                                        </label>
                                        <button type="button" id="serial-clear" class="text-xs text-slate-500 hover:text-slate-700">{{ __('Hapus Pilihan') }}</button>
                                        <span id="serial-selected-count" class="text-xs text-slate-500 ml-auto"></span>
                                    </div>
                                    <div id="serial-list"></div>
                                </div>
                                <div id="serial-total-hpp" class="hidden mt-3 p-3 rounded-lg bg-slate-100 border border-slate-200">
                                    <span class="text-sm font-medium text-slate-700">{{ __('Total Beban HPP') }}:</span>
                                    <span id="serial-total-hpp-value" class="ml-2 text-lg font-semibold text-slate-900">Rp 0</span>
                                </div>
                                <p id="serial-empty" class="text-xs text-amber-600 mt-1 hidden">{{ __('Tidak ada unit tersedia untuk produk ini di lokasi terpilih.') }}</p>
                                <x-input-error :messages="$errors->get('product_unit_ids')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="recorded_date" :value="__('Tanggal Pencatatan')" />
                                <x-text-input id="recorded_date" class="block mt-1 w-full" type="date" name="recorded_date" :value="old('recorded_date', date('Y-m-d'))" required />
                                <x-input-error :messages="$errors->get('recorded_date')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="damage_description" :value="__('Deskripsi Kerusakan')" />
                                <textarea id="damage_description" name="damage_description" rows="4" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('damage_description') }}</textarea>
                                <x-input-error :messages="$errors->get('damage_description')" class="mt-2" />
                            </div>

                            <div class="flex gap-4">
                                <x-primary-button type="submit">{{ __('Simpan') }}</x-primary-button>
                                <a href="{{ route('damaged-goods.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function() {
            const filterLocked = @json($filterLocked ?? false);
            const defaultLocationType = @json($defaultLocationType ?? 'warehouse');
            const defaultLocationId = @json($defaultLocationId ?? null);
            const availableProductsUrl = @json(route('damaged-goods.available-products'));
            const availableSerialsUrl = @json(route('damaged-goods.available-serials'));

            let products = [];
            let units = [];

            function getLocationParams() {
                if (filterLocked) {
                    return { location_type: defaultLocationType, location_id: defaultLocationId };
                }
                const locType = document.getElementById('location_type')?.value;
                const locId = document.getElementById('location_id')?.value;
                return { location_type: locType, location_id: locId ? parseInt(locId, 10) : null };
            }

            async function loadProducts() {
                const params = getLocationParams();
                if (!params.location_type || !params.location_id) {
                    products = [];
                    updateProductUI();
                    return;
                }
                try {
                    const url = new URL(availableProductsUrl);
                    url.searchParams.set('location_type', params.location_type);
                    url.searchParams.set('location_id', params.location_id);
                    const catId = document.getElementById('dg_category_id')?.value;
                    if (catId) url.searchParams.set('category_id', catId);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    products = data.products || [];
                } catch (e) {
                    products = [];
                }
                updateProductUI();
            }

            async function loadSerials() {
                const productId = document.getElementById('product_id')?.value;
                const params = getLocationParams();
                if (!productId || !params.location_type || !params.location_id) {
                    units = [];
                    updateSerialUI();
                    return;
                }
                try {
                    const url = new URL(availableSerialsUrl);
                    url.searchParams.set('product_id', productId);
                    url.searchParams.set('location_type', params.location_type);
                    url.searchParams.set('location_id', params.location_id);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    units = data.units || [];
                } catch (e) {
                    units = [];
                }
                updateSerialUI();
            }

            function getBrands() {
                const s = new Set();
                products.forEach(p => { if (p.brand) s.add(p.brand); });
                return Array.from(s).sort();
            }
            function getSeries(brandVal) {
                const s = new Set();
                products.forEach(p => {
                    if ((!brandVal || p.brand === brandVal) && p.series) s.add(p.series);
                });
                return Array.from(s).sort();
            }
            function escAttr(s) {
                return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
            }
            function productOptionHtml(p) {
                return '<div class="product-option px-3 py-2 cursor-pointer hover:bg-indigo-50 text-sm" data-id="' + p.id + '" data-brand="' + escAttr(p.brand) + '" data-series="' + escAttr(p.series) + '" data-sku="' + escAttr(p.sku) + '">' +
                    '<span class="text-xs text-slate-500">' + escAttr(p.sku) + '</span> <span class="text-slate-400">-</span> <span class="text-slate-800">' + escAttr(p.brand) + ' ' + escAttr(p.series) + '</span> <span class="text-emerald-600 text-xs">(' + (p.in_stock_count || 0) + ')</span></div>';
            }

            function filterProducts() {
                const brandVal = document.getElementById('dg_brand_filter')?.value || '';
                const seriesVal = document.getElementById('dg_series_filter')?.value || '';
                const searchVal = (document.getElementById('dg_product_search')?.value || '').trim().toLowerCase();
                const opts = document.querySelectorAll('#dg_product_list .product-option');
                const listEl = document.getElementById('dg_product_list');
                const emptyEl = document.getElementById('dg_product_empty');
                let visible = 0;
                opts.forEach(o => {
                    const matchBrand = !brandVal || (o.getAttribute('data-brand') || '') === brandVal;
                    const matchSeries = !seriesVal || (o.getAttribute('data-series') || '') === seriesVal;
                    const searchStr = ((o.getAttribute('data-sku') || '') + ' ' + (o.getAttribute('data-brand') || '') + ' ' + (o.getAttribute('data-series') || '')).toLowerCase();
                    const matchSearch = !searchVal || searchStr.includes(searchVal);
                    const show = matchBrand && matchSeries && matchSearch;
                    o.classList.toggle('hidden', !show);
                    if (show) visible++;
                });
                if (listEl) listEl.classList.toggle('hidden', visible === 0);
                if (emptyEl) emptyEl.classList.toggle('hidden', visible > 0);
            }

            function updateProductUI() {
                const listEl = document.getElementById('dg_product_list');
                const brandSel = document.getElementById('dg_brand_filter');
                const seriesSel = document.getElementById('dg_series_filter');
                if (!listEl) return;
                listEl.innerHTML = products.map(p => productOptionHtml(p)).join('');
                if (brandSel) {
                    brandSel.innerHTML = '<option value="">Semua Merk</option>' + getBrands().map(b => '<option value="' + escAttr(b) + '">' + escAttr(b) + '</option>').join('');
                }
                if (seriesSel) {
                    seriesSel.innerHTML = '<option value="">Semua Series</option>' + getSeries(brandSel?.value || '').map(s => '<option value="' + escAttr(s) + '">' + escAttr(s) + '</option>').join('');
                }
                filterProducts();
                attachProductHandlers();
            }

            function updateSerialUI() {
                const placeholder = document.getElementById('serial-placeholder');
                const checkboxesWrap = document.getElementById('serial-checkboxes');
                const listEl = document.getElementById('serial-list');
                const emptyEl = document.getElementById('serial-empty');
                const productId = document.getElementById('product_id')?.value;
                if (!placeholder || !checkboxesWrap || !listEl) return;
                if (!productId) {
                    placeholder.classList.remove('hidden');
                    checkboxesWrap.classList.add('hidden');
                    emptyEl.classList.add('hidden');
                    return;
                }
                if (units.length === 0) {
                    placeholder.classList.add('hidden');
                    checkboxesWrap.classList.add('hidden');
                    emptyEl.classList.remove('hidden');
                    return;
                }
                placeholder.classList.add('hidden');
                checkboxesWrap.classList.remove('hidden');
                emptyEl.classList.add('hidden');
                listEl.innerHTML = units.map(u =>
                    '<label class="flex items-center gap-2 py-1.5 px-2 hover:bg-slate-50 rounded cursor-pointer text-sm">' +
                    '<input type="checkbox" name="product_unit_ids[]" value="' + u.id + '" data-hpp="' + (u.harga_hpp || 0) + '" class="serial-cb rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">' +
                    '<span class="text-slate-800">' + escAttr(u.serial_number) + '</span>' +
                    '<span class="text-xs text-slate-500">(HPP: Rp ' + Number(u.harga_hpp || 0).toLocaleString('id-ID') + ')</span>' +
                    '</label>'
                ).join('');
                document.getElementById('serial-select-all').checked = false;
                updateSerialCount();
                attachSerialHandlers();
            }

            function updateSerialCount() {
                const checkedBoxes = document.querySelectorAll('.serial-cb:checked');
                const checked = checkedBoxes.length;
                const total = document.querySelectorAll('.serial-cb').length;
                const countEl = document.getElementById('serial-selected-count');
                if (countEl) countEl.textContent = checked > 0 ? (checked + ' / ' + total + ' ' + (checked === 1 ? 'dipilih' : 'dipilih')) : '';
                const selectAll = document.getElementById('serial-select-all');
                if (selectAll && total > 0) {
                    selectAll.checked = checked === total;
                    selectAll.indeterminate = checked > 0 && checked < total;
                }
                const totalHppEl = document.getElementById('serial-total-hpp');
                const totalHppValueEl = document.getElementById('serial-total-hpp-value');
                if (totalHppEl && totalHppValueEl) {
                    let sum = 0;
                    checkedBoxes.forEach(cb => { sum += parseFloat(cb.dataset.hpp || 0) || 0; });
                    totalHppEl.classList.toggle('hidden', checked === 0);
                    totalHppValueEl.textContent = 'Rp ' + sum.toLocaleString('id-ID');
                }
            }

            function attachSerialHandlers() {
                const selectAll = document.getElementById('serial-select-all');
                const clearBtn = document.getElementById('serial-clear');
                document.querySelectorAll('.serial-cb').forEach(cb => {
                    cb.onchange = updateSerialCount;
                });
                if (selectAll) {
                    selectAll.onchange = function() {
                        document.querySelectorAll('.serial-cb').forEach(cb => { cb.checked = selectAll.checked; });
                        updateSerialCount();
                    };
                }
                if (clearBtn) {
                    clearBtn.onclick = function() {
                        document.querySelectorAll('.serial-cb').forEach(cb => { cb.checked = false; });
                        if (selectAll) selectAll.checked = false;
                        selectAll && (selectAll.indeterminate = false);
                        updateSerialCount();
                    };
                }
            }

            function formatRupiah(num) {
                if (num == null || num === '' || isNaN(parseFloat(num))) return '';
                return String(Math.round(parseFloat(num)));
            }

            function attachProductHandlers() {
                const trigger = document.getElementById('dg_product_trigger');
                const dropdown = document.getElementById('dg_product_dropdown');
                const searchInput = document.getElementById('dg_product_search');
                const productIdInput = document.getElementById('product_id');
                const labelEl = document.getElementById('dg_product_label');

                if (!trigger || !dropdown) return;
                dropdown.onclick = e => e.stopPropagation();
                trigger.onclick = function(e) {
                    e.stopPropagation();
                    document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden'));
                    dropdown.classList.toggle('hidden');
                    if (!dropdown.classList.contains('hidden') && searchInput) {
                        searchInput.focus();
                        searchInput.value = '';
                        filterProducts();
                    }
                };
                if (searchInput) searchInput.oninput = () => filterProducts();

                document.querySelectorAll('#dg_product_list .product-option').forEach(opt => {
                    opt.onclick = function(e) {
                        e.stopPropagation();
                        const id = this.getAttribute('data-id');
                        const p = products.find(x => String(x.id) === String(id));
                        if (productIdInput) productIdInput.value = id;
                        if (labelEl && p) {
                            labelEl.textContent = (p.sku || '') + ' - ' + (p.brand || '') + ' ' + (p.series || '');
                            labelEl.classList.remove('text-slate-500');
                        }
                        document.querySelectorAll('.serial-cb').forEach(cb => { cb.checked = false; });
                        const selAll = document.getElementById('serial-select-all');
                        if (selAll) selAll.checked = false;
                        dropdown.classList.add('hidden');
                        loadSerials();
                    };
                });
            }

            document.getElementById('dg_category_id')?.addEventListener('change', loadProducts);
            document.getElementById('dg_brand_filter')?.addEventListener('change', function() {
                const seriesSel = document.getElementById('dg_series_filter');
                if (seriesSel) seriesSel.innerHTML = '<option value="">Semua Series</option>' + getSeries(this.value).map(s => '<option value="' + escAttr(s) + '">' + escAttr(s) + '</option>').join('');
                filterProducts();
            });
            document.getElementById('dg_series_filter')?.addEventListener('change', filterProducts);

            if (!filterLocked) {
                document.getElementById('location_type')?.addEventListener('change', function() {
                    const locType = this.value;
                    const locSel = document.getElementById('location_id');
                    const warehouses = @json($warehouses->map(fn($w) => ['id' => $w->id, 'name' => $w->name]));
                    const branches = @json($branches->map(fn($b) => ['id' => $b->id, 'name' => $b->name]));
                    const list = locType === 'warehouse' ? warehouses : branches;
                    locSel.innerHTML = '<option value="">Pilih</option>' + list.map(x => '<option value="' + x.id + '">' + escAttr(x.name) + '</option>').join('');
                    document.getElementById('product_id').value = '';
                    document.getElementById('dg_product_label').textContent = 'Pilih Produk';
                    document.getElementById('dg_product_label').classList.add('text-slate-500');
                    loadProducts();
                    loadSerials();
                });
                document.getElementById('location_id')?.addEventListener('change', function() {
                    document.getElementById('product_id').value = '';
                    loadProducts();
                    loadSerials();
                });
            }

            document.addEventListener('click', () => document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden')));

            setTimeout(function() { loadSerials(); }, 300);

            document.querySelector('form')?.addEventListener('submit', function(e) {
                const cbWrap = document.getElementById('serial-checkboxes');
                const hasSerials = cbWrap && !cbWrap.classList.contains('hidden') && document.querySelectorAll('.serial-cb').length > 0;
                if (hasSerials && document.querySelectorAll('.serial-cb:checked').length === 0) {
                    e.preventDefault();
                    alert('{{ __("Pilih paling tidak satu nomor serial.") }}');
                    return false;
                }
            });

            setTimeout(loadProducts, 200);
        })();
    </script>
    @endpush
</x-app-layout>
