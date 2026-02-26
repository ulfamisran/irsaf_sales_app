<x-app-layout>
    <x-slot name="title">{{ __('Tambah Barang Masuk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Record Incoming Goods') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('incoming-goods.store') }}">
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
                            <div>
                                <x-input-label for="warehouse_id" :value="__('Warehouse')" />
                                <select id="warehouse_id" name="warehouse_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="">{{ __('Select Warehouse') }}</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="quantity" :value="__('Quantity')" />
                                <x-text-input id="quantity" class="block mt-1 w-full" type="number" name="quantity" min="1" :value="old('quantity')" />
                                <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                                <p class="mt-1 text-sm text-gray-500">{{ __('Isi quantity jika belum punya serial number per unit.') }}</p>
                            </div>

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

    <script>
        function countSerials(text) {
            return text
                .split(/[\n,]+/g)
                .map(s => s.trim())
                .filter(Boolean)
                .filter((v, i, arr) => arr.indexOf(v) === i)
                .length;
        }

        const serialEl = document.getElementById('serial_numbers');
        const qtyEl = document.getElementById('quantity');

        if (serialEl && qtyEl) {
            const sync = () => {
                const c = countSerials(serialEl.value || '');
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
    </script>
</x-app-layout>
