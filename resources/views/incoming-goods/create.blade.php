<x-app-layout>
    <x-slot name="title">{{ __('Tambah Barang Masuk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Record Incoming Goods') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('incoming-goods.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="created_by" :value="__('User')" />
                                <x-text-input id="created_by" class="block mt-1 w-full bg-slate-100" type="text" :value="auth()->user()?->name" disabled />
                            </div>
                            @if ($isBranchUser)
                                <div>
                                    <x-locked-location label="{{ __('Cabang') }}" :value="__('Cabang') . ': ' . ($branch?->name ?? '')" />
                                    <input type="hidden" name="location_type" value="branch">
                                    <input type="hidden" name="branch_id" value="{{ $branch?->id }}">
                                </div>
                            @else
                                <div x-data="{ locationType: '{{ old('location_type', 'warehouse') }}' }">
                                    <x-input-label :value="__('Lokasi Tujuan')" />
                                    <div class="mt-2 flex gap-6">
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="radio" name="location_type" value="warehouse" x-model="locationType"
                                                class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="ml-2 text-sm font-medium text-gray-700">Gudang</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="radio" name="location_type" value="branch" x-model="locationType"
                                                class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="ml-2 text-sm font-medium text-gray-700">Cabang</span>
                                        </label>
                                    </div>
                                    <div x-show="locationType === 'warehouse'" x-cloak x-transition class="mt-3">
                                        <x-input-label for="warehouse_id" :value="__('Gudang')" />
                                        <select id="warehouse_id" name="warehouse_id"
                                            class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            :required="locationType === 'warehouse'" :disabled="locationType !== 'warehouse'">
                                            <option value="">Pilih Gudang</option>
                                            @foreach ($warehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}"
                                                    {{ (old('warehouse_id') ?? $selectedWarehouse?->id) == $warehouse->id ? 'selected' : '' }}>
                                                    {{ $warehouse->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
                                    </div>
                                    <div x-show="locationType === 'branch'" x-cloak x-transition class="mt-3">
                                        <x-input-label for="branch_id" :value="__('Cabang')" />
                                        <select id="branch_id" name="branch_id"
                                            class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            :required="locationType === 'branch'" :disabled="locationType !== 'branch'">
                                            <option value="">Pilih Cabang</option>
                                            @foreach ($branches as $b)
                                                <option value="{{ $b->id }}"
                                                    {{ (old('branch_id') ?? $selectedBranch?->id) == $b->id ? 'selected' : '' }}>
                                                    {{ $b->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                                    </div>
                                </div>
                            @endif
                            @if ($selectedProduct)
                                <div>
                                    <x-input-label for="product_id" :value="__('Produk')" />
                                    <x-text-input id="product_id" class="block mt-1 w-full bg-slate-100" type="text"
                                        :value="$selectedProduct->sku . ' - ' . $selectedProduct->brand . ' ' . $selectedProduct->series" disabled />
                                    <input type="hidden" name="product_id" value="{{ $selectedProduct->id }}">
                                </div>
                            @else
                                <div id="product-selector-block" class="space-y-3">
                                    <x-input-label :value="__('Pilih Produk')" class="font-semibold" />
                                    <p class="text-xs text-slate-500">{{ __('Pilih lokasi terlebih dahulu, lalu filter kategori/merk/series.') }}</p>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <x-input-label for="ig_category_id" :value="__('Kategori Barang')" class="text-sm" />
                                            <select id="ig_category_id" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="">{{ __('Semua Kategori') }}</option>
                                                @foreach ($categories as $cat)
                                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label for="ig_brand_filter" :value="__('Merk')" class="text-sm" />
                                            <select id="ig_brand_filter" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="">{{ __('Semua Merk') }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label for="ig_series_filter" :value="__('Series')" class="text-sm" />
                                            <select id="ig_series_filter" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="">{{ __('Semua Series') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label for="ig_product" :value="__('Produk')" class="text-sm" />
                                        <input type="hidden" name="product_id" id="ig_product_id" value="{{ old('product_id') }}" required>
                                        <div class="product-dropdown-wrapper relative">
                                            <button type="button" id="ig_product_trigger" class="product-select-trigger w-full flex items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <span id="ig_product_label" class="product-select-label text-slate-500">{{ __('Pilih Produk') }}</span>
                                                <svg class="h-5 w-5 text-slate-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                                            </button>
                                            <div id="ig_product_dropdown" class="product-dropdown hidden absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">
                                                <div class="p-2 border-b border-gray-100">
                                                    <input type="text" id="ig_product_search" class="product-search w-full rounded-md border border-gray-300 py-2 px-3 text-sm" placeholder="{{ __('Cari SKU, merk, series...') }}">
                                                </div>
                                                <div id="ig_product_list" class="product-dropdown-list max-h-60 overflow-auto py-1"></div>
                                                <div id="ig_product_empty" class="product-dropdown-empty hidden px-3 py-4 text-sm text-slate-500 text-center">{{ __('Tidak ada produk yang cocok.') }}</div>
                                            </div>
                                        </div>
                                        <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
                                    </div>
                                </div>
                            @endif
                            @if (! $selectedProduct)
                                <div>
                                    <x-input-label for="quantity" :value="__('Quantity')" />
                                    <x-text-input id="quantity" class="block mt-1 w-full" type="number" name="quantity" min="1" :value="old('quantity')" />
                                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                                    <p class="mt-1 text-sm text-gray-500">{{ __('Isi quantity jika belum punya serial number per unit.') }}</p>
                                </div>
                            @endif

                            <div>
                                <x-input-label for="serial_numbers" :value="__('Serial Numbers (1 per line)')" />
                                <textarea id="serial_numbers" name="serial_numbers" rows="6" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="SN-001&#10;SN-002&#10;SN-003">{{ old('serial_numbers') }}</textarea>
                                <x-input-error :messages="$errors->get('serial_numbers')" class="mt-2" />
                                <p class="mt-1 text-sm text-gray-500">{{ __('Jika diisi, sistem akan menghitung quantity otomatis dari jumlah baris serial.') }}</p>
                            </div>
                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Save') }}</x-primary-button>
                                <a href="{{ route('incoming-goods.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: @json(session('success')),
                confirmButtonText: 'Baik',
                confirmButtonColor: '#4f46e5'
            });
            @endif
            @if (session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: @json(session('error')),
                confirmButtonText: 'Baik',
                confirmButtonColor: '#dc2626'
            });
            @endif
        });

        (function() {
            function parseSerials(text) {
                return (text || '').split(/[\n,]+/g).map(s => s.trim()).filter(Boolean);
            }
            function countSerials(text) {
                return [...new Set(parseSerials(text))].length;
            }
            function getDuplicateSerials(text) {
                const arr = parseSerials(text);
                const seen = new Set();
                const dups = new Set();
                arr.forEach(v => {
                    if (seen.has(v)) dups.add(v);
                    else seen.add(v);
                });
                return [...dups];
            }

            const serialEl = document.getElementById('serial_numbers');
            const qtyEl = document.getElementById('quantity');
            const form = serialEl?.closest('form');

            if (serialEl && qtyEl) {
                const sync = () => {
                    const c = countSerials(serialEl.value);
                    if (c > 0) {
                        qtyEl.value = c;
                        qtyEl.setAttribute('readonly', 'readonly');
                    } else {
                        qtyEl.removeAttribute('readonly');
                    }
                };
                serialEl.addEventListener('input', sync);
                sync();
            }

            if (form && serialEl) {
                form.addEventListener('submit', function(e) {
                    const dups = getDuplicateSerials(serialEl.value);
                    if (dups.length > 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validasi Gagal',
                            html: 'Nomor serial tidak boleh duplikat:<br><code class="mt-2 block text-sm">' + dups.join(', ') + '</code>',
                            confirmButtonText: 'Baik',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                });
            }
        })();

        @if (!$selectedProduct)
        (function() {
            const availableProductsUrl = @json(route('incoming-goods.available-products'));
            const isBranchUser = @json($isBranchUser);

            let products = [];

            function getLocationParams() {
                if (isBranchUser) return { branch_id: @json($branch?->id ?? 0) };
                const locType = document.querySelector('input[name="location_type"]:checked')?.value;
                const locId = locType === 'warehouse' ? document.getElementById('warehouse_id')?.value : document.getElementById('branch_id')?.value;
                return { location_type: locType, location_id: locId };
            }

            async function loadProducts() {
                const params = getLocationParams();
                if (isBranchUser) {
                    if (!params.branch_id) { products = []; updateUI(); return; }
                } else {
                    if (!params.location_type || !params.location_id) { products = []; updateUI(); return; }
                }

                try {
                    const url = new URL(availableProductsUrl);
                    if (isBranchUser) {
                        url.searchParams.set('branch_id', params.branch_id);
                    } else {
                        url.searchParams.set('location_type', params.location_type);
                        url.searchParams.set('location_id', params.location_id);
                    }
                    const catId = document.getElementById('ig_category_id')?.value;
                    if (catId) url.searchParams.set('category_id', catId);

                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    products = data.products || [];
                } catch (e) {
                    products = [];
                }
                updateUI();
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
                    '<span class="text-xs text-slate-500">' + escAttr(p.sku) + '</span> <span class="text-slate-400">-</span> <span class="text-slate-800">' + escAttr(p.brand) + ' ' + escAttr(p.series) + '</span></div>';
            }

            function filterProducts() {
                const brandVal = document.getElementById('ig_brand_filter')?.value || '';
                const seriesVal = document.getElementById('ig_series_filter')?.value || '';
                const searchVal = (document.getElementById('ig_product_search')?.value || '').trim().toLowerCase();
                const opts = document.querySelectorAll('#ig_product_list .product-option');
                const listEl = document.getElementById('ig_product_list');
                const emptyEl = document.getElementById('ig_product_empty');
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

            function updateUI() {
                const listEl = document.getElementById('ig_product_list');
                const brandSel = document.getElementById('ig_brand_filter');
                const seriesSel = document.getElementById('ig_series_filter');
                if (!listEl) return;

                listEl.innerHTML = products.map(p => productOptionHtml(p)).join('');
                if (brandSel) {
                    brandSel.innerHTML = '<option value="">Semua Merk</option>' + getBrands().map(b => '<option value="' + escAttr(b) + '">' + escAttr(b) + '</option>').join('');
                }
                if (seriesSel) {
                    seriesSel.innerHTML = '<option value="">Semua Series</option>' + getSeries(brandSel?.value || '').map(s => '<option value="' + escAttr(s) + '">' + escAttr(s) + '</option>').join('');
                }
                filterProducts();
                attachOptionHandlers();
            }

            function attachOptionHandlers() {
                const trigger = document.getElementById('ig_product_trigger');
                const dropdown = document.getElementById('ig_product_dropdown');
                const searchInput = document.getElementById('ig_product_search');
                const productIdInput = document.getElementById('ig_product_id');
                const labelEl = document.getElementById('ig_product_label');
                const brandEl = document.getElementById('ig_brand_filter');
                const seriesEl = document.getElementById('ig_series_filter');

                if (!trigger || !dropdown) return;

                dropdown.onclick = e => e.stopPropagation();
                trigger.onclick = function(e) {
                    e.stopPropagation();
                    document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden'));
                    const wasHidden = dropdown.classList.contains('hidden');
                    dropdown.classList.toggle('hidden');
                    if (wasHidden && searchInput) {
                        searchInput.focus();
                        searchInput.value = '';
                        filterProducts();
                    }
                };

                if (searchInput) {
                    searchInput.oninput = () => filterProducts();
                    searchInput.onkeydown = e => { if (e.key === 'Escape') dropdown.classList.add('hidden'); };
                }

                if (brandEl) brandEl.onchange = () => {
                    seriesEl.innerHTML = '<option value="">Semua Series</option>' + getSeries(brandEl.value).map(s => '<option value="' + escAttr(s) + '">' + escAttr(s) + '</option>').join('');
                    filterProducts();
                };
                if (seriesEl) seriesEl.onchange = () => filterProducts();

                listEl?.querySelectorAll('.product-option').forEach(opt => {
                    opt.onclick = function(e) {
                        e.stopPropagation();
                        const id = this.getAttribute('data-id');
                        const p = products.find(x => String(x.id) === String(id));
                        if (productIdInput) productIdInput.value = id;
                        if (labelEl && p) {
                            labelEl.textContent = (p.sku || '') + ' - ' + (p.brand || '') + ' ' + (p.series || '');
                            labelEl.classList.remove('text-slate-500');
                        }
                        dropdown.classList.add('hidden');
                    };
                });
            }

            document.addEventListener('click', () => document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden')));

            document.getElementById('ig_category_id')?.addEventListener('change', loadProducts);
            document.querySelectorAll('input[name="location_type"]').forEach(el => el.addEventListener('change', loadProducts));
            document.getElementById('warehouse_id')?.addEventListener('change', loadProducts);
            document.getElementById('branch_id')?.addEventListener('change', loadProducts);
            document.querySelector('form')?.addEventListener('change', function(e) {
                if (e.target.matches('#warehouse_id, #branch_id')) loadProducts();
            });

            setTimeout(loadProducts, 200);
        })();
        @endif
    </script>
    @endpush
</x-app-layout>
