<x-app-layout>
    <x-slot name="title">{{ __('Pelanggan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Pelanggan / Pembeli') }}</h2>
            <x-icon-btn-add :href="route('customers.create')" :label="__('Tambah Pelanggan')" />
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
                <form method="GET" action="{{ route('customers.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[240px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cari') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Nama / no HP / alamat...') }}"
                            class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status') }}</label>
                        <select name="is_active" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('Aktif') }}</option>
                            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('Nonaktif') }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Nama') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. HP') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Alamat') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Aktif') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($customers as $c)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $c->name }}</td>
                                <td class="px-4 py-3">{{ $c->phone ?? '-' }}</td>
                                <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($c->address, 60) }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $c->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $c->is_active ? __('Aktif') : __('Nonaktif') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-icon-btn-edit :href="route('customers.edit', $c)" />
                                        <form action="{{ route('customers.destroy', $c) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-btn-delete />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data pelanggan.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $customers->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>

