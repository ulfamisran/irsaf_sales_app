<?php

namespace App\Models\Concerns;

use App\Support\ClientIp;

trait RecordsClientDevice
{
    public static function bootRecordsClientDevice(): void
    {
        static::creating(function ($model): void {
            if (app()->runningInConsole()) {
                return;
            }

            $request = request();
            if ($request === null) {
                return;
            }

            if (empty($model->client_ip)) {
                $model->client_ip = ClientIp::resolve($request);
            }
            if (empty($model->client_user_agent)) {
                $agent = trim((string) $request->userAgent());
                $model->client_user_agent = $agent !== '' ? mb_substr($agent, 0, 500) : null;
            }
        });
    }
}
