<x-app-layout>
    <x-slot name="title">{{ __('Penyewaan') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Penyewaan Laptop') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('rentals.store') }}" id="rental-form">
                        @csrf
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="warehouse_id" :value="__('Gudang')" />
                                    <select id="warehouse_id" name="warehouse_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="">{{ __('Pilih Gudang') }}</option>
                                        @foreach ($warehouses as $wh)
                                            <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                                {{ $wh->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p id="products_status" class="mt-1 text-xs text-slate-500"></p>
                                    <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="pickup_date" :value="__('Tanggal Pengambilan')" />
                                    <x-text-input id="pickup_date" class="block mt-1 w-full" type="date" name="pickup_date" :value="old('pickup_date', date('Y-m-d'))" required />
                                    <x-input-error :messages="$errors->get('pickup_date')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="return_date" :value="__('Tanggal Pengembalian')" />
                                    <x-text-input id="return_date" class="block mt-1 w-full" type="date" name="return_date" :value="old('return_date', date('Y-m-d'))" required />
                                    <x-input-error :messages="$errors->get('return_date')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label :value="__('Jumlah Hari')" />
                                    <x-text-input id="total_days" class="block mt-1 w-full bg-slate-50" type="text" value="1" readonly />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="customer_id" :value="__('Penyewa')" />
                                <select id="customer_id" name="customer_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Pilih Penyewa (atau isi penyewa baru)') }}</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
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

                            <div>
                                <x-input-label :value="__('Laptop yang Disewa')" />
                                <p class="mt-1 mb-3 text-xs text-slate-500">{{ __('Hanya laptop bekas di gudang yang bisa disewakan.') }}</p>
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
                                        <tbody id="rental-items">
                                            <tr class="rental-item">
                                                <td class="px-3 py-2">
                                                    <select name="items[0][product_id]" class="product-select block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                                        <option value="">{{ __('Pilih Produk') }}</option>
                                                    </select>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <div class="serial-box text-sm text-slate-600">
                                                        <p class="text-xs text-slate-500">{{ __('Pilih produk dulu') }}</p>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="text" name="items[0][rental_price]" data-rupiah="true" class="rental-price block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right" placeholder="Harga" required>
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <span class="line-total">0</span>
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <button type="button" class="remove-item px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 text-xs" style="display:none">{{ __('Hapus') }}</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" id="add-item" class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">+ {{ __('Tambah Item') }}</button>
                                <div id="items-hidden"></div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                    <div>
                                        <x-input-label for="tax_amount" :value="__('Pajak')" />
                                        <x-text-input id="tax_amount" class="block mt-1 w-full" type="text" name="tax_amount" data-rupiah="true" :value="old('tax_amount', 0)" />
                                        <x-input-error :messages="$errors->get('tax_amount')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="penalty_amount" :value="__('Denda')" />
                                        <x-text-input id="penalty_amount" class="block mt-1 w-full" type="text" name="penalty_amount" data-rupiah="true" :value="old('penalty_amount', 0)" />
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
                                <textarea id="description" name="description" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2">{{ old('description') }}</textarea>
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
                                <x-primary-button>{{ __('Simpan Penyewaan') }}</x-primary-button>
                                <a href="{{ route('rentals.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @php
        $createPaymentMethods = ($paymentMethods ?? collect())->map(fn ($m) => ['id' => $m->id, 'label' => $m->display_label])->values()->toArray();
    @endphp
    <script>
        const paymentMethods = @json($createPaymentMethods);
        const appBaseUrl = @json(request()->getBaseUrl());
        const availableProductsPath = @json(route('rentals.available-products', [], false));
        const availableSerialsPath = @json(route('rentals.available-serials', [], false));
        const availableProductsUrl = appBaseUrl + availableProductsPath;
        const availableSerialsUrl = appBaseUrl + availableSerialsPath;
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

        function productOptionsHtml() {
            return '<option value="">{{ __('Pilih Produk') }}</option>' + products.map(p =>
                '<option value="' + p.id + '">' +
                p.sku + ' - ' + p.brand + ' ' + (p.series || '') + '</option>'
            ).join('');
        }

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

        async function loadProductsForWarehouse() {
            const warehouseId = document.getElementById('warehouse_id')?.value;
            if (!warehouseId) {
                products = [];
                document.querySelectorAll('.product-select').forEach(sel => updateProductSelectOptions(sel));
                setProductsStatus('');
                return;
            }

            try {
                setProductsStatus('Memuat produk...');
                const url = new URL(availableProductsUrl, window.location.origin);
                url.searchParams.set('warehouse_id', warehouseId);
                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`available-products ${res.status}`);
                const data = await res.json();
                products = Array.isArray(data.products) ? data.products : [];
                document.querySelectorAll('.product-select').forEach(sel => updateProductSelectOptions(sel));
                setProductsStatus(
                    products.length > 0 ? `Produk tersedia: ${products.length}` : 'Tidak ada laptop bekas di gudang ini.',
                    products.length > 0 ? 'ok' : 'info'
                );
            } catch (e) {
                console.error('Failed to load products', e);
                setProductsStatus('Gagal memuat produk. Cek koneksi/login.', 'error');
            }
        }

        async function loadSerialsForRow(row) {
            const warehouseId = document.getElementById('warehouse_id')?.value;
            const productSelect = row.querySelector('.product-select');
            const serialBox = row.querySelector('.serial-box');
            if (!serialBox) return;

            const productId = productSelect?.value;
            if (!warehouseId || !productId) {
                serialBox.innerHTML = '<p class="text-xs text-slate-500">{{ __('Pilih produk dulu') }}</p>';
                return;
            }

            try {
                const url = new URL(availableSerialsUrl, window.location.origin);
                url.searchParams.set('warehouse_id', warehouseId);
                url.searchParams.set('product_id', productId);
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

        // Items
        let itemIndex = 1;
        document.getElementById('add-item')?.addEventListener('click', () => {
            const body = document.getElementById('rental-items');
            const tr = document.createElement('tr');
            tr.className = 'rental-item';
            tr.innerHTML = `
                <td class="px-3 py-2">
                    <select name="items[${itemIndex}][product_id]" class="product-select block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        ${productOptionsHtml()}
                    </select>
                </td>
                <td class="px-3 py-2">
                    <div class="serial-box text-sm text-slate-600">
                        <p class="text-xs text-slate-500">{{ __('Pilih produk dulu') }}</p>
                    </div>
                </td>
                <td class="px-3 py-2">
                    <input type="text" name="items[${itemIndex}][rental_price]" data-rupiah="true" class="rental-price block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-right" placeholder="Harga" required>
                </td>
                <td class="px-3 py-2 text-right">
                    <span class="line-total">0</span>
                </td>
                <td class="px-3 py-2 text-right">
                    <button type="button" class="remove-item px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 text-xs">{{ __('Hapus') }}</button>
                </td>
            `;
            body.appendChild(tr);
            itemIndex++;
            toggleRemoveButtons();
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
        });

        document.getElementById('rental-items')?.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-item')) {
                e.target.closest('tr').remove();
                toggleRemoveButtons();
                refreshTotals();
            }
        });

        document.getElementById('rental-items')?.addEventListener('change', (e) => {
            const row = e.target.closest('.rental-item');
            if (!row) return;
            if (e.target.classList.contains('product-select')) {
                loadSerialsForRow(row);
            }
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

        function toggleRemoveButtons() {
            const items = document.querySelectorAll('.rental-item');
            items.forEach((item) => {
                const btn = item.querySelector('.remove-item');
                if (btn) btn.style.display = items.length > 1 ? 'inline-block' : 'none';
            });
        }
        toggleRemoveButtons();

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
        const oldPayments = @json(old('payments', []));
        if (Array.isArray(oldPayments) && oldPayments.length > 0) {
            oldPayments.forEach(p => addPaymentRow(p));
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

        document.getElementById('warehouse_id')?.addEventListener('change', async function() {
            await loadProductsForWarehouse();
            document.querySelectorAll('.rental-item').forEach(row => loadSerialsForRow(row));
        });
        document.getElementById('pickup_date')?.addEventListener('change', refreshDays);
        document.getElementById('return_date')?.addEventListener('change', refreshDays);
        document.getElementById('tax_amount')?.addEventListener('input', refreshTotals);
        document.getElementById('penalty_amount')?.addEventListener('input', refreshTotals);

        function initRentalCreate() {
            loadProductsForWarehouse().then(() => {
                document.querySelectorAll('.rental-item').forEach(row => loadSerialsForRow(row));
            });
            refreshDays();
            refreshTotals();
        }
        function rebuildHiddenItems() {
            const container = document.getElementById('items-hidden');
            if (!container) return;
            container.innerHTML = '';
            let idx = 0;
            document.querySelectorAll('.rental-item').forEach(row => {
                const productId = row.querySelector('.product-select')?.value;
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

        document.getElementById('rental-form')?.addEventListener('submit', (e) => {
            rebuildHiddenItems();
        });
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initRentalCreate);
        } else {
            initRentalCreate();
        }
    </script>
</x-app-layout>
