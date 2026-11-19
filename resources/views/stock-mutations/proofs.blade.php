<x-app-layout>
    <x-slot name="title">{{ __('Bukti Transaksi Distribusi') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Bukti Transaksi Distribusi') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        @if (session('success'))
            <div class="mb-4 rounded-md bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-md bg-red-50 p-4">
                <ul class="list-disc pl-5 text-red-800 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100 bg-slate-50">
                <h3 class="font-medium text-slate-800">{{ $distribution->invoice_number }}</h3>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-sm text-slate-600">
                    <div>{{ __('Tanggal') }}: {{ $distribution->distribution_date?->format('d/m/Y') }}</div>
                    <div>{{ __('Total biaya') }}: Rp {{ number_format((float) $distribution->total, 0, ',', '.') }}</div>
                    <div>{{ __('Dari') }}: {{ $distribution->fromLocationName() }}</div>
                    <div>{{ __('Ke') }}: {{ $distribution->toLocationName() }}</div>
                </div>
            </div>
        </div>

        <x-transaction-proof-fields
            :slots="$distribution->isCancelled() ? 0 : max(0, 2 - $distribution->proofs->count())"
            :existing="$distribution->proofs"
            :owner="$distribution"
            :upload-url="$distribution->isCancelled() ? null : route('stock-mutations.proofs.store', $distribution)"
            :can-delete="! $distribution->isCancelled()"
            :hint="__('Opsional. Maksimal 2 file gambar atau PDF.')"
        />

        <div class="mt-6">
            <a href="{{ route('stock-mutations.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Kembali') }}</a>
        </div>
    </div>
</x-app-layout>
