<x-app-layout>
    <x-slot name="title">{{ __('Tambah Gudang') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Warehouse') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('warehouses.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="address" :value="__('Address')" />
                                <textarea id="address" name="address" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="3">{{ old('address') }}</textarea>
                                <x-input-error :messages="$errors->get('address')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="pic_name" :value="__('PIC Name')" />
                                <x-text-input id="pic_name" class="block mt-1 w-full" type="text" name="pic_name" :value="old('pic_name')" />
                                <x-input-error :messages="$errors->get('pic_name')" class="mt-2" />
                            </div>
                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Save') }}</x-primary-button>
                                <a href="{{ route('warehouses.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
