<x-app-layout>
    <x-slot name="title">{{ __('Tukar Unit') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Tukar Unit') }}</h2>
            @if (auth()->user()?->canModule('unit-exchanges', 'create'))
                <x-icon-btn-add :href="route('sales.unit-exchanges.create')" :label="__('Tukar Unit Baru')" />
            @endif
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        <div class="card-modern overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('sales.unit-exchanges.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cari') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Nomor tukar unit atau invoice...') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium">{{ __('Cari') }}</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Tukar Unit') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Tukar') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Catat') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Unit') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Selisih') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($exchanges as $exchange)
                            @php
                                $difference = (float) $exchange->items->sum('price_difference');
                                $needsAuthSubmit = app(\App\Services\AuthorizationService::class)->needsSubmitHighlight($exchange);
                            @endphp
                            <tr @class([
                                    'hover:bg-red-100/80' => $needsAuthSubmit,
                                    'hover:bg-slate-50/50' => ! $needsAuthSubmit,
                                ])
                                @if ($needsAuthSubmit) style="background-color:#fef2f2; box-shadow: inset 4px 0 0 #ef4444;" @endif>
                                <td class="px-4 py-3 font-medium text-slate-800">
                                    <div class="flex flex-col gap-1">
                                        <span>{{ $exchange->exchange_number }}</span>
                                        <x-authorization-list-badge :model="$exchange" />
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $exchange->status === \App\Models\UnitExchange::STATUS_OPEN ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ $exchange->status === \App\Models\UnitExchange::STATUS_OPEN ? __('Open') : __('Release') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $exchange->sale?->invoice_number ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $exchange->exchange_date?->timezone(\App\Support\CatatRange::TZ)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600"><x-wita-datetime :value="$exchange->created_at" /></td>
                                <td class="px-4 py-3">{{ $exchange->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ $exchange->items->count() }}</td>
                                <td class="px-4 py-3 text-right font-medium {{ $difference < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ number_format($difference, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <x-row-actions>
                                        <x-authorization-submit-action :model="$exchange" module="unit-exchanges" />
                                        <x-release-list-action :model="$exchange" />
                                        <a href="{{ route('sales.unit-exchanges.show', $exchange) }}" style="color:#047857;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            {{ __('Detail') }}
                                        </a>
                                        @if (
                                            auth()->user()?->canModule('unit-exchanges', 'update')
                                            && $exchange->status === \App\Models\UnitExchange::STATUS_OPEN
                                        )
                                            <a href="{{ route('sales.unit-exchanges.edit', $exchange) }}" style="color:#b45309;">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                {{ __('Edit') }}
                                            </a>
                                        @endif
                                        @if (auth()->user()?->canModule('unit-exchanges', 'download'))
                                            <a href="{{ route('sales.unit-exchanges.report', $exchange) }}" target="_blank" style="color:#1d4ed8;">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6h6v6m-8 4h10a2 2 0 002-2V7l-4-4H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                {{ __('Cetak Laporan') }}
                                            </a>
                                        @endif
                                    </x-row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-slate-500">{{ __('Belum ada tukar unit.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $exchanges->links() }}</div>
        </div>
    </div>
</x-app-layout>
