<x-app-layout>
    <x-slot name="title">{{ __('Unit Cabang') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Unit Produk Cabang') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $branch->name }}</p>
            </div>
            <x-icon-btn-back :href="route('branches.index')" :label="__('Kembali ke Cabang')" />
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="card-modern p-6">
                <p class="text-sm text-slate-600">{{ __('Ready (In Stock)') }}</p>
                <p class="text-2xl font-semibold text-emerald-600">{{ (int) ($summary[\App\Models\ProductUnit::STATUS_IN_STOCK] ?? 0) }}</p>
            </div>
            <div class="card-modern p-6">
                <p class="text-sm text-slate-600">{{ __('Sold') }}</p>
                <p class="text-2xl font-semibold text-slate-800">{{ (int) ($summary[\App\Models\ProductUnit::STATUS_SOLD] ?? 0) }}</p>
            </div>
        </div>

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('branches.units', $branch) }}" class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[240px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Produk') }}</label>
                        <select name="product_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->sku }} - {{ $p->brand }} {{ $p->series }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status') }}</label>
                        <select name="status" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="in_stock" {{ request('status') === 'in_stock' ? 'selected' : '' }}>{{ __('Ready') }}</option>
                            <option value="sold" {{ request('status') === 'sold' ? 'selected' : '' }}>{{ __('Sold') }}</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Serial') }}</label>
                        <input type="text" name="serial" value="{{ request('serial') }}" placeholder="SN..." class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('branches.units', $branch) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kategori') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Received') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Sold') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($units as $u)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-mono text-sm">{{ $u->serial_number }}</td>
                                <td class="px-4 py-3">
                                    {{ $u->product?->sku }} - {{ $u->product?->brand }} {{ $u->product?->series }}
                                </td>
                                <td class="px-4 py-3">{{ $u->product?->category?->name }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $u->status === 'in_stock' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                                        {{ $u->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $u->received_date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $u->sold_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada unit produk pada cabang ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $units->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
