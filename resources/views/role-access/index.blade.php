<x-app-layout>
    <x-slot name="title">{{ __('Akses Modul') }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Akses Modul') }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ __('Atur modul dan perintah untuk setiap role. Hanya Super Admin dan Admin Pusat.') }}</p>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto">
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-rose-50 border border-rose-200 p-4 text-rose-800">{{ session('error') }}</div>
        @endif

        @if (! $ready)
            <div class="mb-6 rounded-xl bg-amber-50 border border-amber-200 p-4 text-amber-900">
                {{ __('Tabel akses belum ada. Jalankan php artisan migrate di server, lalu buka halaman ini lagi.') }}
            </div>
        @endif

        <div class="card-modern overflow-hidden mb-6">
            <form method="POST" action="{{ route('role-access.store') }}" class="p-4 grid gap-4 md:grid-cols-[1fr_1fr_1.4fr_auto] md:items-end">
                @csrf
                <div>
                    <label for="display_name" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Nama role') }}</label>
                    <input id="display_name" name="display_name" type="text" value="{{ old('display_name') }}" required maxlength="100"
                        class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="{{ __('Misalnya Admin Toko') }}">
                    <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
                </div>
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Kode role') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="50"
                        class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="{{ __('admin_toko, opsional') }}">
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Keterangan') }}</label>
                    <input id="description" name="description" type="text" value="{{ old('description') }}" maxlength="255"
                        class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>
                <button type="submit" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    {{ __('Tambah Role') }}
                </button>
            </form>
            <p class="px-4 pb-4 text-xs text-slate-500">{{ __('Role baru belum punya akses. Setelah disimpan, centang modul yang boleh dipakai. Role ini juga muncul saat membuat user.') }}</p>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-4 border-b border-slate-100 text-sm text-slate-600">
                {{ __('Super Admin selalu punya akses penuh dan tidak bisa dikunci. Centang role lain mengatur menu dan URL. Batasan cabang atau gudang pada data tetap seperti sebelumnya.') }}
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Izin tersimpan') }}</th>
                            <th class="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($roles as $role)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-800">{{ $role->display_name ?: $role->name }}</div>
                                    <div class="text-xs text-slate-400">{{ $role->name }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if ($role->name === \App\Models\Role::SUPER_ADMIN)
                                        {{ __('Penuh, terkunci') }}
                                    @else
                                        {{ (int) ($counts[$role->id] ?? 0) }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($ready)
                                        <a href="{{ route('role-access.edit', $role) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                                            {{ $role->name === \App\Models\Role::SUPER_ADMIN ? __('Lihat') : __('Atur') }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
