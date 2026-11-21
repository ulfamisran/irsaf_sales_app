<x-app-layout>
    <x-slot name="title">{{ __('Detail Penyewaan') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Detail Penyewaan') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $rental->invoice_number }}</p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                @if (! $rental->requires_authorization || $rental->status === \App\Models\Rental::STATUS_RELEASED)
                    <x-action-btn variant="invoice" :href="route('rentals.invoice', $rental)" target="_blank" :label="__('Invoice')" />
                @endif
                <x-action-btn variant="report" :href="route('rentals.report', $rental)" target="_blank" />
                @if (
                    auth()->user()?->canModule('rentals', 'update')
                    && $rental->status === \App\Models\Rental::STATUS_OPEN
                    && $rental->return_status !== \App\Models\Rental::RETURN_SUDAH
                    && ! $rental->effects_pending
                    && ! app(\App\Services\AuthorizationService::class)->blocksMutation($rental)
                )
                    <x-action-btn variant="edit" :href="route('rentals.edit', $rental)" />
                @endif
                @if (auth()->user()?->isSuperAdminOrAdminPusat() && $rental->status !== \App\Models\Rental::STATUS_CANCEL)
                    <x-action-btn variant="update-tx" :href="route('admin-tx.edit-rental', $rental)" />
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
            <x-action-btn variant="report" :href="route('rentals.report', $rental)" target="_blank" />
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
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Tgl Pengembalian (Rencana)') }}</p>
                    <p class="font-medium text-slate-800">{{ $rental->return_date?->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Jumlah Hari') }}</p>
                    <p class="font-medium text-slate-800">{{ $rental->total_days }}</p>
                </div>
            </div>
        </div>

        @if ($rental->return_status === \App\Models\Rental::RETURN_SUDAH)
            <div class="card-modern overflow-hidden mb-6">
                <div class="p-6 rounded-lg border border-emerald-200 bg-emerald-50/40">
                    <p class="text-sm font-semibold text-emerald-800">{{ __('Informasi Pengembalian') }}</p>
                    <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm text-slate-700">
                        <div>
                            <p class="text-xs text-slate-500">{{ __('Tanggal Pengembalian') }}</p>
                            <p class="font-medium">{{ $rental->actual_return_date?->format('d/m/Y') ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">{{ __('Dicatat Oleh') }}</p>
                            <p class="font-medium">{{ $rental->returnUser?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">{{ __('Waktu Catat') }}</p>
                            <p class="font-medium">
                                @if ($rental->return_recorded_at)
                                    <x-wita-datetime :value="$rental->return_recorded_at" />
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div class="md:col-span-3">
                            <p class="text-xs text-slate-500">{{ __('Catatan Pengembalian') }}</p>
                            <p class="font-medium whitespace-pre-line">{{ $rental->return_notes ?: '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Harga/Hari') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total') }}</th>
                            @if ($rental->return_status === \App\Models\Rental::RETURN_SUDAH)
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kondisi') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Catatan Unit') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($rental->items as $item)
                            <tr>
                                <td class="px-4 py-3">{{ $item->product?->sku }} - {{ $item->product?->brand }} {{ $item->product?->series }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $item->serial_number }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($item->rental_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($item->total, 0, ',', '.') }}</td>
                                @if ($rental->return_status === \App\Models\Rental::RETURN_SUDAH)
                                    <td class="px-4 py-3">
                                        @if ($item->return_condition === \App\Models\RentalItem::CONDITION_BAIK)
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">{{ __('Baik') }}</span>
                                        @elseif ($item->return_condition === \App\Models\RentalItem::CONDITION_BERMASALAH)
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">{{ __('Bermasalah') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $item->return_notes ?: '—' }}</td>
                                @endif
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
                    <x-document-status-badge :status="$rental->status" />
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
            <div class="card-modern overflow-hidden mb-6 {{ session('release_incomplete') ? 'ring-2 ring-amber-400' : '' }}" id="pelunasan-pengembalian">
                <div class="p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">{{ __('Pelunasan & Pengembalian') }}</h3>
                    @if (session('release_incomplete') && session('error'))
                        <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ session('error') }}</div>
                    @endif
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
                        <p class="text-xs text-amber-700 mb-3">{{ __('Wajib pelunasan dan minimal 1 bukti transaksi sebelum pengembalian. Centang semua unit, pilih kondisi, lalu simpan.') }}</p>
                        <form method="POST" action="{{ route('rentals.mark-returned', $rental) }}" id="rental-return-form">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <x-input-label for="actual_return_date" :value="__('Tanggal Pengembalian')" />
                                    <x-text-input id="actual_return_date" class="block mt-1 w-full" type="date" name="actual_return_date" :value="old('actual_return_date', now('Asia/Makassar')->toDateString())" required />
                                    <x-input-error :messages="$errors->get('actual_return_date')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label :value="__('Dicatat Oleh')" />
                                    <x-text-input class="block mt-1 w-full bg-slate-50" type="text" :value="auth()->user()?->name" readonly />
                                </div>
                            </div>

                            <div class="mb-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-100">
                                    <p class="text-sm font-semibold text-slate-800">{{ __('Ceklis Unit Dikembalikan') }}</p>
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                                        <input type="checkbox" id="check-all-return-units" class="rounded border-gray-300">
                                        <span>{{ __('Centang semua') }}</span>
                                    </label>
                                </div>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kembali') }}</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk / Serial') }}</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kondisi') }}</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Catatan') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach ($rental->items as $idx => $item)
                                            @php
                                                $oldItem = old('items.'.$idx, []);
                                            @endphp
                                            <tr>
                                                <td class="px-3 py-3 align-top">
                                                    <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $item->id }}">
                                                    <input type="checkbox" name="items[{{ $idx }}][returned]" value="1" class="return-unit-check rounded border-gray-300" @checked((string) ($oldItem['returned'] ?? '') === '1') required>
                                                </td>
                                                <td class="px-3 py-3 align-top">
                                                    <p class="text-sm font-medium text-slate-800">{{ $item->product?->sku }} — {{ $item->product?->brand }} {{ $item->product?->series }}</p>
                                                    <p class="font-mono text-xs text-slate-500 mt-0.5">{{ $item->serial_number }}</p>
                                                </td>
                                                <td class="px-3 py-3 align-top">
                                                    <select name="items[{{ $idx }}][return_condition]" class="block w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                                                        <option value="">{{ __('Pilih') }}</option>
                                                        <option value="baik" @selected(($oldItem['return_condition'] ?? '') === 'baik')>{{ __('Baik') }}</option>
                                                        <option value="bermasalah" @selected(($oldItem['return_condition'] ?? '') === 'bermasalah')>{{ __('Bermasalah') }}</option>
                                                    </select>
                                                </td>
                                                <td class="px-3 py-3 align-top">
                                                    <input type="text" name="items[{{ $idx }}][return_notes]" value="{{ $oldItem['return_notes'] ?? '' }}" class="block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="{{ __('Opsional') }}">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <x-input-error :messages="$errors->get('items')" class="m-3" />
                                <x-input-error :messages="$errors->get('items.*.returned')" class="m-3" />
                                <x-input-error :messages="$errors->get('items.*.return_condition')" class="m-3" />
                            </div>

                            <div class="mb-4">
                                <x-input-label for="return_notes" :value="__('Catatan Pengembalian')" />
                                <textarea id="return_notes" name="return_notes" rows="2" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm" placeholder="{{ __('Catatan umum pengembalian (opsional)') }}">{{ old('return_notes') }}</textarea>
                                <x-input-error :messages="$errors->get('return_notes')" class="mt-2" />
                            </div>

                            <div class="space-y-3" id="payment-rows"></div>
                            @if ($rentalRemaining > 0.02)
                                <button type="button" id="add-payment" class="inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah Pembayaran') }}</button>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($canRecordPaymentOnly)
                                    <button type="submit" formaction="{{ route('rentals.add-payment', $rental) }}" formnovalidate class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-slate-300 text-slate-800 text-sm font-semibold hover:bg-slate-50">
                                        {{ __('Simpan Pelunasan Saja') }}
                                    </button>
                                @endif
                                <x-primary-button id="btn-save-return">{{ $rental->requires_authorization ? __('Simpan Pelunasan & Pengembalian Unit') : __('Simpan & Tandai Kembali (Release)') }}</x-primary-button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        @if (auth()->user()?->canModule('rentals', 'cancel') && in_array($rental->status, [\App\Models\Rental::STATUS_OPEN, \App\Models\Rental::STATUS_RELEASED], true))
            <div class="mt-10 border border-red-200 rounded-lg p-4 bg-red-50/40">
                <p class="font-semibold text-red-700 mb-2">{{ __('Batalkan Transaksi') }}</p>
                <form method="POST" action="{{ route('rentals.cancel', $rental) }}" data-swal-confirm="{{ $rental->status === \App\Models\Rental::STATUS_RELEASED ? __('Transaksi sudah RELEASED. Batalkan penyewaan ini?') : __('Batalkan penyewaan ini?') }}" data-swal-confirm-color="#dc2626">
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

        const checkAll = document.getElementById('check-all-return-units');
        const unitChecks = () => Array.from(document.querySelectorAll('.return-unit-check'));
        checkAll?.addEventListener('change', () => {
            unitChecks().forEach(chk => { chk.checked = !!checkAll.checked; });
        });
        document.getElementById('rental-return-form')?.addEventListener('submit', (e) => {
            const submitter = e.submitter;
            if (submitter && submitter.getAttribute('formaction') && submitter.getAttribute('formaction').includes('add-payment')) {
                return;
            }
            const unchecked = unitChecks().filter(chk => !chk.checked);
            if (unchecked.length > 0) {
                e.preventDefault();
                if (window.Swal) {
                    window.Swal.fire({ icon: 'warning', title: '{{ __("Lengkapi pengembalian") }}', text: '{{ __("Centang semua unit yang dikembalikan.") }}' });
                } else {
                    alert('{{ __("Centang semua unit yang dikembalikan.") }}');
                }
            }
        });
    </script>
</x-app-layout>
