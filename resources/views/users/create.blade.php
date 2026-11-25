<x-app-layout>
    <x-slot name="title">{{ __('Tambah User') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tambah User') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('users.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="name" :value="__('Nama')" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="email" :value="__('Email')" />
                                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
                                <p class="mt-1 text-xs text-slate-500">{{ __('Password awal akan diset sama dengan email.') }}</p>
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="role_id" :value="__('Role')" />
                                <select id="role_id" name="role_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="">{{ __('Pilih Role') }}</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                            {{ $role->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                            </div>

                            <div id="single_placement_fields">
                                <div>
                                    <x-input-label :value="__('Posisi Penempatan')" />
                                    <div class="mt-2 flex gap-6">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="placement_type" value="cabang" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                {{ old('placement_type', 'cabang') === 'cabang' ? 'checked' : '' }}>
                                            <span class="text-sm text-slate-700">{{ __('Cabang') }}</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="placement_type" value="gudang" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                {{ old('placement_type') === 'gudang' ? 'checked' : '' }}>
                                            <span class="text-sm text-slate-700">{{ __('Gudang') }}</span>
                                        </label>
                                    </div>
                                    <x-input-error :messages="$errors->get('placement_type')" class="mt-2" />
                                </div>

                                <div id="branch_field" class="placement-field mt-4" style="{{ old('placement_type', 'cabang') === 'gudang' ? 'display: none;' : '' }}">
                                    <x-input-label for="branch_id" :value="__('Cabang Penempatan')" />
                                    <select id="branch_id" name="branch_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ __('Pilih Cabang') }}</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                                </div>

                                <div id="warehouse_field" class="placement-field mt-4" style="{{ old('placement_type') === 'gudang' ? '' : 'display: none;' }}">
                                    <x-input-label for="warehouse_id" :value="__('Gudang Penempatan')" />
                                    <select id="warehouse_id" name="warehouse_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ __('Pilih Gudang') }}</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                                {{ $warehouse->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
                                </div>
                            </div>

                            <div id="multi_location_fields" class="space-y-4" style="display: none;">
                                <p class="text-sm text-slate-600">{{ __('Admin Pusat hanya dapat melihat dan mengolah data di cabang/gudang yang dipilih di bawah.') }}</p>
                                @php $oldBranchIds = collect(old('branch_ids', [])); @endphp
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
                                                    @checked($oldBranchIds->contains($branch->id))
                                                >
                                                <span class="text-sm text-slate-700">{{ $branch->name }}</span>
                                            </label>
                                        @empty
                                            <p class="text-sm text-slate-500">{{ __('Belum ada data cabang.') }}</p>
                                        @endforelse
                                    </div>
                                    <x-input-error :messages="$errors->get('branch_ids')" class="mt-2" />
                                </div>
                                @php $oldWarehouseIds = collect(old('warehouse_ids', [])); @endphp
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
                                                    @checked($oldWarehouseIds->contains($warehouse->id))
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
                                <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" {{ old('is_active', '1') ? 'checked' : '' }}>
                                <label for="is_active" class="text-sm text-slate-700">{{ __('Aktif') }}</label>
                            </div>

                            <p class="text-sm text-slate-500">{{ __('User baru otomatis didaftarkan dengan * sehingga dapat mencatat transaksi dari perangkat mana pun. Pembatasan IP diatur di halaman edit user.') }}</p>

                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Save') }}</x-primary-button>
                                <a href="{{ route('users.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </form>
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
