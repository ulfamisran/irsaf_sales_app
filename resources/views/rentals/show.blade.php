<x-app-layout>
    <x-slot name="title">{{ __('Detail Penyewaan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Detail Penyewaan') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $rental->invoice_number }}</p>
            </div>
            <div class="flex gap-2">
                @if (! $rental->requires_authorization || $rental->status === \App\Models\Rental::STATUS_RELEASED)
                <a href="{{ route('rentals.invoice', $rental) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    {{ __('Invoice') }}
                </a>
                @endif
                <a href="{{ route('rentals.report', $rental) }}" target="_blank" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:8px;background:#0f172a;color:#fff;font-size:14px;font-weight:600;text-decoration:none;">
                    {{ __('Cetak Laporan') }}
                </a>
                @if (auth()->user()?->canModule('rentals', 'update') && ! $rental->effects_pending && ! app(\App\Services\AuthorizationService::class)->blocksMutation($rental) && in_array($rental->status, [\App\Models\Rental::STATUS_OPEN, \App\Models\Rental::STATUS_RELEASED], true))
                    @if ($rental->status === \App\Models\Rental::STATUS_OPEN)
                        <a href="{{ route('rentals.edit', $rental) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700">
                            {{ __('Edit') }}
                        </a>
                    @endif
                @endif
                @if (auth()->user()?->isSuperAdminOrAdminPusat() && $rental->status !== \App\Models\Rental::STATUS_CANCEL)
                    <a href="{{ route('admin-tx.edit-rental', $rental) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">
                        {{ __('Update Transaksi') }}
                    </a>
                @endif
                <x-icon-btn-back :href="route('rentals.index')" :label="__('Kembali')" />
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <x-authorization-actions :model="$rental" module="rentals" :release-route="route('rentals.release', $rental)" />
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="mb-4">
            <a href="{{ route('rentals.report', $rental) }}" target="_blank" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:8px;background:#0f172a;color:#fff;font-size:14px;font-weight:600;text-decoration:none;">{{ __('Cetak Laporan') }}</a>
        </div>

        @if ($rental->status === \App\Models\Rental::STATUS_CANCEL)
            <div class="card-modern overflow-hidden mb-6">
                <div class="p-6 rounded-lg border border-rose-200 bg-rose-50/50">
                    <p class="text-sm font-semibold text-rose-700">{{ __('Informasi Pembatalan') }}</p>
                    <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm text-slate-700">
                        <div>
                            <p class="text-xs text-slate-500">{{ __('Tanggal Batal') }}</p>
                            <p class="font-medium">{{ $rental->cancel_date?->format('d/m/Y') ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">{{ __('Dibatalkan Oleh') }}</p>
                            <p class="font-medium">{{ $rental->cancelUser?->name ?? '-' }}</p>
                        </div>
                        <div class="md:col-span-1">
                            <p class="text-xs text-slate-500">{{ __('Alasan Batal') }}</p>
                            <p class="font-medium whitespace-pre-line">{{ $rental->cancel_reason ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Cabang') }}</p>
                    <p class="font-medium text-slate-800">{{ $rental->branch?->name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Lokasi Pengambilan') }}</p>
                    <p class="font-medium text-slate-800">{{ ($rental->location_type ?? 'warehouse') === 'branch' ? ($rental->branch?->name ?? '-') : ($rental->warehouse?->name ?? '-') }}</p>
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
                <div class="mt-4">
                    <x-transaction-proof-fields
                        :require-one="true"
                        :slots="$rental->status === 'open' ? max(0, 2 - $rental->proofs->count()) : 0"
                        :existing="$rental->proofs"
                        :owner="$rental"
                        :upload-url="$rental->status === 'open' ? route('rentals.proofs.store', $rental) : null"
                        :can-delete="$rental->status === 'open'"
                    />
                </div>
            </div>
        </div>

        @if ($rental->status === \App\Models\Rental::STATUS_OPEN && ! $rental->effects_pending && ! app(\App\Services\AuthorizationService::class)->blocksMutation($rental))
            @php
                $authRental = app(\App\Services\AuthorizationService::class);
                $rentalTotal = (float) $rental->total;
                $rentalPaid = (float) $rental->total_paid;
                if ($rentalPaid <= 0 && $rental->relationLoaded('payments')) {
                    $rentalPaid = (float) $rental->payments->sum('amount');
                }
                $rentalRemaining = max(0, $rentalTotal - $rentalPaid);
                $needsReleaseAuth = $rental->requires_authorization && $authRental->rentalNeedsReleaseAuthorization($rental);
                $readyToFinalize = ! $rental->requires_authorization || $authRental->rentalReadyToRelease($rental);
                $canRecordReturn = $rental->return_status !== \App\Models\Rental::RETURN_SUDAH
                    && (! $rental->requires_authorization || $needsReleaseAuth || $readyToFinalize);
                $canRecordPaymentOnly = $rentalRemaining > 0.02
                    && $rental->return_status !== \App\Models\Rental::RETURN_SUDAH
                    && $canRecordReturn;
            @endphp
            <div class="card-modern overflow-hidden mb-6" id="pelunasan-pengembalian">
                <div class="p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">{{ __('Pelunasan & Pengembalian') }}</h3>
                    <div class="rounded-lg border {{ $rentalRemaining > 0 ? 'border-amber-200 bg-amber-50/50' : 'border-emerald-200 bg-emerald-50/50' }} p-3 mb-3">
                        <p class="text-xs text-slate-600">{{ __('Total') }}: {{ number_format($rentalTotal, 0, ',', '.') }}</p>
                        <p class="text-xs text-slate-600">{{ __('Sudah dibayar') }}: {{ number_format($rentalPaid, 0, ',', '.') }}</p>
                        <p class="text-sm font-semibold mt-2 {{ $rentalRemaining > 0 ? 'text-amber-800' : 'text-emerald-800' }}">
                            {{ __('Sisa pembayaran') }}: {{ number_format($rentalRemaining, 0, ',', '.') }}
                        </p>
                    </div>

                    @if ($rental->return_status === \App\Models\Rental::RETURN_SUDAH)
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 mb-3">
                            {{ __('Pengembalian unit sudah dicatat.') }}
                            @if ($needsReleaseAuth)
                                {{ __('Ajukan otorisasi release di kotak atas, lalu tekan Release untuk mengubah status.') }}
                            @elseif ($readyToFinalize && $rental->requires_authorization)
                                {{ __('Tekan Release di kotak atas untuk mengubah status sewa.') }}
                            @endif
                        </div>
                    @elseif ($canRecordReturn)
                        @if ($needsReleaseAuth)
                            <div class="mb-3 rounded-lg border border-orange-200 bg-orange-50 p-3 text-sm text-orange-900">
                                {{ __('Catat pelunasan (jika belum lunas) dan pengembalian unit di sini. Status dokumen tetap Open sampai otorisasi release disetujui.') }}
                            </div>
                        @endif
                        <p class="text-xs text-amber-700 mb-3">{{ __('Wajib pelunasan dan minimal 1 bukti transaksi sebelum pengembalian. Isi pembayaran jika belum lunas.') }}</p>
                        <form method="POST" action="{{ route('rentals.mark-returned', $rental) }}" id="rental-return-form">
                            @csrf
                            <div class="space-y-3" id="payment-rows"></div>
                            @if ($rentalRemaining > 0.02)
                                <button type="button" id="add-payment" class="inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah') }}</button>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($canRecordPaymentOnly)
                                    <button type="submit" formaction="{{ route('rentals.add-payment', $rental) }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-slate-300 text-slate-800 text-sm font-semibold hover:bg-slate-50">
                                        {{ __('Simpan Pelunasan Saja') }}
                                    </button>
                                @endif
                                <x-primary-button>{{ $rental->requires_authorization ? __('Simpan Pelunasan & Pengembalian Unit') : __('Simpan & Tandai Kembali (Release)') }}</x-primary-button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        @if (auth()->user()?->canModule('rentals', 'cancel') && in_array($rental->status, [\App\Models\Rental::STATUS_OPEN, \App\Models\Rental::STATUS_RELEASED], true))
            <div class="mt-10 border border-red-200 rounded-lg p-4 bg-red-50/40">
                <p class="font-semibold text-red-700 mb-2">{{ __('Batalkan Transaksi') }}</p>
                <form method="POST" action="{{ route('rentals.cancel', $rental) }}" onsubmit="return confirm('{{ $rental->status === \App\Models\Rental::STATUS_RELEASED ? __('Transaksi sudah RELEASED. Batalkan penyewaan ini?') : __('Batalkan penyewaan ini?') }}')">
                    @csrf
                    <div class="flex flex-col gap-2 mb-3">
                        <textarea name="cancel_reason" class="w-full rounded-md border-gray-300" rows="2" placeholder="{{ __('Alasan pembatalan') }}" required></textarea>
                        @if ($rental->status === \App\Models\Rental::STATUS_RELEASED)
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="confirm_released" value="1" class="rounded">
                                <span>{{ __('Saya yakin membatalkan transaksi released') }}</span>
                            </label>
                        @endif
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                        {{ __('Batalkan') }}
                    </button>
                </form>
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
            div.style.cssText = 'display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:8px;align-items:end;';
            div.innerHTML = `
                <select name="payments[${idx}][payment_method_id]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    ${paymentOptionsHtml()}
                </select>
                <input type="text" name="payments[${idx}][amount]" data-rupiah="true" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nominal" required>
                <button type="button" class="remove-payment px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200">-</button>
                <input type="text" name="payments[${idx}][notes]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Catatan (opsional)" style="grid-column:1 / -1;">
            `;
            paymentRows.appendChild(div);
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            div.querySelector('.remove-payment')?.addEventListener('click', () => div.remove());
        }

        document.getElementById('add-payment')?.addEventListener('click', addPaymentRow);
        if (paymentRows && document.getElementById('add-payment')) {
            addPaymentRow();
        }
    </script>
</x-app-layout>
