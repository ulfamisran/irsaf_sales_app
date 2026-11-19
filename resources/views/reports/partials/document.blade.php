@php
    $transactionDate = match (true) {
        $owner instanceof \App\Models\Sale => $owner->sale_date,
        $owner instanceof \App\Models\Purchase => $owner->purchase_date,
        $owner instanceof \App\Models\Service => $owner->entry_date,
        $owner instanceof \App\Models\Rental => $owner->pickup_date,
        $owner instanceof \App\Models\UnitExchange => $owner->exchange_date,
        $owner instanceof \App\Models\Distribution => $owner->distribution_date,
        default => null,
    };
    $transactionDateText = $transactionDate ? \Illuminate\Support\Carbon::parse($transactionDate)->format('d/m/Y') : '-';
    $authorName = $owner->user?->name ?: '-';
@endphp
<article class="report-page {{ ($owner instanceof \App\Models\Purchase || $owner instanceof \App\Models\Distribution) ? 'has-sheet' : '' }}">
    <header class="doc-head">
        <h1 class="invoice-no">{{ $number }}</h1>
        <div class="doc-meta">
            <div><span>{{ __('Tanggal Transaksi') }}</span> {{ $transactionDateText }}</div>
            <div><span>{{ __('Dibuat oleh') }}</span> {{ $authorName }}</div>
        </div>
    </header>
    <section class="proof-block">
        <h2>{{ __('Bukti Bayar') }}</h2>
        <div class="proofs">
            @for ($slot = 0; $slot < 2; $slot++)
                @php $proof = $proofs->take(2)->values()->get($slot); @endphp
                <div class="proof">
                    @if ($proof)
                        @php $proofUrl = route($proofRoute, [$owner, $proof]); @endphp
                        @if ($proof->isImage())
                            <img src="{{ $proofUrl }}" alt="{{ $proof->original_name }}">
                        @else
                            <iframe src="{{ $proofUrl }}" title="{{ $proof->original_name }}"></iframe>
                        @endif
                        <p class="proof-note">{{ $proof->note ?: '-' }}</p>
                    @else
                        <div class="empty">{{ __('Belum ada bukti') }}</div>
                    @endif
                </div>
            @endfor
        </div>
    </section>
    @if (!empty($invoiceView))
        <section class="invoice-fit">
            @include($invoiceView, $invoiceData ?? [])
        </section>
    @elseif ($owner instanceof \App\Models\Purchase)
        @include('purchases.partials.report-sheet', ['purchase' => $owner])
    @elseif ($owner instanceof \App\Models\Distribution)
        @include('stock-mutations.partials.report-sheet', ['distribution' => $owner])
    @endif
</article>
