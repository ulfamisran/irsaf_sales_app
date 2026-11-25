<x-app-layout>
    <x-slot name="title">{{ __('Edit User') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit User') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('users.update', $user) }}">
                        @csrf
                        @method('PUT')
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="name" :value="__('Nama')" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $user->name)" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="email" :value="__('Email')" />
                                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $user->email)" required />
                                <p class="mt-1 text-xs text-slate-500">{{ __('Untuk mengubah password, gunakan Reset Password di halaman daftar user.') }}</p>
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="role_id" :value="__('Role')" />
                                <select id="role_id" name="role_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="">{{ __('Pilih Role') }}</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" {{ old('role_id', $user->roles->first()?->id) == $role->id ? 'selected' : '' }}>
                                            {{ $role->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                            </div>

                            @php
                                $isAdminPusatUser = $user->roles->contains(fn ($role) => $role->name === \App\Models\Role::ADMIN_PUSAT);
                                $currentPlacement = old('placement_type') ?? ($user->placement_type === \App\Models\User::PLACEMENT_GUDANG ? 'gudang' : 'cabang');
                                $selectedBranchIds = collect(old('branch_ids', $user->assignedBranches->pluck('id')->all()));
                                $selectedWarehouseIds = collect(old('warehouse_ids', $user->assignedWarehouses->pluck('id')->all()));
                            @endphp
                            <div id="single_placement_fields" style="{{ $isAdminPusatUser ? 'display: none;' : '' }}">
                                <div>
                                    <x-input-label :value="__('Posisi Penempatan')" />
                                    <div class="mt-2 flex gap-6">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="placement_type" value="cabang" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                {{ $currentPlacement === 'cabang' ? 'checked' : '' }}>
                                            <span class="text-sm text-slate-700">{{ __('Cabang') }}</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="placement_type" value="gudang" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                {{ $currentPlacement === 'gudang' ? 'checked' : '' }}>
                                            <span class="text-sm text-slate-700">{{ __('Gudang') }}</span>
                                        </label>
                                    </div>
                                    <x-input-error :messages="$errors->get('placement_type')" class="mt-2" />
                                </div>

                                <div id="branch_field" class="placement-field mt-4" style="{{ $currentPlacement === 'gudang' ? 'display: none;' : '' }}">
                                    <x-input-label for="branch_id" :value="__('Cabang Penempatan')" />
                                    <select id="branch_id" name="branch_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ __('Pilih Cabang') }}</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ old('branch_id', $user->branch_id) == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                                </div>

                                <div id="warehouse_field" class="placement-field mt-4" style="{{ $currentPlacement === 'gudang' ? '' : 'display: none;' }}">
                                    <x-input-label for="warehouse_id" :value="__('Gudang Penempatan')" />
                                    <select id="warehouse_id" name="warehouse_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ __('Pilih Gudang') }}</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}" {{ old('warehouse_id', $user->warehouse_id) == $warehouse->id ? 'selected' : '' }}>
                                                {{ $warehouse->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
                                </div>
                            </div>

                            <div id="multi_location_fields" class="space-y-4" style="{{ $isAdminPusatUser ? '' : 'display: none;' }}">
                                <p class="text-sm text-slate-600">{{ __('Admin Pusat hanya dapat melihat dan mengolah data di cabang/gudang yang dipilih di bawah.') }}</p>
                                <div>
                                    <x-input-label :value="__('Cabang yang Bisa Diakses')" />
                                    <div class="mt-2 rounded-lg border border-slate-200 p-3 max-h-56 overflow-y-auto space-y-2">
                                        @forelse ($branches as $branch)
                                            <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 rounded px-2 py-1.5">
                                                <input
                                                    type="checkbox"
                                                    name="branch_ids[]"
                                                    value="{{ $branch->id }}"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    @checked($selectedBranchIds->contains($branch->id))
                                                >
                                                <span class="text-sm text-slate-700">{{ $branch->name }}</span>
                                            </label>
                                        @empty
                                            <p class="text-sm text-slate-500">{{ __('Belum ada data cabang.') }}</p>
                                        @endforelse
                                    </div>
                                    <x-input-error :messages="$errors->get('branch_ids')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label :value="__('Gudang yang Bisa Diakses')" />
                                    <div class="mt-2 rounded-lg border border-slate-200 p-3 max-h-56 overflow-y-auto space-y-2">
                                        @forelse ($warehouses as $warehouse)
                                            <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 rounded px-2 py-1.5">
                                                <input
                                                    type="checkbox"
                                                    name="warehouse_ids[]"
                                                    value="{{ $warehouse->id }}"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    @checked($selectedWarehouseIds->contains($warehouse->id))
                                                >
                                                <span class="text-sm text-slate-700">{{ $warehouse->name }}</span>
                                            </label>
                                        @empty
                                            <p class="text-sm text-slate-500">{{ __('Belum ada data gudang.') }}</p>
                                        @endforelse
                                    </div>
                                    <x-input-error :messages="$errors->get('warehouse_ids')" class="mt-2" />
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                                <label for="is_active" class="text-sm text-slate-700">{{ __('Aktif') }}</label>
                            </div>

                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Update') }}</x-primary-button>
                                <a href="{{ route('users.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-slate-800">{{ __('Akses IP Publik') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('User hanya dapat mencatat transaksi dari IP publik yang terdaftar. Isi * jika user boleh memakai perangkat apa pun. Satu user dapat memiliki lebih dari satu IP.') }}
                    </p>

                    @if (session('success'))
                        <div class="mt-4 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
                    @endif
                    <x-input-error :messages="$errors->get('ip_address')" class="mt-3" />

                    @php
                        $allowsAnyDevice = $user->publicIps->contains(fn ($row) => $row->isAnyDevice());
                    @endphp
                    <p class="mt-4 text-sm font-medium {{ $allowsAnyDevice ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ $allowsAnyDevice ? __('Status: bebas, semua perangkat.') : __('Status: terbatas pada IP yang terdaftar.') }}
                    </p>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('IP Publik') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Keterangan') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($user->publicIps as $publicIp)
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-slate-800">
                                            {{ $publicIp->isAnyDevice() ? __('* (semua perangkat)') : $publicIp->ip_address }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">{{ $publicIp->note ?: '-' }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <form method="POST" action="{{ route('users.public-ips.destroy', [$user, $publicIp]) }}" onsubmit="return confirm('{{ __('Hapus IP ini?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-rose-100 text-rose-800 hover:bg-rose-200">
                                                    {{ __('Hapus') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-3 py-6 text-center text-sm text-slate-500">{{ __('Belum ada IP. User ini belum bisa mencatat transaksi.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('users.public-ips.store', $user) }}" class="mt-6 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        @csrf
                        <div>
                            <x-input-label for="ip_address" :value="__('IP Publik')" />
                            <x-text-input id="ip_address" name="ip_address" class="block mt-1 w-full" type="text" :value="old('ip_address')" placeholder="* atau 15.235.163.20" required />
                        </div>
                        <div>
                            <x-input-label for="note" :value="__('Keterangan')" />
                            <x-text-input id="note" name="note" class="block mt-1 w-full" type="text" :value="old('note')" placeholder="{{ __('Misalnya kasir kas 1') }}" />
                        </div>
                        <x-primary-button>{{ __('Daftarkan') }}</x-primary-button>
                    </form>

                    @if ($loginIps->isNotEmpty())
                        <div class="mt-6">
                            <p class="text-sm font-medium text-slate-700">{{ __('IP publik dari login terakhir') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($loginIps as $log)
                                    <form method="POST" action="{{ route('users.public-ips.store', $user) }}">
                                        @csrf
                                        <input type="hidden" name="ip_address" value="{{ $log->ip_publik }}">
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200">
                                            {{ __('Daftarkan') }} {{ $log->ip_publik }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const adminPusatRoleId = @json($adminPusatRoleId ? (string) $adminPusatRoleId : '');
            const roleSelect = document.getElementById('role_id');
            const singleFields = document.getElementById('single_placement_fields');
            const multiFields = document.getElementById('multi_location_fields');
            const placementRadios = document.querySelectorAll('input[name="placement_type"]');
            const branchField = document.getElementById('branch_field');
            const warehouseField = document.getElementById('warehouse_field');
            const branchSelect = document.getElementById('branch_id');
            const warehouseSelect = document.getElementById('warehouse_id');

            function togglePlacementFields() {
                const val = document.querySelector('input[name="placement_type"]:checked')?.value;
                if (val === 'gudang') {
                    branchField.style.display = 'none';
                    warehouseField.style.display = 'block';
                    branchSelect.value = '';
                } else {
                    branchField.style.display = 'block';
                    warehouseField.style.display = 'none';
                    warehouseSelect.value = '';
                }
            }

            function toggleRoleMode() {
                const isAdminPusat = adminPusatRoleId !== '' && String(roleSelect.value) === adminPusatRoleId;
                singleFields.style.display = isAdminPusat ? 'none' : 'block';
                multiFields.style.display = isAdminPusat ? 'block' : 'none';
                placementRadios.forEach((el) => { el.disabled = isAdminPusat; });
                if (! isAdminPusat) togglePlacementFields();
            }

            roleSelect.addEventListener('change', toggleRoleMode);
            placementRadios.forEach(r => r.addEventListener('change', togglePlacementFields));
            toggleRoleMode();
        });
    </script>
</x-app-layout>
