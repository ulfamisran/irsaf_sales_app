<x-app-layout>
    <x-slot name="title">{{ __('Mutasi Dana') }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('mutasi-dana.index') }}" class="text-slate-500 hover:text-slate-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Mutasi Dana Baru') }}</h2>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto">
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('mutasi-dana.store') }}" id="mutasiForm" class="card-modern p-6 space-y-6">
            @csrf

            {{-- Location --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="location_type" :value="__('Tipe Lokasi')" />
                    <select id="location_type" name="location_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">{{ __('Pilih Tipe') }}</option>
                        @if ($warehouses->isNotEmpty())
                        <option value="warehouse" {{ old('location_type') === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                        @endif
                        @if ($branches->isNotEmpty())
                        <option value="branch" {{ old('location_type') === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                        @endif
                    </select>
                    <x-input-error :messages="$errors->get('location_type')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="location_id" :value="__('Lokasi')" />
                    <select id="location_id" name="location_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">{{ __('Pilih Lokasi') }}</option>
                    </select>
                    <x-input-error :messages="$errors->get('location_id')" class="mt-1" />
                </div>
            </div>

            {{-- Dana Asal --}}
            <div id="source-section" class="hidden">
                <x-input-label for="source_payment_method_id" :value="__('Dana Asal')" />
                <select id="source_payment_method_id" name="source_payment_method_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="">{{ __('Pilih Dana Asal') }}</option>
                </select>
                <x-input-error :messages="$errors->get('source_payment_method_id')" class="mt-1" />
            </div>

            {{-- Saldo Dana Asal Info --}}
            <div id="source-saldo-info" class="hidden rounded-xl border border-indigo-200 bg-indigo-50 p-5">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-indigo-800" id="source-label">{{ __('Saldo Dana Asal') }}</p>
                        <p class="text-2xl font-bold text-indigo-700" id="source-saldo-display">Rp 0</p>
                    </div>
                </div>
            </div>

            {{-- Dana Tujuan --}}
            <div id="dest-section" class="hidden">
                <x-input-label for="destination_payment_method_id" :value="__('Dana Tujuan')" />
                <select id="destination_payment_method_id" name="destination_payment_method_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="">{{ __('Pilih Dana Tujuan') }}</option>
                </select>
                <x-input-error :messages="$errors->get('destination_payment_method_id')" class="mt-1" />
            </div>

            {{-- Amount --}}
            <div id="amount-section" class="hidden">
                <x-input-label for="amount_display" :value="__('Nominal')" />
                <div class="relative mt-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500 text-sm">Rp</span>
                    <input type="text" id="amount_display" class="block w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="0" value="{{ old('amount') ? number_format(old('amount'), 0, ',', '.') : '' }}" inputmode="numeric">
                </div>
                <input type="hidden" name="amount" id="amount" value="{{ old('amount') }}">
                <p class="mt-1 text-xs text-slate-500" id="max-amount-hint"></p>
                <x-input-error :messages="$errors->get('amount')" class="mt-1" />
            </div>

            {{-- Date --}}
            <div id="date-section" class="hidden">
                <x-input-label for="transaction_date" :value="__('Tanggal')" />
                <input type="date" id="transaction_date" name="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                <x-input-error :messages="$errors->get('transaction_date')" class="mt-1" />
            </div>

            {{-- Description --}}
            <div id="desc-section" class="hidden">
                <x-input-label for="description" :value="__('Keterangan (Opsional)')" />
                <textarea id="description" name="description" rows="2" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Catatan tambahan...') }}">{{ old('description') }}</textarea>
            </div>

            {{-- Submit --}}
            <div id="submit-section" class="hidden flex justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('mutasi-dana.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">{{ __('Batal') }}</a>
                <button type="submit" id="submitBtn" class="px-6 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    {{ __('Proses Mutasi Dana') }}
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const locationType = document.getElementById('location_type');
        const locationId = document.getElementById('location_id');
        const sourceSection = document.getElementById('source-section');
        const sourceSelect = document.getElementById('source_payment_method_id');
        const sourceSaldoInfo = document.getElementById('source-saldo-info');
        const sourceLabel = document.getElementById('source-label');
        const sourceSaldoDisplay = document.getElementById('source-saldo-display');
        const destSection = document.getElementById('dest-section');
        const destSelect = document.getElementById('destination_payment_method_id');
        const amountSection = document.getElementById('amount-section');
        const amountDisplay = document.getElementById('amount_display');
        const amountHidden = document.getElementById('amount');
        const maxAmountHint = document.getElementById('max-amount-hint');
        const dateSection = document.getElementById('date-section');
        const descSection = document.getElementById('desc-section');
        const submitSection = document.getElementById('submit-section');
        const submitBtn = document.getElementById('submitBtn');

        const warehouses = @json($warehouses);
        const branches = @json($branches);
        let allPaymentMethods = [];
        let currentSourceSaldo = 0;

        function formatRupiah(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function parseRupiah(str) {
            return parseInt(String(str).replace(/\D/g, '')) || 0;
        }

        function populateLocationDropdown() {
            const type = locationType.value;
            locationId.innerHTML = '<option value="">' + @json(__('Pilih Lokasi')) + '</option>';

            const list = type === 'warehouse' ? warehouses : (type === 'branch' ? branches : []);
            list.forEach(function(loc) {
                const opt = document.createElement('option');
                opt.value = loc.id;
                opt.textContent = loc.name;
                locationId.appendChild(opt);
            });

            return list;
        }

        locationType.addEventListener('change', function() {
            resetForm();
            const list = populateLocationDropdown();

            if (list.length === 1) {
                locationId.value = list[0].id;
                triggerLocationLoad();
            }
        });

        locationId.addEventListener('change', function() {
            triggerLocationLoad();
        });

        function triggerLocationLoad() {
            resetForm();
            const type = locationType.value;
            const id = locationId.value;
            if (!type || !id) return;
            loadFormData(type, id);
        }

        function loadFormData(type, id) {
            fetch(@json(route('mutasi-dana.form-data')) + '?location_type=' + type + '&location_id=' + id)
                .then(r => r.json())
                .then(function(data) {
                    allPaymentMethods = data.payment_methods || [];

                    if (allPaymentMethods.length === 0) {
                        return;
                    }

                    sourceSelect.innerHTML = '<option value="">' + @json(__('Pilih Dana Asal')) + '</option>';
                    allPaymentMethods.forEach(function(pm) {
                        const opt = document.createElement('option');
                        opt.value = pm.id;
                        const saldoText = 'Rp ' + formatRupiah(Math.floor(pm.saldo));
                        opt.textContent = pm.label + ' (Saldo: ' + saldoText + ')';
                        if (pm.saldo <= 0) {
                            opt.disabled = true;
                            opt.textContent = pm.label + ' (Saldo: ' + saldoText + ' - Tidak tersedia)';
                        }
                        sourceSelect.appendChild(opt);
                    });

                    sourceSection.classList.remove('hidden');
                    validateForm();
                })
                .catch(function(err) {
                    console.error('Load form data error:', err);
                });
        }

        sourceSelect.addEventListener('change', function() {
            const selectedId = parseInt(this.value);
            sourceSaldoInfo.classList.add('hidden');
            destSection.classList.add('hidden');
            amountSection.classList.add('hidden');
            dateSection.classList.add('hidden');
            descSection.classList.add('hidden');
            submitSection.classList.add('hidden');
            currentSourceSaldo = 0;

            if (!selectedId) {
                validateForm();
                return;
            }

            const selectedPm = allPaymentMethods.find(pm => pm.id === selectedId);
            if (!selectedPm) return;

            currentSourceSaldo = selectedPm.saldo;
            sourceLabel.textContent = selectedPm.label + ' - ' + @json(__('Saldo'));
            sourceSaldoDisplay.textContent = 'Rp ' + formatRupiah(Math.floor(selectedPm.saldo));
            sourceSaldoInfo.classList.remove('hidden');

            destSelect.innerHTML = '<option value="">' + @json(__('Pilih Dana Tujuan')) + '</option>';
            allPaymentMethods.forEach(function(pm) {
                if (pm.id === selectedId) return;
                const opt = document.createElement('option');
                opt.value = pm.id;
                opt.textContent = pm.label;
                destSelect.appendChild(opt);
            });

            destSection.classList.remove('hidden');
            amountSection.classList.remove('hidden');
            dateSection.classList.remove('hidden');
            descSection.classList.remove('hidden');
            submitSection.classList.remove('hidden');
            maxAmountHint.textContent = @json(__('Maksimal')) + ': Rp ' + formatRupiah(Math.floor(currentSourceSaldo));
            validateForm();
        });

        function resetForm() {
            allPaymentMethods = [];
            currentSourceSaldo = 0;
            sourceSection.classList.add('hidden');
            sourceSaldoInfo.classList.add('hidden');
            destSection.classList.add('hidden');
            amountSection.classList.add('hidden');
            dateSection.classList.add('hidden');
            descSection.classList.add('hidden');
            submitSection.classList.add('hidden');
            sourceSelect.innerHTML = '<option value="">' + @json(__('Pilih Dana Asal')) + '</option>';
            destSelect.innerHTML = '<option value="">' + @json(__('Pilih Dana Tujuan')) + '</option>';
            submitBtn.disabled = true;
        }

        amountDisplay.addEventListener('input', function() {
            const raw = parseRupiah(this.value);
            this.value = raw > 0 ? formatRupiah(raw) : '';
            amountHidden.value = raw > 0 ? raw : '';
            validateForm();
        });

        destSelect.addEventListener('change', validateForm);

        function validateForm() {
            const sourceOk = sourceSelect.value !== '';
            const destOk = destSelect.value !== '';
            const amt = parseRupiah(amountDisplay.value);
            const amtOk = amt > 0 && amt <= currentSourceSaldo;
            submitBtn.disabled = !(sourceOk && destOk && amtOk);

            if (amt > currentSourceSaldo && amt > 0) {
                amountDisplay.classList.add('border-red-500');
                maxAmountHint.classList.add('text-red-500');
                maxAmountHint.classList.remove('text-slate-500');
            } else {
                amountDisplay.classList.remove('border-red-500');
                maxAmountHint.classList.remove('text-red-500');
                maxAmountHint.classList.add('text-slate-500');
            }
        }

        (function autoInit() {
            let autoType = locationType.value || '';

            if (!autoType) {
                if (warehouses.length >= 1 && branches.length === 0) {
                    autoType = 'warehouse';
                } else if (branches.length >= 1 && warehouses.length === 0) {
                    autoType = 'branch';
                }
            }

            if (!autoType) return;

            locationType.value = autoType;
            const list = populateLocationDropdown();

            let autoLocId = @json(old('location_id', ''));
            if (!autoLocId && list.length === 1) {
                autoLocId = list[0].id;
            }

            if (autoLocId) {
                locationId.value = autoLocId;
                loadFormData(autoType, autoLocId);
            }
        })();
    });
    </script>
    @endpush
</x-app-layout>
