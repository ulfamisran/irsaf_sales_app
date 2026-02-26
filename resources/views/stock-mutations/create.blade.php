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
                            <div>
                                <x-input-label for="product_id" :value="__('Product')" />
                                <select id="product_id" name="product_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="">{{ __('Select Product') }}</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                            {{ $product->sku }} - {{ $product->brand }} {{ $product->series }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="from_location_type" :value="__('From Type')" />
                                    <select id="from_location_type" name="from_location_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="warehouse" {{ old('from_location_type') == 'warehouse' ? 'selected' : '' }}>{{ __('Warehouse') }}</option>
                                        <option value="branch" {{ old('from_location_type') == 'branch' ? 'selected' : '' }}>{{ __('Branch') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="from_location_id" :value="__('From Location')" />
                                    <select id="from_location_id" name="from_location_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="">{{ __('Select') }}</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('from_location_id')" class="mt-2" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="to_location_type" :value="__('To Type')" />
                                    <select id="to_location_type" name="to_location_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="warehouse" {{ old('to_location_type') == 'warehouse' ? 'selected' : '' }}>{{ __('Warehouse') }}</option>
                                        <option value="branch" {{ old('to_location_type') == 'branch' ? 'selected' : '' }}>{{ __('Branch') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="to_location_id" :value="__('To Location')" />
                                    <select id="to_location_id" name="to_location_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="">{{ __('Select') }}</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('to_location_id')" class="mt-2" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="quantity" :value="__('Quantity')" />
                                    <x-text-input id="quantity" class="block mt-1 w-full" type="number" name="quantity" min="1" :value="old('quantity')" />
                                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                                    <p class="mt-1 text-sm text-gray-500">{{ __('Jika mutasi menggunakan serial number, quantity akan dihitung otomatis.') }}</p>
                                </div>
                                <div>
                                    <x-input-label for="mutation_date" :value="__('Mutation Date')" />
                                    <x-text-input id="mutation_date" class="block mt-1 w-full" type="date" name="mutation_date" :value="old('mutation_date', date('Y-m-d'))" required />
                                    <x-input-error :messages="$errors->get('mutation_date')" class="mt-2" />
                                </div>
                            </div>
                            <div>
                                <x-input-label :value="__('Serial Numbers (pilih yang akan dipindahkan)')" />
                                <p id="serials_help" class="mt-1 text-sm text-gray-500">
                                    {{ __('Pilih Produk dan Lokasi Asal untuk menampilkan serial yang tersedia (in stock).') }}
                                </p>

                                <div id="serials_loading" class="mt-3 hidden text-sm text-slate-600">
                                    {{ __('Loading serial numbers...') }}
                                </div>

                                <div id="serials_tools" class="mt-3 hidden">
                                    <div class="flex flex-col md:flex-row md:items-center gap-2">
                                        <input id="serials_search" type="text" class="w-full md:max-w-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="{{ __('Cari serial...') }}">
                                        <div class="flex items-center gap-2">
                                            <button type="button" id="serials_select_all" class="px-3 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
                                                {{ __('Select all') }}
                                            </button>
                                            <button type="button" id="serials_clear" class="px-3 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
                                                {{ __('Clear') }}
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
                                <x-input-label for="notes" :value="__('Notes')" />
                                <textarea id="notes" name="notes" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2">{{ old('notes') }}</textarea>
                            </div>
                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Buat Distribusi') }}</x-primary-button>
                                <a href="{{ route('stock-mutations.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
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

        function updateLocationSelect(selectId, type) {
            const select = document.getElementById(selectId);
            const options = type === 'warehouse' ? warehouses : branches;
            select.innerHTML = '<option value="">Select</option>' + options.map(o => `<option value="${o.id}">${o.name}</option>`).join('');
        }

        document.getElementById('from_location_type').addEventListener('change', function() {
            updateLocationSelect('from_location_id', this.value);
        });
        document.getElementById('to_location_type').addEventListener('change', function() {
            updateLocationSelect('to_location_id', this.value);
        });

        updateLocationSelect('from_location_id', document.getElementById('from_location_type').value);
        updateLocationSelect('to_location_id', document.getElementById('to_location_type').value);
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

        // Initial load (also re-populates old selections if any)
        loadSerials();
    </script>
</x-app-layout>
