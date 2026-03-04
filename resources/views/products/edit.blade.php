<x-app-layout>
    <x-slot name="title">{{ __('Edit Produk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Produk') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('products.update', $product) }}">
                        @csrf
                        @method('PATCH')
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="category_id" :value="__('Kategori')" />
                                <select id="category_id" name="category_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                            </div>
                            {{-- Lokasi Produk: pilihan Gudang/Cabang, pre-fill dari data sebelumnya. Distributor di bawah, dimuat sesuai lokasi. --}}
                            @if (auth()->user()?->hasAnyRole([\App\Models\Role::ADMIN_GUDANG]) && auth()->user()?->branch_id)
                            <div id="location-edit-block">
                                <div>
                                    <x-locked-location label="{{ __('Lokasi Cabang') }}" :value="__('Cabang') . ': ' . (($branches->firstWhere('id', auth()->user()?->branch_id))?->name ?? __('Cabang Anda'))" />
                                    <input type="hidden" name="location_type" value="branch" />
                                    <input type="hidden" name="location_id" value="{{ auth()->user()->branch_id }}" />
                                    <p class="mt-1 text-sm text-slate-500">{{ __('Produk berada di cabang Anda.') }}</p>
                                </div>
                                <div class="mt-4">
                                    <x-input-label for="distributor_id" :value="__('Distributor')" />
                                    <p class="mt-1 text-xs text-slate-500 mb-1">{{ __('Distributor dimuat sesuai lokasi. Jika lokasi berubah, pilih ulang.') }}</p>
                                    <select id="distributor_id" name="distributor_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required disabled>
                                        <option value="">{{ __('Memuat...') }}</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('distributor_id')" class="mt-2" />
                                </div>
                            </div>
                            @else
                            <div id="location-edit-block" x-data="{ locType: '{{ old('location_type', $defaultLocationType ?? 'warehouse') }}' }"
                                 x-init="$nextTick(() => { setTimeout(() => window.loadProductDistributors?.(), 150) })">
                                <div>
                                    <x-input-label :value="__('Lokasi Produk')" />
                                    <p class="mt-1 text-xs text-slate-500 mb-1">{{ __('Pilih jenis lokasi dan cabang/gudang. Wajib diisi jika kosong.') }}</p>
                                    <div class="mt-2 flex gap-6">
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="radio" name="location_type" value="warehouse" x-model="locType"
                                                class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                @change="$nextTick(() => window.loadProductDistributors?.(false))">
                                            <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Gudang') }}</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="radio" name="location_type" value="branch" x-model="locType"
                                                class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                @change="$nextTick(() => window.loadProductDistributors?.(false))">
                                            <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Cabang') }}</span>
                                        </label>
                                    </div>
                                    <template x-if="locType === 'warehouse'">
                                        <div class="mt-3">
                                            <x-input-label for="location_id" :value="__('Gudang')" />
                                            <select name="location_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required
                                                @change="window.loadProductDistributors?.(false)">
                                                <option value="">{{ __('Pilih Gudang') }}</option>
                                                @foreach ($warehouses as $wh)
                                                    <option value="{{ $wh->id }}" {{ old('location_id', ($defaultLocationType ?? '') === 'warehouse' ? $defaultLocationId : null) == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                                        </div>
                                    </template>
                                    <template x-if="locType === 'branch'">
                                        <div class="mt-3">
                                            <x-input-label for="location_id" :value="__('Cabang')" />
                                            <select name="location_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required
                                                @change="window.loadProductDistributors?.(false)">
                                                <option value="">{{ __('Pilih Cabang') }}</option>
                                                @foreach ($branches as $b)
                                                    <option value="{{ $b->id }}" {{ old('location_id', ($defaultLocationType ?? '') === 'branch' ? $defaultLocationId : null) == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                                        </div>
                                    </template>
                                </div>
                                <div class="mt-4">
                                    <x-input-label for="distributor_id" :value="__('Distributor')" />
                                    <p class="mt-1 text-xs text-slate-500 mb-1">{{ __('Pilih Gudang/Cabang lalu lokasi spesifik. Distributor otomatis dimuat. Jika lokasi diubah, pilih distributor ulang.') }}</p>
                                    <select id="distributor_id" name="distributor_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required disabled>
                                        <option value="">{{ __('Pilih Lokasi dulu') }}</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('distributor_id')" class="mt-2" />
                                </div>
                            </div>
                            @endif
                            <div>
                                <x-input-label for="laptop_type" :value="__('Jenis Laptop')" />
                                @if (auth()->user()?->hasAnyRole([\App\Models\Role::ADMIN_CABANG]))
                                    <input type="hidden" name="laptop_type" value="baru" />
                                    <select id="laptop_type" class="block mt-1 w-full rounded-md border-gray-300 bg-slate-100 shadow-sm" disabled>
                                        <option value="baru" selected>{{ __('Baru') }}</option>
                                    </select>
                                @else
                                    <select id="laptop_type" name="laptop_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="baru" {{ old('laptop_type', $product->laptop_type) === 'baru' ? 'selected' : '' }}>{{ __('Baru') }}</option>
                                        <option value="bekas" {{ old('laptop_type', $product->laptop_type) === 'bekas' ? 'selected' : '' }}>{{ __('Bekas') }}</option>
                                    </select>
                                @endif
                                <x-input-error :messages="$errors->get('laptop_type')" class="mt-2" />
                            </div>
                            <div>
                                <input type="hidden" name="sku" id="sku" value="{{ old('sku', $product->sku) }}" />
                            </div>
                            <div>
                                <x-input-label for="brand" :value="__('Merek')" />
                                <x-text-input id="brand" class="block mt-1 w-full" type="text" name="brand" :value="old('brand', $product->brand)" required />
                                <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="series" :value="__('Seri')" />
                                <x-text-input id="series" class="block mt-1 w-full" type="text" name="series" :value="old('series', $product->series)" />
                                <x-input-error :messages="$errors->get('series')" class="mt-2" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <x-input-label for="processor" :value="__('Processor')" />
                                    <x-text-input id="processor" class="block mt-1 w-full" type="text" name="processor" :value="old('processor', $product->processor)" />
                                    <x-input-error :messages="$errors->get('processor')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="ram" :value="__('RAM')" />
                                    <x-text-input id="ram" class="block mt-1 w-full" type="text" name="ram" :value="old('ram', $product->ram)" />
                                    <x-input-error :messages="$errors->get('ram')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="storage" :value="__('Kapasitas Penyimpanan')" />
                                    <x-text-input id="storage" class="block mt-1 w-full" type="text" name="storage" :value="old('storage', $product->storage)" />
                                    <x-input-error :messages="$errors->get('storage')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="color" :value="__('Warna')" />
                                    <x-text-input id="color" class="block mt-1 w-full" type="text" name="color" :value="old('color', $product->color)" />
                                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                                </div>
                            </div>
                            <div>
                                <x-input-label for="specs" :value="__('Spesifikasi')" />
                                <textarea id="specs" name="specs" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="3">{{ old('specs', $product->specs) }}</textarea>
                                <x-input-error :messages="$errors->get('specs')" class="mt-2" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="purchase_price" :value="__('Harga Beli')" />
                                    <x-text-input id="purchase_price" class="block mt-1 w-full" type="text" name="purchase_price" data-rupiah="true" :value="old('purchase_price', $product->purchase_price)" required />
                                    <x-input-error :messages="$errors->get('purchase_price')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="selling_price" :value="__('Harga Jual')" />
                                    <x-text-input id="selling_price" class="block mt-1 w-full" type="text" name="selling_price" data-rupiah="true" :value="old('selling_price', $product->selling_price)" required />
                                    <x-input-error :messages="$errors->get('selling_price')" class="mt-2" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-3">
                                <div>
                                    <x-primary-button type="button" id="generate-sku-btn">{{ __('Generate SKU') }}</x-primary-button>
                                </div>
                                <div>
                                    <x-input-label for="sku_display" :value="__('SKU')" />
                                    <x-text-input id="sku_display" class="block mt-1 w-full bg-slate-100" type="text" value="{{ old('sku', $product->sku) }}" disabled />
                                </div>
                                <div class="flex gap-4">
                                    <x-primary-button id="save-btn">{{ __('Simpan') }}</x-primary-button>
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
        const distributorsUrl = @json(route('data-by-location.distributors'));
        const defaultDistributorId = @json(old('distributor_id', $product->distributor_id));
        let isInitialLoad = true;

        async function loadDistributors(useDefaultSelection = false) {
            const locTypeEl = document.querySelector('input[name="location_type"]:checked') || document.querySelector('input[name="location_type"]');
            const locIdEl = document.querySelector('select[name="location_id"]') || document.querySelector('input[name="location_id"]');
            const locType = locTypeEl?.value;
            const locId = locIdEl?.value;
            const sel = document.getElementById('distributor_id');
            if (!sel) return;
            if (!locType || !locId) {
                sel.innerHTML = '<option value="">' + @json(__('Pilih Lokasi dulu')) + '</option>';
                sel.disabled = true;
                return;
            }
            try {
                const url = new URL(distributorsUrl, window.location.origin);
                url.searchParams.set('location_type', locType);
                url.searchParams.set('location_id', locId);
                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Fetch failed');
                const data = await res.json();
                const distributors = data.distributors || [];
                const shouldSelectDefault = useDefaultSelection && defaultDistributorId;
                sel.innerHTML = '<option value="">' + @json(__('Pilih Distributor')) + '</option>' +
                    distributors.map(d => '<option value="' + d.id + '"' + (shouldSelectDefault && defaultDistributorId == d.id ? ' selected' : '') + '>' + (d.name || '') + '</option>').join('');
                sel.disabled = false;
            } catch (e) {
                sel.innerHTML = '<option value="">' + @json(__('Gagal memuat distributor')) + '</option>';
                sel.disabled = false;
            }
        }
        window.loadProductDistributors = function(useDefaultSelection) {
            const useDefault = useDefaultSelection !== false && isInitialLoad;
            loadDistributors(useDefault);
            if (useDefaultSelection === false) isInitialLoad = false;
        };

        document.querySelectorAll('input[name="location_type"]').forEach(r => r.addEventListener('change', function() {
            isInitialLoad = false;
            setTimeout(() => loadDistributors(false), 50);
        }));
        document.querySelector('form')?.addEventListener('change', function(e) {
            if (e.target.matches('select[name="location_id"]')) {
                isInitialLoad = false;
                loadDistributors(false);
            }
        });
        setTimeout(() => loadDistributors(true), 300);

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
    });
</script>
