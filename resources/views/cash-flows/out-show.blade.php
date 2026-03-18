<x-app-layout>
    <x-slot name="title">{{ __('Detail Pengeluaran') }} #{{ $cashFlow->id }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">
                {{ __('Detail Pengeluaran') }} #{{ $cashFlow->id }}
            </h2>
            <a href="{{ route('cash-flows.out.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                {{ __('Kembali ke Daftar') }}
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto">
        <div class="card-modern overflow-hidden">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Tanggal Transaksi') }}</p>
                        <p class="font-medium">{{ $cashFlow->transaction_date->format('d/m/Y') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Lokasi') }}</p>
                        <p class="font-medium">
                            @if ($cashFlow->warehouse_id)
                                {{ __('Gudang') }}: {{ $cashFlow->warehouse?->name ?? '-' }}
                            @else
                                {{ __('Cabang') }}: {{ $cashFlow->branch?->name ?? '-' }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Jenis Pengeluaran') }}</p>
                        <p class="font-medium">{{ $cashFlow->expenseCategory?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Metode Pembayaran') }}</p>
                        <p class="font-medium">{{ $cashFlow->paymentMethod?->display_label ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Jumlah') }}</p>
                        <p class="text-xl font-bold text-red-600">-{{ number_format($cashFlow->amount, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Dibuat Oleh') }}</p>
                        <p class="font-medium">{{ $cashFlow->user?->name ?? '-' }}</p>
                    </div>
                    @if ($referenceLabel)
                        <div class="md:col-span-2">
                            <p class="text-sm text-slate-500">{{ __('Referensi') }}</p>
                            <p class="font-medium">
                                @if ($referenceUrl)
                                    <a href="{{ $referenceUrl }}" class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $referenceLabel }}</a>
                                @else
                                    {{ $referenceLabel }}
                                @endif
                            </p>
                        </div>
                    @endif
                    @if ($cashFlow->description)
                        <div class="md:col-span-2">
                            <p class="text-sm text-slate-500">{{ __('Nama Pengeluaran') }}</p>
                            <p class="text-slate-700 whitespace-pre-line">{{ $cashFlow->description }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
