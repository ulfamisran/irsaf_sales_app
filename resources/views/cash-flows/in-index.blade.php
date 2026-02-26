<x-app-layout>
    <x-slot name="title">{{ __('Dana Masuk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Pemasukan Lainnya') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('cash-flows.in.index') }}" class="flex flex-wrap gap-4 items-end">
                    @if (auth()->user()->isSuperAdmin())
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cabang') }}</label>
                            <select name="branch_id" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
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
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('cash-flows.in.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                        <a href="{{ route('cash-flows.in.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                            {{ __('Tambah Pemasukan') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="card-modern p-6">
                <p class="text-sm text-slate-600">{{ __('Total Pemasukan Lainnya') }}</p>
                <p class="text-xl font-semibold text-emerald-600">+{{ number_format($totalIn, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                        @if (auth()->user()->isSuperAdmin())
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Cabang') }}</th>
                        @endif
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Deskripsi') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Jumlah') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @forelse ($incomes as $inc)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3">{{ $inc->transaction_date->format('d/m/Y') }}</td>
                            @if (auth()->user()->isSuperAdmin())
                                <td class="px-4 py-3">{{ $inc->branch?->name ?? '-' }}</td>
                            @endif
                            <td class="px-4 py-3">{{ $inc->description }}</td>
                            <td class="px-4 py-3 text-right font-medium text-emerald-600">
                                +{{ number_format($inc->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3">{{ $inc->user?->name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isSuperAdmin() ? 5 : 4 }}" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data pemasukan lainnya.') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $incomes->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>

