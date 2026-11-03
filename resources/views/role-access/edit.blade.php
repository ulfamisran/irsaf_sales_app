<x-app-layout>
    <x-slot name="title">{{ __('Akses') }} {{ $role->display_name ?: $role->name }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ $role->display_name ?: $role->name }}</h2>
                <p class="text-sm text-slate-500 mt-1">{{ __('Centang modul dan perintah yang boleh dipakai role ini.') }}</p>
            </div>
            <a href="{{ route('role-access.index') }}" class="inline-flex items-center px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">{{ __('Kembali') }}</a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-rose-50 border border-rose-200 p-4 text-rose-800">{{ session('error') }}</div>
        @endif

        @if ($locked)
            <div class="mb-6 rounded-xl bg-indigo-50 border border-indigo-200 p-4 text-indigo-900">
                {{ __('Super Admin selalu diizinkan ke semua modul, termasuk halaman ini. Centang di bawah tidak bisa diubah supaya akses pengaturan tidak terkunci.') }}
            </div>
        @endif

        <div class="mb-4 text-sm text-slate-600">
            {{ __('Lihat membuka daftar dan detail. Tambah menyimpan data baru. Ubah menyimpan perubahan. Hapus menghapus data. Unduh untuk export, PDF, dan invoice. Laporan membuka Arus Kas, Monitoring Kas, Laba Rugi, dan menu Laporan. Batal transaksi membatalkan pembelian, penjualan, service, sewa, distribusi, atau mutasi dana.') }}
        </div>

        <form method="POST" action="{{ route('role-access.update', $role) }}">
            @csrf
            @method('PUT')

            @foreach ($grouped as $group => $items)
                <div class="card-modern overflow-hidden mb-6">
                    <div class="px-4 py-3 border-b border-slate-100 font-medium text-slate-800">{{ $group }}</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">{{ __('Modul') }}</th>
                                    @foreach ($actions as $key => $label)
                                        <th class="px-3 py-3 font-medium text-center whitespace-nowrap">{{ $label }}</th>
                                    @endforeach
                                    <th class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($items as $module)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-slate-800 whitespace-nowrap">{{ $module->name }}</td>
                                        @foreach ($actions as $key => $label)
                                            @php
                                                $checked = $locked || isset($granted[$module->id.'|'.$key]);
                                            @endphp
                                            <td class="px-3 py-3 text-center">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[{{ $module->slug }}][{{ $key }}]"
                                                    value="1"
                                                    title="{{ $module->name }} — {{ $label }}"
                                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                    @checked($checked)
                                                    @disabled($locked)
                                                >
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-3 text-right whitespace-nowrap">
                                            @unless ($locked)
                                                <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800" onclick="this.closest('tr').querySelectorAll('input[type=checkbox]').forEach(el => el.checked = true)">{{ __('Semua') }}</button>
                                                <button type="button" class="text-xs text-slate-500 hover:text-slate-700 ml-2" onclick="this.closest('tr').querySelectorAll('input[type=checkbox]').forEach(el => el.checked = false)">{{ __('Kosong') }}</button>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            @unless ($locked)
                <div class="sticky bottom-4 flex justify-end">
                    <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-medium shadow-lg hover:bg-indigo-700">
                        {{ __('Simpan akses') }}
                    </button>
                </div>
            @endunless
        </form>
    </div>
</x-app-layout>
