<?php

namespace App\Jobs;

use App\Models\AuthorizationRequest;
use App\Services\WebPushService;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

class SendAuthorizationPushNotification
{
    use Dispatchable;

    public function __construct(public int $authorizationRequestId) {}

    public function handle(WebPushService $webPush): void
    {
        $request = AuthorizationRequest::query()->find($this->authorizationRequestId);
        if (! $request || $request->status !== AuthorizationRequest::STATUS_PENDING) {
            return;
        }

        try {
            $webPush->notifyAuthorizationSubmitted($request);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
