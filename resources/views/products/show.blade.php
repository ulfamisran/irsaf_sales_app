<x-app-layout>
    <x-slot name="title">{{ __('Detail Produk') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Detail Produk') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $product->sku }} - {{ $product->brand }} {{ $product->series }}</p>
            </div>
            <div class="flex gap-2">
                @php
                    $canEditProduct = !$product->hasSoldUnits()
                        && (auth()->user()?->isSuperAdmin() || auth()->user()?->isAdminPusat() || auth()->user()?->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::ADMIN_GUDANG]));
                    if ($canEditProduct && auth()->user()?->hasAnyRole([\App\Models\Role::ADMIN_GUDANG]) && auth()->user()?->branch_id) {
                        $canEditProduct = $product->location_type === \App\Models\Product::LOCATION_BRANCH && (int) $product->location_id === (int) auth()->user()->branch_id;
                    }
                @endphp
                @if ($canEditProduct)
                    <x-icon-btn-edit :href="route('products.edit', $product)" :label="__('Edit')" />
                @endif
                <x-icon-btn-back :href="route('products.index')" :label="__('Kembali')" />
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Kategori') }}</p>
                        <p class="font-medium text-slate-800">{{ $product->category?->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Jenis Laptop') }}</p>
                        <p class="font-medium text-slate-800">{{ $product->laptop_type ? ucfirst($product->laptop_type) : '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Harga Beli') }}</p>
                        <p class="font-medium text-slate-800">{{ number_format($product->purchase_price, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Harga Jual') }}</p>
                        <p class="font-medium text-slate-800">{{ number_format($product->selling_price, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('User') }}</p>
                        <p class="font-medium text-slate-800">{{ $product->user?->name ?? '-' }}</p>
                    </div>
                </div>
                @if ($product->specs)
                    <div class="mt-4">
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Spesifikasi') }}</p>
                        <p class="text-slate-700 whitespace-pre-line">{{ $product->specs }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('products.show', $product) }}" class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status') }}</label>
                        <select name="status" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="in_stock" {{ request('status') === 'in_stock' ? 'selected' : '' }}>{{ __('In Stock') }}</option>
                            <option value="sold" {{ request('status') === 'sold' ? 'selected' : '' }}>{{ __('Sold') }}</option>
                        </select>
                    </div>
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi Tipe') }}</label>
                        <select name="location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="warehouse" {{ request('location_type') === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                            <option value="branch" {{ request('location_type') === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                        </select>
                    </div>
                    <div class="min-w-[220px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                        <select name="location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($warehouses as $w)
                                <option value="{{ $w->id }}" {{ request('location_id') == $w->id ? 'selected' : '' }}>{{ __('Warehouse') }}: {{ $w->name }}</option>
                            @endforeach
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ request('location_id') == $b->id ? 'selected' : '' }}>{{ __('Branch') }}: {{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Serial') }}</label>
                        <input type="text" name="serial" value="{{ request('serial') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="SN..." />
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('products.show', $product) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial Number') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Received') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Sold') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($units as $u)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-mono text-sm">{{ $u->serial_number }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $u->status === 'in_stock' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                                        {{ $u->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $locationLabel = $u->location_type === \App\Models\Stock::LOCATION_WAREHOUSE
                                            ? __('Gudang')
                                            : __('Cabang');
                                        $locationName = $u->location_type === \App\Models\Stock::LOCATION_WAREHOUSE
                                            ? ($u->warehouse?->name ?? ('#'.$u->location_id))
                                            : ($u->branch?->name ?? ('#'.$u->location_id));
                                    @endphp
                                    {{ $locationLabel }}: {{ $locationName }}
                                </td>
                                <td class="px-4 py-3">{{ $u->received_date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $u->sold_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $u->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $canDeleteUnit = $u->status === \App\Models\ProductUnit::STATUS_IN_STOCK
                                            && (auth()->user()?->isSuperAdmin()
                                                || auth()->user()?->isAdminPusat()
                                                || (auth()->user()?->hasRole(\App\Models\Role::ADMIN_CABANG) && auth()->user()?->branch_id && $u->location_type === \App\Models\Stock::LOCATION_BRANCH && (int) $u->location_id === (int) auth()->user()->branch_id));
                                    @endphp
                                    @if ($canDeleteUnit)
                                        <form action="{{ route('products.units.destroy', [$product, $u]) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-btn-delete :label="__('Hapus')" />
                                        </form>
                                    @else
                                        <span class="text-slate-400 text-sm">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">{{ __('Belum ada unit/serial untuk produk ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $units->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>

