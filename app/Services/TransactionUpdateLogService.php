<?php

namespace App\Services;

use App\Models\TransactionUpdateLog;
use Carbon\Carbon;

class TransactionUpdateLogService
{
    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function log(
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber,
        int $userId,
        string $reason,
        array $changes
    ): TransactionUpdateLog {
        return TransactionUpdateLog::create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_number' => $referenceNumber,
            'user_id' => $userId,
            'updated_at_action' => Carbon::now(),
            'reason' => $reason,
            'changes' => $changes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function diff(array $old, array $new): array
    {
        $changes = [];
        foreach ($new as $key => $newVal) {
            $oldVal = $old[$key] ?? null;
            if ($this->normalize($oldVal) !== $this->normalize($newVal)) {
                $changes[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        return $changes;
    }

    private function normalize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value) || (is_numeric($value) && str_contains((string) $value, '.'))) {
            return number_format((float) $value, 2, '.', '');
        }

        return trim((string) $value);
    }
}
