<x-app-layout>
    <x-slot name="title">{{ __('Edit Pengeluaran Dana') }} #{{ $cashFlow->id }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">
                {{ __('Edit Pengeluaran Dana') }} #{{ $cashFlow->id }}
            </h2>
            <a href="{{ route('cash-flows.out.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                {{ __('Kembali') }}
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6">
                <form method="POST" action="{{ route('cash-flows.out.update', $cashFlow) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-slate-500">{{ __('Lokasi') }}</p>
                            <p class="font-medium text-slate-800">{{ $locationLabel }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">{{ __('Jenis Pengeluaran') }}</p>
                            <p class="font-medium text-slate-800">{{ $cashFlow->expenseCategory?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <x-input-label for="transaction_date" :value="__('Tanggal Transaksi')" />
                            <x-text-input id="transaction_date" class="block mt-1 w-full" type="date" name="transaction_date" :value="old('transaction_date', $cashFlow->transaction_date->toDateString())" required />
                            <x-input-error :messages="$errors->get('transaction_date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="amount" :value="__('Jumlah')" />
                            <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $cashFlow->amount) }}" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('Nama Pengeluaran')" />
                        <x-text-input id="description" class="block mt-1 w-full" type="text" name="description" :value="old('description', $cashFlow->description ?? '')" required />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="payment_method_id" :value="__('Sumber Dana')" />
                        <select id="payment_method_id" name="payment_method_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            @foreach ($paymentMethods as $pm)
                                <option value="{{ $pm->id }}" {{ (int) $cashFlow->payment_method_id === (int) $pm->id ? 'selected' : '' }}>
                                    {{ $pm->display_label }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('payment_method_id')" class="mt-2" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-indigo-600 border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-indigo-700">
                            {{ __('Simpan Perubahan') }}
                        </button>
                        <a href="{{ route('cash-flows.out.index') }}" class="inline-flex items-center px-4 py-2.5 bg-gray-200 border border-transparent rounded-lg font-semibold text-sm text-gray-700 hover:bg-gray-300">
                            {{ __('Batal') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

