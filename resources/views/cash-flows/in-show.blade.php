<x-app-layout>
    <x-slot name="title">{{ __('Detail Kas Masuk Lainnya') }} #{{ $cashFlow->id }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">
                {{ __('Detail Kas Masuk Lainnya') }} #{{ $cashFlow->id }}
            </h2>
            <a href="{{ route('cash-flows.in.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
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
                        <p class="text-sm text-slate-500">{{ __('Waktu Input') }}</p>
                        <p class="font-medium">{{ $cashFlow->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
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
                        <p class="text-sm text-slate-500">{{ __('Kategori Pemasukan') }}</p>
                        <p class="font-medium">{{ $cashFlow->incomeCategory?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Metode Pembayaran') }}</p>
                        <p class="font-medium">{{ $cashFlow->paymentMethod?->display_label ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Jumlah') }}</p>
                        <p class="text-xl font-bold text-emerald-600">+{{ number_format($cashFlow->amount, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Dibuat Oleh') }}</p>
                        <p class="font-medium">{{ $cashFlow->user?->name ?? '-' }}</p>
                    </div>
                    @if ($cashFlow->description)
                        <div class="md:col-span-2">
                            <p class="text-sm text-slate-500">{{ __('Keterangan') }}</p>
                            <p class="text-slate-700 whitespace-pre-line">{{ $cashFlow->description }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
