<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\Branch;
use App\Models\Service;
use App\Models\ServicePayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceService
{
    /**
     * Create a new service.
     * Status open: hanya pelanggan & info laptop, tanpa material dan pembayaran.
     * Status release: wajib pembayaran lengkap.
     */
    public function create(
        int $branchId,
        ?int $customerId,
        string $laptopType,
        ?string $laptopDetail,
        ?string $damageDescription,
        float $serviceCost,
        float $servicePrice,
        string $entryDate,
        array $payments,
        ?string $description = null,
        ?int $userId = null,
        float $materialsTotalPrice = 0.0,
        string $status = 'open'
    ): Service {
        $isRelease = $status === 'release';

        if ($isRelease) {
            if (empty($payments)) {
                throw new InvalidArgumentException(__('Pembayaran wajib diisi untuk status Release.'));
            }
            $paymentSum = $this->sumPayments($payments);
            if ($paymentSum < 0.01) {
                throw new InvalidArgumentException(__('Pembayaran minimal Rp 0,01.'));
            }
        } else {
            $paymentSum = 0.0;
            $servicePrice = 0.0;
            $materialsTotalPrice = 0.0;
        }

        $totalPrice = $servicePrice + max(0, $materialsTotalPrice);
        if ($paymentSum > $totalPrice + 0.02) {
            throw new InvalidArgumentException(
                __('Total pembayaran (:sum) tidak boleh melebihi total service (:total).', [
                    'sum' => number_format($paymentSum, 2, ',', '.'),
                    'total' => number_format($totalPrice, 2, ',', '.'),
                ])
            );
        }

        $branch = Branch::findOrFail($branchId);
        $targetStatus = $isRelease ? Service::STATUS_COMPLETED : Service::STATUS_OPEN;

        return DB::transaction(function () use (
            $branch,
            $customerId,
            $laptopType,
            $laptopDetail,
            $damageDescription,
            $serviceCost,
            $servicePrice,
            $entryDate,
            $payments,
            $paymentSum,
            $totalPrice,
            $description,
            $userId,
            $targetStatus
        ) {
            $invoiceNumber = $this->generateInvoiceNumber();
            $isPaidOff = $targetStatus === Service::STATUS_COMPLETED && $paymentSum >= $totalPrice - 0.02;

            $service = Service::create([
                'invoice_number' => $invoiceNumber,
                'branch_id' => $branch->id,
                'user_id' => $userId ?? auth()->id(),
                'customer_id' => $customerId,
                'laptop_type' => $laptopType,
                'laptop_detail' => $laptopDetail,
                'damage_description' => $damageDescription,
                'service_cost' => $serviceCost,
                'service_price' => $servicePrice,
                'total_paid' => $paymentSum,
                'entry_date' => $entryDate,
                'exit_date' => $targetStatus === Service::STATUS_COMPLETED ? $entryDate : null,
                'pickup_status' => Service::PICKUP_BELUM,
                'payment_status' => $isPaidOff ? Service::PAYMENT_LUNAS : Service::PAYMENT_BELUM_LUNAS,
                'status' => $targetStatus,
                'description' => $description,
            ]);

            foreach ($payments as $p) {
                $amt = round((float) ($p['amount'] ?? 0), 2);
                if ($amt <= 0) {
                    continue;
                }
                ServicePayment::create([
                    'service_id' => $service->id,
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => $amt,
                    'notes' => $p['notes'] ?? null,
                ]);
            }

            foreach (ServicePayment::with('paymentMethod')->where('service_id', $service->id)->get() as $sp) {
                $pm = $sp->paymentMethod;
                $pmLabel = $pm ? $pm->display_label : __('Payment');

                CashFlow::create([
                    'branch_id' => $branch->id,
                    'type' => CashFlow::TYPE_IN,
                    'amount' => $sp->amount,
                    'description' => __('Service') . ' ' . $service->invoice_number . ' - ' . $pmLabel,
                    'reference_type' => CashFlow::REFERENCE_SERVICE,
                    'reference_id' => $service->id,
                    'payment_method_id' => $sp->payment_method_id,
                    'transaction_date' => $entryDate,
                    'user_id' => $userId ?? auth()->id(),
                ]);
            }

            return $service->fresh()->load(['payments.paymentMethod', 'branch', 'user', 'customer']);
        });
    }

    /**
     * Update service (only when status open).
     * When $markAsReleased=true: wajib payments, set status ke completed.
     */
    public function update(
        Service $service,
        ?int $customerId,
        string $laptopType,
        ?string $laptopDetail,
        ?string $damageDescription,
        float $serviceCost,
        float $servicePrice,
        string $entryDate,
        array $payments,
        ?string $description = null,
        ?float $materialsTotalPrice = null,
        bool $markAsReleased = false
    ): Service {
        if ($service->status !== Service::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Service sudah completed atau dibatalkan.'));
        }

        if ($markAsReleased || ! empty($payments)) {
            if (empty($payments)) {
                throw new InvalidArgumentException(__('Pembayaran wajib diisi saat ubah ke Release.'));
            }
            $paymentSum = $this->sumPayments($payments);
            if ($paymentSum < 0.01) {
                throw new InvalidArgumentException(__('Pembayaran minimal Rp 0,01.'));
            }
        } else {
            $paymentSum = 0.0;
        }
        $materialTotal = $materialsTotalPrice !== null
            ? (float) $materialsTotalPrice
            : (float) $service->materials_total_price;
        $totalPrice = $servicePrice + $materialTotal;
        if ($paymentSum > $totalPrice + 0.02) {
            throw new InvalidArgumentException(__('Total pembayaran tidak boleh melebihi total service.'));
        }

        $branch = Branch::findOrFail($service->branch_id);
        $isPaidOff = $markAsReleased && $paymentSum >= $totalPrice - 0.02;

        return DB::transaction(function () use (
            $service,
            $branch,
            $customerId,
            $laptopType,
            $laptopDetail,
            $damageDescription,
            $serviceCost,
            $servicePrice,
            $entryDate,
            $payments,
            $paymentSum,
            $isPaidOff,
            $description,
            $markAsReleased
        ) {
            $oldPayments = ServicePayment::where('service_id', $service->id)->get();
            $oldTotal = $oldPayments->sum('amount');

            $updates = [
                'customer_id' => $customerId,
                'laptop_type' => $laptopType,
                'laptop_detail' => $laptopDetail,
                'damage_description' => $damageDescription,
                'service_cost' => $serviceCost,
                'service_price' => $servicePrice,
                'total_paid' => $paymentSum,
                'entry_date' => $entryDate,
                'payment_status' => $isPaidOff ? Service::PAYMENT_LUNAS : Service::PAYMENT_BELUM_LUNAS,
                'description' => $description,
            ];
            if ($markAsReleased) {
                $updates['status'] = Service::STATUS_COMPLETED;
                $updates['exit_date'] = $entryDate;
            }
            $service->update($updates);

            ServicePayment::where('service_id', $service->id)->delete();
            foreach ($payments as $p) {
                $amt = round((float) ($p['amount'] ?? 0), 2);
                if ($amt <= 0) {
                    continue;
                }
                ServicePayment::create([
                    'service_id' => $service->id,
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => $amt,
                    'notes' => $p['notes'] ?? null,
                ]);
            }

            CashFlow::where('reference_type', CashFlow::REFERENCE_SERVICE)
                ->where('reference_id', $service->id)
                ->delete();

            foreach (ServicePayment::with('paymentMethod')->where('service_id', $service->id)->get() as $sp) {
                $pm = $sp->paymentMethod;
                $pmLabel = $pm ? $pm->display_label : __('Payment');

                CashFlow::create([
                    'branch_id' => $branch->id,
                    'type' => CashFlow::TYPE_IN,
                    'amount' => $sp->amount,
                    'description' => __('Service') . ' ' . $service->invoice_number . ' - ' . $pmLabel,
                    'reference_type' => CashFlow::REFERENCE_SERVICE,
                    'reference_id' => $service->id,
                    'payment_method_id' => $sp->payment_method_id,
                    'transaction_date' => $entryDate,
                    'user_id' => auth()->id(),
                ]);
            }

            return $service->fresh()->load(['payments.paymentMethod', 'branch', 'user', 'customer']);
        });
    }

    /**
     * Add payment (pelunasan) and optionally complete service.
     */
    public function addPayment(
        Service $service,
        array $payments,
        ?string $exitDate = null,
        bool $markCompleted = false,
        bool $markPickedUp = false,
        ?int $userId = null
    ): Service {
        if ($service->status !== Service::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Service sudah completed atau dibatalkan.'));
        }

        if (empty($payments)) {
            throw new InvalidArgumentException(__('Pembayaran wajib diisi.'));
        }

        $newSum = $this->sumPayments($payments);
        if ($newSum < 0.01) {
            throw new InvalidArgumentException(__('Nominal pembayaran minimal Rp 0,01.'));
        }

        $branch = Branch::findOrFail($service->branch_id);
        $totalPrice = (float) $service->total_service_price;
        $currentPaid = (float) $service->total_paid;
        $totalPaid = $currentPaid + $newSum;

        if ($totalPaid > $totalPrice + 0.02) {
            throw new InvalidArgumentException(
                __('Total pembayaran tidak boleh melebihi total service.')
            );
        }

        return DB::transaction(function () use (
            $service,
            $branch,
            $payments,
            $newSum,
            $totalPaid,
            $totalPrice,
            $exitDate,
            $markCompleted,
            $markPickedUp,
            $userId
        ) {
            foreach ($payments as $p) {
                $amt = round((float) ($p['amount'] ?? 0), 2);
                if ($amt <= 0) {
                    continue;
                }
                ServicePayment::create([
                    'service_id' => $service->id,
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => $amt,
                    'notes' => $p['notes'] ?? null,
                ]);
            }

            $txDate = $exitDate ?? $service->entry_date->toDateString();
            foreach ($payments as $p) {
                $amt = round((float) ($p['amount'] ?? 0), 2);
                if ($amt <= 0) {
                    continue;
                }
                $pm = \App\Models\PaymentMethod::find($p['payment_method_id'] ?? 0);
                $pmLabel = $pm ? $pm->display_label : __('Payment');

                CashFlow::create([
                    'branch_id' => $branch->id,
                    'type' => CashFlow::TYPE_IN,
                    'amount' => $amt,
                    'description' => __('Service') . ' ' . $service->invoice_number . ' - ' . $pmLabel,
                    'reference_type' => CashFlow::REFERENCE_SERVICE,
                    'reference_id' => $service->id,
                    'payment_method_id' => (int) ($p['payment_method_id'] ?? 0),
                    'transaction_date' => $txDate,
                    'user_id' => $userId ?? auth()->id(),
                ]);
            }

            $updates = [
                'total_paid' => $totalPaid,
                'payment_status' => $markCompleted && $totalPaid >= $totalPrice - 0.02 ? Service::PAYMENT_LUNAS : Service::PAYMENT_BELUM_LUNAS,
            ];
            if ($exitDate) {
                $updates['exit_date'] = $exitDate;
            }
            if ($markCompleted) {
                $updates['status'] = Service::STATUS_COMPLETED;
            }
            if ($markPickedUp) {
                $updates['pickup_status'] = Service::PICKUP_SUDAH;
            }

            $service->update($updates);

            return $service->fresh()->load(['payments.paymentMethod', 'branch', 'user', 'customer']);
        });
    }

    /**
     * Mark service as completed and/or picked up.
     */
    public function complete(
        Service $service,
        ?string $exitDate = null,
        bool $markPickedUp = false
    ): Service {
        if ($service->status !== Service::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Service sudah completed atau dibatalkan.'));
        }

        $updates = [
            'status' => Service::STATUS_COMPLETED,
            'exit_date' => $exitDate ?? now()->toDateString(),
        ];
        if ($markPickedUp) {
            $updates['pickup_status'] = Service::PICKUP_SUDAH;
        }

        $service->update($updates);

        return $service->fresh()->load(['payments.paymentMethod', 'branch', 'user', 'customer']);
    }

    /**
     * Mark as picked up.
     */
    public function markPickedUp(Service $service): Service
    {
        $service->update(['pickup_status' => Service::PICKUP_SUDAH]);
        return $service->fresh();
    }

    /**
     * Cancel service.
     */
    public function cancel(Service $service, int $userId, string $reason): Service
    {
        if (! in_array($service->status, [Service::STATUS_OPEN, Service::STATUS_COMPLETED], true)) {
            throw new InvalidArgumentException(__('Hanya service open atau selesai yang bisa dibatalkan.'));
        }

        return DB::transaction(function () use ($service, $userId, $reason) {
            $refundDate = now()->toDateString();
            $payments = ServicePayment::with('paymentMethod')->where('service_id', $service->id)->get();
            foreach ($payments as $sp) {
                $pmLabel = $sp->paymentMethod?->display_label ?? __('Payment');
                CashFlow::create([
                    'branch_id' => $service->branch_id,
                    'type' => CashFlow::TYPE_OUT,
                    'amount' => $sp->amount,
                    'description' => __('Cancel Service') . ' ' . $service->invoice_number . ' - ' . $pmLabel,
                    'reference_type' => CashFlow::REFERENCE_SERVICE,
                    'reference_id' => $service->id,
                    'payment_method_id' => $sp->payment_method_id,
                    'transaction_date' => $refundDate,
                    'user_id' => $userId ?? auth()->id(),
                ]);
            }

            ServicePayment::where('service_id', $service->id)->delete();
            $service->update([
                'status' => Service::STATUS_CANCEL,
                'cancel_date' => now()->toDateString(),
                'cancel_user_id' => $userId,
                'cancel_reason' => $reason,
            ]);

            return $service->fresh();
        });
    }

    private function sumPayments(array $payments): float
    {
        $sum = 0.0;
        foreach ($payments as $p) {
            $amt = (float) ($p['amount'] ?? 0);
            if ($amt > 0) {
                $sum += $amt;
            }
        }
        return round($sum, 2);
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'SRV-' . date('Ymd') . '-';
        $last = Service::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();
        $seq = $last ? (int) substr($last->invoice_number, -4) + 1 : 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
