<x-app-layout>
    <x-slot name="title">{{ __('Tambah Dana Masuk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Tambah Pemasukan Lainnya') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6" x-data="cashInForm">
                <form method="POST" action="{{ route('cash-flows.in.store') }}" class="space-y-4">
                    @csrf

                    @php
                        $canChooseLocation = auth()->user()->isSuperAdminOrAdminPusat();
                        $lockedToBranch = auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]) && auth()->user()->branch_id;
                        $lockedToWarehouse = auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_GUDANG]) && auth()->user()->warehouse_id;
                        $defaultLocationType = old('location_type', $lockedToWarehouse ? 'warehouse' : 'branch');
                    @endphp

                    @if($canChooseLocation)
                        <div>
                            <x-input-label :value="__('Tipe Lokasi')" />
                            <div class="mt-2 flex gap-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="location_type" value="branch" x-model="locationType" x-on:change="onLocationTypeChange()"
                                        {{ $defaultLocationType === 'branch' ? 'checked' : '' }}>
                                    <span class="ml-2">{{ __('Cabang') }}</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="location_type" value="warehouse" x-model="locationType" x-on:change="onLocationTypeChange()"
                                        {{ $defaultLocationType === 'warehouse' ? 'checked' : '' }}>
                                    <span class="ml-2">{{ __('Gudang') }}</span>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('location_type')" class="mt-2" />
                        </div>
                        <div x-show="locationType === 'branch'" x-transition>
                            <x-input-label for="branch_id" :value="__('Cabang')" />
                            <select id="branch_id" name="branch_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                x-model="branchId" x-on:change="loadFormData()"
                                :disabled="locationType !== 'branch'">
                                <option value="">{{ __('Pilih Cabang') }}</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                        </div>
                        <div x-show="locationType === 'warehouse'" x-transition>
                            <x-input-label for="warehouse_id" :value="__('Gudang')" />
                            <select id="warehouse_id" name="warehouse_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                x-model="warehouseId" x-on:change="loadFormData()"
                                :disabled="locationType !== 'warehouse'">
                                <option value="">{{ __('Pilih Gudang') }}</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" {{ old('warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
                        </div>
                    @elseif($lockedToBranch)
                        <input type="hidden" name="location_type" value="branch">
                        <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
                        <input type="hidden" name="warehouse_id" value="">
                        <div class="rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700">
                            {{ __('Lokasi') }}: {{ __('Cabang') }} - {{ \App\Models\Branch::find(auth()->user()->branch_id)?->name ?? '-' }}
                        </div>
                    @elseif($lockedToWarehouse)
                        <input type="hidden" name="location_type" value="warehouse">
                        <input type="hidden" name="branch_id" value="">
                        <input type="hidden" name="warehouse_id" value="{{ auth()->user()->warehouse_id }}">
                        <div class="rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700">
                            {{ __('Lokasi') }}: {{ __('Gudang') }} - {{ \App\Models\Warehouse::find(auth()->user()->warehouse_id)?->name ?? '-' }}
                        </div>
                    @endif

                    <div>
                        <x-input-label for="income_category_id" :value="__('Kategori Pemasukan')" />
                        <select id="income_category_id" name="income_category_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">{{ __('Pilih Kategori Pemasukan') }}</option>
                            @foreach ($incomeCategories as $cat)
                                <option value="{{ $cat->id }}" {{ old('income_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('income_category_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="payment_method_id" :value="__('Pilih Kas')" />
                        <select id="payment_method_id" name="payment_method_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">{{ __('Pilih Kas') }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Metode pembayaran/kas berdasarkan lokasi (cabang/gudang) yang dipilih') }}</p>
                        <x-input-error :messages="$errors->get('payment_method_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="transaction_date" :value="__('Tanggal')" />
                        <x-text-input id="transaction_date" class="block mt-1 w-full" type="date" name="transaction_date" :value="old('transaction_date', date('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('transaction_date')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="amount" :value="__('Jumlah')" />
                        <x-text-input id="amount" class="block mt-1 w-full" type="text" name="amount" data-rupiah="true" :value="old('amount')" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('Keterangan / Sumber Pemasukan')" />
                        <textarea id="description" name="description" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="flex gap-3">
                        <x-primary-button>{{ __('Simpan') }}</x-primary-button>
                        <a href="{{ route('cash-flows.in.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">
                            {{ __('Batal') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @php
        $formDataUrl = route('data-by-location.form-data', [], false);
        $appBase = request()->getBaseUrl();
    @endphp
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('cashInForm', () => ({
                locationType: '{{ old('location_type', $defaultLocationType ?? 'branch') }}',
                branchId: '{{ old('branch_id', $lockedToBranch ?? false ? auth()->user()->branch_id : '') }}',
                warehouseId: '{{ old('warehouse_id', $lockedToWarehouse ?? false ? auth()->user()->warehouse_id : '') }}',
                paymentMethodId: '{{ old('payment_method_id', '') }}',
                formDataUrl: @json($formDataUrl),
                appBase: @json($appBase),
                get locationId() {
                    return this.locationType === 'branch' ? this.branchId : this.warehouseId;
                },
                onLocationTypeChange() {
                    if (this.locationType === 'branch') this.warehouseId = '';
                    else this.branchId = '';
                    this.loadFormData();
                },
                async loadFormData() {
                    const locId = this.locationType === 'branch' ? this.branchId : this.warehouseId;
                    if (!locId) {
                        const pmSelect = document.getElementById('payment_method_id');
                        if (pmSelect) pmSelect.innerHTML = '<option value="">' + @json(__('Pilih Kas')) + '</option>';
                        return;
                    }
                    const pmSelect = document.getElementById('payment_method_id');
                    if (!pmSelect) return;
                    try {
                        const url = new URL(this.appBase + this.formDataUrl, window.location.origin);
                        url.searchParams.set('location_type', this.locationType);
                        url.searchParams.set('location_id', locId);
                        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Fetch failed');
                        const data = await res.json();
                        const methods = data.payment_methods || [];
                        const oldVal = pmSelect.value;
                        pmSelect.innerHTML = '<option value="">' + @json(__('Pilih Kas')) + '</option>' +
                            methods.map(m => '<option value="' + m.id + '">' + (m.label || '') + '</option>').join('');
                        if (oldVal && methods.some(m => m.id == oldVal)) pmSelect.value = oldVal;
                        this.paymentMethodId = pmSelect.value || '';
                    } catch (e) { console.error('loadFormData failed', e); }
                },
                init() {
                    if (this.locationId) this.$nextTick(() => this.loadFormData());
                }
            }));
        });
    </script>
</x-app-layout>

