@php
    $money = fn (mixed $amount): string => 'Rp '.number_format((float) $amount, 0, ',', '.');
    $place = function (?string $type, ?string $name, mixed $id): string {
        $kind = $type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang');

        return trim($kind.' '.($name ?: '#'.$id));
    };
    $fromName = $distribution->from_location_type === \App\Models\Stock::LOCATION_WAREHOUSE
        ? (\App\Models\Warehouse::find($distribution->from_location_id)?->name)
        : (\App\Models\Branch::find($distribution->from_location_id)?->name);
    $toName = $distribution->to_location_type === \App\Models\Stock::LOCATION_WAREHOUSE
        ? (\App\Models\Warehouse::find($distribution->to_location_id)?->name)
        : (\App\Models\Branch::find($distribution->to_location_id)?->name);
    $fromText = $place($distribution->from_location_type, $fromName, $distribution->from_location_id);
    $toText = $place($distribution->to_location_type, $toName, $distribution->to_location_id);
    $moveText = $fromText.' → '.$toText;
    $total = (float) $distribution->total;
    $paid = (float) $distribution->total_paid;
    if ($distribution->isCancelled()) {
        $payStatus = __('Dibatalkan');
    } elseif ($total <= 0) {
        $payStatus = __('Gratis');
    } elseif ($paid + 0.02 >= $total) {
        $payStatus = __('Lunas');
    } else {
        $payStatus = __('Belum Lunas');
    }
    $rows = [];
    foreach ($distribution->details as $detail) {
        $product = trim(implode(' ', array_filter([
            $detail->product?->sku,
            $detail->product?->brand,
            $detail->product?->series,
        ])));
        $biaya = (float) ($detail->biaya_distribusi_per_unit ?? 0);
        $serials = array_values(array_filter(array_map(
            'trim',
            preg_split('/[\r\n,]+/', (string) ($detail->serial_numbers ?? '')) ?: []
        )));
        $hppNote = $detail->affects_hpp === true
            ? __('Mempengaruhi HPP')
            : ($detail->affects_hpp === false ? __('Tidak mempengaruhi HPP') : '');
        if ($serials === []) {
            $qty = (int) $detail->quantity;
            $rows[] = [
                'product' => $product !== '' ? $product : '-',
                'serial' => '',
                'qty' => $qty,
                'biaya' => $biaya,
                'total' => $biaya * $qty,
                'hpp' => $hppNote,
            ];
            continue;
        }
        foreach ($serials as $serial) {
            $rows[] = [
                'product' => $product !== '' ? $product : '-',
                'serial' => $serial,
                'qty' => 1,
                'biaya' => $biaya,
                'total' => $biaya,
                'hpp' => $hppNote,
            ];
        }
    }
@endphp
<section class="purchase-sheet">
    <h2>{{ __('Detail Distribusi') }}</h2>
    <div class="purchase-sums">
        <div>
            <span>{{ __('Dari') }}</span>
            <strong>{{ $fromText }}</strong>
        </div>
        <div>
            <span>{{ __('Ke') }}</span>
            <strong>{{ $toText }}</strong>
        </div>
        <div>
            <span>{{ __('Total Biaya') }}</span>
            <strong>{{ $money($total) }}</strong>
        </div>
        <div>
            <span>{{ __('Terbayar') }}</span>
            <strong>{{ $money($paid) }}</strong>
        </div>
        <div>
            <span>{{ __('Sisa') }}</span>
            <strong>{{ $money(max(0, $total - $paid)) }}</strong>
        </div>
        <div>
            <span>{{ __('Status Bayar') }}</span>
            <strong>{{ $payStatus }}</strong>
        </div>
        <div>
            <span>{{ __('Jumlah Unit') }}</span>
            <strong>{{ array_sum(array_column($rows, 'qty')) }}</strong>
        </div>
        <div>
            <span>{{ __('Perpindahan') }}</span>
            <strong>{{ $moveText }}</strong>
        </div>
    </div>
    <table class="purchase-table">
        <thead>
            <tr>
                <th class="center">{{ __('No') }}</th>
                <th>{{ __('Produk') }}</th>
                <th>{{ __('Nomor Serial') }}</th>
                <th class="center">{{ __('Qty') }}</th>
                <th class="num">{{ __('Biaya / Unit') }}</th>
                <th class="num">{{ __('Total Biaya') }}</th>
                <th>{{ __('Perpindahan Lokasi') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        {{ $row['product'] }}
                        @if ($row['hpp'] !== '')
                            <div>{{ $row['hpp'] }}</div>
                        @endif
                    </td>
                    <td class="purchase-serial">{{ $row['serial'] !== '' ? $row['serial'] : '-' }}</td>
                    <td class="center">{{ $row['qty'] }}</td>
                    <td class="num">{{ $money($row['biaya']) }}</td>
                    <td class="num">{{ $money($row['total']) }}</td>
                    <td>{{ $moveText }}</td>
                </tr>
            @empty
                <tr>
                    <td class="center">-</td>
                    <td colspan="6">{{ __('Belum ada unit') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>
