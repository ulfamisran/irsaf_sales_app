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
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4">
                    <ul class="list-disc pl-5 text-red-800 text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('stock-mutations.store') }}" id="distribution-form">
                        @csrf
                        <div class="space-y-6">
                            {{-- Location From --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @if (!empty($lockFromLocation) && $fromLocationLabel)
                                    <div class="md:col-span-2">
                                        <x-input-label :value="__('Lokasi Asal')" />
                                        <x-locked-location label="{{ __('Lokasi Asal (default sesuai user)') }}" :value="$fromLocationLabel" />
                                        <input type="hidden" id="from_location_type" name="from_location_type" value="{{ $defaultFromLocationType }}" />
                                        <input type="hidden" id="from_location_id" name="from_location_id" value="{{ $defaultFromLocationId }}" />
                                        <p class="mt-1 text-xs text-slate-500">{{ __('Lokasi asal mengikuti gudang/cabang Anda.') }}</p>
                                    </div>
                                @else
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
                                @endif
                            </div>

                            {{-- Product Items --}}
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 class="font-semibold text-slate-800">{{ __('Produk Distribusi') }}</h3>
                                        <p class="text-xs text-slate-500">{{ __('Pilih lokasi asal terlebih dahulu, lalu tambahkan produk.') }}</p>
                                    </div>
                                    <button type="button" id="add-product-btn" class="inline-flex items-center gap-1 px-3 py-2 rounded-md bg-indigo-50 text-indigo-700 text-sm font-medium hover:bg-indigo-100 border border-indigo-200">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                        {{ __('Tambah Produk') }}
                                    </button>
                                </div>
                                <div id="product-items-container" class="space-y-4"></div>
                            </div>

                            {{-- Location To --}}
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

                            {{-- Date --}}
                            <div>
                                <x-input-label for="mutation_date" :value="__('Tanggal Mutasi')" />
                                <x-text-input id="mutation_date" class="block mt-1 w-full md:w-1/3" type="date" name="mutation_date" :value="old('mutation_date', date('Y-m-d'))" required />
                                <x-input-error :messages="$errors->get('mutation_date')" class="mt-2" />
                            </div>

                            {{-- Grand Total + Payments --}}
                            <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                <h4 class="text-sm font-semibold text-slate-800 mb-1">{{ __('Biaya Distribusi') }}</h4>
                                <p class="text-xs text-slate-500 mb-4">{{ __('Total biaya distribusi dari semua produk. Jika ada biaya, unit akan mengalami kenaikan HPP & Harga Jual. Wajib pakai nomor serial.') }}</p>
                                <div class="rounded-md bg-white border border-slate-200 p-3 mb-4">
                                    <div class="text-xs text-slate-500">{{ __('Grand Total Biaya Distribusi') }}</div>
                                    <div id="grand_total_text" class="text-lg font-semibold text-emerald-700">Rp 0</div>
                                </div>
                                <div id="distribution_payments_section" class="hidden">
                                    <div class="flex items-center justify-between gap-3 mb-2">
                                        <p class="font-semibold text-slate-800 text-sm">{{ __('Metode Pembayaran') }}</p>
                                        <button type="button" id="add-distribution-payment" class="inline-flex px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">
                                            + {{ __('Tambah') }}
                                        </button>
                                    </div>
                                    <p class="text-xs text-slate-500 mb-3">{{ __('Bisa menggunakan lebih dari 1 metode pembayaran. Pemasukan dicatat di lokasi asal.') }}</p>
                                    <div id="distribution_payment_rows" class="space-y-2"></div>
                                    <div class="mt-3 text-sm text-slate-700">
                                        <span>{{ __('Total pembayaran') }}: </span><span id="distribution_payment_sum_text" class="font-semibold">Rp 0</span>
                                        <span class="ml-2 text-slate-500">({{ __('selisih') }} <span id="distribution_payment_diff_text">Rp 0</span>)</span>
                                    </div>
                                    <x-input-error :messages="$errors->get('distribution_payments')" class="mt-2" />
                                </div>
                            </div>

                            {{-- Notes --}}
                            <div>
                                <x-input-label for="notes" :value="__('Catatan')" />
                                <textarea id="notes" name="notes" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2">{{ old('notes') }}</textarea>
                            </div>

                            {{-- Submit --}}
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
        const categories = @json($categories);
        const oldFromLocationId = @json(old('from_location_id'));
        const oldToLocationId = @json(old('to_location_id'));
        const lockFromLocation = @json($lockFromLocation ?? false);
        const availableProductsUrl = @json(route('stock-mutations.available-products'));
        const availableSerialsUrl = @json(route('stock-mutations.available-serials'));
        const formDataUrl = @json(route('data-by-location.form-data'));
        const oldItems = @json(old('items') ? array_values(old('items')) : []);
        const oldPayments = @json(old('distribution_payments', []));

        let itemCounter = 0;
        const itemManagers = [];
        let distributionPaymentMethods = [];
        let distributionPaymentIndex = 0;

        function escAttr(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }
        function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
        function setVisible(el, v) { if (el) el.classList.toggle('hidden', !v); }
        function toNumberRupiah(val) {
            const raw = String(val ?? '').replace(/[^\d]/g, '');
            return raw ? parseFloat(raw) : 0;
        }

        function updateLocationSelect(selectId, type) {
            const sel = document.getElementById(selectId);
            if (!sel || sel.tagName !== 'SELECT') return;
            const opts = type === 'warehouse' ? warehouses : branches;
            sel.innerHTML = '<option value="">Pilih</option>' + opts.map(o => `<option value="${o.id}">${escHtml(o.name)}</option>`).join('');
        }

        async function loadPaymentMethodsForOrigin() {
            const fromType = document.getElementById('from_location_type')?.value;
            const fromId = document.getElementById('from_location_id')?.value;
            if (!fromType || !fromId) { distributionPaymentMethods = []; updateDistributionPaymentOptions(); return; }
            try {
                const url = new URL(formDataUrl);
                url.searchParams.set('location_type', fromType);
                url.searchParams.set('location_id', fromId);
                const res = await fetch(url);
                const data = await res.json();
                distributionPaymentMethods = data.payment_methods || [];
            } catch (e) { distributionPaymentMethods = []; }
            updateDistributionPaymentOptions();
        }

        function updateDistributionPaymentOptions() {
            const opts = '<option value="">Pilih metode</option>' +
                distributionPaymentMethods.map(pm => '<option value="' + pm.id + '">' + escAttr(pm.label || pm.id) + '</option>').join('');
            document.querySelectorAll('#distribution_payment_rows select[name*="payment_method_id"]').forEach(sel => {
                const oldVal = sel.value;
                sel.innerHTML = opts;
                if (oldVal && distributionPaymentMethods.some(m => m.id == oldVal)) sel.value = oldVal;
            });
        }

        function getGrandTotal() {
            let total = 0;
            itemManagers.forEach(m => { total += m.getSubtotal(); });
            return total;
        }

        function updateGrandTotal() {
            const total = getGrandTotal();
            const el = document.getElementById('grand_total_text');
            if (el) el.textContent = 'Rp ' + total.toLocaleString('id-ID');
            const section = document.getElementById('distribution_payments_section');
            if (section) section.classList.toggle('hidden', total <= 0);
            refreshDistributionPaymentSum();
        }

        function refreshDistributionPaymentSum() {
            let sum = 0;
            document.querySelectorAll('#distribution_payment_rows input[name*="[amount]"]').forEach(inp => {
                sum += toNumberRupiah(inp.value);
            });
            const total = getGrandTotal();
            const diff = total - sum;
            const sumEl = document.getElementById('distribution_payment_sum_text');
            const diffEl = document.getElementById('distribution_payment_diff_text');
            if (sumEl) sumEl.textContent = 'Rp ' + sum.toLocaleString('id-ID');
            if (diffEl) {
                diffEl.textContent = 'Rp ' + diff.toLocaleString('id-ID');
                diffEl.className = diff === 0 ? 'text-emerald-600' : (diff > 0 ? 'text-amber-600' : 'text-red-600');
            }
        }

        function addDistributionPaymentRow(pref = {}) {
            const container = document.getElementById('distribution_payment_rows');
            if (!container) return;
            const idx = distributionPaymentIndex++;
            const opts = '<option value="">Pilih metode</option>' +
                distributionPaymentMethods.map(pm => '<option value="' + pm.id + '">' + escAttr(pm.label || pm.id) + '</option>').join('');
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 md:grid-cols-[minmax(260px,2fr)_minmax(160px,1fr)_auto] gap-3 items-end';
            div.innerHTML = '<div><select name="distribution_payments[' + idx + '][payment_method_id]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 min-h-[42px]">' + opts + '</select></div>' +
                '<div><input type="text" name="distribution_payments[' + idx + '][amount]" data-rupiah="true" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 min-h-[42px]" placeholder="Nominal"></div>' +
                '<button type="button" class="remove-dp-row px-3 py-2.5 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm self-end min-h-[42px]">-</button>';
            container.appendChild(div);
            if (pref.payment_method_id) div.querySelector('select').value = String(pref.payment_method_id);
            if (pref.amount) div.querySelector('input[name*="[amount]"]').value = String(pref.amount);
            div.querySelectorAll('select,input').forEach(el => el.addEventListener('input', refreshDistributionPaymentSum));
            div.querySelector('.remove-dp-row')?.addEventListener('click', () => { div.remove(); refreshDistributionPaymentSum(); });
            if (window.attachRupiahFormatter) window.attachRupiahFormatter(div);
        }

        document.getElementById('add-distribution-payment')?.addEventListener('click', () => addDistributionPaymentRow());

        class ProductItem {
            constructor(index, el, data = {}) {
                this.index = index;
                this.el = el;
                this.products = [];
                this.currentSerials = [];
                this.lastSerialKey = '';
                this.oldSerials = new Set();

                if (data.serial_numbers && Array.isArray(data.serial_numbers)) {
                    data.serial_numbers.forEach(s => this.oldSerials.add(String(s).trim()));
                }

                this.bind();
                this.listen();

                if (data.quantity) this.qtyEl.value = data.quantity;
                if (data.biaya_distribusi_per_unit) this.biayaEl.value = data.biaya_distribusi_per_unit;

                this.loadProducts().then(() => {
                    if (data.product_id) {
                        this.setProductById(data.product_id);
                        this.lastSerialKey = '';
                        this.loadSerials();
                    }
                });
            }

            bind() {
                this.categoryEl = this.el.querySelector('[data-field="category_id"]');
                this.brandEl = this.el.querySelector('[data-field="brand_filter"]');
                this.seriesEl = this.el.querySelector('[data-field="series_filter"]');
                this.productTrigger = this.el.querySelector('[data-field="product_trigger"]');
                this.productDropdown = this.el.querySelector('[data-field="product_dropdown"]');
                this.productSearch = this.el.querySelector('[data-field="product_search"]');
                this.productList = this.el.querySelector('[data-field="product_list"]');
                this.productEmpty = this.el.querySelector('[data-field="product_empty"]');
                this.productLabel = this.el.querySelector('[data-field="product_label"]');
                this.productIdInput = this.el.querySelector('[data-field="product_id"]');
                this.serialsHelp = this.el.querySelector('[data-field="serials_help"]');
                this.serialsLoading = this.el.querySelector('[data-field="serials_loading"]');
                this.serialsTools = this.el.querySelector('[data-field="serials_tools"]');
                this.serialsSearch = this.el.querySelector('[data-field="serials_search"]');
                this.serialsSelectAll = this.el.querySelector('[data-field="serials_select_all"]');
                this.serialsClear = this.el.querySelector('[data-field="serials_clear"]');
                this.serialsMeta = this.el.querySelector('[data-field="serials_meta"]');
                this.serialsList = this.el.querySelector('[data-field="serials_list"]');
                this.qtyEl = this.el.querySelector('[data-field="quantity"]');
                this.biayaEl = this.el.querySelector('[data-field="biaya_distribusi_per_unit"]');
                this.subtotalEl = this.el.querySelector('[data-field="item_subtotal"]');
                this.formulaEl = this.el.querySelector('[data-field="item_formula"]');
            }

            listen() {
                this.categoryEl?.addEventListener('change', () => this.loadProducts());
                this.brandEl?.addEventListener('change', () => { this.updateSeriesOptions(); this.filterProducts(); });
                this.seriesEl?.addEventListener('change', () => this.filterProducts());

                if (this.productTrigger && this.productDropdown) {
                    this.productDropdown.addEventListener('click', e => e.stopPropagation());
                    this.productTrigger.addEventListener('click', e => {
                        e.stopPropagation();
                        document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden'));
                        const wasHidden = this.productDropdown.classList.contains('hidden');
                        this.productDropdown.classList.toggle('hidden');
                        if (wasHidden && this.productSearch) {
                            this.productSearch.focus();
                            this.productSearch.value = '';
                            this.filterProducts();
                        }
                    });
                }

                this.productSearch?.addEventListener('input', () => this.filterProducts());
                this.productSearch?.addEventListener('keydown', e => { if (e.key === 'Escape') this.productDropdown?.classList.add('hidden'); });

                this.serialsSearch?.addEventListener('input', () => this.applySerialFilter());
                this.serialsSelectAll?.addEventListener('click', () => {
                    this.serialsList?.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                        if (!cb.closest('[data-serial-row]')?.classList.contains('hidden')) cb.checked = true;
                    });
                    this.updateQtyFromSelection();
                });
                this.serialsClear?.addEventListener('click', () => {
                    this.serialsList?.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
                    this.updateQtyFromSelection();
                });

                this.qtyEl?.addEventListener('input', () => { this.updateSubtotal(); updateGrandTotal(); });
                this.biayaEl?.addEventListener('input', () => { this.updateSubtotal(); updateGrandTotal(); });
            }

            getQty() {
                const checked = this.serialsList?.querySelectorAll('input[type="checkbox"]:checked').length || 0;
                if (checked > 0) return checked;
                return parseInt(this.qtyEl?.value || '0', 10) || 0;
            }

            getBiaya() { return parseFloat(this.biayaEl?.value || '0') || 0; }
            getSubtotal() { return this.getQty() * this.getBiaya(); }

            updateSubtotal() {
                const qty = this.getQty();
                const biaya = this.getBiaya();
                const total = qty * biaya;
                if (this.subtotalEl) this.subtotalEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
                if (this.formulaEl) this.formulaEl.textContent = qty + ' unit × Rp ' + biaya.toLocaleString('id-ID');
            }

            updateQtyFromSelection() {
                const checked = this.serialsList?.querySelectorAll('input[type="checkbox"]:checked').length || 0;
                if (checked > 0) {
                    if (this.qtyEl) { this.qtyEl.value = checked; this.qtyEl.setAttribute('readonly', 'readonly'); }
                } else {
                    if (this.qtyEl) { this.qtyEl.removeAttribute('readonly'); }
                }
                if (this.serialsMeta) this.serialsMeta.textContent = checked > 0 ? `${checked} dipilih` : '';
                this.updateSubtotal();
                updateGrandTotal();
            }

            clearSelection() {
                if (this.productIdInput) this.productIdInput.value = '';
                if (this.productLabel) { this.productLabel.textContent = 'Pilih Produk'; this.productLabel.className = 'text-slate-500'; }
                this.currentSerials = [];
                this.lastSerialKey = '';
                if (this.serialsList) this.serialsList.innerHTML = '';
                setVisible(this.serialsList, false);
                setVisible(this.serialsTools, false);
                if (this.serialsHelp) this.serialsHelp.textContent = 'Pilih produk & lokasi asal untuk menampilkan serial.';
                if (this.qtyEl) { this.qtyEl.value = ''; this.qtyEl.removeAttribute('readonly'); }
                this.updateSubtotal();
            }

            async loadProducts() {
                const fromType = document.getElementById('from_location_type')?.value;
                const fromId = document.getElementById('from_location_id')?.value;
                const catId = this.categoryEl?.value;
                if (!fromType || !fromId) { this.products = []; this.updateProductUI(); return; }
                try {
                    const url = new URL(availableProductsUrl);
                    url.searchParams.set('from_location_type', fromType);
                    url.searchParams.set('from_location_id', fromId);
                    if (catId) url.searchParams.set('category_id', catId);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.products = data.products || [];
                } catch (e) { this.products = []; }
                this.updateProductUI();
            }

            getBrands() {
                const s = new Set();
                this.products.forEach(p => { if (p.brand) s.add(p.brand); });
                return Array.from(s).sort();
            }

            getSeries(brand) {
                const s = new Set();
                this.products.forEach(p => { if ((!brand || p.brand === brand) && p.series) s.add(p.series); });
                return Array.from(s).sort();
            }

            updateSeriesOptions() {
                if (!this.seriesEl) return;
                this.seriesEl.innerHTML = '<option value="">Semua Series</option>' +
                    this.getSeries(this.brandEl?.value || '').map(s => `<option value="${escAttr(s)}">${escAttr(s)}</option>`).join('');
            }

            updateProductUI() {
                if (!this.productList) return;
                this.productList.innerHTML = this.products.map(p => this.productOptionHtml(p)).join('');
                if (this.brandEl) {
                    this.brandEl.innerHTML = '<option value="">Semua Merk</option>' +
                        this.getBrands().map(b => `<option value="${escAttr(b)}">${escAttr(b)}</option>`).join('');
                }
                this.updateSeriesOptions();
                this.filterProducts();
                this.attachProductOptionHandlers();
            }

            productOptionHtml(p) {
                const price = p.selling_price != null ? Number(p.selling_price).toLocaleString('id-ID') : '0';
                const color = p.color ? ` <span class="text-slate-400">-</span> <span class="text-xs text-slate-600">${escAttr(p.color)}</span>` : '';
                return `<div class="product-option px-3 py-2 cursor-pointer hover:bg-indigo-50 text-sm" data-id="${p.id}" data-brand="${escAttr(p.brand)}" data-series="${escAttr(p.series)}" data-sku="${escAttr(p.sku)}" data-color="${escAttr(p.color)}">` +
                    `<div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">` +
                    `<span class="text-xs text-slate-500">${escAttr(p.sku)}</span> <span class="text-slate-400">-</span> <span class="text-slate-800">${escAttr(p.brand)} ${escAttr(p.series)}</span>${color}` +
                    ` <span class="text-slate-400">-</span> <span class="text-emerald-600 font-medium ml-auto">${price}</span></div>` +
                    `<span class="text-xs text-slate-500">(${p.in_stock_count ?? 0} unit)</span></div>`;
            }

            filterProducts() {
                const brand = this.brandEl?.value || '';
                const series = this.seriesEl?.value || '';
                const search = (this.productSearch?.value || '').trim().toLowerCase();
                const opts = this.productList?.querySelectorAll('.product-option') || [];
                let visible = 0;
                opts.forEach(o => {
                    const mb = !brand || o.dataset.brand === brand;
                    const ms = !series || o.dataset.series === series;
                    const str = `${o.dataset.sku} ${o.dataset.brand} ${o.dataset.series} ${o.dataset.color}`.toLowerCase();
                    const mq = !search || str.includes(search);
                    const show = mb && ms && mq;
                    o.classList.toggle('hidden', !show);
                    if (show) visible++;
                });
                if (this.productList) this.productList.classList.toggle('hidden', visible === 0);
                if (this.productEmpty) this.productEmpty.classList.toggle('hidden', visible > 0);
            }

            attachProductOptionHandlers() {
                this.productList?.querySelectorAll('.product-option').forEach(opt => {
                    opt.onclick = (e) => {
                        e.stopPropagation();
                        const id = opt.dataset.id;
                        if (this.productIdInput) { this.productIdInput.value = id; this.productIdInput.dispatchEvent(new Event('change', { bubbles: true })); }
                        this.updateProductLabel(id);
                        this.productDropdown?.classList.add('hidden');
                        this.lastSerialKey = '';
                        this.loadSerials();
                    };
                });
            }

            setProductById(id) {
                if (this.productIdInput) this.productIdInput.value = id;
                this.updateProductLabel(id);
            }

            updateProductLabel(id) {
                if (!this.productLabel) return;
                if (!id) { this.productLabel.textContent = 'Pilih Produk'; this.productLabel.className = 'text-slate-500'; return; }
                const p = this.products.find(x => String(x.id) === String(id));
                if (p) {
                    this.productLabel.innerHTML = `<span class="text-xs text-slate-500">${escHtml(p.sku)}</span> <span class="text-slate-800">${escHtml((p.brand || '') + ' ' + (p.series || '')).trim()}</span>`;
                    this.productLabel.classList.remove('text-slate-500');
                }
            }

            async loadSerials() {
                const productId = this.productIdInput?.value;
                const fromType = document.getElementById('from_location_type')?.value;
                const fromId = document.getElementById('from_location_id')?.value;
                if (!productId || !fromType || !fromId) {
                    this.renderSerials([]);
                    if (this.serialsHelp) this.serialsHelp.textContent = 'Pilih produk & lokasi asal untuk menampilkan serial.';
                    return;
                }
                const key = `${productId}|${fromType}|${fromId}`;
                if (key === this.lastSerialKey) return;
                this.lastSerialKey = key;
                setVisible(this.serialsLoading, true);
                setVisible(this.serialsTools, false);
                setVisible(this.serialsList, false);
                try {
                    const url = new URL(availableSerialsUrl, window.location.origin);
                    url.searchParams.set('product_id', productId);
                    url.searchParams.set('from_location_type', fromType);
                    url.searchParams.set('from_location_id', fromId);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.renderSerials(data.serial_numbers || [], data || {});
                } catch (e) {
                    this.renderSerials([]);
                    if (this.serialsHelp) this.serialsHelp.textContent = 'Gagal memuat serial.';
                } finally {
                    setVisible(this.serialsLoading, false);
                }
            }

            renderSerials(serials, meta = {}) {
                this.currentSerials = serials || [];
                if (!this.serialsList) return;
                this.serialsList.innerHTML = '';
                if (this.currentSerials.length === 0) {
                    setVisible(this.serialsTools, false);
                    setVisible(this.serialsList, false);
                    if (this.serialsHelp) this.serialsHelp.textContent = 'Serial tidak ditemukan. Gunakan jumlah manual jika produk tanpa serial.';
                    this.updateQtyFromSelection();
                    return;
                }
                if (this.serialsHelp) {
                    let extra = meta.truncated ? ` (${this.currentSerials.length} dari ${meta.total_available})` : '';
                    this.serialsHelp.textContent = `Centang serial yang akan dipindahkan.${extra}`;
                }
                const frag = document.createDocumentFragment();
                const namePrefix = `items[${this.index}][serial_numbers][]`;
                this.currentSerials.forEach(sn => {
                    const row = document.createElement('label');
                    row.className = 'flex items-center gap-2 text-sm text-slate-700';
                    row.setAttribute('data-serial-row', '1');
                    row.setAttribute('data-serial', sn);
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.name = namePrefix;
                    cb.value = sn;
                    cb.className = 'rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';
                    const span = document.createElement('span');
                    span.className = 'font-mono text-xs';
                    span.textContent = sn;
                    row.appendChild(cb);
                    row.appendChild(span);
                    frag.appendChild(row);
                });
                this.serialsList.appendChild(frag);
                if (this.oldSerials.size > 0) {
                    this.serialsList.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                        if (this.oldSerials.has(cb.value)) cb.checked = true;
                    });
                }
                setVisible(this.serialsTools, true);
                setVisible(this.serialsList, true);
                this.serialsList.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    cb.addEventListener('change', () => { this.updateQtyFromSelection(); });
                });
                this.applySerialFilter();
                this.updateQtyFromSelection();
            }

            applySerialFilter() {
                const q = (this.serialsSearch?.value || '').trim().toLowerCase();
                (this.serialsList?.querySelectorAll('[data-serial-row]') || []).forEach(row => {
                    const sn = (row.dataset.serial || '').toLowerCase();
                    row.classList.toggle('hidden', q && !sn.includes(q));
                });
            }
        }

        function buildItemHTML(idx) {
            const catOpts = categories.map(c => `<option value="${c.id}">${escAttr(c.name)}</option>`).join('');
            return `<div class="flex items-center justify-between mb-3">` +
                `<h4 class="font-semibold text-slate-800 text-sm">` +
                `<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold mr-1 item-number">${idx + 1}</span>` +
                `Produk</h4>` +
                `<button type="button" class="remove-product-item inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50">` +
                `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>` +
                `Hapus</button></div>` +
                `<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">` +
                `<div><label class="block text-xs font-medium text-slate-700 mb-0.5">Kategori</label>` +
                `<select data-field="category_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">` +
                `<option value="">Semua Kategori</option>${catOpts}</select></div>` +
                `<div><label class="block text-xs font-medium text-slate-700 mb-0.5">Merk</label>` +
                `<select data-field="brand_filter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">` +
                `<option value="">Semua Merk</option></select></div>` +
                `<div><label class="block text-xs font-medium text-slate-700 mb-0.5">Series</label>` +
                `<select data-field="series_filter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">` +
                `<option value="">Semua Series</option></select></div></div>` +
                `<div class="mb-3">` +
                `<label class="block text-xs font-medium text-slate-700 mb-0.5">Produk</label>` +
                `<input type="hidden" name="items[${idx}][product_id]" data-field="product_id" value="">` +
                `<div class="relative">` +
                `<button type="button" data-field="product_trigger" class="w-full flex items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">` +
                `<span data-field="product_label" class="text-slate-500">Pilih Produk</span>` +
                `<svg class="h-5 w-5 text-slate-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>` +
                `</button>` +
                `<div data-field="product_dropdown" class="product-dropdown hidden absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">` +
                `<div class="p-2 border-b border-gray-100">` +
                `<input type="text" data-field="product_search" placeholder="Cari SKU, merk, series..."` +
                ` class="w-full rounded-md border border-gray-300 py-2 px-3 text-sm placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500"></div>` +
                `<div data-field="product_list" class="max-h-48 overflow-auto py-1"></div>` +
                `<div data-field="product_empty" class="hidden px-3 py-4 text-sm text-slate-500 text-center">Tidak ada produk.</div>` +
                `</div></div>` +
                `<p class="text-xs text-emerald-600 mt-0.5">Angka dalam kurung = stok unit di lokasi asal.</p></div>` +
                `<div class="mb-3">` +
                `<label class="block text-xs font-medium text-slate-700 mb-0.5">Nomor Serial</label>` +
                `<p data-field="serials_help" class="text-xs text-gray-500">Pilih produk & lokasi asal untuk menampilkan serial.</p>` +
                `<div data-field="serials_loading" class="mt-2 hidden text-sm text-slate-600">Memuat serial...</div>` +
                `<div data-field="serials_tools" class="mt-2 hidden">` +
                `<div class="flex flex-col md:flex-row md:items-center gap-2">` +
                `<input data-field="serials_search" type="text" class="w-full md:max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Cari serial...">` +
                `<div class="flex items-center gap-2">` +
                `<button type="button" data-field="serials_select_all" class="px-2 py-1.5 rounded-md bg-gray-100 text-gray-700 text-xs hover:bg-gray-200">Pilih Semua</button>` +
                `<button type="button" data-field="serials_clear" class="px-2 py-1.5 rounded-md bg-gray-100 text-gray-700 text-xs hover:bg-gray-200">Bersihkan</button>` +
                `</div>` +
                `<div data-field="serials_meta" class="text-xs text-gray-600 md:ml-auto"></div>` +
                `</div></div>` +
                `<div data-field="serials_list" class="mt-2 hidden max-h-48 overflow-auto rounded-md border border-gray-200 p-2 space-y-1 bg-white"></div></div>` +
                `<div class="grid grid-cols-1 md:grid-cols-3 gap-3">` +
                `<div><label class="block text-xs font-medium text-slate-700 mb-0.5">Jumlah</label>` +
                `<input type="number" name="items[${idx}][quantity]" data-field="quantity" min="1" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="0">` +
                `<p class="text-xs text-gray-500 mt-0.5">Otomatis jika pakai serial.</p></div>` +
                `<div><label class="block text-xs font-medium text-slate-700 mb-0.5">Biaya Distribusi/Unit (Rp)</label>` +
                `<input type="number" name="items[${idx}][biaya_distribusi_per_unit]" data-field="biaya_distribusi_per_unit" min="0" step="0.01" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="0"></div>` +
                `<div class="flex items-end"><div class="rounded-md bg-white border border-slate-200 p-2 w-full">` +
                `<div class="text-xs text-slate-500">Subtotal</div>` +
                `<div data-field="item_subtotal" class="text-sm font-semibold text-emerald-700">Rp 0</div>` +
                `<div class="text-xs text-slate-500"><span data-field="item_formula">0 unit × Rp 0</span></div>` +
                `</div></div></div>`;
        }

        function addProductItem(data = {}) {
            const idx = itemCounter++;
            const wrapper = document.createElement('div');
            wrapper.className = 'product-item-card rounded-lg border border-slate-200 bg-white p-4';
            wrapper.setAttribute('data-item-idx', String(idx));
            wrapper.innerHTML = buildItemHTML(idx);
            document.getElementById('product-items-container').appendChild(wrapper);
            wrapper.querySelector('.remove-product-item')?.addEventListener('click', () => removeProductItem(idx));
            const manager = new ProductItem(idx, wrapper, data);
            itemManagers.push(manager);
            updateItemNumbers();
            updateRemoveButtons();
            return manager;
        }

        function removeProductItem(idx) {
            const wrapper = document.querySelector(`[data-item-idx="${idx}"]`);
            if (wrapper) wrapper.remove();
            const mi = itemManagers.findIndex(m => m.index === idx);
            if (mi >= 0) itemManagers.splice(mi, 1);
            updateItemNumbers();
            updateRemoveButtons();
            updateGrandTotal();
        }

        function updateItemNumbers() {
            document.querySelectorAll('.product-item-card').forEach((card, i) => {
                const numEl = card.querySelector('.item-number');
                if (numEl) numEl.textContent = String(i + 1);
            });
        }

        function updateRemoveButtons() {
            document.querySelectorAll('.remove-product-item').forEach(btn => {
                btn.style.display = itemManagers.length <= 1 ? 'none' : '';
            });
        }

        document.getElementById('add-product-btn')?.addEventListener('click', () => addProductItem());
        document.addEventListener('click', () => document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden')));

        function onFromLocationChange() {
            itemManagers.forEach(m => { m.clearSelection(); m.loadProducts(); });
            loadPaymentMethodsForOrigin();
        }

        if (!lockFromLocation) {
            document.getElementById('from_location_type').addEventListener('change', function() {
                updateLocationSelect('from_location_id', this.value);
                onFromLocationChange();
            });
            document.getElementById('from_location_id').addEventListener('change', onFromLocationChange);
        }
        document.getElementById('to_location_type').addEventListener('change', function() {
            updateLocationSelect('to_location_id', this.value);
        });

        if (!lockFromLocation) {
            updateLocationSelect('from_location_id', document.getElementById('from_location_type').value);
            if (oldFromLocationId) document.getElementById('from_location_id').value = oldFromLocationId;
        }
        updateLocationSelect('to_location_id', document.getElementById('to_location_type').value);
        if (oldToLocationId) document.getElementById('to_location_id').value = oldToLocationId;

        (async function init() {
            await loadPaymentMethodsForOrigin();
            if (Array.isArray(oldPayments) && oldPayments.length > 0) {
                oldPayments.forEach(p => {
                    if (p && (p.payment_method_id || (p.amount && parseFloat(p.amount) > 0))) {
                        addDistributionPaymentRow({ payment_method_id: p.payment_method_id, amount: p.amount || '' });
                    }
                });
            }
            if (Array.isArray(oldItems) && oldItems.length > 0) {
                oldItems.forEach(item => addProductItem(item || {}));
            } else {
                addProductItem();
            }
            updateGrandTotal();
        })();

        document.getElementById('distribution-form')?.addEventListener('submit', function() {
            document.querySelectorAll('#distribution_payment_rows [data-rupiah="true"]').forEach(inp => {
                const num = toNumberRupiah(inp.value);
                inp.value = num > 0 ? num : '';
            });
        });
    </script>
</x-app-layout>
