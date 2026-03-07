<x-app-layout>
    <x-slot name="title">{{ __('Riwayat Pembelian') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Riwayat Pembelian') }}</h2>
            <x-icon-btn-add :href="route('purchases.create')" :label="__('Catat Pembelian')" />
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
                <form method="GET" action="{{ route('purchases.index') }}" class="flex flex-wrap gap-3 items-end">
                    @if ($canFilterLocation)
                        <div class="min-w-[140px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                            <select name="location_type" id="filter_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="warehouse" {{ request('location_type') == 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                <option value="branch" {{ request('location_type') == 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                            </select>
                        </div>
                        <div class="min-w-[180px] filter-warehouse">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Gudang') }}</label>
                            <select name="warehouse_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" {{ request('warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-[180px] filter-branch" style="display:none">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cabang') }}</label>
                            <select name="branch_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        @if ($filterLocked)
                            <div class="min-w-[200px]">
                                <x-locked-location label="{{ __('Lokasi') }}" :value="$locationLabel" />
                            </div>
                        @endif
                    @endif
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Distributor') }}</label>
                        <select name="distributor_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($distributors as $d)
                                <option value="{{ $d->id }}" {{ request('distributor_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cari') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="No. invoice, distributor...">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('purchases.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Distributor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Terbayar') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($purchases as $p)
                            <tr class="hover:bg-slate-50/50 {{ $p->isCancelled() ? 'bg-slate-50' : '' }}">
                                <td class="px-4 py-3 font-medium">
                                    {{ $p->invoice_number }}
                                    @if ($p->isCancelled())
                                        <span class="ml-2 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">{{ __('Dibatalkan') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $p->purchase_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $p->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($p->warehouse_id)
                                        {{ __('Gudang') }}: {{ $p->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $p->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($p->total, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="{{ $p->isPaidOff() ? 'text-emerald-600 font-medium' : 'text-amber-600' }}">
                                        {{ number_format($p->total_paid, 0, ',', '.') }}
                                    </span>
                                    @if (!$p->isPaidOff())
                                        <span class="text-xs text-slate-500">(sisa {{ number_format((float)$p->total - (float)$p->total_paid, 0, ',', '.') }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $p->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2 justify-center">
                                        <a href="{{ route('purchases.show', ['purchase' => $p, 'view' => 'detail']) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            {{ __('Detail') }}
                                        </a>
                                        @if (!$p->isCancelled())
                                            <a href="{{ route('purchases.show', ['purchase' => $p, 'view' => 'cancel']) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                {{ __('Cancel') }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data pembelian.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $purchases->links() }}</div>
            </div>
        </div>
    </div>

    @if ($canFilterLocation)
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locType = document.getElementById('filter_location_type');
            const whBlock = document.querySelector('.filter-warehouse');
            const brBlock = document.querySelector('.filter-branch');
            function toggle() {
                const v = locType?.value;
                if (v === 'warehouse') {
                    if (whBlock) whBlock.style.display = '';
                    if (brBlock) brBlock.style.display = 'none';
                } else {
                    if (whBlock) whBlock.style.display = 'none';
                    if (brBlock) brBlock.style.display = '';
                }
            }
            locType?.addEventListener('change', toggle);
            toggle();
        });
    </script>
    @endpush
    @endif
</x-app-layout>
