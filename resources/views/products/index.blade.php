<x-app-layout>
    <x-slot name="title">{{ __('Produk') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Produk') }}</h2>
            <x-icon-btn-add :href="route('products.create')" :label="__('Tambah Produk')" />
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cari') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('SKU, brand, atau series...') }}"
                            class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Kategori') }}</label>
                        <select name="category_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('SKU') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Brand') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Series') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kategori') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Harga Beli') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Harga Jual') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Stok Ready') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($products as $product)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">{{ $product->sku }}</td>
                                <td class="px-4 py-3">{{ $product->brand }}</td>
                                <td class="px-4 py-3">{{ $product->series }}</td>
                                <td class="px-4 py-3">{{ $product->category?->name }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($product->purchase_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($product->selling_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ (int) ($product->ready_stock ?? 0) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @php
                                            $isAks = in_array(strtoupper($product->category?->name ?? ''), ['AKS'])
                                                || in_array(strtoupper($product->category?->code ?? ''), ['AKS']);
                                        @endphp
                                        @if (!$isAks)
                                            <x-icon-btn-view :href="route('products.show', $product)" :label="__('Unit')" />
                                        @endif
                                        <x-icon-btn-edit :href="route('products.edit', $product)" />
                                        <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-btn-delete />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data produk.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $products->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
