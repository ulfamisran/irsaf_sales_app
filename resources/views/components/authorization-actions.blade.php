@props(['model', 'module', 'releaseRoute' => null])
@php
    $auth = app(\App\Services\AuthorizationService::class);
    $phase = $auth->phase($model);
    $number = $model->invoice_number ?? $model->exchange_number ?? ('#'.$model->getKey());
    $isService = $model instanceof \App\Models\Service;
    $isRental = $model instanceof \App\Models\Rental;
    $serviceNeedsReleaseAuth = $isService && $auth->serviceNeedsReleaseAuthorization($model);
    $serviceReadyToRelease = $isService && $auth->serviceReadyToRelease($model);
    $rentalNeedsReleaseAuth = $isRental && $auth->rentalNeedsReleaseAuthorization($model);
    $rentalReadyToRelease = $isRental && $auth->rentalReadyToRelease($model);
    $rentalNeedsEffectsRelease = $isRental
        && $phase === 'approved'
        && (bool) $model->effects_pending
        && $auth->approvedAuthorizationCount($model) >= 1;
    $canRelease = $phase === 'approved' && $releaseRoute && (
        ($model instanceof \App\Models\Sale && $model->status === \App\Models\Sale::STATUS_OPEN)
        || ($model instanceof \App\Models\Purchase && $model->status === \App\Models\Purchase::STATUS_OPEN)
        || ($model instanceof \App\Models\Distribution && $model->status === \App\Models\Distribution::STATUS_OPEN)
        || ($model instanceof \App\Models\UnitExchange && $model->status === \App\Models\UnitExchange::STATUS_OPEN)
        || $rentalNeedsEffectsRelease
    );
    $rejectedButOpen = ($isService || $isRental)
        && $phase === 'rejected'
        && $model->status === ($isService ? \App\Models\Service::STATUS_OPEN : \App\Models\Rental::STATUS_OPEN);
