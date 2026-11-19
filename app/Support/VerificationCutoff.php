<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;

class VerificationCutoff
{
    public const KEY = 'verification_cutoff_date';

    public static function date(): ?string
    {
        if (! Schema::hasTable('app_settings')) {
            return null;
        }

        $value = AppSetting::query()->where('key', self::KEY)->value('value');
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    public static function save(?string $date): void
    {
        if ($date === null || $date === '') {
            AppSetting::query()->where('key', self::KEY)->delete();

            return;
        }

        AppSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => $date]
        );
    }
}
