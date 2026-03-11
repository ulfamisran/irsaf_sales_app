<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DebtController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $query = Purchase::with(['distributor', 'warehouse', 'branch', 'user'])
            ->where('total', '>', 0)
            ->where('status', '!=', Purchase::STATUS_CANCELLED)
            ->orderBy('due_date')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;
        $lockedBranchId = null;
        $lockedWarehouseId = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $lockedBranchId = (int) $user->branch_id;
                $locationLabel = __('Cabang') . ': ' . (Branch::find($user->branch_id)?->name ?? '');
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $lockedWarehouseId = (int) $user->warehouse_id;
                $locationLabel = __('Gudang') . ': ' . (Warehouse::find($user->warehouse_id)?->name ?? '');
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            } elseif ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
        }

        // Filter status: outstanding (default) | lunas
        $statusFilter = $request->get('status', 'outstanding');
        if ($statusFilter === 'lunas') {
            $query->whereRaw('(COALESCE(total_paid, 0) >= total - 0.02)');
        } else {
            $query->whereRaw('(COALESCE(total_paid, 0) < total - 0.02)');
        }

        // Total unpaid across all matching records (before pagination)
        $totalUnpaid = 0.0;
        if ($statusFilter === 'outstanding') {
            $totalUnpaid = (float) (clone $query)->get()->sum(fn ($p) => max(0, (float) $p->total - (float) ($p->total_paid ?? 0)));
        }

        $purchases = $query->paginate(20)->withQueryString();

        // Compute remaining per row and due-soon flag
        $today = Carbon::today();
        $dueWarningDays = 3;
        $rows = $purchases->map(function ($p) use ($today, $dueWarningDays) {
            $total = (float) $p->total;
            $paid = (float) ($p->total_paid ?? 0);
            $remaining = max(0, $total - $paid);
            $dueDate = $p->due_date;
            $daysUntilDue = $dueDate ? $today->diffInDays($dueDate, false) : null;
            $isDueSoon = $dueDate && $daysUntilDue !== null && $daysUntilDue >= 0 && $daysUntilDue < $dueWarningDays;

            return (object) [
                'purchase' => $p,
                'remaining' => $remaining,
                'due_date' => $dueDate,
                'is_due_soon' => $isDueSoon,
                'days_until_due' => $daysUntilDue,
            ];
        });

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return view('debts.index', compact(
            'purchases',
            'rows',
            'totalUnpaid',
            'warehouses',
            'branches',
            'canFilterLocation',
            'filterLocked',
            'locationLabel',
            'lockedBranchId',
            'lockedWarehouseId',
            'statusFilter'
        ));
    }

    public function paymentHistory(Purchase $purchase): View
    {
        $user = request()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $purchase->branch_id !== $user->branch_id) {
                abort(403);
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $purchase->warehouse_id !== $user->warehouse_id) {
                abort(403);
            }
        }
        $purchase->load(['distributor', 'payments.paymentMethod', 'payments.user']);

        return view('debts.payment-history', compact('purchase'));
    }
}
