<x-app-layout>
    <x-slot name="title">{{ __('Tukar Unit') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Tukar Unit') }}</h2>
            <x-icon-btn-back :href="route('sales.unit-exchanges.index')" :label="__('Kembali')" />
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-amber-50 border border-amber-200 p-4 text-amber-800">
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($exchange && $exchange->proofs->isNotEmpty())
            <div class="mb-6">
                <x-transaction-proof-fields
                    :slots="0"
                    :existing="$exchange->proofs"
                    :owner="$exchange"
                    :can-delete="true"
                />
            </div>
        @endif

        <form method="POST" action="{{ $exchange ? route('sales.unit-exchanges.update', $exchange) : route('sales.unit-exchanges.store') }}" id="exchange-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="sale_id" id="sale_id" value="{{ old('sale_id', $initial['id'] ?? '') }}">

            <div class="card-modern overflow-hidden mb-6">
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('No. Tukar Unit') }}</label>
                        <input type="text" value="{{ $number }}" class="mt-1 block w-full rounded-md border-gray-300 bg-slate-100 text-sm" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('Tanggal Tukar') }}</label>
                        <input type="text" value="{{ \Illuminate\Support\Carbon::parse($today)->format('d/m/Y') }}" class="mt-1 block w-full rounded-md border-gray-300 bg-slate-100 text-sm" readonly>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Tanggal tukar unit tidak bisa di-backdate.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('User') }}</label>
                        <input type="text" value="{{ $userName }}" class="mt-1 block w-full rounded-md border-gray-300 bg-slate-100 text-sm" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('Tanggal Catat') }}</label>
                        <input type="text" value="{{ $recordedAt }}" class="mt-1 block w-full rounded-md border-gray-300 bg-slate-100 text-sm" readonly>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">{{ __('Status') }}</label>
                        <div class="mt-2 flex gap-6">
                            <label class="inline-flex items-center">
                                <input type="radio" name="status" value="open" class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('status', $exchange?->status ?? 'open') === 'open')>
                                <span class="ml-2 text-sm text-slate-700">{{ __('Open') }}</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="status" value="released" class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('status', $exchange?->status ?? 'open') === 'released')>
                                <span class="ml-2 text-sm text-slate-700">{{ __('Release') }}</span>
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Open masih bisa diubah. Release mengunci tukar unit dan mewajibkan 1 bukti transaksi.') }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label for="invoice_search" class="block text-sm font-medium text-slate-700">{{ __('Invoice Penjualan Release') }}</label>
                        <input id="invoice_search" type="text" readonly placeholder="{{ __('Klik untuk memilih invoice...') }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm cursor-pointer" value="{{ $initial['invoice_number'] ?? '' }}">
                        <p id="invoice_meta" class="mt-2 text-sm text-slate-600">{{ $initial ? __('Pelanggan').': '.($initial['customer'] ?? '-') : '' }}</p>
                    </div>
                </div>
            </div>

            <div class="card-modern overflow-hidden">
                <div class="p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">{{ __('Unit pada Invoice') }}</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('SKU') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Nomor Serial') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Harga Unit') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                                </tr>
                            </thead>
                            <tbody id="unit_rows" class="divide-y divide-gray-200">
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('Pilih invoice penjualan yang sudah Release.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @if (max(0, 2 - ($exchange?->proofs->count() ?? 0)) > 0)
                        <div class="mt-6">
                            <x-transaction-proof-fields
                                :require-one="true"
                                :slots="max(0, 2 - ($exchange?->proofs->count() ?? 0))"
                                :hint="__('Wajib 1 file gambar atau PDF saat Release. File kedua opsional.')"
                            />
                        </div>
                    @endif
                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">{{ __('Simpan Tukar Unit') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div id="invoice_modal" hidden style="position:fixed;inset:0;z-index:80;background:rgba(15,23,42,.72);padding:24px;align-items:center;justify-content:center;">
        <div data-invoice-panel style="background:#fff;border-radius:16px;width:min(920px,100%);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 50px rgba(15,23,42,.35);">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;padding:14px 16px;border-bottom:1px solid #e2e8f0;">
                <p style="margin:0;font-size:15px;font-weight:700;color:#0f172a;">{{ __('Pilih Invoice Penjualan Release') }}</p>
                <button type="button" data-invoice-close style="border:0;border-radius:8px;background:#f1f5f9;color:#0f172a;padding:6px 12px;font-size:13px;font-weight:700;cursor:pointer;">{{ __('Tutup') }}</button>
            </div>
            <div style="padding:12px 16px;border-bottom:1px solid #e2e8f0;">
                <input id="invoice_lookup" type="text" autocomplete="off" placeholder="{{ __('Cari nomor invoice atau nama pelanggan...') }}" style="display:block;width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font-size:14px;">
            </div>
            <div style="overflow:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead>
                        <tr style="background:#f8fafc;text-align:left;">
                            <th style="padding:10px 12px;font-size:12px;color:#64748b;">{{ __('No. Invoice') }}</th>
                            <th style="padding:10px 12px;font-size:12px;color:#64748b;">{{ __('Pelanggan') }}</th>
                            <th style="padding:10px 12px;font-size:12px;color:#64748b;">{{ __('Tanggal') }}</th>
                            <th style="padding:10px 12px;font-size:12px;color:#64748b;text-align:right;">{{ __('Total') }}</th>
                            <th style="padding:10px 12px;font-size:12px;color:#64748b;text-align:right;">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody id="invoice_results"></tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            const invoiceUrl = @json(route('sales.unit-exchanges.invoices'));
            const serialUrl = @json(route('sales.unit-exchanges.serials'));
            const productUrl = @json(route('sales.unit-exchanges.products'));
            const initialInvoice = @json($initial);
            const presetItems = @json($presetItems ?? []);
            const lockInvoice = @json((bool) ($lockInvoice ?? false));
            const saleInput = document.getElementById('sale_id');
            const searchInput = document.getElementById('invoice_search');
            const invoiceModal = document.getElementById('invoice_modal');
            const lookupInput = document.getElementById('invoice_lookup');
            const resultsBox = document.getElementById('invoice_results');
            const meta = document.getElementById('invoice_meta');
            const unitRows = document.getElementById('unit_rows');
            const form = document.getElementById('exchange-form');
            let paymentMethods = [];
            let searchTimer = null;
            let replacementProducts = [];
            let replacementSaleId = '';

            function esc(value) {
                return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
            }

            function openInvoiceModal() {
                if (lockInvoice) return;
                invoiceModal.hidden = false;
                invoiceModal.style.display = 'flex';
                lookupInput.value = '';
                lookupInput.focus();
                searchInvoices('');
            }

            function closeInvoiceModal() {
                invoiceModal.hidden = true;
                invoiceModal.style.display = 'none';
                clearTimeout(searchTimer);
            }

            function money(value) {
                return new Intl.NumberFormat('id-ID').format(Math.round(Number(value) || 0));
            }

            function renderUnits(invoice) {
                paymentMethods = invoice.payment_methods || [];
                if (replacementSaleId !== String(invoice.id)) {
                    replacementProducts = [];
                    replacementSaleId = '';
                }
                saleInput.value = invoice.id;
                searchInput.value = invoice.invoice_number;
                meta.textContent = @json(__('Pelanggan')) + ': ' + (invoice.customer || '-') + ' · ' + (invoice.sale_date || '');
                const units = invoice.units || [];
                if (!units.length) {
                    unitRows.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">' + @json(__('Invoice ini tidak memiliki nomor serial.')) + '</td></tr>';
                    return;
                }
                unitRows.innerHTML = units.map((unit, index) => rowHtml(unit, index)).join('');
            }

            function replacementCardHtml() {
                const field = 'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
                return '<div data-replacement-card class="mb-4 rounded-xl border border-slate-200 bg-white overflow-hidden">'
                    + '<div class="px-4 py-3 border-b border-slate-200" style="background:linear-gradient(to right,#eef2ff,#f8fafc);">'
                    + '<p class="text-sm font-semibold text-slate-800">' + @json(__('Produk Pengganti')) + '</p>'
                    + '<p class="text-xs text-slate-500">' + @json(__('Pilih produk dan nomor serial pengganti dari stok lokasi invoice ini.')) + '</p>'
                    + '</div>'
                    + '<div class="p-4">'
                    + '<label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('Cari Produk / Nomor Serial')) + '</label>'
                    + '<input type="text" data-replace-lookup class="' + field + '" placeholder="' + @json(__('Ketik SKU, produk, atau nomor serial...')) + '" autocomplete="off">'
                    + '<div data-replace-results class="hidden mt-1 max-h-64 overflow-auto rounded-md border border-gray-200 bg-white shadow-lg"></div>'
                    + '<div class="grid grid-cols-1 md:grid-cols-3 gap-3 my-3">'
                    + '<div><label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('Kategori')) + '</label><select data-replace-category class="' + field + '"></select></div>'
                    + '<div><label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('Brand')) + '</label><select data-replace-brand class="' + field + '"></select></div>'
                    + '<div><label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('Series')) + '</label><select data-replace-series class="' + field + '"></select></div>'
                    + '</div>'
                    + '<label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('Produk')) + '</label>'
                    + '<select data-replace-product class="' + field + ' mb-3"><option value="">' + @json(__('Pilih Produk')) + '</option></select>'
                    + '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">'
                    + '<div><label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('Nomor Serial')) + '</label><input type="text" data-replace-serial-search class="' + field + '" placeholder="' + @json(__('Search serial...')) + '"></div>'
                    + '<div><label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('Scan Serial')) + '</label><input type="text" data-replace-scan class="' + field + '" placeholder="' + @json(__('Scan barcode/QR + Enter')) + '"></div>'
                    + '<div><label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('Serial Dipilih')) + '</label><select data-replace-serial size="3" class="' + field + '"></select></div>'
                    + '</div></div></div>';
            }

            function rowHtml(unit, index) {
                const options = paymentMethods.map((method) => '<option value="' + method.id + '">' + esc(method.label) + '</option>').join('');
                return '<tr data-unit-row>'
                    + '<td class="px-4 py-3">' + esc(unit.sku) + '</td>'
                    + '<td class="px-4 py-3">' + esc(unit.product) + '</td>'
                    + '<td class="px-4 py-3 font-mono text-sm">' + esc(unit.serial) + '</td>'
                    + '<td class="px-4 py-3 text-right">' + money(unit.price) + '</td>'
                    + '<td class="px-4 py-3 text-right"><button type="button" class="exchange-toggle px-3 py-1.5 rounded-lg bg-amber-100 text-amber-800 text-sm font-medium" data-index="' + index + '">' + @json(__('Tukar')) + '</button></td>'
                    + '</tr>'
                    + '<tr data-exchange-panel data-old-price="' + esc(unit.price) + '" hidden>'
                    + '<td colspan="5" class="px-4 py-4 bg-slate-50">'
                    + '<input type="hidden" data-field="old_serial" value="' + esc(unit.serial) + '" disabled>'
                    + replacementCardHtml()
                    + '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">'
                    + '<div><label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('SN pengganti')) + '</label>'
                    + '<input type="text" data-field="new_serial" class="block w-full rounded-md border-gray-300 text-sm bg-slate-100" readonly disabled>'
                    + '<p data-replacement class="mt-1 text-xs text-slate-500"></p></div>'
                    + '<div><label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('Selisih harga')) + '</label>'
                    + '<input type="number" step="1" data-field="price_difference" value="0" class="block w-full rounded-md border-gray-300 text-sm" disabled>'
                    + '<p class="mt-1 text-xs text-slate-500">' + @json(__('Positif = pemasukan. Negatif = pengembalian dana.')) + '</p></div>'
                    + '<div data-payment-wrap class="hidden"><label class="block text-xs font-medium text-slate-600 mb-1">' + @json(__('Metode pembayaran')) + '</label>'
                    + '<select data-field="payment_method_id" class="block w-full rounded-md border-gray-300 text-sm" disabled><option value="">' + @json(__('Pilih metode')) + '</option>' + options + '</select></div>'
                    + '</div></td></tr>';
            }

            async function loadInvoice(id) {
                const response = await fetch(@json(route('sales.unit-exchanges.invoice', ['sale' => '__SALE__'])).replace('__SALE__', id), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) {
                    return;
                }
                renderUnits(await response.json());
            }

            async function searchInvoices(q) {
                const response = await fetch(invoiceUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                if (!response.ok) {
                    resultsBox.innerHTML = '<tr><td colspan="5" style="padding:16px;text-align:center;color:#64748b;">' + @json(__('Invoice release tidak ditemukan.')) + '</td></tr>';
                    return;
                }
                const data = await response.json();
                const invoices = data.invoices || [];
                if (!invoices.length) {
                    resultsBox.innerHTML = '<tr><td colspan="5" style="padding:16px;text-align:center;color:#64748b;">' + @json(__('Invoice release tidak ditemukan.')) + '</td></tr>';
                    return;
                }
                resultsBox.innerHTML = invoices.map((invoice) => '<tr style="border-top:1px solid #e2e8f0;">'
                    + '<td style="padding:10px 12px;font-weight:600;color:#0f172a;">' + esc(invoice.invoice_number) + '</td>'
                    + '<td style="padding:10px 12px;color:#334155;">' + esc(invoice.customer) + '</td>'
                    + '<td style="padding:10px 12px;color:#334155;">' + esc(invoice.sale_date) + '</td>'
                    + '<td style="padding:10px 12px;text-align:right;color:#0f172a;">' + money(invoice.total) + '</td>'
                    + '<td style="padding:10px 12px;text-align:right;"><button type="button" data-invoice="' + esc(invoice.id) + '" style="border:0;border-radius:8px;background:#4f46e5;color:#fff;padding:6px 12px;font-size:13px;font-weight:600;cursor:pointer;">' + @json(__('Pilih')) + '</button></td>'
                    + '</tr>').join('');
            }

            searchInput.addEventListener('click', openInvoiceModal);
            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openInvoiceModal();
                }
            });
            lookupInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                const q = lookupInput.value.trim();
                searchTimer = setTimeout(() => searchInvoices(q), 250);
            });
            lookupInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') event.preventDefault();
            });
            invoiceModal.addEventListener('click', function (event) {
                if (event.target.id === 'invoice_modal' || event.target.closest('[data-invoice-close]')) {
                    closeInvoiceModal();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !invoiceModal.hidden) closeInvoiceModal();
            });

            resultsBox.addEventListener('click', function (event) {
                const button = event.target.closest('[data-invoice]');
                if (!button) return;
                closeInvoiceModal();
                loadInvoice(button.dataset.invoice);
            });

            function optionHtml(value, label) {
                return '<option value="' + esc(value) + '">' + esc(label) + '</option>';
            }

            async function ensureReplacementProducts() {
                if (!saleInput.value) return [];
                if (replacementSaleId === String(saleInput.value) && replacementProducts.length) return replacementProducts;
                const response = await fetch(productUrl + '?sale_id=' + encodeURIComponent(saleInput.value), { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                replacementProducts = data.products || [];
                replacementSaleId = String(saleInput.value);
                return replacementProducts;
            }

            function fillSelect(select, values, placeholder, selected) {
                const unique = [];
                values.forEach((value) => {
                    const text = String(value ?? '');
                    if (text !== '' && !unique.includes(text)) unique.push(text);
                });
                unique.sort((a, b) => a.localeCompare(b));
                select.innerHTML = '<option value="">' + esc(placeholder) + '</option>' + unique.map((value) => optionHtml(value, value)).join('');
                if (selected && unique.includes(String(selected))) select.value = String(selected);
            }

            function filteredProducts(panel) {
                const category = panel.querySelector('[data-replace-category]').value;
                const brand = panel.querySelector('[data-replace-brand]').value;
                const series = panel.querySelector('[data-replace-series]').value;
                return replacementProducts.filter((product) => {
                    if (category && String(product.category_id ?? '') !== category) return false;
                    if (brand && product.brand !== brand) return false;
                    if (series && product.series !== series) return false;
                    return true;
                });
            }

            function fillProductSelect(panel, selectedId) {
                const select = panel.querySelector('[data-replace-product]');
                select.innerHTML = '<option value="">' + @json(__('Pilih Produk')) + '</option>'
                    + filteredProducts(panel).map((product) => {
                        const name = [product.sku, product.brand, product.series, product.color].filter(Boolean).join(' · ');
                        return optionHtml(product.id, name);
                    }).join('');
                if (selectedId) select.value = String(selectedId);
            }

            function refreshBrandSeries(panel) {
                const category = panel.querySelector('[data-replace-category]').value;
                const brandSelect = panel.querySelector('[data-replace-brand]');
                const seriesSelect = panel.querySelector('[data-replace-series]');
                const previousBrand = brandSelect.value;
                const previousSeries = seriesSelect.value;
                const inCategory = replacementProducts.filter((product) => !category || String(product.category_id ?? '') === category);
                fillSelect(brandSelect, inCategory.map((product) => product.brand), @json(__('Semua Brand')), previousBrand);
                const brand = brandSelect.value;
                const inBrand = inCategory.filter((product) => !brand || product.brand === brand);
                fillSelect(seriesSelect, inBrand.map((product) => product.series), @json(__('Semua Series')), previousSeries);
                fillProductSelect(panel, panel.querySelector('[data-replace-product]').value);
            }

            async function setupReplacementCard(panel) {
                await ensureReplacementProducts();
                const category = panel.querySelector('[data-replace-category]');
                const seen = new Set();
                const categories = [];
                replacementProducts.forEach((product) => {
                    const id = String(product.category_id ?? '');
                    if (!id || seen.has(id)) return;
                    seen.add(id);
                    categories.push({ id: id, name: product.category_name || id });
                });
                categories.sort((a, b) => a.name.localeCompare(b.name));
                category.innerHTML = '<option value="">' + @json(__('Semua Kategori')) + '</option>'
                    + categories.map((item) => optionHtml(item.id, item.name)).join('');
                refreshBrandSeries(panel);
            }

            function applyReplacement(panel, unit) {
                const needle = String(unit.serial || '').toLowerCase();
                const used = Array.from(unitRows.querySelectorAll('[data-exchange-panel]')).some((other) => {
                    if (other === panel || other.hasAttribute('hidden')) return false;
                    return (other.querySelector('[data-field="new_serial"]').value || '').toLowerCase() === needle;
                });
                if (used) {
                    alert(@json(__('Nomor serial ini sudah dipilih pada baris tukar lain.')));
                    return;
                }
                panel.querySelector('[data-field="new_serial"]').value = unit.serial || '';
                panel.querySelector('[data-replacement]').textContent = [unit.sku, unit.product].filter(Boolean).join(' · ');
                const difference = Math.round((Number(unit.price) || 0) - Number(panel.dataset.oldPrice || 0));
                panel.querySelector('[data-field="price_difference"]').value = String(difference);
                panel.querySelector('[data-payment-wrap]').classList.toggle('hidden', difference === 0);
                panel.querySelector('[data-replace-results]').classList.add('hidden');
            }

            async function loadPanelSerials(panel, preferred) {
                const productId = panel.querySelector('[data-replace-product]').value;
                const select = panel.querySelector('[data-replace-serial]');
                if (!productId || !saleInput.value) {
                    select.innerHTML = '';
                    panel._serialHits = [];
                    return;
                }
                let url = serialUrl + '?sale_id=' + encodeURIComponent(saleInput.value) + '&product_id=' + encodeURIComponent(productId);
                const q = panel.querySelector('[data-replace-serial-search]').value.trim();
                if (q.length >= 2) url += '&q=' + encodeURIComponent(q);
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                const units = data.units || [];
                panel._serialHits = units;
                select.innerHTML = units.map((unit) => '<option value="' + esc(unit.serial) + '">' + esc(unit.serial) + '</option>').join('');
                if (preferred) select.value = preferred;
            }

            async function selectProductForUnit(panel, unit) {
                if (!panel.querySelector('[data-replace-category]').options.length) {
                    await setupReplacementCard(panel);
                }
                const category = panel.querySelector('[data-replace-category]');
                const brand = panel.querySelector('[data-replace-brand]');
                const series = panel.querySelector('[data-replace-series]');
                const product = panel.querySelector('[data-replace-product]');
                category.value = unit.category_id ? String(unit.category_id) : '';
                brand.value = '';
                series.value = '';
                product.value = '';
                refreshBrandSeries(panel);
                brand.value = unit.brand || '';
                refreshBrandSeries(panel);
                series.value = unit.series || '';
                fillProductSelect(panel, unit.product_id);
                await loadPanelSerials(panel, unit.serial);
                applyReplacement(panel, unit);
            }

            async function searchReplacement(panel) {
                const input = panel.querySelector('[data-replace-lookup]');
                const box = panel.querySelector('[data-replace-results]');
                const q = input.value.trim();
                if (q.length < 2 || !saleInput.value) {
                    box.classList.add('hidden');
                    return;
                }
                const response = await fetch(serialUrl + '?sale_id=' + encodeURIComponent(saleInput.value) + '&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                const units = data.units || [];
                const exact = units.find((unit) => String(unit.serial).toLowerCase() === q.toLowerCase());
                if (exact) {
                    box.classList.add('hidden');
                    input.value = '';
                    await selectProductForUnit(panel, exact);
                    return;
                }
                panel._lookupHits = units;
                box.innerHTML = units.length
                    ? units.map((unit, index) => '<button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50" data-replace-hit="' + index + '">' + esc(unit.serial) + ' · ' + esc(unit.sku) + ' · ' + esc(unit.product) + '</button>').join('')
                    : '<p class="px-3 py-2 text-sm text-slate-500">' + @json(__('Serial stok tidak ditemukan di lokasi ini.')) + '</p>';
                box.classList.remove('hidden');
            }

            unitRows.addEventListener('click', async function (event) {
                const toggle = event.target.closest('.exchange-toggle');
                if (toggle) {
                    const panel = toggle.closest('tr').nextElementSibling;
                    const open = panel.hasAttribute('hidden');
                    panel.toggleAttribute('hidden', !open);
                    panel.querySelectorAll('[data-field]').forEach((field) => { field.disabled = !open; });
                    toggle.textContent = open ? @json(__('Batal Tukar')) : @json(__('Tukar'));
                    if (open && !panel.querySelector('[data-replace-category]').options.length) {
                        setupReplacementCard(panel);
                    }
                    return;
                }
                const hit = event.target.closest('[data-replace-hit]');
                if (!hit) return;
                const panel = hit.closest('[data-exchange-panel]');
                const unit = (panel._lookupHits || [])[Number(hit.dataset.replaceHit)];
                if (unit) await selectProductForUnit(panel, unit);
            });

            unitRows.addEventListener('change', async function (event) {
                const field = event.target;
                const panel = field.closest('[data-exchange-panel]');
                if (!panel) return;
                if (field.matches('[data-replace-category], [data-replace-brand]')) {
                    if (field.matches('[data-replace-category]')) {
                        panel.querySelector('[data-replace-brand]').value = '';
                    }
                    panel.querySelector('[data-replace-series]').value = '';
                    panel.querySelector('[data-replace-product]').value = '';
                    refreshBrandSeries(panel);
                    panel.querySelector('[data-replace-serial]').innerHTML = '';
                    return;
                }
                if (field.matches('[data-replace-series]')) {
                    panel.querySelector('[data-replace-product]').value = '';
                    fillProductSelect(panel, '');
                    panel.querySelector('[data-replace-serial]').innerHTML = '';
                    return;
                }
                if (field.matches('[data-replace-product]')) {
                    await loadPanelSerials(panel);
                    return;
                }
                if (field.matches('[data-replace-serial]')) {
                    const unit = (panel._serialHits || []).find((item) => item.serial === field.value);
                    if (unit) applyReplacement(panel, unit);
                }
            });

            unitRows.addEventListener('input', function (event) {
                const field = event.target;
                const panel = field.closest('[data-exchange-panel]');
                if (!panel) return;
                if (field.dataset.field === 'price_difference') {
                    const needed = Number(field.value) !== 0;
                    panel.querySelector('[data-payment-wrap]').classList.toggle('hidden', !needed);
                    return;
                }
                if (field.matches('[data-replace-lookup], [data-replace-serial-search]')) {
                    clearTimeout(field.searchTimer);
                    field.searchTimer = setTimeout(() => {
                        if (field.matches('[data-replace-lookup]')) searchReplacement(panel);
                        else loadPanelSerials(panel);
                    }, 250);
                }
            });

            unitRows.addEventListener('keydown', async function (event) {
                if (event.key !== 'Enter') return;
                const field = event.target;
                if (!field.matches('[data-replace-scan]') && !field.matches('[data-replace-lookup]')) return;
                event.preventDefault();
                const panel = field.closest('[data-exchange-panel]');
                const q = field.value.trim();
                if (q.length < 2 || !saleInput.value) return;
                const response = await fetch(serialUrl + '?sale_id=' + encodeURIComponent(saleInput.value) + '&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                const units = data.units || [];
                const exact = units.find((unit) => String(unit.serial).toLowerCase() === q.toLowerCase()) || (units.length === 1 ? units[0] : null);
                if (!exact) return;
                field.value = '';
                await selectProductForUnit(panel, exact);
            });

            form.addEventListener('submit', function () {
                let index = 0;
                form.querySelectorAll('[data-exchange-panel]').forEach((panel) => {
                    const enabled = !panel.hasAttribute('hidden');
                    panel.querySelectorAll('[data-field]').forEach((field) => {
                        if (!enabled) {
                            field.disabled = true;
                            field.removeAttribute('name');
                            return;
                        }
                        field.disabled = false;
                        field.name = 'items[' + index + '][' + field.dataset.field + ']';
                    });
                    if (enabled) index += 1;
                });
            });

            function applyPresets() {
                presetItems.forEach((item) => {
                    unitRows.querySelectorAll('[data-exchange-panel]').forEach((panel) => {
                        const oldField = panel.querySelector('[data-field="old_serial"]');
                        if (!oldField || oldField.value !== item.old_serial || !panel.hasAttribute('hidden')) return;
                        const toggle = panel.previousElementSibling?.querySelector('.exchange-toggle');
                        if (toggle) toggle.click();
                        panel.querySelector('[data-field="new_serial"]').value = item.new_serial || '';
                        panel.querySelector('[data-field="price_difference"]').value = item.price_difference ?? 0;
                        const pay = panel.querySelector('[data-field="payment_method_id"]');
                        if (pay && item.payment_method_id) pay.value = String(item.payment_method_id);
                        panel.querySelector('[data-payment-wrap]').classList.toggle('hidden', Number(item.price_difference) === 0);
                        const label = panel.querySelector('[data-replacement]');
                        if (label) label.textContent = item.label || '';
                    });
                });
            }

            if (initialInvoice) {
                renderUnits(initialInvoice);
                applyPresets();
            }
        </script>
    @endpush
</x-app-layout>
