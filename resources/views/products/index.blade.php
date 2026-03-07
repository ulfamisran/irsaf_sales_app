<x-app-layout>
    <x-slot name="title">{{ __('Produk') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Produk') }}</h2>
            @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::ADMIN_GUDANG]))
                <x-icon-btn-add :href="route('products.create')" :label="__('Tambah Produk')" />
            @else
                <button type="button" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg font-semibold text-sm text-white bg-gradient-to-r from-indigo-600 to-indigo-700 opacity-60 cursor-not-allowed" disabled>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Tambah Produk') }}
                </button>
            @endif
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
                <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap gap-3 items-end" x-data="{ locType: '{{ $locationType ?? '' }}' }">
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
                    <div class="min-w-[140px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status Produk') }}</label>
                        <select name="status" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ __('Aktif') }}</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ __('Nonaktif') }}</option>
                        </select>
                    </div>
                    <div class="min-w-[140px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Filter Lokasi') }}</label>
                        <select name="location_type" x-model="locType" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="warehouse">{{ __('Gudang') }}</option>
                            <option value="branch">{{ __('Cabang') }}</option>
                        </select>
                    </div>
                    <template x-if="locType === 'warehouse'">
                        <div class="min-w-[180px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Gudang') }}</label>
                            <select name="location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Pilih Gudang') }}</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" {{ ($locationType === 'warehouse' && $locationId == $w->id) ? 'selected' : '' }}>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>
                    <template x-if="locType === 'branch'">
                        <div class="min-w-[180px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cabang') }}</label>
                            <select name="location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Pilih Cabang') }}</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ ($locationType === 'branch' && $locationId == $b->id) ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('products.index', ['location_type' => '', 'location_id' => '', 'category_id' => '', 'status' => '', 'search' => '']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('SKU') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Brand') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Series') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kategori') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Distributor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Harga Jual') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Stok Gudang') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Stok Cabang') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($products as $product)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 text-slate-600">{{ $products->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3">{{ $product->sku }}</td>
                                <td class="px-4 py-3">{{ $product->brand }}</td>
                                <td class="px-4 py-3">{{ $product->series }}</td>
                                <td class="px-4 py-3">{{ $product->laptop_type ? ucfirst($product->laptop_type) : '-' }}</td>
                                <td class="px-4 py-3">{{ $product->category?->name }}</td>
                                <td class="px-4 py-3">{{ $product->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $product->location?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $product->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($product->selling_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ (int) ($product->warehouse_stock ?? 0) }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ (int) ($product->branch_stock ?? 0) }}</td>
                                <td class="px-4 py-3">
                                    @if ($product->is_active)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            {{ __('Aktif') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                                            {{ __('Nonaktif') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                        @php
                                            $isAks = in_array(strtoupper($product->category?->name ?? ''), ['AKS'])
                                                || in_array(strtoupper($product->category?->code ?? ''), ['AKS']);
                                        @endphp
                                        @php
                                            $canEdit = (auth()->user()?->isSuperAdmin() || auth()->user()?->isAdminPusat() || auth()->user()?->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::ADMIN_GUDANG]))
                                                && ($product->sold_units_count ?? 0) == 0;
                                            if ($canEdit && auth()->user()?->hasAnyRole([\App\Models\Role::ADMIN_GUDANG]) && auth()->user()?->branch_id) {
                                                $canEdit = $product->location_type === \App\Models\Product::LOCATION_BRANCH && (int) $product->location_id === (int) auth()->user()->branch_id;
                                            }
                                        @endphp
                                        @if ($canEdit)
                                            <x-icon-btn-edit :href="route('products.edit', $product)" :label="__('Edit')" />
                                        @endif
                                        @if (!$isAks)
                                            <x-icon-btn-view :href="route('products.show', $product)" :label="__('Unit')" />
                                            <a href="{{ route('stock-mutations.index', ['product_id' => $product->id]) }}"
                                               class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                               title="{{ __('Detail Distribusi') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                                </svg>
                                                {{ __('Distribusi') }}
                                            </a>
                                        @endif
                                        @if (auth()->user()?->isSuperAdmin())
                                            <form action="{{ route('products.toggle-active', $product) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-colors {{ $product->is_active ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                    {{ $product->is_active ? __('Nonaktifkan') : __('Aktifkan') }}
                                                </button>
                                            </form>
                                        @endif
                                        @if ($product->is_active)
                                            <a href="{{ route('incoming-goods.create', ['product_id' => $product->id]) }}"
                                               class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                                                {{ __('Tambah Unit') }}
                                            </a>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-400">
                                                {{ __('Tambah Unit') }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data produk.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $products->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
