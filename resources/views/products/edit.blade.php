<x-app-layout>
    <x-slot name="title">{{ __('Edit Produk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Product') }}
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
                                <x-input-label for="category_id" :value="__('Category')" />
                                <select id="category_id" name="category_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="sku" :value="__('SKU')" />
                                <x-text-input id="sku" class="block mt-1 w-full" type="text" name="sku" :value="old('sku', $product->sku)" required />
                                <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="brand" :value="__('Brand')" />
                                <x-text-input id="brand" class="block mt-1 w-full" type="text" name="brand" :value="old('brand', $product->brand)" required />
                                <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="series" :value="__('Series')" />
                                <x-text-input id="series" class="block mt-1 w-full" type="text" name="series" :value="old('series', $product->series)" />
                                <x-input-error :messages="$errors->get('series')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="specs" :value="__('Specifications')" />
                                <textarea id="specs" name="specs" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="3">{{ old('specs', $product->specs) }}</textarea>
                                <x-input-error :messages="$errors->get('specs')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="laptop_type" :value="__('Jenis Laptop')" />
                                <select id="laptop_type" name="laptop_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="baru" {{ old('laptop_type', $product->laptop_type) === 'baru' ? 'selected' : '' }}>{{ __('Baru') }}</option>
                                    <option value="bekas" {{ old('laptop_type', $product->laptop_type) === 'bekas' ? 'selected' : '' }}>{{ __('Bekas') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('laptop_type')" class="mt-2" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="purchase_price" :value="__('Purchase Price')" />
                                    <x-text-input id="purchase_price" class="block mt-1 w-full" type="text" name="purchase_price" data-rupiah="true" :value="old('purchase_price', $product->purchase_price)" required />
                                    <x-input-error :messages="$errors->get('purchase_price')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="selling_price" :value="__('Selling Price')" />
                                    <x-text-input id="selling_price" class="block mt-1 w-full" type="text" name="selling_price" data-rupiah="true" :value="old('selling_price', $product->selling_price)" required />
                                    <x-input-error :messages="$errors->get('selling_price')" class="mt-2" />
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Update') }}</x-primary-button>
                                <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
