<x-app-layout>
    <x-slot name="title">{{ __('Distributor') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Distributor') }}</h2>
            @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_GUDANG, \App\Models\Role::ADMIN_CABANG, \App\Models\Role::ADMIN_PUSAT]))
                <x-icon-btn-add :href="route('distributors.create')" :label="__('Tambah Distributor')" />
            @else
                <button type="button" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg font-semibold text-sm text-white bg-gradient-to-r from-indigo-600 to-indigo-700 opacity-60 cursor-not-allowed" disabled>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Tambah Distributor') }}
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
                <form method="GET" action="{{ route('distributors.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cari') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Nama, alamat, atau no.hp...') }}"
                            class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <x-location-filter
                        :filter-locked="$locationFilter['filterLocked']"
                        :location-type="$locationFilter['locationType']"
                        :location-id="$locationFilter['locationId']"
                        :location-label="$locationFilter['locationLabel']"
                        :branches="$locationFilter['branches']"
                        :warehouses="$locationFilter['warehouses']"
                    />
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('distributors.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Nama') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Alamat') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. HP') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($distributors as $distributor)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    @if($distributor->isGlobal())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 text-xs">{{ __('Semua Cabang & Gudang') }}</span>
                                    @elseif($distributor->branch_id)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-800 text-xs">{{ __('Cabang') }}: {{ $distributor->branch?->name ?? '-' }}</span>
                                    @elseif($distributor->warehouse_id)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-violet-50 text-violet-800 text-xs">{{ __('Gudang') }}: {{ $distributor->warehouse?->name ?? '-' }}</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $distributor->name }}</td>
                                <td class="px-4 py-3">{{ Str::limit($distributor->address, 50) }}</td>
                                <td class="px-4 py-3">{{ $distributor->phone }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if (auth()->user()->isSuperAdmin())
                                            <x-icon-btn-edit :href="route('distributors.edit', $distributor)" />
                                            <form action="{{ route('distributors.destroy', $distributor) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <x-icon-btn-delete />
                                            </form>
                                        @else
                                            <button type="button" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 text-slate-400 cursor-not-allowed" disabled>
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M17.414 2.586a2 2 0 010 2.828l-9.9 9.9a1 1 0 01-.353.222l-4 1.333a1 1 0 01-1.263-1.263l1.333-4a1 1 0 01.222-.353l9.9-9.9a2 2 0 012.828 0z"/>
                                                </svg>
                                            </button>
                                            <button type="button" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 text-slate-400 cursor-not-allowed" disabled>
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M6 7a1 1 0 011-1h6a1 1 0 011 1v9a2 2 0 01-2 2H8a2 2 0 01-2-2V7zm2-3a1 1 0 00-1 1v1h6V5a1 1 0 00-1-1H8z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data distributor.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $distributors->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
