<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserActivityHistoryImporter
{
    public function import(): int
    {
        if (! Schema::hasTable('user_activity_logs')) {
            return 0;
        }

        $inserted = 0;
        $inserted += $this->importAuditLogs();
        $inserted += $this->importLoginLogs();
        $inserted += $this->importVerifications();
        $inserted += $this->importReceivingLogs();
        $inserted += $this->importUnitChanges();

        return $inserted;
    }

    private function importAuditLogs(): int
    {
        if (! Schema::hasTable('audit_logs')) {
            return 0;
        }

        $count = 0;
        DB::table('audit_logs')->orderBy('id')->chunkById(400, function ($rows) use (&$count) {
            $payload = [];
            foreach ($rows as $row) {
                [$action, $module] = $this->mapAudit((string) $row->action);
                $payload[] = $this->row(
                    'audit_logs',
                    (int) $row->id,
                    $row->user_id,
                    $action,
                    $module,
                    $row->description ?: (string) $row->action,
                    $row->reference_type,
                    $row->reference_id,
                    null,
                    null,
                    $row->created_at
                );
            }
            $count += $this->insert($payload);
        });

        return $count;
    }

    private function importLoginLogs(): int
    {
        if (! Schema::hasTable('login_logs')) {
            return 0;
        }

        $count = 0;
        DB::table('login_logs')->orderBy('id')->chunkById(400, function ($rows) use (&$count) {
            $payload = [];
            foreach ($rows as $row) {
                $payload[] = $this->row(
                    'login_logs',
                    (int) $row->id,
                    $row->user_id,
                    'login',
                    'auth',
                    'Masuk ke sistem',
                    null,
                    null,
                    $row->ip_publik,
                    $row->user_agent,
                    $row->logged_in_at ?: $row->created_at
                );
            }
            $count += $this->insert($payload);
        });

        return $count;
    }

    private function importVerifications(): int
    {
        if (! Schema::hasTable('transaction_verifications')) {
            return 0;
        }

        $count = 0;
        DB::table('transaction_verifications')->orderBy('id')->chunkById(400, function ($rows) use (&$count) {
            $payload = [];
            foreach ($rows as $row) {
                $result = (int) $row->flag_rejected === 1 ? 'Tolak verifikasi' : 'Terima verifikasi';
                $payload[] = $this->row(
                    'transaction_verifications',
                    (int) $row->id,
                    $row->user_id,
                    'update',
                    'verification',
                    trim($result.' '.($row->document_number ?: '')),
                    $row->reference_type,
                    $row->reference_id,
                    $row->client_ip,
                    null,
                    $row->verified_at ?: $row->created_at
                );
            }
            $count += $this->insert($payload);
        });

        return $count;
    }

    private function importReceivingLogs(): int
    {
        if (! Schema::hasTable('purchase_receiving_logs')) {
            return 0;
        }

        $count = 0;
        DB::table('purchase_receiving_logs as logs')
            ->leftJoin('purchases', 'purchases.id', '=', 'logs.purchase_id')
            ->orderBy('logs.id')
            ->select('logs.*', 'purchases.invoice_number')
            ->chunkById(400, function ($rows) use (&$count) {
                $payload = [];
                foreach ($rows as $row) {
                    $result = match ($row->result) {
                        'baik' => 'Baik',
                        'reture' => 'Retur',
                        'pending' => 'Pending',
                        default => (string) $row->result,
                    };
                    $payload[] = $this->row(
                        'purchase_receiving_logs',
                        (int) $row->id,
                        $row->checked_by,
                        'update',
                        'purchase-receiving',
                        trim('Cek pembelian '.($row->invoice_number ?: '#'.$row->purchase_id).' · '.$result.' · '.$row->qty),
                        'purchase',
                        $row->purchase_id,
                        null,
                        null,
                        $row->checked_at ?: $row->created_at
                    );
                }
                $count += $this->insert($payload);
            }, 'logs.id', 'id');

        return $count;
    }

    private function importUnitChanges(): int
    {
        if (! Schema::hasTable('product_unit_change_logs')) {
            return 0;
        }

        $count = 0;
        DB::table('product_unit_change_logs')->orderBy('id')->chunkById(400, function ($rows) use (&$count) {
            $payload = [];
            foreach ($rows as $row) {
                $serial = $row->serial_snapshot ? ' · '.$row->serial_snapshot : '';
                $reason = $row->reason ? ' · '.$row->reason : '';
                $payload[] = $this->row(
                    'product_unit_change_logs',
                    (int) $row->id,
                    $row->user_id,
                    'update',
                    'products',
                    trim('Ubah unit'.$serial.$reason),
                    'product_unit',
                    $row->product_unit_id,
                    null,
                    null,
                    $row->changed_at ?: $row->created_at
                );
            }
            $count += $this->insert($payload);
        });

        return $count;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function mapAudit(string $action): array
    {
        return match ($action) {
            'product.create' => ['create', 'products'],
            'product.update', 'product_unit.update' => ['update', 'products'],
            'purchase.create' => ['create', 'purchases'],
            'purchase.update' => ['update', 'purchases'],
            'incoming_goods.create' => ['create', 'incoming-goods'],
            'sale.cancel' => ['cancel', 'sales'],
            'sale.update', 'sale.update_hpp' => ['update', 'sales'],
            'service.update' => ['update', 'services'],
            'service.cancel' => ['cancel', 'services'],
            'rental.update' => ['update', 'rentals'],
            'rental.cancel' => ['cancel', 'rentals'],
            'mutasi_dana.cancel' => ['cancel', 'mutasi-dana'],
            'stock_mutation.cancel' => ['cancel', 'stock-mutations'],
            default => [str_contains($action, 'cancel') ? 'cancel' : (str_contains($action, 'update') ? 'update' : 'create'), null],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $source,
        int $sourceId,
        mixed $userId,
        string $action,
        ?string $module,
        string $description,
        mixed $referenceType,
        mixed $referenceId,
        mixed $ip,
        mixed $agent,
        mixed $occurredAt
    ): array {
        $now = now();

        return [
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'description' => mb_substr(trim($description), 0, 500),
            'route_name' => null,
            'http_method' => null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'ip_address' => $ip ? mb_substr((string) $ip, 0, 45) : null,
            'user_agent' => $agent ? mb_substr((string) $agent, 0, 500) : null,
            'occurred_at' => $occurredAt ?: $now,
            'source' => $source,
            'source_id' => $sourceId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return DB::table('user_activity_logs')->insertOrIgnore($rows);
    }
}
