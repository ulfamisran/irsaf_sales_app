<x-app-layout>
    <x-slot name="title">{{ __('Tambah Produk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tambah Produk') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('products.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="created_by" :value="__('Pengguna')" />
                                <x-text-input id="created_by" class="block mt-1 w-full bg-slate-100" type="text" :value="auth()->user()?->name" disabled />
                            </div>
                            <div>
                                <x-input-label for="category_id" :value="__('Kategori')" />
                                <select id="category_id" name="category_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="">{{ __('Pilih Kategori') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="distributor_id" :value="__('Distributor')" />
                                <select id="distributor_id" name="distributor_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="">{{ __('Pilih Distributor') }}</option>
                                    @foreach ($distributors as $distributor)
                                        <option value="{{ $distributor->id }}" {{ old('distributor_id') == $distributor->id ? 'selected' : '' }}>{{ $distributor->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('distributor_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="laptop_type" :value="__('Jenis Laptop')" />
                                @if (auth()->user()?->hasAnyRole([\App\Models\Role::ADMIN_CABANG]))
                                    <input type="hidden" name="laptop_type" value="baru" />
                                    <select id="laptop_type" class="block mt-1 w-full rounded-md border-gray-300 bg-slate-100 shadow-sm" disabled>
                                        <option value="baru" selected>{{ __('Baru') }}</option>
                                    </select>
                                @else
                                    <select id="laptop_type" name="laptop_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="baru" {{ old('laptop_type', 'baru') === 'baru' ? 'selected' : '' }}>{{ __('Baru') }}</option>
                                        <option value="bekas" {{ old('laptop_type') === 'bekas' ? 'selected' : '' }}>{{ __('Bekas') }}</option>
                                    </select>
                                @endif
                                <x-input-error :messages="$errors->get('laptop_type')" class="mt-2" />
                            </div>
                            <div>
                                <input type="hidden" name="sku" id="sku" value="{{ old('sku') }}" />
                            </div>
                            <div>
                                <x-input-label for="brand" :value="__('Merek')" />
                                <x-text-input id="brand" class="block mt-1 w-full" type="text" name="brand" :value="old('brand')" required />
                                <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="series" :value="__('Seri')" />
                                <x-text-input id="series" class="block mt-1 w-full" type="text" name="series" :value="old('series')" />
                                <x-input-error :messages="$errors->get('series')" class="mt-2" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <x-input-label for="processor" :value="__('Processor')" />
                                    <x-text-input id="processor" class="block mt-1 w-full" type="text" name="processor" :value="old('processor')" />
                                    <x-input-error :messages="$errors->get('processor')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="ram" :value="__('RAM')" />
                                    <x-text-input id="ram" class="block mt-1 w-full" type="text" name="ram" :value="old('ram')" />
                                    <x-input-error :messages="$errors->get('ram')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="storage" :value="__('Kapasitas Penyimpanan')" />
                                    <x-text-input id="storage" class="block mt-1 w-full" type="text" name="storage" :value="old('storage')" />
                                    <x-input-error :messages="$errors->get('storage')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="color" :value="__('Warna')" />
                                    <x-text-input id="color" class="block mt-1 w-full" type="text" name="color" :value="old('color')" />
                                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                                </div>
                            </div>
                            <div>
                                <x-input-label for="specs" :value="__('Spesifikasi')" />
                                <textarea id="specs" name="specs" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="3">{{ old('specs') }}</textarea>
                                <x-input-error :messages="$errors->get('specs')" class="mt-2" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                            <x-input-label for="purchase_price" :value="__('Harga Beli')" />
                            <x-text-input id="purchase_price" class="block mt-1 w-full" type="text" name="purchase_price" data-rupiah="true" :value="old('purchase_price', 0)" required />
                                    <x-input-error :messages="$errors->get('purchase_price')" class="mt-2" />
                                </div>
                                <div>
                            <x-input-label for="selling_price" :value="__('Harga Jual')" />
                            <x-text-input id="selling_price" class="block mt-1 w-full" type="text" name="selling_price" data-rupiah="true" :value="old('selling_price', 0)" required />
                                    <x-input-error :messages="$errors->get('selling_price')" class="mt-2" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-3">
                                <div>
                                    <x-primary-button type="button" id="generate-sku-btn">{{ __('Generate SKU') }}</x-primary-button>
                                </div>
                                <div>
                                    <x-input-label for="sku_display" :value="__('SKU')" />
                                    <x-text-input id="sku_display" class="block mt-1 w-full bg-slate-100" type="text" value="{{ old('sku') }}" disabled />
                                </div>
                                <div class="flex gap-4">
                                    <x-primary-button id="save-btn" disabled class="opacity-60 cursor-not-allowed">{{ __('Simpan') }}</x-primary-button>
                                <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const generateBtn = document.getElementById('generate-sku-btn');
        const skuInput = document.getElementById('sku');
        const skuDisplay = document.getElementById('sku_display');
        const saveBtn = document.getElementById('save-btn');

        const getValue = (name) => {
            const el = document.querySelector(`[name="${name}"]`);
            return el ? el.value : '';
        };

        const sanitize = (value) => {
            return String(value || '')
                .trim()
                .toUpperCase()
                .replace(/\s+/g, '')
                .replace(/[^A-Z0-9]/g, '');
        };

        const brandSegment = (value) => {
            const cleaned = sanitize(value).replace(/[AEIOU]/g, '');
            return cleaned !== '' ? cleaned : 'NA';
        };

        const segment = (value) => {
            const cleaned = sanitize(value);
            return cleaned !== '' ? cleaned : 'NA';
        };

        const random3 = () => {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let out = '';
            for (let i = 0; i < 3; i++) {
                out += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return out;
        };

        generateBtn.addEventListener('click', function () {
            const laptopType = getValue('laptop_type') === 'baru' ? 'NW' : 'SC';
            const sku = [
                'LP',
                laptopType,
                brandSegment(getValue('brand')),
                segment(getValue('series')),
                segment(getValue('processor')),
                segment(getValue('ram')),
                segment(getValue('storage')),
                random3()
            ].join('-');

            skuInput.value = sku;
            skuDisplay.value = sku;
            saveBtn.disabled = false;
            saveBtn.classList.remove('opacity-60', 'cursor-not-allowed');
        });

        if (skuInput.value) {
            skuDisplay.value = skuInput.value;
            saveBtn.disabled = false;
            saveBtn.classList.remove('opacity-60', 'cursor-not-allowed');
        }
    });
</script>
