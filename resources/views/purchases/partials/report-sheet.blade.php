@php
    $sheet = app(\App\Services\PurchaseReceivingService::class)->reportSheet($purchase);
    $summary = $sheet['summary'];
    $money = fn (mixed $amount): string => 'Rp '.number_format((float) $amount, 0, ',', '.');
    $rowNo = 0;
@endphp
<section class="purchase-sheet">
    <h2>{{ __('Detail Pembelian') }}</h2>
    <div class="purchase-sums">
        <div>
            <span>{{ __('Utang Diakui') }}</span>
            <strong>{{ $money($summary['recognized_debt']) }}</strong>
        </div>
        <div>
            <span>{{ __('Uang Dipending') }}</span>
            <strong>{{ $money($summary['deferred_value']) }}</strong>
        </div>
        <div>
            <span>{{ __('Retur') }}</span>
            <strong>{{ $money($summary['return_value']) }}</strong>
        </div>
        <div>
            <span>{{ __('Belum Dicek') }}</span>
            <strong>{{ $money($summary['unchecked_value']) }}</strong>
        </div>
        <div>
            <span>{{ __('Terbayar') }}</span>
            <strong>{{ $money($summary['paid']) }}</strong>
        </div>
        <div>
            <span>{{ __('Sisa Utang') }}</span>
            <strong>{{ $money($summary['remaining_recognized']) }}</strong>
        </div>
        <div>
            <span>{{ __('Status Bayar') }}</span>
            <strong>{{ $summary['payment_status_label'] }}</strong>
        </div>
        <div>
            <span>{{ __('Status Cek') }}</span>
            <strong>{{ $summary['check_status_label'] }}</strong>
        </div>
    </div>
    <table class="purchase-table">
        <thead>
            <tr>
                <th class="center">{{ __('No') }}</th>
                <th>{{ __('Unit') }}</th>
                <th>{{ __('Hasil Cek') }}</th>
                <th class="num">{{ __('Nilai') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sheet['lines'] as $line)
                <tr class="purchase-product">
                    <td colspan="4">
                        {{ $line['product'] }}
                        · {{ __('Qty') }} {{ $line['quantity'] }}
                        · {{ $money($line['unit_price']) }}
                    </td>
                </tr>
                @forelse ($line['units'] as $unit)
                    @php $rowNo++; @endphp
                    <tr>
                        <td class="center">{{ $rowNo }}</td>
                        <td class="purchase-serial">
                            @if ($unit['serial'] !== '')
                                {{ $unit['serial'] }}
                            @else
                                {{ __('Qty') }} {{ $unit['qty'] }}
                            @endif
                        </td>
                        <td><span class="mark mark-{{ $unit['status'] }}">{{ $unit['label'] }}</span></td>
                        <td class="num">{{ $money($unit['amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="center">-</td>
                        <td colspan="3">{{ __('Belum ada unit') }}</td>
                    </tr>
                @endforelse
            @empty
                <tr>
                    <td colspan="4" class="center">-</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>
