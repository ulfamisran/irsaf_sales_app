<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Role;
use App\Models\TransactionVerification;
use App\Models\Warehouse;
use App\Services\TransactionVerificationService;
use App\Support\VerificationCutoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class TransactionVerificationController extends Controller
{
    public function __construct(
        protected TransactionVerificationService $verificationService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->ensureVerifier($user);
        $type = (string) $request->input('type', TransactionVerification::TYPE_SALE);
        $tab = $request->input('tab') === 'riwayat' ? 'riwayat' : 'siap';
        $canFilterLocation = $user->isSuperAdminOrAdminPusat();

        return view('verification.index', [
            'rows' => $tab === 'siap' ? $this->verificationService->paginate($user, $request) : null,
            'history' => $tab === 'riwayat' ? $this->verificationService->paginateHistory($user, $request) : null,
            'summary' => $tab === 'siap'
                ? $this->verificationService->pendingCounts($user, $request)
                : $this->verificationService->historyCounts($user, $request),
            'type' => $type,
            'types' => $this->types(),
            'tab' => $tab,
            'canFilterLocation' => $canFilterLocation,
            'branches' => $canFilterLocation ? Branch::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'warehouses' => $canFilterLocation ? Warehouse::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'cutoffDate' => VerificationCutoff::date(),
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->ensureVerifier($user);
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $saved = $this->verificationService->acceptMany($user, $validated['type'], $validated['ids']);

        return back()->with('success', __('Verifikasi diterima: :count transaksi.', ['count' => $saved]));
    }

    public function reject(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->ensureVerifier($user);
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required', 'integer'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->verificationService->reject($user, $validated['type'], (int) $validated['id'], $validated['notes']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Transaksi ditolak dan dibatalkan.'));
    }

    private function ensureVerifier($user): void
    {
        if (! $user || ! $user->hasAnyRole([
            Role::SUPER_ADMIN,
            Role::ADMIN_PUSAT,
            Role::KEPALA_CABANG,
            Role::KEPALA_GUDANG,
        ])) {
            abort(403, __('Anda tidak punya akses verifikasi.'));
        }
    }

    /**
     * @return array<string, string>
     */
    private function types(): array
    {
        return [
            TransactionVerification::TYPE_SALE => __('Penjualan'),
            TransactionVerification::TYPE_PURCHASE => __('Pembelian'),
            TransactionVerification::TYPE_SERVICE => __('Service'),
            TransactionVerification::TYPE_RENTAL => __('Penyewaan'),
            TransactionVerification::TYPE_CASH_IN => __('Kas Masuk'),
            TransactionVerification::TYPE_CASH_OUT => __('Kas Keluar'),
            TransactionVerification::TYPE_CASH_OUT_EXTERNAL => __('Kas Keluar Eksternal'),
        ];
    }
}
