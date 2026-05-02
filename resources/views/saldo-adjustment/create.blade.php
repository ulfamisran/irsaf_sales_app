<x-app-layout>
    <x-slot name="title">{{ __('Penyesuaian Saldo Baru') }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('saldo-adjustment.index') }}" class="text-slate-500 hover:text-slate-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Penyesuaian Saldo Baru') }}</h2>
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

        <form method="POST" action="{{ route('saldo-adjustment.store') }}" id="saldoAdjustmentForm" class="card-modern p-6 space-y-6">
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

            {{-- Type IN/OUT --}}
            <div id="type-section" class="hidden">
                <x-input-label :value="__('Jenis Penyesuaian')" />
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 rounded-lg border border-slate-300 p-3 cursor-pointer hover:bg-emerald-50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                        <input type="radio" name="type" value="IN" class="text-emerald-600 focus:ring-emerald-500" {{ old('type') === 'IN' ? 'checked' : '' }} required>
                        <span>
                            <span class="block font-medium text-emerald-700">{{ __('Pemasukan (IN)') }}</span>
                            <span class="block text-xs text-slate-500">{{ __('Tambah saldo kas') }}</span>
                        </span>
                    </label>
                    <label class="flex items-center gap-3 rounded-lg border border-slate-300 p-3 cursor-pointer hover:bg-red-50 has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                        <input type="radio" name="type" value="OUT" class="text-red-600 focus:ring-red-500" {{ old('type') === 'OUT' ? 'checked' : '' }} required>
                        <span>
                            <span class="block font-medium text-red-700">{{ __('Pengeluaran (OUT)') }}</span>
                            <span class="block text-xs text-slate-500">{{ __('Kurangi saldo kas') }}</span>
                        </span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('type')" class="mt-1" />
            </div>

            {{-- Payment method (sumber dana) --}}
            <div id="pm-section" class="hidden">
                <x-input-label for="payment_method_id" :value="__('Sumber Dana')" />
                <select id="payment_method_id" name="payment_method_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="">{{ __('Pilih Sumber Dana') }}</option>
                </select>
                <x-input-error :messages="$errors->get('payment_method_id')" class="mt-1" />
            </div>

            {{-- Saldo info --}}
            <div id="saldo-info" class="hidden rounded-xl border border-indigo-200 bg-indigo-50 p-5">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-indigo-800" id="saldo-label">{{ __('Saldo Saat Ini') }}</p>
                        <p class="text-2xl font-bold text-indigo-700" id="saldo-display">Rp 0</p>
                    </div>
                </div>
            </div>

            {{-- Amount --}}
            <div id="amount-section" class="hidden">
                <x-input-label for="amount_display" :value="__('Nominal')" />
                <div class="relative mt-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500 text-sm">Rp</span>
                    <input type="text" id="amount_display" class="block w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="0" value="{{ old('amount') ? number_format(old('amount'), 0, ',', '.') : '' }}" inputmode="numeric">
                </div>
                <input type="hidden" name="amount" id="amount" value="{{ old('amount') }}">
                <x-input-error :messages="$errors->get('amount')" class="mt-1" />
            </div>

            {{-- Date --}}
            <div id="date-section" class="hidden">
                <x-input-label for="transaction_date" :value="__('Tanggal')" />
                <input type="date" id="transaction_date" name="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                <x-input-error :messages="$errors->get('transaction_date')" class="mt-1" />
            </div>

            {{-- Description / Catatan --}}
            <div id="desc-section" class="hidden">
                <x-input-label for="description" :value="__('Catatan')" />
                <textarea id="description" name="description" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Alasan / penjelasan penyesuaian saldo (wajib diisi)...') }}" required>{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-1" />
            </div>

            {{-- Submit --}}
            <div id="submit-section" class="hidden flex justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('saldo-adjustment.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">{{ __('Batal') }}</a>
                <button type="submit" id="submitBtn" class="px-6 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    {{ __('Proses Penyesuaian Saldo') }}
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const locationType = document.getElementById('location_type');
        const locationId = document.getElementById('location_id');
        const typeSection = document.getElementById('type-section');
        const typeRadios = document.querySelectorAll('input[name="type"]');
        const pmSection = document.getElementById('pm-section');
        const pmSelect = document.getElementById('payment_method_id');
        const saldoInfo = document.getElementById('saldo-info');
        const saldoLabel = document.getElementById('saldo-label');
        const saldoDisplay = document.getElementById('saldo-display');
        const amountSection = document.getElementById('amount-section');
        const amountDisplay = document.getElementById('amount_display');
        const amountHidden = document.getElementById('amount');
        const dateSection = document.getElementById('date-section');
        const descSection = document.getElementById('desc-section');
        const description = document.getElementById('description');
        const submitSection = document.getElementById('submit-section');
        const submitBtn = document.getElementById('submitBtn');

        const warehouses = @json($warehouses);
        const branches = @json($branches);
        let allPaymentMethods = [];

        function formatRupiah(num) {
            return Math.floor(Math.abs(num)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        function parseRupiah(str) {
            return parseInt(String(str).replace(/\D/g, '')) || 0;
        }

        function populateLocationDropdown() {
            const type = locationType.value;
            locationId.innerHTML = '<option value="">' + @json(__('Pilih Lokasi')) + '</option>';
            const list = type === 'warehouse' ? warehouses : (type === 'branch' ? branches : []);
            list.forEach(function (loc) {
                const opt = document.createElement('option');
                opt.value = loc.id;
                opt.textContent = loc.name;
                locationId.appendChild(opt);
            });
            return list;
        }

        function resetAfterLocation() {
            allPaymentMethods = [];
            typeSection.classList.add('hidden');
            pmSection.classList.add('hidden');
            saldoInfo.classList.add('hidden');
            amountSection.classList.add('hidden');
            dateSection.classList.add('hidden');
            descSection.classList.add('hidden');
            submitSection.classList.add('hidden');
            pmSelect.innerHTML = '<option value="">' + @json(__('Pilih Sumber Dana')) + '</option>';
            typeRadios.forEach(r => { r.checked = false; });
            submitBtn.disabled = true;
        }

        locationType.addEventListener('change', function () {
            resetAfterLocation();
            const list = populateLocationDropdown();
            if (list.length === 1) {
                locationId.value = list[0].id;
                triggerLocationLoad();
            }
        });

        locationId.addEventListener('change', function () {
            resetAfterLocation();
            triggerLocationLoad();
        });

        function triggerLocationLoad() {
            const type = locationType.value;
            const id = locationId.value;
            if (!type || !id) return;
            loadFormData(type, id);
        }

        function loadFormData(type, id) {
            fetch(@json(route('saldo-adjustment.form-data')) + '?location_type=' + encodeURIComponent(type) + '&location_id=' + encodeURIComponent(id))
                .then(r => r.json())
                .then(function (data) {
                    allPaymentMethods = data.payment_methods || [];
                    typeSection.classList.remove('hidden');
                })
                .catch(function (err) { console.error('Form data error:', err); });
        }

        typeRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (allPaymentMethods.length === 0) {
                    return;
                }
                pmSelect.innerHTML = '<option value="">' + @json(__('Pilih Sumber Dana')) + '</option>';
                allPaymentMethods.forEach(function (pm) {
                    const opt = document.createElement('option');
                    opt.value = pm.id;
                    const saldoText = (pm.saldo < 0 ? '-' : '') + 'Rp ' + formatRupiah(pm.saldo);
                    opt.textContent = pm.label + ' (Saldo: ' + saldoText + ')';
                    opt.dataset.saldo = pm.saldo;
                    opt.dataset.label = pm.label;
                    pmSelect.appendChild(opt);
                });
                pmSection.classList.remove('hidden');
                saldoInfo.classList.add('hidden');
                amountSection.classList.add('hidden');
                dateSection.classList.add('hidden');
                descSection.classList.add('hidden');
                submitSection.classList.add('hidden');
                validateForm();
            });
        });

        pmSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!this.value) {
                saldoInfo.classList.add('hidden');
                amountSection.classList.add('hidden');
                dateSection.classList.add('hidden');
                descSection.classList.add('hidden');
                submitSection.classList.add('hidden');
                validateForm();
                return;
            }
            const saldo = parseFloat(opt.dataset.saldo || '0');
            const label = opt.dataset.label || '';
            saldoLabel.textContent = label + ' - ' + @json(__('Saldo'));
            saldoDisplay.textContent = (saldo < 0 ? '-' : '') + 'Rp ' + formatRupiah(saldo);
            saldoInfo.classList.remove('hidden');
            amountSection.classList.remove('hidden');
            dateSection.classList.remove('hidden');
            descSection.classList.remove('hidden');
            submitSection.classList.remove('hidden');
            validateForm();
        });

        amountDisplay.addEventListener('input', function () {
            const raw = parseRupiah(this.value);
            this.value = raw > 0 ? formatRupiah(raw) : '';
            amountHidden.value = raw > 0 ? raw : '';
            validateForm();
        });

        description.addEventListener('input', validateForm);

        function validateForm() {
            const typeOk = Array.from(typeRadios).some(r => r.checked);
            const pmOk = pmSelect.value !== '';
            const amt = parseRupiah(amountDisplay.value);
            const amtOk = amt > 0;
            const descOk = (description.value || '').trim().length > 0;
            submitBtn.disabled = !(typeOk && pmOk && amtOk && descOk);
        }

        // Auto-init dari old() (kalau form di-restore karena validasi gagal).
        (function autoInit() {
            const oldType = locationType.value;
            if (!oldType) return;
            const list = populateLocationDropdown();
            const oldLocId = @json(old('location_id', ''));
            if (oldLocId) {
                locationId.value = oldLocId;
                loadFormData(oldType, oldLocId);
            } else if (list.length === 1) {
                locationId.value = list[0].id;
                loadFormData(oldType, list[0].id);
            }
        })();
    });
    </script>
    @endpush
</x-app-layout>
