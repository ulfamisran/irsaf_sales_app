<x-app-layout>
    <x-slot name="title">{{ __('Penyesuaian Saldo') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Riwayat Penyesuaian Saldo') }}</h2>
            <x-icon-btn-add :href="route('saldo-adjustment.create')" :label="__('Penyesuaian Saldo Baru')" />
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
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        {{-- Info banner --}}
        <div class="mb-6 rounded-xl bg-indigo-50 border border-indigo-200 p-4 text-indigo-800 text-sm">
            {{ __('Penyesuaian Saldo hanya dapat dilakukan oleh Super Admin atau Admin Pusat. Setiap penyesuaian akan tercatat sebagai pemasukan/pengeluaran kas dengan kategori "Penyesuaian Saldo" (kode ADJ-SALDO) dan otomatis muncul di Laporan Arus Kas serta Detail Monitoring Kas.') }}
        </div>

        {{-- Filter --}}
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('saldo-adjustment.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[140px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                        <select name="location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="warehouse" {{ request('location_type') === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                            <option value="branch" {{ request('location_type') === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                        </select>
                    </div>
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                        <select name="location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($warehouses as $w)
                                <option value="{{ $w->id }}" {{ request('location_type') === 'warehouse' && (int) request('location_id') === (int) $w->id ? 'selected' : '' }}>{{ __('Gudang') }}: {{ $w->name }}</option>
                            @endforeach
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ request('location_type') === 'branch' && (int) request('location_id') === (int) $b->id ? 'selected' : '' }}>{{ __('Cabang') }}: {{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[140px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Jenis') }}</label>
                        <select name="type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="IN" {{ request('type') === 'IN' ? 'selected' : '' }}>{{ __('Pemasukan (IN)') }}</option>
                            <option value="OUT" {{ request('type') === 'OUT' ? 'selected' : '' }}>{{ __('Pengeluaran (OUT)') }}</option>
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
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('saldo-adjustment.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">{{ __('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Sumber Dana') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Nominal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Catatan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($records as $rec)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 text-sm">{{ $rec->transaction_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($rec->warehouse_id)
                                        {{ __('Gudang') }}: {{ $rec->warehouse?->name ?? '-' }}
                                    @elseif ($rec->branch_id)
                                        {{ __('Cabang') }}: {{ $rec->branch?->name ?? '-' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($rec->type === \App\Models\CashFlow::TYPE_IN)
                                        <span class="inline-flex items-center px-2 py-1 rounded-md bg-emerald-100 text-emerald-700 text-xs font-medium">{{ __('Pemasukan (IN)') }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded-md bg-red-100 text-red-700 text-xs font-medium">{{ __('Pengeluaran (OUT)') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $rec->paymentMethod?->display_label ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-sm font-medium {{ $rec->type === \App\Models\CashFlow::TYPE_IN ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ $rec->type === \App\Models\CashFlow::TYPE_IN ? '+' : '-' }}Rp {{ number_format($rec->amount, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $rec->description ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm">{{ $rec->user?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">{{ __('Belum ada riwayat penyesuaian saldo.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $records->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
