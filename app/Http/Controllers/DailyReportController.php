<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\DamagedGood;
use App\Models\DistributionDetail;
use App\Models\IncomingGood;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleTradeIn;
use App\Models\Service;
use App\Models\User;
use App\Support\ExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyReportController extends Controller
{
    public const TX_BARANG_MASUK = 'Input Barang Masuk';

    public const TX_PEMBELIAN = 'Input Pembelian';

    public const TX_PENJUALAN = 'Input Penjualan';

    public const TX_SERVICE = 'Input Service';

    public const TX_SEWA = 'Input Sewa';

    public const TX_TUKAR_TAMBAH = 'Input Tukar Tambah';

    public const TX_BARANG_RUSAK = 'Input Barang Rusak';

    public const TX_KAS_MASUK = 'Input Kas Masuk Lainnya';

    public const TX_KAS_KELUAR = 'Input Kas Keluar Lainnya';

    /**
     * @return list<string>
     */
    public static function transactionTypes(): array
    {
        return [
            self::TX_BARANG_MASUK,
            self::TX_PEMBELIAN,
            self::TX_PENJUALAN,
            self::TX_SERVICE,
            self::TX_SEWA,
            self::TX_TUKAR_TAMBAH,
            self::TX_BARANG_RUSAK,
            self::TX_KAS_MASUK,
            self::TX_KAS_KELUAR,
        ];
    }

    public function index(): View
    {
        $this->ensureStrictSuperAdmin();

        return view('daily-reports.index');
    }

    public function barangMasuk(Request $request): View
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildBarangMasukData($request);

        return view('daily-reports.barang-masuk', $data);
    }

    public function barangMasukExport(Request $request): StreamedResponse
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildBarangMasukData($request);
        $html = view('daily-reports.exports.barang-masuk', $data)->render();

        return ExcelExporter::downloadFromHtml(
            $html,
            'laporan-barang-masuk-'.$data['date'].'-'.now()->format('His').'.xlsx',
            'generic'
        );
    }

    public function barangMasukExportPdf(Request $request)
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildBarangMasukData($request);
        $pdf = Pdf::loadView('daily-reports.exports.barang-masuk-pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('laporan-barang-masuk-'.$data['date'].'-'.now()->format('His').'.pdf');
    }

    public function userActivity(Request $request): View
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildUserActivityData($request);

        return view('daily-reports.user-activity', $data);
    }

    public function userActivityExport(Request $request): StreamedResponse
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildUserActivityData($request);
        $html = view('daily-reports.exports.user-activity', $data)->render();

        return ExcelExporter::downloadFromHtml(
            $html,
            'laporan-log-pengguna-'.$data['date'].'-'.now()->format('His').'.xlsx',
            'generic'
        );
    }

    public function userActivityExportPdf(Request $request)
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildUserActivityData($request);
        $pdf = Pdf::loadView('daily-reports.exports.user-activity-pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('laporan-log-pengguna-'.$data['date'].'-'.now()->format('His').'.pdf');
    }

    public function barangTerjual(Request $request): View
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildBarangTerjualData($request);

        return view('daily-reports.barang-terjual', $data);
    }

    public function barangTerjualExport(Request $request): StreamedResponse
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildBarangTerjualData($request);
        $html = view('daily-reports.exports.barang-terjual', $data)->render();

        return ExcelExporter::downloadFromHtml(
            $html,
            'laporan-barang-terjual-'.$data['date'].'-'.now()->format('His').'.xlsx',
            'generic'
        );
    }

    public function barangTerjualExportPdf(Request $request)
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildBarangTerjualData($request);
        $pdf = Pdf::loadView('daily-reports.exports.barang-terjual-pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('laporan-barang-terjual-'.$data['date'].'-'.now()->format('His').'.pdf');
    }

    public function service(Request $request): View
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildServiceData($request);

        return view('daily-reports.service', $data);
    }

    public function serviceExport(Request $request): StreamedResponse
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildServiceData($request);
        $html = view('daily-reports.exports.service', $data)->render();

        return ExcelExporter::downloadFromHtml(
            $html,
            'laporan-service-'.$data['date'].'-'.now()->format('His').'.xlsx',
            'generic'
        );
    }

    public function serviceExportPdf(Request $request)
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildServiceData($request);
        $pdf = Pdf::loadView('daily-reports.exports.service-pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('laporan-service-'.$data['date'].'-'.now()->format('His').'.pdf');
    }

    public function barangRusak(Request $request): View
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildBarangRusakData($request);

        return view('daily-reports.barang-rusak', $data);
    }

    public function barangRusakExport(Request $request): StreamedResponse
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildBarangRusakData($request);
        $html = view('daily-reports.exports.barang-rusak', $data)->render();

        return ExcelExporter::downloadFromHtml(
            $html,
            'laporan-barang-rusak-'.$data['date'].'-'.now()->format('His').'.xlsx',
            'generic'
        );
    }

    public function barangRusakExportPdf(Request $request)
    {
        $this->ensureStrictSuperAdmin();
        $data = $this->buildBarangRusakData($request);
        $pdf = Pdf::loadView('daily-reports.exports.barang-rusak-pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('laporan-barang-rusak-'.$data['date'].'-'.now()->format('His').'.pdf');
    }

    /**
     * @return array{date: string, rows: Collection, totalPembelian: float}
     */
    private function buildBarangMasukData(Request $request): array
    {
        $date = $this->resolveDate($request);

        $purchases = PurchaseDetail::with(['purchase.user', 'purchase.distributor', 'purchase.warehouse', 'purchase.branch', 'product'])
            ->whereHas('purchase', function ($q) use ($date) {
                $q->whereDate('purchase_date', $date)
                    ->where('status', '!=', 'cancelled');
            })
            ->get()
            ->map(function (PurchaseDetail $d) {
                $p = $d->purchase;

                return [
                    'jenis' => 'Pembelian',
                    'referensi' => $p?->invoice_number ?? '-',
                    'produk' => $this->productLabel($d->product),
                    'qty' => (int) $d->quantity,
                    'serial' => $d->serial_numbers ?: '-',
                    'lokasi' => $this->locationLabel($p?->warehouse, $p?->branch, $p?->warehouse_id, $p?->branch_id),
                    'user' => $p?->user?->name ?? '-',
                    'tanggal' => $p?->purchase_date,
                    'waktu' => $p?->created_at,
                    'keterangan' => $p?->jenis_pembelian ?? '-',
                    'nilai' => (float) ($d->subtotal ?? 0),
                    'detail_url' => $p ? route('purchases.show', ['purchase' => $p, 'view' => 'detail']) : null,
                ];
            });

        $incoming = IncomingGood::with(['user', 'product', 'warehouse', 'branch'])
            ->whereDate('received_date', $date)
            ->get()
            ->map(function (IncomingGood $ig) {
                return [
                    'jenis' => 'Input Barang Masuk',
                    'referensi' => '#'.$ig->id,
                    'produk' => $this->productLabel($ig->product),
                    'qty' => (int) $ig->quantity,
                    'serial' => '-',
                    'lokasi' => $this->locationLabel($ig->warehouse, $ig->branch, $ig->warehouse_id, $ig->branch_id),
                    'user' => $ig->user?->name ?? '-',
                    'tanggal' => $ig->received_date,
                    'waktu' => $ig->created_at,
                    'keterangan' => $ig->notes ?: '-',
                    'nilai' => null,
                    'detail_url' => route('incoming-goods.show', $ig),
                ];
            });

        $distributions = DistributionDetail::with(['distribution.user', 'product'])
            ->whereHas('distribution', function ($q) use ($date) {
                $q->whereDate('distribution_date', $date)
                    ->where('status', '!=', 'cancelled');
            })
            ->get()
            ->map(function (DistributionDetail $d) {
                $dist = $d->distribution;
                $tujuan = $dist?->toLocationName() ?? '-';
                $asal = $dist?->fromLocationName() ?? '-';

                return [
                    'jenis' => 'Distribusi',
                    'referensi' => $dist?->invoice_number ?? '-',
                    'produk' => $this->productLabel($d->product),
                    'qty' => (int) $d->quantity,
                    'serial' => $d->serial_numbers ?: '-',
                    'lokasi' => __('Tujuan').': '.$tujuan,
                    'user' => $dist?->user?->name ?? '-',
                    'tanggal' => $dist?->distribution_date,
                    'waktu' => $dist?->created_at,
                    'keterangan' => $asal.' → '.$tujuan,
                    'nilai' => (float) $d->quantity * (float) ($d->biaya_distribusi_per_unit ?? 0),
                    'detail_url' => $dist ? route('stock-mutations.invoice', $dist) : null,
                ];
            });

        $tradeIns = SaleTradeIn::with(['sale.user', 'sale.branch', 'sale.warehouse', 'product', 'category'])
            ->whereHas('sale', function ($q) use ($date) {
                $q->whereDate('sale_date', $date)
                    ->where('status', '!=', Sale::STATUS_CANCEL);
            })
            ->get()
            ->map(function (SaleTradeIn $ti) {
                $sale = $ti->sale;
                $produk = $ti->product
                    ? $this->productLabel($ti->product)
                    : trim(($ti->sku ? $ti->sku.' - ' : '').($ti->brand ?? '').' '.($ti->series ?? ''));

                return [
                    'jenis' => 'Tukar Tambah',
                    'referensi' => $sale?->invoice_number ?? '-',
                    'produk' => $produk !== '' ? $produk : '-',
                    'qty' => 1,
                    'serial' => $ti->serial_number ?: '-',
                    'lokasi' => $this->locationLabel($sale?->warehouse, $sale?->branch, $sale?->warehouse_id, $sale?->branch_id),
                    'user' => $sale?->user?->name ?? '-',
                    'tanggal' => $sale?->sale_date,
                    'waktu' => $sale?->created_at ?? $ti->created_at,
                    'keterangan' => 'Nilai: '.number_format((float) $ti->trade_in_value, 0, ',', '.'),
                    'nilai' => (float) $ti->trade_in_value,
                    'detail_url' => $sale ? route('sales.show', $sale) : null,
                ];
            });

        $rows = $purchases
            ->concat($incoming)
            ->concat($distributions)
            ->concat($tradeIns)
            ->sortByDesc(fn ($r) => ($r['waktu']?->timestamp ?? 0))
            ->values();

        $totalPembelian = (float) Purchase::query()
            ->whereDate('purchase_date', $date)
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        return [
            'date' => $date,
            'rows' => $rows,
            'totalPembelian' => $totalPembelian,
        ];
    }

    /**
     * @return array{date: string, activities: Collection, users: Collection, transactionTypes: list<string>, filterUserId: ?int, filterJenis: ?string, totalPembelian: ?float, totalPenjualan: ?float}
     */
    private function buildUserActivityData(Request $request): array
    {
        $date = $this->resolveDate($request);
        $userId = $request->filled('user_id') ? (int) $request->user_id : null;
        $jenis = $request->filled('jenis_transaksi') ? (string) $request->jenis_transaksi : null;

        $activities = $this->collectUserActivities($date, $userId, $jenis)
            ->sortByDesc(fn ($r) => ($r['waktu']?->timestamp ?? 0))
            ->values();

        $totalPembelian = null;
        $totalPenjualan = null;

        if ($jenis === null || $jenis === '' || $jenis === self::TX_PEMBELIAN) {
            $q = Purchase::query()
                ->whereDate('purchase_date', $date)
                ->where('status', '!=', 'cancelled')
                ->when($userId, fn ($query) => $query->where('user_id', $userId));
            if ($jenis === self::TX_PEMBELIAN || ($jenis === null || $jenis === '')) {
                $totalPembelian = (float) $q->sum('total');
            }
        }

        if ($jenis === null || $jenis === '' || $jenis === self::TX_PENJUALAN) {
            $q = Sale::query()
                ->whereDate('sale_date', $date)
                ->where('status', '!=', Sale::STATUS_CANCEL)
                ->when($userId, fn ($query) => $query->where('user_id', $userId));
            if ($jenis === self::TX_PENJUALAN || ($jenis === null || $jenis === '')) {
                $totalPenjualan = (float) $q->sum('total');
            }
        }

        // Only surface totals relevant to filter selection
        if ($jenis === self::TX_PEMBELIAN) {
            $totalPenjualan = null;
        } elseif ($jenis === self::TX_PENJUALAN) {
            $totalPembelian = null;
        } elseif ($jenis !== null && $jenis !== '') {
            $totalPembelian = null;
            $totalPenjualan = null;
        }

        return [
            'date' => $date,
            'activities' => $activities,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'transactionTypes' => self::transactionTypes(),
            'filterUserId' => $userId,
            'filterJenis' => $jenis,
            'totalPembelian' => $totalPembelian,
            'totalPenjualan' => $totalPenjualan,
        ];
    }

    /**
     * @return array{date: string, rows: Collection, totalPenjualan: float}
     */
    private function buildBarangTerjualData(Request $request): array
    {
        $date = $this->resolveDate($request);

        $details = SaleDetail::with(['sale.user', 'sale.branch', 'sale.warehouse', 'product'])
            ->whereHas('sale', function ($q) use ($date) {
                $q->whereDate('sale_date', $date)
                    ->where('status', '!=', Sale::STATUS_CANCEL);
            })
            ->get()
            ->map(function (SaleDetail $d) {
                $sale = $d->sale;

                return [
                    'invoice' => $sale?->invoice_number ?? '-',
                    'produk' => $this->productLabel($d->product),
                    'unit' => $d->serial_numbers ?: '-',
                    'qty' => (int) $d->quantity,
                    'lokasi' => $this->locationLabel($sale?->warehouse, $sale?->branch, $sale?->warehouse_id, $sale?->branch_id),
                    'user' => $sale?->user?->name ?? '-',
                    'tanggal' => $sale?->sale_date,
                    'waktu' => $sale?->created_at,
                    'status_lunas' => $sale?->isPaidOff() ? 'Lunas' : 'Belum Lunas',
                    'nilai' => (float) $d->quantity * (float) ($d->price ?? 0),
                    'total_invoice' => $sale?->total,
                    'detail_url' => $sale ? route('sales.show', $sale) : null,
                ];
            })
            ->sortByDesc(fn ($r) => ($r['waktu']?->timestamp ?? 0))
            ->values();

        $totalPenjualan = (float) Sale::query()
            ->whereDate('sale_date', $date)
            ->where('status', '!=', Sale::STATUS_CANCEL)
            ->sum('total');

        return [
            'date' => $date,
            'rows' => $details,
            'totalPenjualan' => $totalPenjualan,
        ];
    }

    /**
     * @return array{date: string, masuk: Collection, keluar: Collection, menunggu: Collection}
     */
    private function buildServiceData(Request $request): array
    {
        $date = $this->resolveDate($request);

        $masuk = Service::with(['user', 'customer', 'branch', 'warehouse'])
            ->where('status', '!=', Service::STATUS_CANCEL)
            ->whereDate('entry_date', $date)
            ->orderByDesc('created_at')
            ->get();

        $keluar = Service::with(['user', 'customer', 'branch', 'warehouse'])
            ->where('status', Service::STATUS_COMPLETED)
            ->whereDate('exit_date', $date)
            ->orderByDesc('exit_date')
            ->get();

        $menunggu = Service::with(['user', 'customer', 'branch', 'warehouse'])
            ->where('status', Service::STATUS_COMPLETED)
            ->where('pickup_status', Service::PICKUP_BELUM)
            ->where(function ($q) use ($date) {
                $q->whereDate('exit_date', '<=', $date)
                    ->orWhere(function ($q2) use ($date) {
                        $q2->whereNull('exit_date')->whereDate('entry_date', '<=', $date);
                    });
            })
            ->orderByDesc('exit_date')
            ->orderByDesc('entry_date')
            ->get();

        return [
            'date' => $date,
            'masuk' => $masuk,
            'keluar' => $keluar,
            'menunggu' => $menunggu,
        ];
    }

    /**
     * @return array{date: string, rows: Collection, totalHpp: float}
     */
    private function buildBarangRusakData(Request $request): array
    {
        $date = $this->resolveDate($request);

        $rows = DamagedGood::with(['user', 'productUnit.product.category', 'productUnit.branch', 'productUnit.warehouse'])
            ->whereDate('recorded_date', $date)
            ->orderByDesc('created_at')
            ->get();

        return [
            'date' => $date,
            'rows' => $rows,
            'totalHpp' => (float) $rows->sum('harga_hpp'),
        ];
    }

    private function ensureStrictSuperAdmin(): void
    {
        if (! request()->user()?->isStrictSuperAdmin()) {
            abort(403, __('Unauthorized.'));
        }
    }

    private function resolveDate(Request $request): string
    {
        $raw = $request->get('date');
        if (! $raw) {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function productLabel($product): string
    {
        if (! $product) {
            return '-';
        }

        return trim(($product->sku ?? '').' - '.($product->brand ?? '').' '.($product->series ?? ''));
    }

    private function locationLabel($warehouse = null, $branch = null, $warehouseId = null, $branchId = null): string
    {
        if ($warehouseId || $warehouse) {
            return __('Gudang').': '.($warehouse?->name ?? '#'.$warehouseId);
        }
        if ($branchId || $branch) {
            return __('Cabang').': '.($branch?->name ?? '#'.$branchId);
        }

        return '-';
    }

    private function serviceLocationLabel(Service $svc): string
    {
        return $this->locationLabel($svc->warehouse, $svc->branch, $svc->warehouse_id, $svc->branch_id);
    }

    private function collectUserActivities(string $date, ?int $userId, ?string $jenis): Collection
    {
        $all = collect();

        if ($this->includeType($jenis, self::TX_BARANG_MASUK)) {
            IncomingGood::with(['user', 'product', 'warehouse', 'branch'])
                ->whereDate('received_date', $date)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->get()
                ->each(function (IncomingGood $ig) use ($all) {
                    $all->push([
                        'jenis' => self::TX_BARANG_MASUK,
                        'referensi' => '#'.$ig->id,
                        'deskripsi' => $this->productLabel($ig->product).' qty '.$ig->quantity,
                        'lokasi' => $this->locationLabel($ig->warehouse, $ig->branch, $ig->warehouse_id, $ig->branch_id),
                        'user' => $ig->user?->name ?? '-',
                        'user_id' => $ig->user_id,
                        'tanggal' => $ig->received_date,
                        'waktu' => $ig->created_at,
                        'detail_url' => route('incoming-goods.show', $ig),
                    ]);
                });
        }

        if ($this->includeType($jenis, self::TX_PEMBELIAN)) {
            Purchase::with(['user', 'warehouse', 'branch'])
                ->whereDate('purchase_date', $date)
                ->where('status', '!=', 'cancelled')
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->get()
                ->each(function (Purchase $p) use ($all) {
                    $all->push([
                        'jenis' => self::TX_PEMBELIAN,
                        'referensi' => $p->invoice_number,
                        'deskripsi' => ($p->jenis_pembelian ?? 'Pembelian').' — total '.number_format((float) $p->total, 0, ',', '.'),
                        'lokasi' => $this->locationLabel($p->warehouse, $p->branch, $p->warehouse_id, $p->branch_id),
                        'user' => $p->user?->name ?? '-',
                        'user_id' => $p->user_id,
                        'tanggal' => $p->purchase_date,
                        'waktu' => $p->created_at,
                        'detail_url' => route('purchases.show', ['purchase' => $p, 'view' => 'detail']),
                    ]);
                });
        }

        if ($this->includeType($jenis, self::TX_PENJUALAN)) {
            Sale::with(['user', 'warehouse', 'branch'])
                ->whereDate('sale_date', $date)
                ->where('status', '!=', Sale::STATUS_CANCEL)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->get()
                ->each(function (Sale $sale) use ($all) {
                    $all->push([
                        'jenis' => self::TX_PENJUALAN,
                        'referensi' => $sale->invoice_number,
                        'deskripsi' => 'Penjualan — total '.number_format((float) $sale->total, 0, ',', '.'),
                        'lokasi' => $this->locationLabel($sale->warehouse, $sale->branch, $sale->warehouse_id, $sale->branch_id),
                        'user' => $sale->user?->name ?? '-',
                        'user_id' => $sale->user_id,
                        'tanggal' => $sale->sale_date,
                        'waktu' => $sale->created_at,
                        'detail_url' => route('sales.show', $sale),
                    ]);
                });
        }

        if ($this->includeType($jenis, self::TX_SERVICE)) {
            Service::with(['user', 'warehouse', 'branch'])
                ->whereDate('entry_date', $date)
                ->where('status', '!=', Service::STATUS_CANCEL)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->get()
                ->each(function (Service $svc) use ($all) {
                    $all->push([
                        'jenis' => self::TX_SERVICE,
                        'referensi' => $svc->invoice_number,
                        'deskripsi' => trim(($svc->laptop_type ?? '').' '.($svc->laptop_detail ?? '')),
                        'lokasi' => $this->serviceLocationLabel($svc),
                        'user' => $svc->user?->name ?? '-',
                        'user_id' => $svc->user_id,
                        'tanggal' => $svc->entry_date,
                        'waktu' => $svc->created_at,
                        'detail_url' => route('services.show', $svc),
                    ]);
                });
        }

        if ($this->includeType($jenis, self::TX_SEWA)) {
            Rental::with(['user', 'warehouse', 'branch'])
                ->whereDate('pickup_date', $date)
                ->where('status', '!=', Rental::STATUS_CANCEL)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->get()
                ->each(function (Rental $rental) use ($all) {
                    $all->push([
                        'jenis' => self::TX_SEWA,
                        'referensi' => $rental->invoice_number,
                        'deskripsi' => 'Sewa — total '.number_format((float) $rental->total, 0, ',', '.'),
                        'lokasi' => $this->locationLabel($rental->warehouse, $rental->branch, $rental->warehouse_id, $rental->branch_id),
                        'user' => $rental->user?->name ?? '-',
                        'user_id' => $rental->user_id,
                        'tanggal' => $rental->pickup_date,
                        'waktu' => $rental->created_at,
                        'detail_url' => route('rentals.show', $rental),
                    ]);
                });
        }

        if ($this->includeType($jenis, self::TX_TUKAR_TAMBAH)) {
            SaleTradeIn::with(['sale.user', 'sale.warehouse', 'sale.branch'])
                ->whereHas('sale', function ($q) use ($date, $userId) {
                    $q->whereDate('sale_date', $date)
                        ->where('status', '!=', Sale::STATUS_CANCEL)
                        ->when($userId, fn ($q2) => $q2->where('user_id', $userId));
                })
                ->get()
                ->each(function (SaleTradeIn $ti) use ($all) {
                    $sale = $ti->sale;
                    $all->push([
                        'jenis' => self::TX_TUKAR_TAMBAH,
                        'referensi' => $sale?->invoice_number ?? '-',
                        'deskripsi' => trim(($ti->brand ?? '').' '.($ti->series ?? '').' SN: '.($ti->serial_number ?? '-')),
                        'lokasi' => $this->locationLabel($sale?->warehouse, $sale?->branch, $sale?->warehouse_id, $sale?->branch_id),
                        'user' => $sale?->user?->name ?? '-',
                        'user_id' => $sale?->user_id,
                        'tanggal' => $sale?->sale_date,
                        'waktu' => $sale?->created_at ?? $ti->created_at,
                        'detail_url' => $sale ? route('sales.show', $sale) : null,
                    ]);
                });
        }

        if ($this->includeType($jenis, self::TX_BARANG_RUSAK)) {
            DamagedGood::with(['user', 'productUnit.warehouse', 'productUnit.branch'])
                ->whereDate('recorded_date', $date)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->get()
                ->each(function (DamagedGood $dg) use ($all) {
                    $unit = $dg->productUnit;
                    $lokasi = '-';
                    if ($unit) {
                        $lokasi = $unit->location_type === 'warehouse'
                            ? $this->locationLabel($unit->warehouse, null, $unit->location_id, null)
                            : $this->locationLabel(null, $unit->branch, null, $unit->location_id);
                    }
                    $all->push([
                        'jenis' => self::TX_BARANG_RUSAK,
                        'referensi' => '#'.$dg->id,
                        'deskripsi' => 'SN: '.$dg->serial_number.' — '.($dg->damage_description ?? ''),
                        'lokasi' => $lokasi,
                        'user' => $dg->user?->name ?? '-',
                        'user_id' => $dg->user_id,
                        'tanggal' => $dg->recorded_date,
                        'waktu' => $dg->created_at,
                        'detail_url' => route('damaged-goods.show', $dg),
                    ]);
                });
        }

        if ($this->includeType($jenis, self::TX_KAS_MASUK)) {
            CashFlow::with(['user', 'warehouse', 'branch'])
                ->where('type', CashFlow::TYPE_IN)
                ->where('reference_type', CashFlow::REFERENCE_OTHER)
                ->whereDate('transaction_date', $date)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->get()
                ->each(function (CashFlow $cf) use ($all) {
                    $all->push([
                        'jenis' => self::TX_KAS_MASUK,
                        'referensi' => '#'.$cf->id,
                        'deskripsi' => ($cf->description ?? '-').' — '.number_format((float) $cf->amount, 0, ',', '.'),
                        'lokasi' => $this->locationLabel($cf->warehouse, $cf->branch, $cf->warehouse_id, $cf->branch_id),
                        'user' => $cf->user?->name ?? '-',
                        'user_id' => $cf->user_id,
                        'tanggal' => $cf->transaction_date,
                        'waktu' => $cf->created_at,
                        'detail_url' => route('cash-flows.in.show', $cf),
                    ]);
                });
        }

        if ($this->includeType($jenis, self::TX_KAS_KELUAR)) {
            CashFlow::with(['user', 'warehouse', 'branch'])
                ->where('type', CashFlow::TYPE_OUT)
                ->where('reference_type', CashFlow::REFERENCE_EXPENSE)
                ->whereDate('transaction_date', $date)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->get()
                ->each(function (CashFlow $cf) use ($all) {
                    $all->push([
                        'jenis' => self::TX_KAS_KELUAR,
                        'referensi' => '#'.$cf->id,
                        'deskripsi' => ($cf->description ?? '-').' — '.number_format((float) $cf->amount, 0, ',', '.'),
                        'lokasi' => $this->locationLabel($cf->warehouse, $cf->branch, $cf->warehouse_id, $cf->branch_id),
                        'user' => $cf->user?->name ?? '-',
                        'user_id' => $cf->user_id,
                        'tanggal' => $cf->transaction_date,
                        'waktu' => $cf->created_at,
                        'detail_url' => route('cash-flows.out.show', $cf),
                    ]);
                });
        }

        return $all;
    }

    private function includeType(?string $filter, string $type): bool
    {
        return $filter === null || $filter === '' || $filter === $type;
    }
}
