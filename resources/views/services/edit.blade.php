<x-app-layout>
    <x-slot name="title">{{ __('Edit Servis') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Service') }}: {{ $service->invoice_number }}</h2>
            <x-icon-btn-back :href="route('services.show', $service)" :label="__('Kembali')" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('services.update', $service) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="branch_id" value="{{ $service->branch_id }}">
                        <div class="space-y-4">
                            <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="mark_release" value="1" id="mark_release" {{ old('mark_release') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Ubah ke Release (wajib input material dan pembayaran)') }}</span>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="branch_display" :value="__('Cabang')" />
                                    <x-text-input id="branch_display" class="block mt-1 w-full bg-slate-100" type="text" :value="$service->branch?->name" disabled />
                                </div>
                                <div>
                                    <x-input-label for="entry_date" :value="__('Tanggal Masuk')" />
                                    <x-text-input id="entry_date" class="block mt-1 w-full" type="date" name="entry_date" :value="old('entry_date', $service->entry_date->toDateString())" required />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="customer_id" :value="__('Pelanggan')" />
                                <select id="customer_id" name="customer_id" class="block mt-1 w-full rounded-md border-gray-300">
                                    <option value="">{{ __('Pilih Pelanggan') }}</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" {{ old('customer_id', $service->customer_id) == $c->id ? 'selected' : '' }}>
                                            {{ $c->name }}{{ $c->phone ? ' - '.$c->phone : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="new-customer-fields" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="customer_new_name" :value="__('Nama Pelanggan Baru')" />
                                    <x-text-input id="customer_new_name" class="block mt-1 w-full" type="text" name="customer_new_name" :value="old('customer_new_name')" />
                                </div>
                                <div>
                                    <x-input-label for="customer_new_phone" :value="__('No. HP')" />
                                    <x-text-input id="customer_new_phone" class="block mt-1 w-full" type="text" name="customer_new_phone" :value="old('customer_new_phone')" />
                                </div>
                                <div>
                                    <x-input-label for="customer_new_address" :value="__('Alamat')" />
                                    <x-text-input id="customer_new_address" class="block mt-1 w-full" type="text" name="customer_new_address" :value="old('customer_new_address')" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="laptop_type" :value="__('Jenis Laptop')" />
                                    <x-text-input id="laptop_type" class="block mt-1 w-full" type="text" name="laptop_type" :value="old('laptop_type', $service->laptop_type)" required />
                                </div>
                                <div>
                                    <x-input-label for="laptop_detail" :value="__('Detail Laptop')" />
                                    <x-text-input id="laptop_detail" class="block mt-1 w-full" type="text" name="laptop_detail" :value="old('laptop_detail', $service->laptop_detail)" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="damage_description" :value="__('Kerusakan')" />
                                <textarea id="damage_description" name="damage_description" class="block mt-1 w-full rounded-md border-gray-300" rows="3">{{ old('damage_description', $service->damage_description) }}</textarea>
                            </div>

                            @if (($service->sparePartServicePurchases ?? collect())->isNotEmpty())
                                <div class="rounded-lg border border-indigo-200 bg-indigo-50/40 p-4 text-sm text-slate-800">
                                    <p class="font-semibold text-indigo-900">{{ __('Sparepart dari Pembelian (terhubung)') }}</p>
                                    <p class="mt-1 text-xs text-slate-600">{{ __('Baris berikut dihitung otomatis dari menu Pembelian — jenis Pembelian Sparepart Service.') }}</p>
                                    <ul class="mt-2 space-y-1 list-disc list-inside">
                                        @foreach ($service->sparePartServicePurchases as $pur)
                                            <li>
                                                <a href="{{ route('purchases.show', $pur) }}" class="text-indigo-700 hover:underline font-medium">{{ $pur->invoice_number }}</a>
                                                <span class="text-slate-600">— {{ $pur->purchase_date?->format('d/m/Y') }} — Rp {{ number_format((float) $pur->total, 0, ',', '.') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div id="materials-section" class="border rounded-lg p-4 bg-slate-50">
                                <p class="font-semibold text-slate-800">{{ __('Bahan/Material Service (input manual, opsional)') }}</p>
                                <div id="material-rows" class="mt-3 space-y-2"></div>
                                <button type="button" id="add-material" class="mt-2 inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah') }}</button>
                            </div>

                            @php
                                $sparepartInvoiceTotal = ($service->sparePartServicePurchases ?? collect())->sum(fn ($p) => (float) ($p->total ?? 0));
                                $jasa = (float) old('service_fee', $service->service_price);
                                $totalBiayaService = $jasa + $sparepartInvoiceTotal;
                            @endphp

                            <div>
                                <x-input-label for="service_fee" :value="__('Biaya Jasa Service')" />
                                <x-text-input id="service_fee" class="block mt-1 w-full" type="text" name="service_fee" data-rupiah="true" :value="old('service_fee', $service->service_price)" required />
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-4 text-sm text-slate-800">
                                <p class="font-semibold">{{ __('Total Biaya Service') }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ __('Rumus: total invoice pembelian sparepart (terhubung) + biaya jasa service') }}</p>
                                <p id="total-biaya-service-amount" class="mt-2 text-xl font-bold text-indigo-700">Rp {{ number_format($totalBiayaService, 0, ',', '.') }}</p>
                            </div>

                            <div>
                                <x-input-label for="description" :value="__('Keterangan')" />
                                <textarea id="description" name="description" class="block mt-1 w-full rounded-md border-gray-300" rows="2">{{ old('description', $service->description) }}</textarea>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4 bg-slate-50/50">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="mark_picked_up" value="1" {{ old('mark_picked_up', $service->pickup_status === 'sudah_diambil') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Sudah Diambil') }}</span>
                                </label>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Status Pengambilan') }}</p>
                            </div>

                            <div class="border rounded-lg p-4 bg-slate-50">
                                <p class="font-semibold">{{ __('Metode Pembayaran') }}</p>
                                <div id="payment-rows" class="mt-3 space-y-2"></div>
                                <button type="button" id="add-payment" class="mt-2 inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah') }}</button>
                                <div class="mt-3 text-sm">
                                    <span>{{ __('Total') }}: </span><span id="paymentSumText">0</span>
                                    <span class="text-slate-500">({{ __('selisih') }} <span id="paymentDiffText">0</span>)</span>
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Update Service') }}</x-primary-button>
                                <a href="{{ route('services.show', $service) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 rounded-md">Batal</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @php
        $editPaymentMethods = ($paymentMethods ?? collect())->map(fn ($m) => ['id' => $m->id, 'label' => $m->display_label])->values()->toArray();
        $editOldPayments = old('payments', ($service->payments ?? collect())->map(fn ($p) => ['payment_method_id' => $p->payment_method_id, 'amount' => (float)$p->amount, 'notes' => $p->notes])->toArray());
        $editOldMaterials = old('materials', ($service->serviceMaterials ?? collect())->map(fn ($m) => ['product_id' => $m->product_id, 'quantity' => (int)$m->quantity, 'price' => (float)$m->price, 'notes' => $m->notes])->toArray());
        $editMaterialProducts = ($materialProducts ?? collect())->map(fn ($p) => [
            'id' => $p->id,
            'category_id' => (int) ($p->category_id ?? 0),
            'category_name' => (string) ($p->category_name ?? '-'),
            'label' => trim(($p->sku ?? '').' - '.($p->brand ?? '').' '.($p->series ?? '')),
            'stock_qty' => (int) ($p->stock_qty ?? 0),
        ])->values()->toArray();
        $saldoMapBranch = $saldoMapBranch ?? [];
    @endphp
    <script>
        const editPaymentMethods = @json($editPaymentMethods);
        const editOldPayments = @json($editOldPayments);
        const editOldMaterials = @json($editOldMaterials);
        const editMaterialProducts = @json($editMaterialProducts);
        const saldoMapBranch = @json($saldoMapBranch);
        const fixedBranchId = @json($service->branch_id);
        const sparepartPurchasesTotal = @json(round((float) (($service->sparePartServicePurchases ?? collect())->sum('total')), 2));
        const paymentRows = document.getElementById('payment-rows');
        let paymentIdx = 0;
        function paymentOpts() {
            return '<option value="">Pilih</option>' + editPaymentMethods.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
        }
        function materialProductOpts(selectedId = null) {
            return '<option value="">Pilih produk in-stock</option>' + editMaterialProducts.map(p => {
                const selected = selectedId && p.id == selectedId ? 'selected' : '';
                return `<option value="${p.id}" ${selected}>${p.label} (Stok: ${Number(p.stock_qty || 0).toLocaleString('id-ID')})</option>`;
            }).join('');
        }
        function materialCategoryOpts(selectedCategoryId = null) {
            const categoryMap = {};
            editMaterialProducts.forEach(p => {
                const cid = String(p.category_id || 0);
                if (cid === '0') return;
                if (!categoryMap[cid]) categoryMap[cid] = p.category_name || '-';
            });
            const keys = Object.keys(categoryMap).sort((a, b) => categoryMap[a].localeCompare(categoryMap[b], 'id'));
            return '<option value="">Pilih kategori</option>' + keys.map(cid => {
                const selected = selectedCategoryId && String(selectedCategoryId) === String(cid) ? 'selected' : '';
                return `<option value="${cid}" ${selected}>${categoryMap[cid]}</option>`;
            }).join('');
        }
        function materialProductOptsByCategory(selectedId = null, categoryId = null) {
            return '<option value="">Pilih produk in-stock</option>' + editMaterialProducts
                .filter(p => !categoryId || String(p.category_id || 0) === String(categoryId))
                .map(p => {
                    const selected = selectedId && p.id == selectedId ? 'selected' : '';
                    return `<option value="${p.id}" ${selected}>${p.label} (Stok: ${Number(p.stock_qty || 0).toLocaleString('id-ID')})</option>`;
                }).join('');
        }
        function addPayRow(p = {}) {
            const i = paymentIdx++;
            const div = document.createElement('div');
            div.className = 'flex gap-2 items-end';
            div.innerHTML = `
                <div class="flex-1"><select name="payments[${i}][payment_method_id]" class="block w-full rounded-md border-gray-300" required>${paymentOpts()}</select></div>
                <div class="w-40"><input type="text" name="payments[${i}][amount]" data-rupiah="true" class="block w-full rounded-md border-gray-300" placeholder="Nominal" required></div>
                <div class="flex-1"><input type="text" name="payments[${i}][notes]" class="block w-full rounded-md border-gray-300" placeholder="Catatan"></div>
                <button type="button" class="remove-pay px-3 py-2 bg-red-100 text-red-700 rounded">-</button>
            `;
            paymentRows.appendChild(div);
            if (p.payment_method_id) div.querySelector('select').value = p.payment_method_id;
            if (p.amount) div.querySelector('input[name*="[amount]"]').value = p.amount;
            if (p.notes) div.querySelector('input[name*="[notes]"]').value = p.notes || '';
            div.querySelector('.remove-pay')?.addEventListener('click', () => { div.remove(); refresh(); });
            div.querySelectorAll('select,input').forEach(el => el.addEventListener('input', refresh));
            if (window.attachRupiahFormatter) window.attachRupiahFormatter(div);
        }
        function toNumber(val) {
            if (typeof window.parseRupiahToNumber === 'function') {
                return window.parseRupiahToNumber(val);
            }
            const raw = String(val ?? '').replace(/[^\d]/g, '');
            return raw ? parseFloat(raw) : 0;
        }
        function toQty(val) {
            const num = parseFloat(String(val ?? '').replace(',', '.'));
            return Number.isFinite(num) ? num : 0;
        }

        function totalMaterialsPrice() {
            let total = 0;
            document.querySelectorAll('#material-rows input[name*="[quantity]"]').forEach((qtyInput) => {
                const row = qtyInput.closest('.grid');
                const productSelect = row?.querySelector('select[name*="[product_id]"]');
                const priceInput = row?.querySelector('input[name*="[price]"]');
                const productVal = String(productSelect?.value || '').trim();
                if (!productVal) return;
                const qty = toQty(qtyInput.value || 0);
                const price = toNumber(priceInput?.value || 0);
                if (qty > 0 && price > 0) total += qty * price;
            });
            return total;
        }

        function refresh() {
            const fee = toNumber(document.getElementById('service_fee')?.value || 0) || 0;
            const spare = Number(sparepartPurchasesTotal) || 0;
            const totalBiayaService = spare + fee;
            const totalBiayaEl = document.getElementById('total-biaya-service-amount');
            if (totalBiayaEl) {
                totalBiayaEl.textContent = 'Rp ' + Math.round(totalBiayaService).toLocaleString('id-ID');
            }
            let sum = 0;
            document.querySelectorAll('#payment-rows input[name*="[amount]"]').forEach(inp => sum += toNumber(inp.value || 0));
            document.getElementById('paymentSumText').textContent = sum.toLocaleString('id-ID');
            document.getElementById('paymentDiffText').textContent = (totalBiayaService - sum).toLocaleString('id-ID');
        }
        document.getElementById('add-payment')?.addEventListener('click', () => addPayRow());
        document.getElementById('service_fee')?.addEventListener('input', refresh);
        document.getElementById('service_fee')?.addEventListener('blur', refresh);
        if (Array.isArray(editOldPayments) && editOldPayments.length > 0) {
            editOldPayments.forEach(p => addPayRow(p));
        } else {
            addPayRow();
        }
        refresh();

        const materialRows = document.getElementById('material-rows');
        let materialIdx = 0;
        function addMaterialRow(pref = {}) {
            if (!materialRows) return;
            const i = materialIdx++;
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 md:grid-cols-5 gap-2 items-end';
            div.innerHTML = `
                <div class="md:col-span-2">
                    <select class="material-category-select block w-full rounded-md border-gray-300">
                        ${materialCategoryOpts(pref.category_id)}
                    </select>
                </div>
                <div class="md:col-span-1">
                    <select name="materials[${i}][product_id]" class="material-product-select block w-full rounded-md border-gray-300" required>
                        ${materialProductOptsByCategory(pref.product_id, pref.category_id)}
                    </select>
                </div>
                <div>
                    <input type="number" name="materials[${i}][quantity]" step="1" min="1" class="block w-full rounded-md border-gray-300" placeholder="Qty" required>
                </div>
                <div>
                    <input type="text" name="materials[${i}][price]" data-rupiah="true" class="block w-full rounded-md border-gray-300" placeholder="Harga" required>
                </div>
                <div class="flex gap-2">
                    <input type="text" name="materials[${i}][notes]" class="block w-full rounded-md border-gray-300" placeholder="Catatan">
                    <button type="button" class="remove-material px-3 py-2 bg-red-100 text-red-700 rounded">-</button>
                </div>
            `;
            materialRows.appendChild(div);
            const catSel = div.querySelector('.material-category-select');
            const prodSel = div.querySelector('.material-product-select');
            catSel?.addEventListener('change', () => {
                const currentProd = prodSel?.value || '';
                if (prodSel) {
                    prodSel.innerHTML = materialProductOptsByCategory(currentProd, catSel.value || '');
                    if (currentProd && !Array.from(prodSel.options).some(o => o.value === currentProd)) {
                        prodSel.value = '';
                    }
                }
                refresh();
            });
            if (pref.quantity) div.querySelector('input[name*="[quantity]"]').value = pref.quantity;
            if (pref.price) div.querySelector('input[name*="[price]"]').value = pref.price;
            if (pref.notes) div.querySelector('input[name*="[notes]"]').value = pref.notes || '';
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            div.querySelectorAll('input,select').forEach(el => el.addEventListener('input', refresh));
            div.querySelector('.remove-material')?.addEventListener('click', () => { div.remove(); refresh(); });
        }
        if (Array.isArray(editOldMaterials) && editOldMaterials.length > 0) {
            editOldMaterials.forEach(m => addMaterialRow(m));
        }
        document.getElementById('add-material')?.addEventListener('click', () => addMaterialRow());
        refresh();
        document.querySelectorAll('#material-rows select[name*="[product_id]"]').forEach(select => {
            const row = select.closest('.grid');
            const catSel = row?.querySelector('.material-category-select');
            const currentCat = catSel?.value || '';
            const current = select.value;
            if (catSel) catSel.innerHTML = materialCategoryOpts(currentCat);
            select.innerHTML = materialProductOptsByCategory(current, currentCat);
            if (current && Array.from(select.options).some(o => o.value === current)) select.value = current;
        });

        document.getElementById('customer_id')?.addEventListener('change', function() {
            document.getElementById('new-customer-fields').style.display = this.value ? 'none' : '';
        });
        document.getElementById('new-customer-fields').style.display = document.getElementById('customer_id')?.value ? 'none' : '';

        document.getElementById('mark_release')?.addEventListener('change', function() {
            const required = this.checked;
            document.querySelectorAll('#payment-rows select[name*="payment_method_id"], #payment-rows input[name*="[amount]"]').forEach(el => {
                el.toggleAttribute('required', required);
            });
            document.getElementById('service_fee')?.toggleAttribute('required', required);
        });
        if (document.getElementById('mark_release')?.checked) {
            document.querySelectorAll('#payment-rows select[name*="payment_method_id"], #payment-rows input[name*="[amount]"]').forEach(el => {
                el.setAttribute('required', 'required');
            });
            document.getElementById('service_fee')?.setAttribute('required', 'required');
        }

        setTimeout(() => refresh(), 0);
    </script>
</x-app-layout>
