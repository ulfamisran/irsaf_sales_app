<x-app-layout>
    <x-slot name="title">{{ __('Buat Mutasi Stok') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Buat Distribusi Stok') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('stock-mutations.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="from_location_type" :value="__('Tipe Asal')" />
                                    <select id="from_location_type" name="from_location_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="warehouse" {{ old('from_location_type') == 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                        <option value="branch" {{ old('from_location_type') == 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="from_location_id" :value="__('Lokasi Asal')" />
                                    <select id="from_location_id" name="from_location_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="">{{ __('Pilih') }}</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('from_location_id')" class="mt-2" />
                                </div>
                            </div>
                            <div id="product-selector-block" class="space-y-3">
                                <x-input-label :value="__('Pilih Produk')" class="font-semibold" />
                                <p class="text-xs text-slate-500">{{ __('Pilih lokasi asal terlebih dahulu, lalu filter kategori, merk, dan series.') }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <x-input-label for="sm_category_id" :value="__('Kategori Barang')" class="text-sm" />
                                        <select id="sm_category_id" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">{{ __('Semua Kategori') }}</option>
                                            @foreach ($categories as $cat)
                                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="sm_brand_filter" :value="__('Merk')" class="text-sm" />
                                        <select id="sm_brand_filter" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">{{ __('Semua Merk') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="sm_series_filter" :value="__('Series')" class="text-sm" />
                                        <select id="sm_series_filter" class="block mt-0.5 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">{{ __('Semua Series') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="product_select_trigger" :value="__('Produk')" class="text-sm" />
                                    <input type="hidden" id="product_id" name="product_id" value="{{ old('product_id') }}" required>
                                    <div class="relative mt-0.5">
                                        <button type="button" id="product_select_trigger" class="w-full flex items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <span id="product_select_label" class="text-slate-500">{{ __('Pilih Produk') }}</span>
                                            <svg class="h-5 w-5 text-slate-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <div id="product_dropdown" class="product-dropdown hidden absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">
                                            <div class="p-2 border-b border-gray-100">
                                                <input type="text" id="product_search" placeholder="{{ __('Cari SKU, merk, series...') }}"
                                                    class="w-full rounded-md border border-gray-300 py-2 px-3 text-sm placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                            <div id="product_dropdown_list" class="max-h-60 overflow-auto py-1"></div>
                                            <div id="product_dropdown_empty" class="hidden px-3 py-4 text-sm text-slate-500 text-center">
                                                {{ __('Tidak ada produk yang cocok.') }}
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-emerald-600 mt-1">
                                        {{ __('Angka di dalam kurung menunjukkan stok unit yang tersedia di lokasi asal.') }}
                                    </p>
                                </div>
                                <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="to_location_type" :value="__('Tipe Tujuan')" />
                                    <select id="to_location_type" name="to_location_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="warehouse" {{ old('to_location_type') == 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                        <option value="branch" {{ old('to_location_type') == 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="to_location_id" :value="__('Lokasi Tujuan')" />
                                    <select id="to_location_id" name="to_location_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="">{{ __('Pilih') }}</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('to_location_id')" class="mt-2" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="quantity" :value="__('Jumlah')" />
                                    <x-text-input id="quantity" class="block mt-1 w-full" type="number" name="quantity" min="1" :value="old('quantity')" />
                                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                                    <p class="mt-1 text-sm text-gray-500">{{ __('Jika mutasi menggunakan serial number, jumlah akan dihitung otomatis.') }}</p>
                                </div>
                                <div>
                                    <x-input-label for="mutation_date" :value="__('Tanggal Mutasi')" />
                                    <x-text-input id="mutation_date" class="block mt-1 w-full" type="date" name="mutation_date" :value="old('mutation_date', date('Y-m-d'))" required />
                                    <x-input-error :messages="$errors->get('mutation_date')" class="mt-2" />
                                </div>
                            </div>
                            <div>
                                <x-input-label :value="__('Nomor Serial (pilih yang akan dipindahkan)')" />
                                <p id="serials_help" class="mt-1 text-sm text-gray-500">
                                    {{ __('Pilih Produk dan Lokasi Asal untuk menampilkan serial yang tersedia (in stock).') }}
                                </p>

                                <div id="serials_loading" class="mt-3 hidden text-sm text-slate-600">
                                    {{ __('Memuat nomor serial...') }}
                                </div>

                                <div id="serials_tools" class="mt-3 hidden">
                                    <div class="flex flex-col md:flex-row md:items-center gap-2">
                                        <input id="serials_search" type="text" class="w-full md:max-w-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="{{ __('Cari serial...') }}">
                                        <div class="flex items-center gap-2">
                                            <button type="button" id="serials_select_all" class="px-3 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
                                                {{ __('Pilih Semua') }}
                                            </button>
                                            <button type="button" id="serials_clear" class="px-3 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
                                                {{ __('Bersihkan') }}
                                            </button>
                                        </div>
                                        <div id="serials_meta" class="text-sm text-gray-600 md:ml-auto"></div>
                                    </div>
                                </div>

                                <div id="serials_list" class="mt-3 hidden max-h-64 overflow-auto rounded-md border border-gray-200 p-3 space-y-2 bg-white"></div>
                                <x-input-error :messages="$errors->get('serial_numbers')" class="mt-2" />
                                <x-input-error :messages="$errors->get('serial_numbers.*')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="notes" :value="__('Catatan')" />
                                <textarea id="notes" name="notes" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2">{{ old('notes') }}</textarea>
                            </div>
                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Buat Distribusi') }}</x-primary-button>
                                <a href="{{ route('stock-mutations.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const warehouses = @json($warehouses);
        const branches = @json($branches);
        const oldFromLocationId = @json(old('from_location_id'));
        const oldToLocationId = @json(old('to_location_id'));

        function updateLocationSelect(selectId, type) {
            const select = document.getElementById(selectId);
            const options = type === 'warehouse' ? warehouses : branches;
            select.innerHTML = '<option value="">Pilih</option>' + options.map(o => `<option value="${o.id}">${o.name}</option>`).join('');
        }

        document.getElementById('from_location_type').addEventListener('change', function() {
            updateLocationSelect('from_location_id', this.value);
        });
        document.getElementById('to_location_type').addEventListener('change', function() {
            updateLocationSelect('to_location_id', this.value);
        });

        updateLocationSelect('from_location_id', document.getElementById('from_location_type').value);
        updateLocationSelect('to_location_id', document.getElementById('to_location_type').value);
        if (oldFromLocationId) document.getElementById('from_location_id').value = oldFromLocationId;
        if (oldToLocationId) document.getElementById('to_location_id').value = oldToLocationId;
    </script>

    <script>
        const availableSerialsUrl = @json(route('stock-mutations.available-serials'));
        const oldSerialInput = @json(old('serial_numbers'));

        function normalizeOldSerials(input) {
            if (!input) return [];
            if (Array.isArray(input)) return input.map(s => String(s).trim()).filter(Boolean);
            if (typeof input === 'string') {
                return input
                    .split(/[\n,]+/g)
                    .map(s => s.trim())
                    .filter(Boolean);
            }
            return [];
        }

        const oldSerials = new Set(normalizeOldSerials(oldSerialInput));

        const productEl = document.getElementById('product_id');
        const fromTypeEl = document.getElementById('from_location_type');
        const fromIdEl = document.getElementById('from_location_id');
        const qtyEl = document.getElementById('quantity');

        const helpEl = document.getElementById('serials_help');
        const loadingEl = document.getElementById('serials_loading');
        const toolsEl = document.getElementById('serials_tools');
        const searchEl = document.getElementById('serials_search');
        const selectAllBtn = document.getElementById('serials_select_all');
        const clearBtn = document.getElementById('serials_clear');
        const metaEl = document.getElementById('serials_meta');
        const listEl = document.getElementById('serials_list');

        let currentSerials = [];
        let lastFetchKey = '';

        function setVisible(el, visible) {
            if (!el) return;
            el.classList.toggle('hidden', !visible);
        }

        function updateQtyFromSelection() {
            if (!qtyEl || !listEl) return;
            const checked = listEl.querySelectorAll('input[type="checkbox"][name="serial_numbers[]"]:checked').length;
            if (checked > 0) {
                qtyEl.value = String(checked);
                qtyEl.setAttribute('readonly', 'readonly');
                qtyEl.dataset.autoQty = '1';
            } else {
                qtyEl.removeAttribute('readonly');
                if (qtyEl.dataset.autoQty === '1') {
                    qtyEl.value = '';
                    delete qtyEl.dataset.autoQty;
                }
            }
            if (metaEl) {
                metaEl.textContent = checked > 0 ? `${checked} dipilih` : '';
            }
        }

        function applySearchFilter() {
            const q = (searchEl?.value || '').trim().toLowerCase();
            const rows = listEl?.querySelectorAll('[data-serial-row="1"]') || [];
            rows.forEach(row => {
                const sn = (row.getAttribute('data-serial') || '').toLowerCase();
                row.classList.toggle('hidden', q && !sn.includes(q));
            });
        }

        function renderSerials(serials, meta = {}) {
            currentSerials = serials || [];
            if (!listEl) return;
            listEl.innerHTML = '';

            if (currentSerials.length === 0) {
                setVisible(toolsEl, false);
                setVisible(listEl, false);
                if (helpEl) {
                    helpEl.textContent = 'Serial in stock tidak ditemukan untuk produk & lokasi asal yang dipilih. Jika produk tidak memakai serial, gunakan Quantity.';
                }
                updateQtyFromSelection();
                return;
            }

            if (helpEl) {
                let extra = '';
                if (meta.truncated) {
                    extra = ` (menampilkan ${currentSerials.length} dari total ${meta.total_available})`;
                }
                helpEl.textContent = `Centang serial yang akan dipindahkan.${extra}`;
            }

            const frag = document.createDocumentFragment();
            currentSerials.forEach(sn => {
                const row = document.createElement('label');
                row.className = 'flex items-center gap-2 text-sm text-slate-700';
                row.setAttribute('data-serial-row', '1');
                row.setAttribute('data-serial', sn);
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'serial_numbers[]';
                cb.value = sn;
                cb.className = 'rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';

                const label = document.createElement('span');
                label.className = 'font-mono';
                label.textContent = sn;

                row.appendChild(cb);
                row.appendChild(label);
                frag.appendChild(row);
            });
            listEl.appendChild(frag);

            // Restore old selections (after validation error)
            if (oldSerials.size > 0) {
                listEl.querySelectorAll('input[type="checkbox"][name="serial_numbers[]"]').forEach(cb => {
                    if (oldSerials.has(cb.value)) cb.checked = true;
                });
            }

            setVisible(toolsEl, true);
            setVisible(listEl, true);

            listEl.querySelectorAll('input[type="checkbox"][name="serial_numbers[]"]').forEach(cb => {
                cb.addEventListener('change', updateQtyFromSelection);
            });
            applySearchFilter();
            updateQtyFromSelection();
        }

        async function loadSerials() {
            const productId = productEl?.value;
            const fromType = fromTypeEl?.value;
            const fromId = fromIdEl?.value;

            if (!productId || !fromType || !fromId) {
                renderSerials([]);
                if (helpEl) {
                    helpEl.textContent = 'Pilih Produk dan Lokasi Asal untuk menampilkan serial yang tersedia (in stock).';
                }
                return;
            }

            const key = `${productId}|${fromType}|${fromId}`;
            if (key === lastFetchKey) return;
            lastFetchKey = key;

            setVisible(loadingEl, true);
            setVisible(toolsEl, false);
            setVisible(listEl, false);

            try {
                const url = new URL(availableSerialsUrl, window.location.origin);
                url.searchParams.set('product_id', productId);
                url.searchParams.set('from_location_type', fromType);
                url.searchParams.set('from_location_id', fromId);

                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                renderSerials(data.serial_numbers || [], data || {});
            } catch (e) {
                renderSerials([]);
                if (helpEl) {
                    helpEl.textContent = 'Gagal memuat serial. Silakan coba lagi.';
                }
            } finally {
                setVisible(loadingEl, false);
            }
        }

        if (productEl) productEl.addEventListener('change', () => { lastFetchKey = ''; loadSerials(); });
        if (fromTypeEl) fromTypeEl.addEventListener('change', () => { lastFetchKey = ''; loadSerials(); });
        if (fromIdEl) fromIdEl.addEventListener('change', () => { lastFetchKey = ''; loadSerials(); });
        if (searchEl) searchEl.addEventListener('input', applySearchFilter);
        if (selectAllBtn) selectAllBtn.addEventListener('click', () => {
            if (!listEl) return;
            listEl.querySelectorAll('input[type="checkbox"][name="serial_numbers[]"]').forEach(cb => cb.checked = true);
            updateQtyFromSelection();
        });
        if (clearBtn) clearBtn.addEventListener('click', () => {
            if (!listEl) return;
            listEl.querySelectorAll('input[type="checkbox"][name="serial_numbers[]"]').forEach(cb => cb.checked = false);
            updateQtyFromSelection();
        });

        let productsForDropdown = [];

        const availableProductsUrl = @json(route('stock-mutations.available-products'));

        async function loadProducts() {
            const fromType = document.getElementById('from_location_type')?.value;
            const fromId = document.getElementById('from_location_id')?.value;
            const catId = document.getElementById('sm_category_id')?.value;

            if (!fromType || !fromId) {
                productsForDropdown = [];
                updateProductUI();
                return;
            }

            try {
                const url = new URL(availableProductsUrl);
                url.searchParams.set('from_location_type', fromType);
                url.searchParams.set('from_location_id', fromId);
                if (catId) url.searchParams.set('category_id', catId);

                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                productsForDropdown = data.products || [];
            } catch (e) {
                productsForDropdown = [];
            }
            updateProductUI();
        }

        function getBrands() {
            const s = new Set();
            productsForDropdown.forEach(p => { if (p.brand) s.add(p.brand); });
            return Array.from(s).sort();
        }

        function getSeries(brandVal) {
            const s = new Set();
            productsForDropdown.forEach(p => {
                if ((!brandVal || p.brand === brandVal) && p.series) s.add(p.series);
            });
            return Array.from(s).sort();
        }

        function escAttr(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
        }

        function productOptionHtml(p) {
            const price = p.selling_price != null ? Number(p.selling_price).toLocaleString('id-ID') : '0';
            const colorPart = p.color ? ' <span class="text-slate-400">-</span> <span class="text-xs text-slate-600">' + escAttr(p.color) + '</span>' : '';
            return '<div class="product-option px-3 py-2 cursor-pointer hover:bg-indigo-50 text-sm" data-id="' + p.id + '" data-brand="' + escAttr(p.brand) + '" data-series="' + escAttr(p.series) + '" data-sku="' + escAttr(p.sku) + '" data-color="' + escAttr(p.color) + '">' +
                '<div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">' +
                '<span class="text-xs text-slate-500">' + escAttr(p.sku) + '</span> <span class="text-slate-400">-</span> <span class="text-slate-800">' + escAttr(p.brand) + ' ' + escAttr(p.series) + '</span>' + colorPart +
                ' <span class="text-slate-400">-</span> <span class="text-emerald-600 font-medium ml-auto">' + price + '</span></div>' +
                '<span class="text-xs text-slate-500">(' + (p.in_stock_count ?? 0) + ' unit)</span></div>';
        }

        function filterProductOptions() {
            const brandVal = document.getElementById('sm_brand_filter')?.value || '';
            const seriesVal = document.getElementById('sm_series_filter')?.value || '';
            const searchVal = (document.getElementById('product_search')?.value || '').trim().toLowerCase();
            const opts = document.querySelectorAll('#product_dropdown_list .product-option');
            const listEl = document.getElementById('product_dropdown_list');
            const emptyEl = document.getElementById('product_dropdown_empty');
            let visible = 0;

            opts.forEach(o => {
                const matchBrand = !brandVal || (o.getAttribute('data-brand') || '') === brandVal;
                const matchSeries = !seriesVal || (o.getAttribute('data-series') || '') === seriesVal;
                const searchStr = ((o.getAttribute('data-sku') || '') + ' ' + (o.getAttribute('data-brand') || '') + ' ' + (o.getAttribute('data-series') || '') + ' ' + (o.getAttribute('data-color') || '')).toLowerCase();
                const matchSearch = !searchVal || searchStr.includes(searchVal);
                const show = matchBrand && matchSeries && matchSearch;
                o.classList.toggle('hidden', !show);
                if (show) visible++;
            });

            if (listEl) listEl.classList.toggle('hidden', visible === 0);
            if (emptyEl) emptyEl.classList.toggle('hidden', visible > 0);
        }

        function updateProductUI() {
            const listEl = document.getElementById('product_dropdown_list');
            const brandSel = document.getElementById('sm_brand_filter');
            const seriesSel = document.getElementById('sm_series_filter');
            if (!listEl) return;

            listEl.innerHTML = productsForDropdown.map(p => productOptionHtml(p)).join('');
            if (brandSel) {
                brandSel.innerHTML = '<option value="">Semua Merk</option>' + getBrands().map(b => '<option value="' + escAttr(b) + '">' + escAttr(b) + '</option>').join('');
            }
            if (seriesSel) {
                seriesSel.innerHTML = '<option value="">Semua Series</option>' + getSeries(brandSel?.value || '').map(s => '<option value="' + escAttr(s) + '">' + escAttr(s) + '</option>').join('');
            }
            filterProductOptions();
            attachProductOptionHandlers();
        }

        function escapeHtml(s) {
            const div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        function updateProductLabel(productId) {
            const labelEl = document.getElementById('product_select_label');
            if (!labelEl) return;
            if (!productId) {
                labelEl.textContent = 'Pilih Produk';
                labelEl.classList.add('text-slate-500');
                return;
            }
            const p = productsForDropdown.find(x => String(x.id) === String(productId));
            if (p) {
                labelEl.innerHTML = '<span class="text-xs text-slate-500">' + escapeHtml(p.sku) + '</span> <span class="text-slate-800">' + escapeHtml((p.brand || '') + ' ' + (p.series || '')).trim() + '</span>';
                labelEl.classList.remove('text-slate-500');
            }
        }

        function attachProductOptionHandlers() {
            const productDropdown = document.getElementById('product_dropdown');
            const productTrigger = document.getElementById('product_select_trigger');
            const searchInput = document.getElementById('product_search');
            const productIdInput = document.getElementById('product_id');
            const brandEl = document.getElementById('sm_brand_filter');
            const seriesEl = document.getElementById('sm_series_filter');
            const listEl = document.getElementById('product_dropdown_list');

            if (!productTrigger || !productDropdown) return;

            productDropdown.onclick = e => e.stopPropagation();
            productTrigger.onclick = function(e) {
                e.stopPropagation();
                document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden'));
                const wasHidden = productDropdown.classList.contains('hidden');
                productDropdown.classList.toggle('hidden');
                if (wasHidden && searchInput) {
                    searchInput.focus();
                    searchInput.value = '';
                    filterProductOptions();
                }
            };

            if (searchInput) {
                searchInput.oninput = () => filterProductOptions();
                searchInput.onkeydown = e => { if (e.key === 'Escape') productDropdown.classList.add('hidden'); };
            }

            if (brandEl) brandEl.onchange = () => {
                seriesEl.innerHTML = '<option value="">Semua Series</option>' + getSeries(brandEl.value).map(s => '<option value="' + escAttr(s) + '">' + escAttr(s) + '</option>').join('');
                filterProductOptions();
            };
            if (seriesEl) seriesEl.onchange = () => filterProductOptions();

            listEl?.querySelectorAll('.product-option').forEach(opt => {
                opt.onclick = function(e) {
                    e.stopPropagation();
                    const id = this.getAttribute('data-id');
                    if (productIdInput) {
                        productIdInput.value = id;
                        productIdInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    updateProductLabel(id);
                    productDropdown.classList.add('hidden');
                    lastFetchKey = '';
                    loadSerials();
                };
            });
        }

        document.addEventListener('click', () => document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden')));

        document.getElementById('from_location_type')?.addEventListener('change', loadProducts);
        document.getElementById('from_location_id')?.addEventListener('change', loadProducts);
        document.getElementById('sm_category_id')?.addEventListener('change', loadProducts);

        const oldProductId = @json(old('product_id'));
        loadProducts().then(function() {
            if (oldProductId) {
                const oldProduct = productsForDropdown.find(p => String(p.id) === String(oldProductId));
                if (oldProduct) {
                    const brandEl = document.getElementById('sm_brand_filter');
                    const seriesEl = document.getElementById('sm_series_filter');
                    if (brandEl && oldProduct.brand) brandEl.value = oldProduct.brand;
                    if (seriesEl) {
                        seriesEl.innerHTML = '<option value="">Semua Series</option>' + getSeries(oldProduct.brand || '').map(s => '<option value="' + escAttr(s) + '">' + escAttr(s) + '</option>').join('');
                        if (oldProduct.series) seriesEl.value = oldProduct.series;
                    }
                    updateProductLabel(oldProductId);
                }
            }
        });

        loadSerials();
    </script>
</x-app-layout>
