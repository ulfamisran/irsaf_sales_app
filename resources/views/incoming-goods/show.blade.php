<x-app-layout>
    <x-slot name="title">{{ __('Detail Barang Masuk') }} #{{ $incomingGood->id }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Detail Barang Masuk') }} #{{ $incomingGood->id }}</h2>
            <x-icon-btn-back :href="route('incoming-goods.index')" :label="__('Kembali')" />
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Tanggal') }}</p>
                        <p class="font-medium">{{ $incomingGood->received_date?->format('d/m/Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Waktu Input') }}</p>
                        <p class="font-medium">{{ $incomingGood->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Lokasi') }}</p>
                        <p class="font-medium">
                            @if ($incomingGood->warehouse_id)
                                {{ __('Gudang') }}: {{ $incomingGood->warehouse?->name ?? '-' }}
                            @else
                                {{ __('Cabang') }}: {{ $incomingGood->branch?->name ?? '-' }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('User') }}</p>
                        <p class="font-medium">{{ $incomingGood->user?->name ?? '-' }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-slate-500">{{ __('Produk') }}</p>
                        <p class="font-medium">
                            {{ ($incomingGood->product?->sku ?? '-') }} - {{ $incomingGood->product?->brand ?? '' }} {{ $incomingGood->product?->series ?? '' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Kategori') }}</p>
                        <p class="font-medium">{{ $incomingGood->product?->category?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ __('Qty') }}</p>
                        <p class="font-medium">{{ $incomingGood->quantity }}</p>
                    </div>
                    @if ($incomingGood->notes)
                        <div class="md:col-span-2">
                            <p class="text-sm text-slate-500">{{ __('Catatan / Serial') }}</p>
                            <p class="text-slate-700 whitespace-pre-line">{{ $incomingGood->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($units->isNotEmpty())
            <div class="card-modern overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-slate-800">{{ __('Unit / Serial') }}</h3>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Serial') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Posisi') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($units as $u)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-sm">{{ $u->serial_number }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($u->location_type === 'warehouse')
                                            {{ __('Gudang') }}: {{ $u->warehouse?->name ?? '-' }}
                                        @else
                                            {{ __('Cabang') }}: {{ $u->branch?->name ?? '-' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ $u->status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
