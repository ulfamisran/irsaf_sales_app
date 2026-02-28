<x-app-layout>
    <x-slot name="title">{{ __('Detail Penyewaan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Detail Penyewaan') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $rental->invoice_number }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('rentals.invoice', $rental) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    {{ __('Invoice') }}
                </a>
                <x-icon-btn-back :href="route('rentals.index')" :label="__('Kembali')" />
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Cabang') }}</p>
                    <p class="font-medium text-slate-800">{{ $rental->branch?->name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Gudang') }}</p>
                    <p class="font-medium text-slate-800">{{ $rental->warehouse?->name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Penyewa') }}</p>
                    <p class="font-medium text-slate-800">{{ $rental->customer?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Tgl Pengambilan') }}</p>
                    <p class="font-medium text-slate-800">{{ $rental->pickup_date?->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Tgl Pengembalian') }}</p>
                    <p class="font-medium text-slate-800">{{ $rental->return_date?->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Jumlah Hari') }}</p>
                    <p class="font-medium text-slate-800">{{ $rental->total_days }}</p>
                </div>
            </div>
        </div>

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Harga/Hari') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($rental->items as $item)
                            <tr>
                                <td class="px-4 py-3">{{ $item->product?->sku }} - {{ $item->product?->brand }} {{ $item->product?->series }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $item->serial_number }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($item->rental_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($item->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2"></div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="flex justify-between text-sm text-slate-600">
                            <span>{{ __('Subtotal') }}</span>
                            <span>{{ number_format($rental->subtotal, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm text-slate-600 mt-1">
                            <span>{{ __('Pajak') }}</span>
                            <span>{{ number_format($rental->tax_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm text-slate-600 mt-1">
                            <span>{{ __('Denda') }}</span>
                            <span>{{ number_format($rental->penalty_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm text-slate-800 mt-2 font-semibold">
                            <span>{{ __('Total') }}</span>
                            <span>{{ number_format($rental->total, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $rental->isPaidOff() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $rental->isPaidOff() ? __('Lunas') : __('Belum Lunas') }}
                    </span>
                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $rental->return_status === 'sudah_kembali' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $rental->return_status === 'sudah_kembali' ? __('Sudah Kembali') : __('Belum Kembali') }}
                    </span>
                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $rental->status === 'released' ? 'bg-emerald-100 text-emerald-800' : ($rental->status === 'cancel' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') }}">
                        {{ $rental->status === 'released' ? __('Release') : ($rental->status === 'cancel' ? __('Dibatalkan') : __('Open/Draft')) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6 overflow-x-auto">
                <h3 class="font-semibold text-slate-800 mb-3">{{ __('Riwayat Pembayaran') }}</h3>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Metode') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Nominal') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rental->payments as $p)
                            <tr>
                                <td class="px-4 py-3">{{ $p->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $p->paymentMethod?->display_label ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($p->amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-slate-500">{{ __('Belum ada pembayaran.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($rental->status === \App\Models\Rental::STATUS_OPEN)
            <div class="card-modern overflow-hidden mb-6">
                <div class="p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">{{ __('Pelunasan & Pengembalian') }}</h3>
                    <p class="text-xs text-amber-700 mb-3">{{ __('Wajib pelunasan sebelum pengembalian. Isi pembayaran jika belum lunas.') }}</p>
                    <form method="POST" action="{{ route('rentals.mark-returned', $rental) }}" id="rental-return-form">
                        @csrf
                        <div class="space-y-3" id="payment-rows"></div>
                        <button type="button" id="add-payment" class="inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah') }}</button>
                        <div class="mt-4">
                            <x-primary-button>{{ __('Simpan & Tandai Kembali') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    @php
        $paymentMethodsSimple = ($paymentMethods ?? collect())->map(fn ($m) => ['id' => $m->id, 'label' => $m->display_label])->values()->toArray();
    @endphp
    <script>
        const paymentMethods = @json($paymentMethodsSimple);
        const paymentRows = document.getElementById('payment-rows');
        let paymentIndex = 0;

        function paymentOptionsHtml() {
            return '<option value="">{{ __('Pilih metode') }}</option>' + paymentMethods.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
        }

        function addPaymentRow() {
            if (!paymentRows) return;
            const idx = paymentIndex++;
            const div = document.createElement('div');
            div.className = 'flex flex-col md:flex-row gap-2 items-end';
            div.innerHTML = `
                <div class="flex-1">
                    <select name="payments[${idx}][payment_method_id]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        ${paymentOptionsHtml()}
                    </select>
                </div>
                <div class="w-full md:w-48">
                    <input type="text" name="payments[${idx}][amount]" data-rupiah="true" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nominal" required>
                </div>
                <div class="w-full md:w-64">
                    <input type="text" name="payments[${idx}][notes]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Catatan (opsional)">
                </div>
                <button type="button" class="remove-payment px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200">-</button>
            `;
            paymentRows.appendChild(div);
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            div.querySelector('.remove-payment')?.addEventListener('click', () => div.remove());
        }

        document.getElementById('add-payment')?.addEventListener('click', addPaymentRow);
        if (paymentRows) {
            addPaymentRow();
        }
    </script>
</x-app-layout>
