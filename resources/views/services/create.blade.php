<x-app-layout>
    <x-slot name="title">{{ __('Tambah Servis') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Service Laptop Baru') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('services.store') }}" id="service-form">
                        @csrf
                        <div class="space-y-4">
                            <div class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4">
                                <x-input-label :value="__('Status Transaksi')" class="font-semibold" />
                                <p class="text-xs text-slate-600 mt-1 mb-3">{{ __('Pilih Open: hanya input pelanggan & info laptop. Pilih Release: input lengkap termasuk material dan pembayaran.') }}</p>
                                <div class="flex gap-6">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="status" value="open" {{ old('status', 'open') === 'open' ? 'checked' : '' }} id="status_open" class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Open') }}</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="status" value="release" {{ old('status') === 'release' ? 'checked' : '' }} id="status_release" class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Release') }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    @if($branches->count() === 1)
                                        <x-locked-location label="{{ __('Cabang') }}" :value="__('Cabang') . ': ' . $branches->first()->name" />
                                        <input type="hidden" id="branch_id" name="branch_id" value="{{ $branches->first()->id }}">
                                    @else
                                        <x-input-label for="branch_id" :value="__('Cabang')" />
                                        <select id="branch_id" name="branch_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                            <option value="">{{ __('Pilih Cabang') }}</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="entry_date" :value="__('Tanggal Masuk')" />
                                    <x-text-input id="entry_date" class="block mt-1 w-full" type="date" name="entry_date" :value="old('entry_date', date('Y-m-d'))" required />
                                    <x-input-error :messages="$errors->get('entry_date')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="customer_id" :value="__('Pelanggan')" />
                                <select id="customer_id" name="customer_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Pilih Pelanggan (atau isi pelanggan baru)') }}</option>
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
                                    <x-input-label for="customer_new_name" :value="__('Nama Pelanggan Baru')" />
                                    <x-text-input id="customer_new_name" class="block mt-1 w-full" type="text" name="customer_new_name" :value="old('customer_new_name')" placeholder="Nama pelanggan" />
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

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="laptop_type" :value="__('Jenis Laptop')" />
                                    <x-text-input id="laptop_type" class="block mt-1 w-full" type="text" name="laptop_type" :value="old('laptop_type')" placeholder="Contoh: ASUS ROG, Lenovo ThinkPad" required />
                                    <x-input-error :messages="$errors->get('laptop_type')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="laptop_detail" :value="__('Detail Laptop')" />
                                    <x-text-input id="laptop_detail" class="block mt-1 w-full" type="text" name="laptop_detail" :value="old('laptop_detail')" placeholder="Spesifikasi, serial number, dll" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="damage_description" :value="__('Kerusakan')" />
                                <textarea id="damage_description" name="damage_description" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="3" placeholder="Deskripsi kerusakan">{{ old('damage_description') }}</textarea>
                                <x-input-error :messages="$errors->get('damage_description')" class="mt-2" />
                            </div>

                            <div id="release-only-section" style="display: none;">
                            <div id="materials-section" class="border rounded-lg p-4 bg-slate-50">
                                <p class="font-semibold text-slate-800">{{ __('Bahan/Material Service') }}</p>
                                <p class="text-xs text-slate-500 mt-1">{{ __('Isi material yang diganti/dibeli (opsional).') }}</p>
                                <div class="mt-3 flex justify-between items-center">
                                    <button type="button" id="add-material" class="inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah') }}</button>
                                </div>
                                <div id="material-rows" class="mt-3 space-y-2"></div>
                                <x-input-error :messages="$errors->get('materials')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="service_fee" :value="__('Biaya Jasa Service')" />
                                <x-text-input id="service_fee" class="block mt-1 w-full" type="text" name="service_fee" data-rupiah="true" :value="old('service_fee', 0)" />
                                <x-input-error :messages="$errors->get('service_fee')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="description" :value="__('Keterangan (Opsional)')" />
                                <textarea id="description" name="description" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2">{{ old('description') }}</textarea>
                            </div>

                            <div id="payments-section" class="border rounded-lg p-4 bg-slate-50">
                                <p class="font-semibold text-slate-800">{{ __('Pembayaran (Wajib untuk Release)') }}</p>
                                <p class="text-xs text-amber-700 mt-1">{{ __('Service Release wajib membayar minimal. Boleh kurang dari total service.') }}</p>
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
                            </div>

                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Simpan Service') }}</x-primary-button>
                                <a href="{{ route('services.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @php
        $createPaymentMethods = ($paymentMethods ?? collect())->map(fn ($m) => ['id' => $m->id, 'label' => $m->display_label])->values()->toArray();
        $saldoMapBranch = $saldoMapBranch ?? [];
    @endphp
    <script>
        let paymentMethods = @json($createPaymentMethods);
        let saldoMapBranch = Object.assign({}, @json($saldoMapBranch));
        const paymentRows = document.getElementById('payment-rows');
        let paymentIndex = 0;
        const formDataUrl = @json(route('data-by-location.form-data', [], false));
        const appBase = @json(request()->getBaseUrl());

        async function loadFormDataForBranch() {
            const branchId = document.getElementById('branch_id')?.value;
            if (!branchId) {
                paymentMethods = [];
                saldoMapBranch = {};
                const custSel = document.getElementById('customer_id');
                if (custSel) custSel.innerHTML = '<option value="">' + @json(__('Pilih cabang dulu')) + '</option>';
                document.querySelectorAll('#payment-rows select[name*="payment_method_id"]').forEach(sel => { sel.innerHTML = '<option value="">Pilih metode</option>'; });
                document.querySelectorAll('#material-rows select[name*="[payment_method_id]"]').forEach(sel => { sel.innerHTML = '<option value="">Sumber dana</option>'; });
                return;
            }
            try {
                const url = new URL(appBase + formDataUrl, window.location.origin);
                url.searchParams.set('location_type', 'branch');
                url.searchParams.set('location_id', branchId);
                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Fetch failed');
                const data = await res.json();
                paymentMethods = (data.payment_methods || []).map(m => ({ id: m.id, label: m.label }));
                saldoMapBranch[branchId] = data.saldo_per_pm || {};
                const customers = data.customers || [];
                const custSel = document.getElementById('customer_id');
                if (custSel) {
                    custSel.innerHTML = '<option value="">' + @json(__('Pilih Pelanggan (atau isi pelanggan baru)')) + '</option>' +
                        customers.map(c => '<option value="' + c.id + '">' + (c.name || '') + (c.phone ? ' - ' + c.phone : '') + '</option>').join('');
                }
                document.querySelectorAll('#payment-rows select[name*="payment_method_id"]').forEach(sel => {
                    const oldVal = sel.value;
                    sel.innerHTML = '<option value="">Pilih metode</option>' + paymentMethods.map(m => '<option value="' + m.id + '">' + (m.label || '') + '</option>').join('');
                    if (oldVal && paymentMethods.some(m => m.id == oldVal)) sel.value = oldVal;
                });
                if (typeof refreshMaterialPaymentOptions === 'function') refreshMaterialPaymentOptions();
            } catch (e) { console.error('loadFormDataForBranch failed', e); }
        }

        document.getElementById('branch_id')?.addEventListener('change', loadFormDataForBranch);
        if (document.getElementById('branch_id')?.value) loadFormDataForBranch();

        function toggleReleaseSection() {
            const isRelease = document.getElementById('status_release')?.checked;
            const section = document.getElementById('release-only-section');
            if (section) section.style.display = isRelease ? '' : 'none';
            const feeEl = document.getElementById('service_fee');
            if (feeEl) feeEl.toggleAttribute('required', isRelease);
            document.querySelectorAll('#payment-rows select[name*="payment_method_id"], #payment-rows input[name*="[amount]"]').forEach(el => {
                el.toggleAttribute('required', isRelease);
            });
            if (!isRelease) {
                if (feeEl) feeEl.value = '0';
                document.getElementById('payment-rows')?.querySelectorAll('.remove-payment').forEach(btn => btn.click());
            } else if (document.getElementById('payment-rows')?.children.length === 0) {
                addPaymentRow();
            }
        }
        document.getElementById('status_open')?.addEventListener('change', toggleReleaseSection);
        document.getElementById('status_release')?.addEventListener('change', toggleReleaseSection);
        toggleReleaseSection();

        function paymentOptionsHtml() {
            return '<option value="">Pilih metode</option>' + paymentMethods.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
        }
        function currentBranchId() {
            const branchSelect = document.getElementById('branch_id');
            if (branchSelect && branchSelect.value) return String(branchSelect.value);
            if (branchSelect && branchSelect.options.length === 1) return String(branchSelect.options[0].value);
            return '';
        }
        function materialPaymentOptionsHtml() {
            const branchId = currentBranchId();
            return '<option value="">Sumber dana</option>' + paymentMethods.map(m => {
                const saldo = branchId && saldoMapBranch?.[branchId]?.[m.id] !== undefined ? Number(saldoMapBranch[branchId][m.id]) : 0;
                const disabled = branchId === '' || saldo <= 0;
                return `<option value="${m.id}" ${disabled ? 'disabled' : ''}>${m.label} (Saldo: ${Number(saldo).toLocaleString('id-ID')})</option>`;
            }).join('');
        }

        function addPaymentRow(pref = {}) {
            if (!paymentRows) return;
            const idx = paymentIndex++;
            const div = document.createElement('div');
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
                const nameInput = row?.querySelector('input[name*="[name]"]');
                const priceInput = row?.querySelector('input[name*="[price]"]');
                const pmSelect = row?.querySelector('select[name*="[payment_method_id]"]');
                const nameVal = String(nameInput?.value || '').trim();
                const pmVal = String(pmSelect?.value || '').trim();
                if (!nameVal) return;
                if (!pmVal) return;
                const qty = toQty(qtyInput.value || '0');
                const price = toNumber(priceInput?.value || '0');
                if (qty > 0 && price > 0) total += qty * price;
            });
            return total;
        }

        function refreshPaymentSum() {
            const fee = toNumber(document.getElementById('service_fee')?.value || '0') || 0;
            const totalPrice = fee + totalMaterialsPrice();
            let sum = 0;
            document.querySelectorAll('#payment-rows input[name*="[amount]"]').forEach(inp => {
                const v = toNumber(inp.value || '0');
                if (v > 0) sum += v;
            });
            const sumEl = document.getElementById('paymentSumText');
            const diffEl = document.getElementById('paymentDiffText');
            if (sumEl) sumEl.textContent = Number(sum).toLocaleString('id-ID');
            if (diffEl) diffEl.textContent = Number(totalPrice - sum).toLocaleString('id-ID');
        }

        document.getElementById('add-payment')?.addEventListener('click', () => addPaymentRow());
        document.getElementById('service_fee')?.addEventListener('input', refreshPaymentSum);

        const materialRows = document.getElementById('material-rows');
        let materialIndex = 0;
        function addMaterialRow(pref = {}) {
            if (!materialRows) return;
            const idx = materialIndex++;
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 md:grid-cols-6 gap-2 items-end';
            div.innerHTML = `
                <div class="md:col-span-2">
                    <input type="text" name="materials[${idx}][name]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nama material" required>
                </div>
                <div>
                    <input type="number" name="materials[${idx}][quantity]" step="0.01" min="0.01" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Qty" required>
                </div>
                <div>
                    <select name="materials[${idx}][payment_method_id]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        ${materialPaymentOptionsHtml()}
                    </select>
                </div>
                <div>
                    <input type="text" name="materials[${idx}][price]" data-rupiah="true" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Harga" required>
                </div>
                <div class="flex gap-2">
                    <input type="text" name="materials[${idx}][notes]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Catatan">
                    <button type="button" class="remove-material px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200">-</button>
                </div>
            `;
            materialRows.appendChild(div);
            if (pref.name) div.querySelector('input[name*="[name]"]').value = String(pref.name);
            if (pref.quantity) div.querySelector('input[name*="[quantity]"]').value = String(pref.quantity);
            if (pref.payment_method_id) div.querySelector('select[name*="[payment_method_id]"]').value = String(pref.payment_method_id);
            if (pref.price) div.querySelector('input[name*="[price]"]').value = String(pref.price);
            if (pref.notes) div.querySelector('input[name*="[notes]"]').value = String(pref.notes || '');
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            div.querySelectorAll('input').forEach(el => el.addEventListener('input', refreshPaymentSum));
            div.querySelector('.remove-material')?.addEventListener('click', () => { div.remove(); refreshPaymentSum(); });
        }

        function refreshMaterialPaymentOptions() {
            document.querySelectorAll('#material-rows select[name*="[payment_method_id]"]').forEach(select => {
                const current = select.value;
                select.innerHTML = materialPaymentOptionsHtml();
                if (current) select.value = current;
            });
        }

        const initialStatus = @json(old('status', 'open'));
        const oldPayments = @json(old('payments', []));
        if (initialStatus === 'release') {
            if (Array.isArray(oldPayments) && oldPayments.length > 0) {
                oldPayments.forEach(p => addPaymentRow(p));
            } else {
                addPaymentRow();
            }
        }
        refreshPaymentSum();

        const oldMaterials = @json(old('materials', []));
        if (initialStatus === 'release' && Array.isArray(oldMaterials) && oldMaterials.length > 0) {
            oldMaterials.forEach(m => addMaterialRow(m));
        }
        document.getElementById('add-material')?.addEventListener('click', () => addMaterialRow());
        refreshPaymentSum();
        document.getElementById('branch_id')?.addEventListener('change', refreshMaterialPaymentOptions);
        refreshMaterialPaymentOptions();

        const customerSelect = document.getElementById('customer_id');
        const newCustomerFields = document.getElementById('new-customer-fields');
        const toggleCustomerFields = () => {
            const hasCustomer = !!(customerSelect && customerSelect.value);
            if (newCustomerFields) newCustomerFields.style.display = hasCustomer ? 'none' : '';
        };
        customerSelect?.addEventListener('change', toggleCustomerFields);
        toggleCustomerFields();
    </script>
</x-app-layout>
