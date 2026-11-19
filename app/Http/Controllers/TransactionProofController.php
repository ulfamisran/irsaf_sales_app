<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Stock;
use App\Models\TransactionProof;
use App\Models\UnitExchange;
use App\Services\TransactionProofService;
use App\Support\UploadVault;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionProofController extends Controller
{
    public function store(Request $request, string $document, TransactionProofService $proofs): RedirectResponse
    {
        // $document mengisi parameter rute induk supaya bukti tidak tertukar dengan id transaksi.
        $owner = $this->owner($request);
        $this->authorizeOwner($owner);
        $this->ensureOpen($owner);

        if ($request->has('proof_files')) {
            $proofs->validateUploads($request, $owner, false, $owner instanceof Purchase);
            $before = $owner->proofs()->count();
            $proofs->storeUploaded($request, $owner, $request->user()?->id);
            if ($owner->proofs()->count() === $before) {
                throw ValidationException::withMessages([
                    'proof_files' => __('Pilih 1 file bukti transaksi.'),
                ]);
            }

            return back()->with('success', __('Bukti transaksi berhasil disimpan.'));
        }

        $noteRule = $owner instanceof Purchase ? 'required' : 'nullable';
        $request->validate([
            'proof_file' => ['required', function ($attribute, $value, $fail) use ($proofs) {
                if (! $value instanceof \Illuminate\Http\UploadedFile || ! $value->isValid()) {
                    $error = $value instanceof \Illuminate\Http\UploadedFile ? $value->getError() : UPLOAD_ERR_NO_FILE;
                    $fail($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                        ? __('Ukuran bukti melebihi batas unggah server (:limit). Cukup unggah 1 file yang lebih kecil.', ['limit' => (string) ini_get('upload_max_filesize')])
                        : __('Pilih 1 file bukti transaksi.'));

                    return;
                }
                if ($value->getSize() > 8192 * 1024) {
                    $fail(__('Ukuran bukti transaksi maksimal 8 MB.'));

                    return;
                }
                if (! $proofs->allowsFile($value)) {
                    $fail(__('Bukti transaksi harus berupa gambar atau PDF.'));
                }
            }],
            'note' => [$noteRule, 'string', 'max:255'],
        ], [
            'proof_file.required' => __('Pilih file bukti transaksi.'),
            'proof_file.max' => __('Ukuran bukti transaksi maksimal 8 MB.'),
            'note.required' => __('Keterangan bukti wajib diisi.'),
        ]);

        $proofs->storeFile($owner, $request->file('proof_file'), $request->input('note'), $request->user()?->id);

        return back()->with('success', __('Bukti transaksi berhasil disimpan.'));
    }

    public function destroy(Request $request, string $document, TransactionProof $proof, TransactionProofService $proofs): RedirectResponse
    {
        // $document mengisi parameter rute induk supaya bukti tidak tertukar dengan id transaksi.
        $owner = $this->owner($request);
        $this->authorizeOwner($owner);
        $this->ensureOwned($owner, $proof);
        $this->ensureOpen($owner);
        $proofs->delete($proof);

        return back()->with('success', __('Bukti transaksi berhasil dihapus.'));
    }

    public function show(Request $request, string $document, TransactionProof $proof): StreamedResponse
    {
        // $document mengisi parameter rute induk supaya bukti tidak tertukar dengan id transaksi.
        $owner = $this->owner($request);
        $this->authorizeOwner($owner);
        $this->ensureOwned($owner, $proof);
        UploadVault::ensure('local', $proof->file_path);
        abort_unless(Storage::disk('local')->exists($proof->file_path), 404);

        return Storage::disk('local')->response($proof->file_path, $proof->original_name);
    }

    private function owner(Request $request): Model
    {
        $map = [
            'sale' => Sale::class,
            'service' => Service::class,
            'rental' => Rental::class,
            'purchase' => Purchase::class,
            'exchange' => UnitExchange::class,
            'distribution' => Distribution::class,
        ];

        foreach ($map as $parameter => $class) {
            $value = $request->route($parameter);
            if ($value instanceof $class) {
                return $value;
            }
            if (is_scalar($value) && (string) $value !== '') {
                $owner = $class::query()->find($value);
                if ($owner instanceof Model) {
                    return $owner;
                }
            }
        }

        abort(404);
    }

    private function ensureOwned(Model $owner, TransactionProof $proof): void
    {
        abort_unless(
            $proof->proofable_type === $owner->getMorphClass() && (int) $proof->proofable_id === (int) $owner->getKey(),
            404
        );
    }

    private function ensureOpen(Model $owner): void
    {
        if ($owner instanceof Purchase) {
            if ($owner->isCancelled()) {
                abort(403, __('Bukti transaksi tidak dapat diubah pada pembelian yang dibatalkan.'));
            }

            return;
        }

        if ($owner instanceof Distribution) {
            if ($owner->isCancelled()) {
                abort(403, __('Bukti transaksi tidak dapat diubah pada distribusi yang dibatalkan.'));
            }

            return;
        }

        if (($owner->status ?? null) !== 'open') {
            abort(403, __('Bukti transaksi hanya dapat diubah saat dokumen masih Open.'));
        }
    }

    private function authorizeOwner(Model $owner): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403, __('Unauthorized.'));
        }
        if ($user->isSuperAdminOrAdminPusat()) {
            return;
        }

        if ($owner instanceof UnitExchange) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR, Role::KEPALA_CABANG]) && $user->branch_id && (int) $owner->branch_id === (int) $user->branch_id && $owner->warehouse_id === null) {
                return;
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id && (int) $owner->warehouse_id === (int) $user->warehouse_id) {
                return;
            }
            abort(403, __('Unauthorized.'));
        }

        if ($owner instanceof Sale) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR, Role::KEPALA_CABANG]) && $user->branch_id && (int) $owner->branch_id === (int) $user->branch_id && ! $owner->isWarehouseSale()) {
                return;
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id && $owner->isWarehouseSale() && (int) $owner->warehouse_id === (int) $user->warehouse_id) {
                return;
            }
            abort(403, __('Unauthorized.'));
        }

        if ($owner instanceof Purchase) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KEPALA_CABANG]) && (int) $owner->branch_id === (int) $user->branch_id) {
                return;
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && (int) $owner->warehouse_id === (int) $user->warehouse_id) {
                return;
            }
            abort(403, __('Unauthorized.'));
        }

        if ($owner instanceof Distribution) {
            if ($user->branch_id) {
                $involved = ($owner->from_location_type === Stock::LOCATION_BRANCH && (int) $owner->from_location_id === (int) $user->branch_id)
                    || ($owner->to_location_type === Stock::LOCATION_BRANCH && (int) $owner->to_location_id === (int) $user->branch_id);
                if (! $involved) {
                    abort(403, __('Unauthorized.'));
                }

                return;
            }
            if ($user->warehouse_id) {
                $involved = ($owner->from_location_type === Stock::LOCATION_WAREHOUSE && (int) $owner->from_location_id === (int) $user->warehouse_id)
                    || ($owner->to_location_type === Stock::LOCATION_WAREHOUSE && (int) $owner->to_location_id === (int) $user->warehouse_id);
                if (! $involved) {
                    abort(403, __('Unauthorized.'));
                }
            }

            return;
        }

        if ($owner instanceof Service) {
            $isWarehouse = ($owner->location_type ?? Service::LOCATION_BRANCH) === Service::LOCATION_WAREHOUSE;
            if ($isWarehouse && $user->warehouse_id && (int) $user->warehouse_id === (int) $owner->warehouse_id && (
                $user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG])
                || ($user->hasRole(Role::KASIR) && ! $user->branch_id)
            )) {
                return;
            }
            if (! $isWarehouse && $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR, Role::KEPALA_CABANG]) && (int) $user->branch_id === (int) $owner->branch_id) {
                return;
            }
            abort(403, __('Unauthorized.'));
        }

        if ($user->branch_id && (int) $owner->branch_id !== (int) $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
    }
}
