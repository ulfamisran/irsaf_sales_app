<?php

namespace App\Http\Controllers;

use App\Services\AuthorizationService;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function __construct(
        private WebPushService $webPush,
        private AuthorizationService $authorizations,
    ) {}

    public function vapidPublicKey(): JsonResponse
    {
        abort_unless($this->webPush->enabled(), 503);

        return response()->json([
            'publicKey' => $this->webPush->publicKey(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->authorizations->canOpenQueue($user), 403);
        abort_unless($this->webPush->enabled(), 503);

        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        $this->webPush->saveSubscription($user, $validated);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->authorizations->canOpenQueue($user), 403);

        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $this->webPush->deleteSubscription($user, $validated['endpoint']);

        return response()->json(['ok' => true]);
    }
}
