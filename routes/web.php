<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashFlowController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\IncomingGoodsController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\LandingPageAdminController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StockMutationController;
use App\Http\Controllers\StockInOutController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

Route::get('/', [LandingPageController::class, 'index'])->name('landing.index');

Route::get('/dashboard', function (Request $request) {
    $user = auth()->user();

    // Date range (default: last 7 days)
    $end = $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : Carbon::today();
    $start = $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : (clone $end)->subDays(6);

    $start = $start->startOfDay();
    $end = $end->startOfDay();
    if ($start->gt($end)) {
        [$start, $end] = [$end, $start];
    }

    // Safety limit to avoid overly large charts
    $maxDays = 120;
    $days = $start->diffInDays($end) + 1;
    if ($days > $maxDays) {
        $start = (clone $end)->subDays($maxDays - 1);
        $days = $maxDays;
    }

    $dates = collect(range(0, $days - 1))->map(fn ($i) => (clone $start)->addDays($i));
    $labels = $dates->map(fn ($d) => $d->format('d/m'))->values();
    $keys = $dates->map(fn ($d) => $d->toDateString())->values();

    $isBranchUser = $user
        && ! $user->isSuperAdmin()
        && $user->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR])
        && $user->branch_id;

    // 1) Pergerakan jumlah barang masuk dan barang terjual
    if ($isBranchUser) {
        // Barang masuk untuk cabang: Distribusi masuk ke cabang
        $incomingMap = DB::table('stock_mutations')
            ->selectRaw('mutation_date as d, SUM(quantity) as qty')
            ->where('to_location_type', \App\Models\Stock::LOCATION_BRANCH)
            ->where('to_location_id', (int) $user->branch_id)
            ->whereBetween('mutation_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('mutation_date')
            ->pluck('qty', 'd');

        $incomingLabel = 'Barang Masuk (Distribusi)';
        // Barang terjual untuk cabang: penjualan (sale_details)
        $soldMap = DB::table('sale_details')
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->selectRaw('sales.sale_date as d, SUM(sale_details.quantity) as qty')
            ->where('sales.branch_id', (int) $user->branch_id)
            ->whereBetween('sales.sale_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('sales.sale_date')
            ->pluck('qty', 'd');
    } else {
        // Barang masuk global: Incoming Goods (ke gudang)
        $incomingMap = DB::table('incoming_goods')
            ->selectRaw('received_date as d, SUM(quantity) as qty')
            ->whereBetween('received_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('received_date')
            ->pluck('qty', 'd');

        $incomingLabel = 'Barang Masuk';

        // Barang terjual global: penjualan (sale_details)
        $soldMap = DB::table('sale_details')
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->selectRaw('sales.sale_date as d, SUM(sale_details.quantity) as qty')
            ->whereBetween('sales.sale_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('sales.sale_date')
            ->pluck('qty', 'd');
    }

    $incomingSeries = $keys->map(fn ($k) => (int) ($incomingMap[$k] ?? 0))->values();
    $soldSeries = $keys->map(fn ($k) => (int) ($soldMap[$k] ?? 0))->values();

    // 2) Pergerakan dana masuk dan dana keluar (Cash Flow)
    $cashBase = DB::table('cash_flows')
        ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()]);
    if ($isBranchUser) {
        $cashBase->where('branch_id', (int) $user->branch_id);
    }

    $cashInMap = (clone $cashBase)
        ->selectRaw('transaction_date as d, SUM(amount) as amt')
        ->where('type', \App\Models\CashFlow::TYPE_IN)
        ->groupBy('transaction_date')
        ->pluck('amt', 'd');

    $cashOutMap = (clone $cashBase)
        ->selectRaw('transaction_date as d, SUM(amount) as amt')
        ->where('type', \App\Models\CashFlow::TYPE_OUT)
        ->groupBy('transaction_date')
        ->pluck('amt', 'd');

    $cashInSeries = $keys->map(fn ($k) => (float) ($cashInMap[$k] ?? 0))->values();
    $cashOutSeries = $keys->map(fn ($k) => (float) ($cashOutMap[$k] ?? 0))->values();

    return view('dashboard', [
        'chartLabels' => $labels,
        'chartIncomingLabel' => $incomingLabel,
        'chartIncomingQty' => $incomingSeries,
        'chartSoldQty' => $soldSeries,
        'chartCashIn' => $cashInSeries,
        'chartCashOut' => $cashOutSeries,
        'chartRangeText' => $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
        'chartDateFrom' => $start->toDateString(),
        'chartDateTo' => $end->toDateString(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('branches/{branch}/units', [BranchController::class, 'units'])
        ->middleware('role:admin_cabang')
        ->name('branches.units');
    Route::resource('branches', BranchController::class)->middleware('role:admin_cabang');
    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
    Route::resource('customers', CustomerController::class)
        ->middleware('role:admin_cabang,kasir')
        ->except(['show']);
    Route::resource('payment-methods', PaymentMethodController::class)
        ->middleware('role:admin_cabang,kasir')
        ->except(['show']);
    Route::resource('warehouses', WarehouseController::class)->middleware('role:staff_gudang');

    Route::get('stock-mutations', [StockMutationController::class, 'index'])
        ->middleware('role:admin_cabang,kasir,staff_gudang')
        ->name('stock-mutations.index');
    Route::get('stock-mutations/create', [StockMutationController::class, 'create'])
        ->middleware('role:staff_gudang')
        ->name('stock-mutations.create');
    Route::get('stock-mutations/available-serials', [StockMutationController::class, 'availableSerials'])
        ->middleware('role:staff_gudang')
        ->name('stock-mutations.available-serials');
    Route::post('stock-mutations', [StockMutationController::class, 'store'])
        ->middleware('role:staff_gudang')
        ->name('stock-mutations.store');

    Route::get('stock-inout', [StockInOutController::class, 'index'])
        ->middleware('role:admin_cabang,kasir,staff_gudang')
        ->name('stock-inout.index');

    Route::get('incoming-goods', [IncomingGoodsController::class, 'index'])
        ->middleware('role:staff_gudang')
        ->name('incoming-goods.index');
    Route::get('incoming-goods/create', [IncomingGoodsController::class, 'create'])
        ->middleware('role:staff_gudang')
        ->name('incoming-goods.create');
    Route::post('incoming-goods', [IncomingGoodsController::class, 'store'])
        ->middleware('role:staff_gudang')
        ->name('incoming-goods.store');

    Route::get('sales/available-serials', [SaleController::class, 'availableSerials'])
        ->middleware('role:admin_cabang,kasir')
        ->name('sales.available-serials');
    Route::get('sales/available-products', [SaleController::class, 'availableProducts'])
        ->middleware('role:admin_cabang,kasir')
        ->name('sales.available-products');
    Route::get('sales/{sale}/invoice', [SaleController::class, 'invoice'])
        ->middleware('role:admin_cabang,kasir')
        ->name('sales.invoice');
    Route::post('sales/{sale}/release', [SaleController::class, 'release'])
        ->middleware('role:admin_cabang,kasir')
        ->name('sales.release');
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])
        ->middleware('role:admin_cabang,kasir')
        ->name('sales.cancel');
    Route::resource('sales', SaleController::class)
        ->middleware('role:admin_cabang,kasir')
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);

    Route::get('rentals/available-serials', [RentalController::class, 'availableSerials'])
        ->middleware('role:staff_gudang,kasir')
        ->name('rentals.available-serials');
    Route::get('rentals/available-products', [RentalController::class, 'availableProducts'])
        ->middleware('role:staff_gudang,kasir')
        ->name('rentals.available-products');
    Route::get('rentals/{rental}/invoice', [RentalController::class, 'invoice'])
        ->middleware('role:staff_gudang,kasir')
        ->name('rentals.invoice');
    Route::post('rentals/{rental}/add-payment', [RentalController::class, 'addPayment'])
        ->middleware('role:staff_gudang,kasir')
        ->name('rentals.add-payment');
    Route::post('rentals/{rental}/mark-returned', [RentalController::class, 'markReturned'])
        ->middleware('role:staff_gudang,kasir')
        ->name('rentals.mark-returned');
    Route::resource('rentals', RentalController::class)
        ->middleware('role:staff_gudang,kasir')
        ->only(['index', 'create', 'store', 'show']);

    Route::get('services/{service}/invoice', [ServiceController::class, 'invoice'])
        ->middleware('role:admin_cabang,kasir')
        ->name('services.invoice');
    Route::post('services/{service}/add-payment', [ServiceController::class, 'addPayment'])
        ->middleware('role:admin_cabang,kasir')
        ->name('services.add-payment');
    Route::post('services/{service}/complete', [ServiceController::class, 'complete'])
        ->middleware('role:admin_cabang,kasir')
        ->name('services.complete');
    Route::post('services/{service}/mark-picked-up', [ServiceController::class, 'markPickedUp'])
        ->middleware('role:admin_cabang,kasir')
        ->name('services.mark-picked-up');
    Route::post('services/{service}/cancel', [ServiceController::class, 'cancel'])
        ->middleware('role:admin_cabang,kasir')
        ->name('services.cancel');
    Route::resource('services', ServiceController::class)
        ->middleware('role:admin_cabang,kasir')
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);

    Route::get('cash-flows', [CashFlowController::class, 'index'])
        ->middleware('role:admin_cabang,kasir')
        ->name('cash-flows.index');
    Route::get('cash-flows/in', [CashFlowController::class, 'inIndex'])
        ->middleware('role:admin_cabang,kasir')
        ->name('cash-flows.in.index');
    Route::get('cash-flows/in/create', [CashFlowController::class, 'createIn'])
        ->middleware('role:admin_cabang,kasir')
        ->name('cash-flows.in.create');
    Route::post('cash-flows/in', [CashFlowController::class, 'storeIn'])
        ->middleware('role:admin_cabang,kasir')
        ->name('cash-flows.in.store');
    Route::get('cash-flows/out/list', [CashFlowController::class, 'outIndex'])
        ->middleware('role:admin_cabang,kasir')
        ->name('cash-flows.out.index');
    Route::get('cash-flows/out/create', [CashFlowController::class, 'createOut'])
        ->middleware('role:admin_cabang,kasir')
        ->name('cash-flows.out.create');
    Route::post('cash-flows/out', [CashFlowController::class, 'storeOut'])
        ->middleware('role:admin_cabang,kasir')
        ->name('cash-flows.out.store');

    Route::resource('expense-categories', ExpenseCategoryController::class)
        ->middleware('role:admin_cabang,kasir')
        ->except(['show']);

    Route::get('finance/profit-loss', [FinanceController::class, 'profitLoss'])
        ->middleware('role:admin_cabang,kasir')
        ->name('finance.profit-loss');
    Route::get('finance/cash-monitoring', [FinanceController::class, 'cashMonitoring'])
        ->middleware('role:admin_cabang,kasir')
        ->name('finance.cash-monitoring');
    Route::get('finance/cash-monitoring/detail', [FinanceController::class, 'cashMonitoringDetail'])
        ->middleware('role:admin_cabang,kasir')
        ->name('finance.cash-monitoring.detail');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/stock-warehouse', [ReportController::class, 'stockWarehouse'])
        ->middleware('role:staff_gudang')
        ->name('reports.stock-warehouse');
    Route::get('reports/stock-branch', [ReportController::class, 'stockBranch'])
        ->middleware('role:admin_cabang,kasir')
        ->name('reports.stock-branch');

    Route::middleware('role:super_admin')->group(function () {
        Route::get('landing-page', [LandingPageAdminController::class, 'index'])
            ->name('landing-page.manage');
        Route::put('landing-page/settings', [LandingPageAdminController::class, 'updateSettings'])
            ->name('landing-page.settings.update');
        Route::post('landing-page/slides', [LandingPageAdminController::class, 'storeSlide'])
            ->name('landing-page.slides.store');
        Route::put('landing-page/slides/{slide}', [LandingPageAdminController::class, 'updateSlide'])
            ->name('landing-page.slides.update');
        Route::delete('landing-page/slides/{slide}', [LandingPageAdminController::class, 'destroySlide'])
            ->name('landing-page.slides.destroy');
        Route::post('landing-page/features', [LandingPageAdminController::class, 'storeFeature'])
            ->name('landing-page.features.store');
        Route::put('landing-page/features/{feature}', [LandingPageAdminController::class, 'updateFeature'])
            ->name('landing-page.features.update');
        Route::delete('landing-page/features/{feature}', [LandingPageAdminController::class, 'destroyFeature'])
            ->name('landing-page.features.destroy');
        Route::post('landing-page/instagram', [LandingPageAdminController::class, 'storeInstagramPost'])
            ->name('landing-page.instagram.store');
        Route::put('landing-page/instagram/{post}', [LandingPageAdminController::class, 'updateInstagramPost'])
            ->name('landing-page.instagram.update');
        Route::delete('landing-page/instagram/{post}', [LandingPageAdminController::class, 'destroyInstagramPost'])
            ->name('landing-page.instagram.destroy');

        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
        Route::patch('users/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])->name('users.toggle-active');
    });
});

require __DIR__.'/auth.php';
