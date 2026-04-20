<x-app-layout>
    <x-slot name="title">{{ __('Tambah Pembayaran Distribusi') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Tambah Pembayaran Distribusi') }}</h2>
    </x-slot>

    <div class="max-w-2xl mx-auto">
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100 bg-slate-50">
                <h3 class="font-medium text-slate-800">{{ __('Info Distribusi') }}</h3>
                <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm text-slate-600">
                    <div>{{ __('Invoice') }}: {{ $distribution->invoice_number }}</div>
                    <div>{{ __('Tanggal') }}: {{ $distribution->distribution_date->format('d/m/Y') }}</div>
                    <div>{{ __('Dari') }}: {{ $distribution->from_location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang') }} {{ $fromLocation?->name ?? $distribution->from_location_id }}</div>
                    <div>{{ __('Ke') }}: {{ $distribution->to_location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang') }} {{ $toLocation?->name ?? $distribution->to_location_id }}</div>
                    <div>{{ __('Total Biaya') }}: {{ number_format($totalBiaya, 0, ',', '.') }}</div>
                    <div>{{ __('Sudah Bayar') }}: {{ number_format($totalPaid, 0, ',', '.') }}</div>
                    <div class="font-semibold text-red-600">{{ __('Sisa') }}: {{ number_format($sisa, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6">
                <form method="POST" action="{{ route('stock-mutations.store-payment', $distribution) }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="payment_method_id" :value="__('Metode Pembayaran / Kas')" />
                        <select id="payment_method_id" name="payment_method_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">{{ __('Pilih Metode') }}</option>
                            @foreach ($paymentMethods as $pm)
                                <option value="{{ $pm->id }}" {{ old('payment_method_id') == $pm->id ? 'selected' : '' }}>{{ $pm->display_label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Metode pembayaran harus dari lokasi asal distribusi') }}</p>
                        <x-input-error :messages="$errors->get('payment_method_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="transaction_date" :value="__('Tanggal Pembayaran')" />
                        <x-text-input id="transaction_date" class="block mt-1 w-full" type="date" name="transaction_date" :value="old('transaction_date', $distribution->distribution_date?->format('Y-m-d') ?? date('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('transaction_date')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="amount" :value="__('Nominal') . ' (maks: ' . number_format($sisa, 0, ',', '.') . ')'" />
                        <x-text-input id="amount" class="block mt-1 w-full" type="text" name="amount" data-rupiah="true" value="{{ old('amount', (string) (int) $sisa) }}" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <x-primary-button>{{ __('Simpan Pembayaran') }}</x-primary-button>
                        <a href="{{ route('stock-mutations.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">
                            {{ __('Batal') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.attachRupiahFormatter === 'function') {
                window.attachRupiahFormatter();
            }
        });
    </script>
    @endpush
</x-app-layout>
