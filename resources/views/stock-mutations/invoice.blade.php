<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Invoice') }} {{ $stockMutation->invoice_number ?? ('DIST-#'.$stockMutation->id) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
        :root{
            --ink:#111827;
            --muted:#4b5563;
            --border:#111827;
            --light:#f3f4f6;
            --paid:#16a34a;
            --unpaid:#b91c1c;
        }
        @page { size: A4 landscape; margin: 10mm; }
        body { color: var(--ink); }
        .inv-page{
            max-width: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
        }
        .inv-card{
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            padding: 14px 14px 12px;
        }
        @media print{
            .inv-card{ border: none; border-radius: 0; padding: 0; }
        }
        .inv-top{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap: 14px;
            margin-bottom: 6px;
        }
        .inv-company{
            display:flex;
            flex-direction: column;
            gap: 0;
            align-items:flex-start;
            min-width: 55%;
        }
        .inv-logo{
            width: auto;
            height: auto;
            border: 0;
            background: transparent;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight: 900;
            letter-spacing: .5px;
            color:#000;
            flex: 0 0 auto;
        }
        .inv-logo img{ max-width: 100%; max-height: 42px; display:block; }
        .inv-co-name{ font-weight: 800; font-size: 15px; line-height: 1.1; }
        .inv-co-line{ font-size: 11px; color: var(--ink); line-height: 1.15; }
        .inv-co-muted{ font-size: 11px; color: var(--muted); line-height: 1.15; }

        .inv-meta{
            text-align:right;
            min-width: 40%;
        }
        .inv-meta .title{ font-weight: 900; font-size: 16px; letter-spacing: .5px; }
        .inv-meta .invno{ font-weight: 800; font-size: 12px; margin-top: 1px; }
        .inv-meta .row{ font-size: 11px; color: var(--ink); line-height: 1.2; }
        .inv-meta .row span{ color: var(--muted); }
        .inv-meta .status-box{
            margin-top: 4px;
            display:inline-block;
            padding: 6px 16px;
            border: 2px solid #16a34a;
            color:#166534;
            font-weight: 900;
            background: #ecfdf5;
            border-radius: 2px;
            font-size: 12px;
        }
        .inv-meta .status-box.unpaid{
            border-color: var(--unpaid);
            color: #7f1d1d;
            background: #fef2f2;
        }
        .inv-meta .status-box.na{
            border-color: #64748b;
            color: #475569;
            background: #f1f5f9;
        }
        .inv-meta .status-box.cancelled{
            border-color: #64748b;
            color: #1e293b;
            background: #e2e8f0;
        }
        .inv-card.inv-cancelled{
            position: relative;
            border: 2px dashed #94a3b8;
        }
        @media print {
            .inv-card.inv-cancelled .inv-top,
            .inv-card.inv-cancelled .inv-customer,
            .inv-card.inv-cancelled .inv-table,
            .inv-card.inv-cancelled .inv-bottom,
            .inv-card.inv-cancelled .inv-section,
            .inv-card.inv-cancelled .inv-sign {
                position: relative;
                z-index: 1;
            }
            .inv-card.inv-cancelled::after{
                content: 'DIBATALKAN';
                position: absolute;
                top: 40%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-18deg);
                font-size: 42px;
                font-weight: 900;
                color: rgba(148, 163, 184, 0.35);
                letter-spacing: 0.15em;
                pointer-events: none;
                z-index: 0;
            }
        }

        .inv-customer{
            font-size: 11px;
            margin: 6px 0 6px;
        }
        .inv-customer table{
            width: 100%;
            border-collapse: collapse;
        }
        .inv-customer td{ padding: 0; vertical-align: top; line-height: 1.2; }
        .inv-customer .lbl{ width: 62px; color: var(--ink); }
        .inv-customer .colon{ width: 10px; }
        .inv-customer .val{ color: var(--ink); }
        .inv-customer .trx{ margin-top: 3px; font-weight: 700; }
        .inv-customer .trx .muted{ font-weight: 500; color: var(--muted); }

        .inv-table{
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .inv-table th, .inv-table td{
            border: 1px solid var(--border);
            padding: 4px 5px;
            line-height: 1.15;
        }
        .inv-table thead th{
            background: #fff;
            font-weight: 800;
            text-align: center;
        }
        .inv-table .num{ text-align: right; white-space: nowrap; }
        .inv-table .center{ text-align: center; }
        .inv-desc{ white-space: pre-line; }
        .inv-desc .sku{ font-weight: 700; }
        .inv-desc .muted{ color: var(--muted); }

        .inv-bottom{
            display:flex;
            gap: 12px;
            margin-top: 6px;
            align-items: stretch;
        }
        .inv-terbilang{
            flex: 1 1 auto;
            border: 1px solid var(--border);
            padding: 6px;
            min-height: 54px;
        }
        .inv-terbilang .label{ font-weight: 800; margin-bottom: 6px; }
        .inv-terbilang .words{ font-style: italic; }
        .inv-totals{
            flex: 0 0 40%;
        }
        .inv-totals table{
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .inv-totals td{
            border: 1px solid var(--border);
            padding: 3px 5px;
            line-height: 1.15;
        }
        .inv-totals td.lbl{ color: var(--ink); }
        .inv-totals td.val{ text-align: right; white-space: nowrap; }

        .inv-section{
            margin-top: 6px;
        }
        .inv-section .label{
            font-weight: 800;
            margin-bottom: 4px;
            font-size: 11px;
        }
        .inv-pay-summary{
            display:flex;
            justify-content:flex-end;
            margin-top: 4px;
            font-size: 11px;
        }
        .inv-pay-summary .box{
            width: 280px;
        }
        .inv-pay-summary .row{
            display:flex;
            justify-content:space-between;
            padding: 2px 0;
        }
        .inv-sign{
            display:flex;
            justify-content:space-between;
            margin-top: 10px;
            font-size: 11px;
        }
        .inv-sign .col{
            width: 46%;
            text-align: left;
        }
        .inv-sign .col.right{
            text-align: right;
        }
        .inv-sign .name{
            margin-top: 26px;
            font-weight: 800;
        }
    </style>
</head>
<body class="bg-slate-100">
    @php
        $invoiceCancelled = $invoiceCancelled ?? false;
        $invNo = $stockMutation->invoice_number ?? ('DIST-#' . $stockMutation->id);
        $totalBiaya = $allMutations->sum(fn ($m) => (float) ($m->biaya_distribusi_per_unit ?? 0) * (int) $m->quantity);
        $totalPaid = (float) $cashFlows->sum('amount');
        $isPaid = $totalBiaya <= 0 || ($totalPaid + 0.02 >= $totalBiaya);
        if ($invoiceCancelled) {
            $statusText = 'DIBATALKAN';
            $statusClass = 'cancelled';
        } else {
            $statusText = $totalBiaya <= 0 ? 'N/A' : ($isPaid ? 'LUNAS' : 'BELUM LUNAS');
            $statusClass = $totalBiaya <= 0 ? 'na' : ($isPaid ? '' : 'unpaid');
        }

        if (!function_exists('irsaf_terbilang_dist')) {
            function irsaf_terbilang_dist(int $nilai): string {
                $nilai = abs($nilai);
                $huruf = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
                if ($nilai < 12) return $huruf[$nilai];
                if ($nilai < 20) return irsaf_terbilang_dist($nilai - 10) . " belas";
                if ($nilai < 100) return irsaf_terbilang_dist(intdiv($nilai, 10)) . " puluh" . (($nilai % 10) ? " " . irsaf_terbilang_dist($nilai % 10) : "");
                if ($nilai < 200) return "seratus" . (($nilai - 100) ? " " . irsaf_terbilang_dist($nilai - 100) : "");
                if ($nilai < 1000) return irsaf_terbilang_dist(intdiv($nilai, 100)) . " ratus" . (($nilai % 100) ? " " . irsaf_terbilang_dist($nilai % 100) : "");
                if ($nilai < 2000) return "seribu" . (($nilai - 1000) ? " " . irsaf_terbilang_dist($nilai - 1000) : "");
                if ($nilai < 1000000) return irsaf_terbilang_dist(intdiv($nilai, 1000)) . " ribu" . (($nilai % 1000) ? " " . irsaf_terbilang_dist($nilai % 1000) : "");
                if ($nilai < 1000000000) return irsaf_terbilang_dist(intdiv($nilai, 1000000)) . " juta" . (($nilai % 1000000) ? " " . irsaf_terbilang_dist($nilai % 1000000) : "");
                if ($nilai < 1000000000000) return irsaf_terbilang_dist(intdiv($nilai, 1000000000)) . " miliar" . (($nilai % 1000000000) ? " " . irsaf_terbilang_dist($nilai % 1000000000) : "");
                return irsaf_terbilang_dist(intdiv($nilai, 1000000000000)) . " triliun" . (($nilai % 1000000000000) ? " " . irsaf_terbilang_dist($nilai % 1000000000000) : "");
            }
        }
        $terbilang = trim(irsaf_terbilang_dist((int) round($totalBiaya)));

        $fromLabel = $stockMutation->from_location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang');
        $toLabel = $stockMutation->to_location_type === \App\Models\Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang');
        $fromName = $fromLocation?->name ?? ('#' . $stockMutation->from_location_id);
        $toName = $toLocation?->name ?? ('#' . $stockMutation->to_location_id);

        $companyName = config('app.name', 'IRSAF');
        $companyAddress = null;
        $companyPhone = null;
        if ($stockMutation->from_location_type === \App\Models\Stock::LOCATION_BRANCH && $fromLocation) {
            $companyName = $fromLocation->name;
            $companyAddress = $fromLocation->address ?? null;
            $companyPhone = $fromLocation->phone ?? null;
        } elseif ($stockMutation->from_location_type === \App\Models\Stock::LOCATION_WAREHOUSE && $fromLocation) {
            $companyName = $fromLocation->name;
            $companyAddress = $fromLocation->address ?? null;
            $companyPhone = $fromLocation->phone ?? null;
        }

        $logoPath = public_path('images/logo.png');
        $fallbackLogoPath = public_path('images/invoice-logo.png');
        $logoUrl = null;
        if (file_exists($logoPath)) {
            $logoUrl = asset('images/logo.png');
        } elseif (file_exists($fallbackLogoPath)) {
            $logoUrl = asset('images/invoice-logo.png');
        }

        $trxAt = $stockMutation->mutation_date ?? $stockMutation->created_at ?? now();
        $firstMutInv = $allMutations->first();
    @endphp
    @if ($invoiceCancelled)
        <div class="no-print max-w-4xl mx-auto px-4 pt-4">
            <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900 text-sm text-center space-y-1">
                <p class="font-semibold">{{ __('Invoice distribusi ini telah dibatalkan.') }}</p>
                @if ($firstMutInv?->cancel_date)
                    <p class="text-xs">{{ __('Tanggal pembatalan') }}: {{ $firstMutInv->cancel_date->translatedFormat('d F Y') }}</p>
                @endif
                @if ($firstMutInv?->cancelUser)
                    <p class="text-xs">{{ __('Oleh') }}: {{ $firstMutInv->cancelUser->name }}</p>
                @endif
                @if ($firstMutInv?->cancel_reason)
                    <p class="text-xs text-left mt-2 pt-2 border-t border-amber-200/80"><span class="font-medium">{{ __('Alasan') }}:</span> {{ $firstMutInv->cancel_reason }}</p>
                @endif
            </div>
        </div>
    @endif
    <div class="no-print max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="{{ route('stock-mutations.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-200 text-slate-800 hover:bg-slate-300">{{ __('Kembali') }}</a>
        <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">{{ __('Print') }}</button>
    </div>

    <div class="inv-page px-4 pb-8">
        <div class="inv-card {{ $invoiceCancelled ? 'inv-cancelled' : '' }}">
            <div class="inv-top">
                <div class="inv-company">
                    <div class="inv-logo" aria-label="Logo">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ config('app.name', 'Logo') }}">
                        @else
                            IT
                        @endif
                    </div>
                    <div>
                        <div class="inv-co-name">{{ $companyName }}</div>
                        @if ($companyAddress)
                            <div class="inv-co-line">{{ $companyAddress }}</div>
                        @endif
                        <div class="inv-co-line">{{ __('Telepon') }}: {{ $companyPhone ?? '-' }}</div>
                    </div>
                </div>

                <div class="inv-meta">
                    <div class="title">INVOICE DISTRIBUSI</div>
                    <div class="invno">{{ $invNo }}</div>
                    <div class="row"><span>Tanggal:</span> {{ $stockMutation->mutation_date->translatedFormat('d F Y') }}</div>
                    <div class="row"><span>Status Bayar:</span> <strong>{{ $statusText }}</strong></div>
                    <div class="row"><span>Termin:</span> -</div>
                    <div class="row"><span>Jatuh Tempo:</span> -</div>
                    <div class="status-box {{ $statusClass }}">{{ $statusText }}</div>
                </div>
            </div>

            <div class="inv-customer">
                <table>
                    <tr>
                        <td class="lbl">Dari</td><td class="colon">:</td>
                        <td class="val">{{ $fromLabel }} {{ $fromName }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Ke</td><td class="colon">:</td>
                        <td class="val">{{ $toLabel }} {{ $toName }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Produk</td><td class="colon">:</td>
                        <td class="val">{{ $allMutations->count() }} produk</td>
                    </tr>
                </table>
                <div class="trx">
                    <span class="muted">Transaksi</span>
                    : {{ $trxAt->translatedFormat('d F Y H:i') }}
                </div>
            </div>

            <table class="inv-table">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>Deskripsi</th>
                        <th style="width:55px;">Qty</th>
                        <th style="width:105px;">Biaya Satuan</th>
                        <th style="width:105px;">Harga Total</th>
                        <th style="width:75px;">Diskon</th>
                        <th style="width:105px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($allMutations as $idx => $mut)
                        @php
                            $lineTotal = (float) ($mut->biaya_distribusi_per_unit ?? 0) * (int) $mut->quantity;
                            $serialText = trim((string) ($mut->serial_numbers ?? ''));
                        @endphp
                        <tr>
                            <td class="center">{{ $idx + 1 }}</td>
                            <td class="inv-desc">
                                <div class="sku">
                                    {{ $mut->product?->sku ?? '-' }}
                                    {{ $mut->product?->brand ? $mut->product->brand : '' }}
                                    {{ $mut->product?->series ? $mut->product->series : '' }}
                                </div>
                                @if ($serialText !== '')
                                    <div class="muted">{{ $serialText }}</div>
                                @endif
                                @if ($mut->notes && $idx === 0)
                                    <div>{{ $mut->notes }}</div>
                                @endif
                            </td>
                            <td class="num">{{ number_format((float) $mut->quantity, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) ($mut->biaya_distribusi_per_unit ?? 0), 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($lineTotal, 0, ',', '.') }}</td>
                            <td class="num">0</td>
                            <td class="num">{{ number_format($lineTotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="inv-bottom">
                <div class="inv-terbilang">
                    <div class="label">Terbilang</div>
                    <div class="words">"{{ $terbilang === '' ? '-' : $terbilang }} rupiah"</div>
                </div>
                <div class="inv-totals">
                    <table>
                        <tr>
                            <td class="lbl">Subtotal</td>
                            <td class="val">{{ number_format($totalBiaya, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Diskon</td>
                            <td class="val">0</td>
                        </tr>
                        <tr>
                            <td class="lbl">Penyesuaian</td>
                            <td class="val">0</td>
                        </tr>
                        <tr>
                            <td class="lbl"><strong>Total</strong></td>
                            <td class="val"><strong>{{ number_format($totalBiaya, 0, ',', '.') }}</strong></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="inv-section">
                <div class="label">Pembayaran</div>
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">No</th>
                            <th style="width:170px;">Tanggal Pembayaran</th>
                            <th>Metode Pembayaran</th>
                            <th style="width:120px;">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $payIdx = 0; $payDate = $stockMutation->mutation_date ?? $stockMutation->created_at ?? now(); @endphp
                        @foreach ($cashFlows as $cf)
                            @php
                                $method = $cf->paymentMethod ? strtoupper($cf->paymentMethod->display_label) : '-';
                                $pDate = $cf->transaction_date ?? $cf->created_at ?? $payDate;
                            @endphp
                            <tr>
                                <td class="center">{{ ++$payIdx }}</td>
                                <td class="center">{{ $pDate->translatedFormat('d F Y') }}</td>
                                <td>{{ $method }}</td>
                                <td class="num">{{ number_format((float) $cf->amount, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        @if ($payIdx === 0)
                            <tr>
                                <td colspan="4" class="center">-</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                <div class="inv-pay-summary">
                    <div class="box">
                        <div class="row"><span>Total Bayar</span><strong>{{ number_format($totalPaid, 0, ',', '.') }}</strong></div>
                        <div class="row"><span>Kembali</span><strong>{{ number_format(max(0, $totalPaid - $totalBiaya), 0, ',', '.') }}</strong></div>
                    </div>
                </div>
            </div>

            <div class="inv-sign">
                <div class="col">
                    Diterima oleh,
                    <div class="name">{{ $toName }}</div>
                </div>
                <div class="col right">
                    Hormat Kami,
                    <div class="name">{{ $stockMutation->user?->name ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
