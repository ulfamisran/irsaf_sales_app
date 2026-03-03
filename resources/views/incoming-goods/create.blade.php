<x-app-layout>
    <x-slot name="title">{{ __('Tambah Barang Masuk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Record Incoming Goods') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('incoming-goods.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="created_by" :value="__('User')" />
                                <x-text-input id="created_by" class="block mt-1 w-full bg-slate-100" type="text" :value="auth()->user()?->name" disabled />
                            </div>
                            <div>
                                <x-input-label for="product_id" :value="__('Product')" />
                                @if ($selectedProduct)
                                    <x-text-input id="product_id" class="block mt-1 w-full bg-slate-100" type="text"
                                        :value="$selectedProduct->sku . ' - ' . $selectedProduct->brand . ' ' . $selectedProduct->series" disabled />
                                    <input type="hidden" name="product_id" value="{{ $selectedProduct->id }}">
                                @else
                                    <select id="product_id" name="product_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="">{{ __('Select Product') }}</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                                {{ $product->sku }} - {{ $product->brand }} {{ $product->series }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
                                @endif
                            </div>
                            @if ($isBranchUser)
                                <div>
                                    <x-locked-location label="{{ __('Cabang') }}" :value="__('Cabang') . ': ' . ($branch?->name ?? '')" />
                                    <input type="hidden" name="location_type" value="branch">
                                    <input type="hidden" name="branch_id" value="{{ $branch?->id }}">
                                </div>
                            @else
                                <div x-data="{ locationType: '{{ old('location_type', 'warehouse') }}' }">
                                    <x-input-label :value="__('Lokasi Tujuan')" />
                                    <div class="mt-2 flex gap-6">
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="radio" name="location_type" value="warehouse" x-model="locationType"
                                                class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="ml-2 text-sm font-medium text-gray-700">Gudang</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="radio" name="location_type" value="branch" x-model="locationType"
                                                class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="ml-2 text-sm font-medium text-gray-700">Cabang</span>
                                        </label>
                                    </div>
                                    <div x-show="locationType === 'warehouse'" x-cloak x-transition class="mt-3">
                                        <x-input-label for="warehouse_id" :value="__('Gudang')" />
                                        <select id="warehouse_id" name="warehouse_id"
                                            class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            :required="locationType === 'warehouse'" :disabled="locationType !== 'warehouse'">
                                            <option value="">Pilih Gudang</option>
                                            @foreach ($warehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}"
                                                    {{ (old('warehouse_id') ?? $selectedWarehouse?->id) == $warehouse->id ? 'selected' : '' }}>
                                                    {{ $warehouse->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
                                    </div>
                                    <div x-show="locationType === 'branch'" x-cloak x-transition class="mt-3">
                                        <x-input-label for="branch_id" :value="__('Cabang')" />
                                        <select id="branch_id" name="branch_id"
                                            class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            :required="locationType === 'branch'" :disabled="locationType !== 'branch'">
                                            <option value="">Pilih Cabang</option>
                                            @foreach ($branches as $b)
                                                <option value="{{ $b->id }}"
                                                    {{ (old('branch_id') ?? $selectedBranch?->id) == $b->id ? 'selected' : '' }}>
                                                    {{ $b->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                                    </div>
                                </div>
                            @endif
                            @if (! $selectedProduct)
                                <div>
                                    <x-input-label for="quantity" :value="__('Quantity')" />
                                    <x-text-input id="quantity" class="block mt-1 w-full" type="number" name="quantity" min="1" :value="old('quantity')" />
                                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                                    <p class="mt-1 text-sm text-gray-500">{{ __('Isi quantity jika belum punya serial number per unit.') }}</p>
                                </div>
                            @endif

                            <div>
                                <x-input-label for="serial_numbers" :value="__('Serial Numbers (1 per line)')" />
                                <textarea id="serial_numbers" name="serial_numbers" rows="6" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="SN-001&#10;SN-002&#10;SN-003">{{ old('serial_numbers') }}</textarea>
                                <x-input-error :messages="$errors->get('serial_numbers')" class="mt-2" />
                                <p class="mt-1 text-sm text-gray-500">{{ __('Jika diisi, sistem akan menghitung quantity otomatis dari jumlah baris serial.') }}</p>
                            </div>
                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Save') }}</x-primary-button>
                                <a href="{{ route('incoming-goods.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: @json(session('success')),
                confirmButtonText: 'Baik',
                confirmButtonColor: '#4f46e5'
            });
            @endif
            @if (session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: @json(session('error')),
                confirmButtonText: 'Baik',
                confirmButtonColor: '#dc2626'
            });
            @endif
        });

        (function() {
            function parseSerials(text) {
                return (text || '').split(/[\n,]+/g).map(s => s.trim()).filter(Boolean);
            }
            function countSerials(text) {
                return [...new Set(parseSerials(text))].length;
            }
            function getDuplicateSerials(text) {
                const arr = parseSerials(text);
                const seen = new Set();
                const dups = new Set();
                arr.forEach(v => {
                    if (seen.has(v)) dups.add(v);
                    else seen.add(v);
                });
                return [...dups];
            }

            const serialEl = document.getElementById('serial_numbers');
            const qtyEl = document.getElementById('quantity');
            const form = serialEl?.closest('form');

            if (serialEl && qtyEl) {
                const sync = () => {
                    const c = countSerials(serialEl.value);
                    if (c > 0) {
                        qtyEl.value = c;
                        qtyEl.setAttribute('readonly', 'readonly');
                    } else {
                        qtyEl.removeAttribute('readonly');
                    }
                };
                serialEl.addEventListener('input', sync);
                sync();
            }

            if (form && serialEl) {
                form.addEventListener('submit', function(e) {
                    const dups = getDuplicateSerials(serialEl.value);
                    if (dups.length > 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validasi Gagal',
                            html: 'Nomor serial tidak boleh duplikat:<br><code class="mt-2 block text-sm">' + dups.join(', ') + '</code>',
                            confirmButtonText: 'Baik',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                });
            }
        })();
    </script>
    @endpush
</x-app-layout>
