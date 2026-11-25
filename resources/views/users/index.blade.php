<x-app-layout>
    <x-slot name="title">{{ __('Kelola User') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Kelola User') }}</h2>
            <x-icon-btn-add :href="route('users.create')" :label="__('Tambah User')" />
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
            <div class="mb-6 rounded-xl bg-rose-50 border border-rose-200 p-4 text-rose-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v4a1 1 0 002 0V7zm-1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                @php
                    $selectedLocationType = request('location_type', '');
                    $selectedLocationId = request('location_id');
                @endphp
                <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cari') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Nama / email...') }}"
                            class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Role') }}</label>
                        <select name="role_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua Role') }}</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>
                                    {{ $role->display_name ?: $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[150px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                        <select name="location_type" id="user_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="cabang" @selected($selectedLocationType === 'cabang')>{{ __('Cabang') }}</option>
                            <option value="gudang" @selected($selectedLocationType === 'gudang')>{{ __('Gudang') }}</option>
                        </select>
                    </div>
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                        <select name="location_id" id="user_location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @disabled($selectedLocationType === '')>
                            <option value="">{{ __('Semua Lokasi') }}</option>
                            @if ($selectedLocationType === 'cabang')
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) $selectedLocationId === (string) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            @elseif ($selectedLocationType === 'gudang')
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((string) $selectedLocationId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="min-w-[140px]">
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
                        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Email') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Role') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Penempatan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('IP Publik') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($users as $user)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $user->name }}</td>
                                <td class="px-4 py-3">{{ $user->email }}</td>
                                <td class="px-4 py-3">{{ $user->roles->pluck('display_name')->implode(', ') ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $user->placement_display }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $user->publicIpSummary() }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $user->is_active ? __('Aktif') : __('Nonaktif') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-icon-btn-edit :href="route('users.edit', $user)" />
                                        <form action="{{ route('users.reset-password', $user) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-100 text-amber-800 hover:bg-amber-200">
                                                {{ __('Reset Password') }}
                                            </button>
                                        </form>
                                        <form action="{{ route('users.toggle-active', $user) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium {{ $user->is_active ? 'bg-rose-100 text-rose-800 hover:bg-rose-200' : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' }}">
                                                {{ $user->is_active ? __('Nonaktifkan') : __('Aktifkan') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data user.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $users->links() }}</div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeEl = document.getElementById('user_location_type');
            const idEl = document.getElementById('user_location_id');
            if (!typeEl || !idEl) return;

            const branches = @json($branches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values());
            const warehouses = @json($warehouses->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->values());
            const selectedId = @json((string) request('location_id', ''));

            function fillLocations(keepSelected) {
                const type = typeEl.value;
                idEl.innerHTML = '<option value="">{{ __('Semua Lokasi') }}</option>';
                if (!type) {
                    idEl.disabled = true;
                    idEl.value = '';
                    return;
                }
                idEl.disabled = false;
                const list = type === 'cabang' ? branches : warehouses;
                list.forEach((item) => {
                    const opt = document.createElement('option');
                    opt.value = String(item.id);
                    opt.textContent = item.name;
                    if (keepSelected && selectedId && selectedId === String(item.id)) opt.selected = true;
                    idEl.appendChild(opt);
                });
            }

            typeEl.addEventListener('change', function () {
                fillLocations(false);
            });
        });
    </script>
</x-app-layout>
