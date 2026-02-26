<x-app-layout>
    <x-slot name="title">{{ __('Tambah Kategori Pengeluaran') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Tambah Jenis Pengeluaran') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto">
            <div class="card-modern overflow-hidden">
                <div class="p-6">
                    <form method="POST" action="{{ route('expense-categories.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="name" :value="__('Nama')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="code" :value="__('Kode (opsional)')" />
                            <x-text-input id="code" class="block mt-1 w-full" type="text" name="code" :value="old('code')" />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="description" :value="__('Deskripsi (opsional)')" />
                            <textarea id="description" name="description" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="is_active" type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label for="is_active" class="text-sm text-slate-700">{{ __('Aktif') }}</label>
                        </div>

                        <div class="flex gap-3">
                            <x-primary-button>{{ __('Simpan') }}</x-primary-button>
                            <a href="{{ route('expense-categories.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">
                                {{ __('Batal') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

