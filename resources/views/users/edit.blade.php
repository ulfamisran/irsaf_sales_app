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
                                $currentPlacement = old('placement_type') ?? ($user->placement_type === \App\Models\User::PLACEMENT_GUDANG ? 'gudang' : 'cabang');
                            @endphp
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

                            <div id="branch_field" class="placement-field" style="{{ $currentPlacement === 'gudang' ? 'display: none;' : '' }}">
                                <x-input-label for="branch_id" :value="__('Cabang Penempatan')" />
                                <select id="branch_id" name="branch_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" {{ $currentPlacement === 'cabang' ? 'required' : '' }}>
                                    <option value="">{{ __('Pilih Cabang') }}</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ old('branch_id', $user->branch_id) == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                            </div>

                            <div id="warehouse_field" class="placement-field" style="{{ $currentPlacement === 'gudang' ? '' : 'display: none;' }}">
                                <x-input-label for="warehouse_id" :value="__('Gudang Penempatan')" />
                                <select id="warehouse_id" name="warehouse_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" {{ $currentPlacement === 'gudang' ? 'required' : '' }}>
                                    <option value="">{{ __('Pilih Gudang') }}</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" {{ old('warehouse_id', $user->warehouse_id) == $warehouse->id ? 'selected' : '' }}>
                                            {{ $warehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
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
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
                    branchSelect.removeAttribute('required');
                    branchSelect.value = '';
                    warehouseSelect.setAttribute('required', 'required');
                } else {
                    branchField.style.display = 'block';
                    warehouseField.style.display = 'none';
                    warehouseSelect.removeAttribute('required');
                    warehouseSelect.value = '';
                    branchSelect.setAttribute('required', 'required');
                }
            }

            placementRadios.forEach(r => r.addEventListener('change', togglePlacementFields));
            togglePlacementFields();
        });
    </script>
</x-app-layout>
