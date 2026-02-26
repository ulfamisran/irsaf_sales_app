<x-app-layout>
    <x-slot name="title">{{ __('Tambah Produk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Product') }}
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
                                <x-input-label for="category_id" :value="__('Category')" />
                                <select id="category_id" name="category_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="">{{ __('Select Category') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="sku" :value="__('SKU')" />
                                <x-text-input id="sku" class="block mt-1 w-full" type="text" name="sku" :value="old('sku')" required />
                                <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="brand" :value="__('Brand')" />
                                <x-text-input id="brand" class="block mt-1 w-full" type="text" name="brand" :value="old('brand')" required />
                                <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="series" :value="__('Series')" />
                                <x-text-input id="series" class="block mt-1 w-full" type="text" name="series" :value="old('series')" />
                                <x-input-error :messages="$errors->get('series')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="specs" :value="__('Specifications')" />
                                <textarea id="specs" name="specs" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="3">{{ old('specs') }}</textarea>
                                <x-input-error :messages="$errors->get('specs')" class="mt-2" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                            <x-input-label for="purchase_price" :value="__('Purchase Price')" />
                            <x-text-input id="purchase_price" class="block mt-1 w-full" type="text" name="purchase_price" data-rupiah="true" :value="old('purchase_price', 0)" required />
                                    <x-input-error :messages="$errors->get('purchase_price')" class="mt-2" />
                                </div>
                                <div>
                            <x-input-label for="selling_price" :value="__('Selling Price')" />
                            <x-text-input id="selling_price" class="block mt-1 w-full" type="text" name="selling_price" data-rupiah="true" :value="old('selling_price', 0)" required />
                                    <x-input-error :messages="$errors->get('selling_price')" class="mt-2" />
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Save') }}</x-primary-button>
                                <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
