<x-app-layout>
    <x-slot name="title">{{ __('Konfigurasi Otorisasi') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Konfigurasi Otorisasi') }}</h2>
    </x-slot>

    <div class="max-w-5xl mx-auto">
        @if (session('success'))
            <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('authorization-settings.update') }}" class="card-modern p-6 space-y-6">
            @csrf
            @method('PUT')
            <p class="text-sm text-slate-600">{{ __('Jika modul dimatikan, transaksi berjalan seperti biasa. Jika dinyalakan, dokumen baru wajib diajukan dan baru bisa di-release setelah disetujui. Satu persetujuan cukup. Pembuat dokumen tidak dapat menyetujui dokumennya sendiri. Halaman Otorisasi hanya tampil bagi user yang dipilih di bawah.') }}</p>

            <div class="border border-indigo-200 bg-indigo-50/60 rounded-xl p-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <h3 class="font-semibold text-slate-800">{{ __('Role yang tampil di pilihan user otorisasi') }}</h3>
                        <p class="text-sm text-slate-600 mt-1">{{ __('Centang role yang boleh muncul saat memilih pengguna otorisasi per modul. Perubahan role memperbarui daftar user di bawah setelah disimpan.') }}</p>
                    </div>
                </div>
                @php
                    $pickedRoles = array_map('intval', (array) old('picker_role_ids', $pickerRoleIds));
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @forelse ($roles as $role)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="picker_role_ids[]" value="{{ $role->id }}" class="rounded border-slate-300 text-indigo-600" @checked(in_array((int) $role->id, $pickedRoles, true))>
                            <span class="font-medium text-slate-800">{{ $role->display_name ?: $role->name }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('Belum ada role.') }}</p>
                    @endforelse
                </div>
            </div>

            @foreach ($modules as $slug => $meta)
                @php
                    $active = (bool) ($settings[$slug]->is_active ?? false);
                    $selected = ($assignees[$slug] ?? collect())->pluck('user_id')->map(fn ($id) => (int) $id)->all();
                @endphp
                <div class="border border-slate-200 rounded-xl p-4">
                    <label class="flex items-center gap-3 font-semibold text-slate-800">
                        <input type="hidden" name="modules[{{ $slug }}][is_active]" value="0">
                        <input type="checkbox" name="modules[{{ $slug }}][is_active]" value="1" @checked(old('modules.'.$slug.'.is_active', $active))>
                        {{ $meta['label'] }}
                    </label>
                    @php
                        $picked = array_map('intval', (array) old('modules.'.$slug.'.user_ids', $selected));
                    @endphp
                    <div class="mt-3" x-data="{ q: '' }">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <label class="block text-sm text-slate-600">{{ __('Pengguna yang boleh menyetujui') }}</label>
                            <span class="text-xs text-slate-500">{{ __('Bisa lebih dari satu') }}</span>
                        </div>
                        @if ($users->isEmpty())
                            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ __('Belum ada user yang cocok. Centang minimal satu role di atas, lalu simpan.') }}</p>
                        @else
                            <input type="search" x-model="q" placeholder="{{ __('Cari pengguna') }}" class="w-full rounded-lg border border-slate-300 text-sm mb-2">
                            <div class="max-h-52 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                                @foreach ($users as $assignee)
                                    @php
                                        $roleLabel = $assignee->roles->pluck('display_name')->filter()->implode(', ');
                                        $label = mb_strtolower($assignee->name.' '.$roleLabel.' '.$assignee->placement_display);
                                    @endphp
                                    <label class="flex items-center gap-3 px-3 py-2 text-sm cursor-pointer hover:bg-slate-50" x-show="q === '' || @js($label).includes(q.toLowerCase())">
                                        <input type="checkbox" name="modules[{{ $slug }}][user_ids][]" value="{{ $assignee->id }}" class="rounded border-slate-300 text-indigo-600" @checked(in_array((int) $assignee->id, $picked, true))>
                                        <span class="font-medium text-slate-800">{{ $assignee->name }}</span>
                                        <span class="text-xs text-slate-500">{{ $roleLabel }} · {{ $assignee->placement_display }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold">{{ __('Simpan') }}</button>
        </form>
    </div>
</x-app-layout>