@endphp
@if ($phase !== 'off' && $phase !== 'released')
    <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950">
        @if ($phase === 'draft' || $rejectedButOpen)
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="min-w-0">
                    @if ($rejectedButOpen)
                        {{ __('Otorisasi ditolak. Perbaiki data bila perlu, lalu ajukan ulang.') }}
                    @elseif ($isService)
                        {{ __('Service open :no menunggu diajukan untuk otorisasi. Setelah disetujui, invoice bisa dipakai untuk pembelian sparepart.', ['no' => $number]) }}
                    @elseif ($isRental)
                        {{ __('Penyewaan open :no menunggu diajukan untuk otorisasi. Setelah disetujui, penyewaan bisa diaktifkan.', ['no' => $number]) }}
                    @else
                        {{ __('Dokumen :no menunggu diajukan untuk otorisasi. Selama menunggu, stok, kas, dan invoice belum dijalankan.', ['no' => $number]) }}
                    @endif
                </p>
                <form method="POST" action="{{ route('authorizations.submit') }}" class="shrink-0 self-end sm:self-center">
                    @csrf
                    <input type="hidden" name="module" value="{{ $module }}">
                    <input type="hidden" name="document_id" value="{{ $model->getKey() }}">
                    <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold whitespace-nowrap">{{ __('Ajukan Otorisasi') }}</button>
                </form>
            </div>
        @elseif ($phase === 'pending')
            <p>
                @if (($isService || $isRental) && $auth->approvedAuthorizationCount($model) >= 1)
                    {{ __('Menunggu otorisasi data lengkap (sebelum release). Dokumen belum bisa diubah sampai disetujui atau ditolak.') }}
                @else
                    {{ __('Menunggu otorisasi. Hanya detail yang tersedia sampai disetujui atau ditolak.') }}
                @endif
            </p>
        @elseif ($phase === 'approved')
            @if ($serviceNeedsReleaseAuth)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="min-w-0">{{ __('Otorisasi open disetujui. Lengkapi data service (tarif, pembayaran, dll), simpan, lalu ajukan otorisasi lagi sebelum release.') }}</p>
                    <div class="flex flex-wrap gap-2 shrink-0 self-end sm:self-center">
                        <a href="{{ route('services.edit', $model) }}" class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold whitespace-nowrap">{{ __('Lengkapi data') }}</a>
                        <form method="POST" action="{{ route('authorizations.submit') }}">
                            @csrf
                            <input type="hidden" name="module" value="{{ $module }}">
                            <input type="hidden" name="document_id" value="{{ $model->getKey() }}">
                            <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold whitespace-nowrap">{{ __('Ajukan Otorisasi Release') }}</button>
                        </form>
                    </div>
                </div>
            @elseif ($serviceReadyToRelease)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="min-w-0">{{ __('Otorisasi data lengkap disetujui. Service siap di-release.') }}</p>
                    <a href="{{ route('services.edit', $model) }}" class="shrink-0 self-end sm:self-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold whitespace-nowrap">{{ __('Release di Edit') }}</a>
                </div>
            @elseif ($rentalNeedsEffectsRelease)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="min-w-0">{{ __('Otorisasi open disetujui. Tekan Aktifkan Penyewaan untuk menjalankan stok/kas keluar, lalu catat pelunasan & pengembalian unit di halaman ini.') }}</p>
                    @if ($releaseRoute)
                        <form method="POST" action="{{ $releaseRoute }}" class="shrink-0 self-end sm:self-center">
                            @csrf
                            <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold whitespace-nowrap">{{ __('Aktifkan Penyewaan') }}</button>
                        </form>
                    @endif
                </div>
            @elseif ($rentalNeedsReleaseAuth)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="min-w-0">
                        @if ($model->return_status === \App\Models\Rental::RETURN_SUDAH)
                            {{ __('Pengembalian sudah dicatat. Ajukan otorisasi release, lalu ubah status menjadi Release.') }}
                        @else
                            {{ __('Catat pelunasan & pengembalian unit di bawah, lalu ajukan otorisasi release.') }}
                        @endif
                    </p>
                    @if ($model->return_status === \App\Models\Rental::RETURN_SUDAH)
                        <form method="POST" action="{{ route('authorizations.submit') }}" class="shrink-0 self-end sm:self-center">
                            @csrf
                            <input type="hidden" name="module" value="{{ $module }}">
                            <input type="hidden" name="document_id" value="{{ $model->getKey() }}">
                            <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold whitespace-nowrap">{{ __('Ajukan Otorisasi Release') }}</button>
                        </form>
                    @endif
                </div>
            @elseif ($rentalReadyToRelease)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    @if ($model->return_status === \App\Models\Rental::RETURN_SUDAH)
                        <p class="min-w-0">{{ __('Otorisasi release disetujui. Ubah status sewa menjadi Release.') }}</p>
                        <form method="POST" action="{{ route('rentals.finalize-release', $model) }}" class="shrink-0 self-end sm:self-center">
                            @csrf
                            <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold whitespace-nowrap">{{ __('Release') }}</button>
                        </form>
                    @else
                        <p class="min-w-0">{{ __('Otorisasi release disetujui. Catat pengembalian unit di bawah, lalu tekan Release.') }}</p>
                    @endif
                </div>
            @else
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="min-w-0">{{ __('Otorisasi disetujui. Release dapat dilakukan.') }}</p>
                    @if ($canRelease)
                        <form method="POST" action="{{ $releaseRoute }}" class="shrink-0 self-end sm:self-center">
                            @csrf
                            <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold whitespace-nowrap">{{ __('Release') }}</button>
                        </form>
                    @endif
                </div>
            @endif
        @elseif ($phase === 'rejected')
            <p>{{ __('Otorisasi ditolak dan dokumen dibatalkan.') }}</p>
        @endif
    </div>
@endif
