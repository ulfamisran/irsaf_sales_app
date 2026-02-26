<x-app-layout>
    <x-slot name="title">{{ __('Tambah Metode Pembayaran') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tambah Metode Pembayaran') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('payment-methods.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="jenis_pembayaran" :value="__('Jenis Pembayaran')" />
                                <x-text-input id="jenis_pembayaran" class="block mt-1 w-full" type="text" name="jenis_pembayaran" :value="old('jenis_pembayaran')" required autofocus placeholder="Tunai / Transfer / QRIS" />
                                <x-input-error :messages="$errors->get('jenis_pembayaran')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="nama_bank" :value="__('Nama Bank (Opsional)')" />
                                    <x-text-input id="nama_bank" class="block mt-1 w-full" type="text" name="nama_bank" :value="old('nama_bank')" placeholder="BNI / BCA / Mandiri" />
                                    <x-input-error :messages="$errors->get('nama_bank')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="no_rekening" :value="__('No Rekening (Opsional)')" />
                                    <x-text-input id="no_rekening" class="block mt-1 w-full" type="text" name="no_rekening" :value="old('no_rekening')" placeholder="1234567890" />
                                    <x-input-error :messages="$errors->get('no_rekening')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="atas_nama_bank" :value="__('Atas Nama Bank (Opsional)')" />
                                <x-text-input id="atas_nama_bank" class="block mt-1 w-full" type="text" name="atas_nama_bank" :value="old('atas_nama_bank')" placeholder="Nama pemilik rekening" />
                                <x-input-error :messages="$errors->get('atas_nama_bank')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="keterangan" :value="__('Keterangan (Opsional)')" />
                                <textarea id="keterangan" name="keterangan" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="3">{{ old('keterangan') }}</textarea>
                                <x-input-error :messages="$errors->get('keterangan')" class="mt-2" />
                            </div>

                            <div class="flex items-center gap-2">
                                <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" {{ old('is_active', '1') ? 'checked' : '' }}>
                                <label for="is_active" class="text-sm text-slate-700">{{ __('Aktif') }}</label>
                            </div>

                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Save') }}</x-primary-button>
                                <a href="{{ route('payment-methods.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

