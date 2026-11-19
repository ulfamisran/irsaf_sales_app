<?php

namespace App\Services;

use App\Models\AuthorizationAssignee;
use App\Models\AuthorizationRequest;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    public function enabled(): bool
    {
        return Schema::hasTable('push_subscriptions')
            && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    public function publicKey(): ?string
    {
        $key = config('webpush.vapid.public_key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * @param  array{endpoint: string, keys?: array{p256dh?: string, auth?: string}, contentEncoding?: string}  $payload
     */
    public function saveSubscription(User $user, array $payload): PushSubscription
    {
        $endpoint = (string) ($payload['endpoint'] ?? '');
        if ($endpoint === '') {
            throw new \InvalidArgumentException(__('Endpoint push tidak valid.'));
        }

        return PushSubscription::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
            ],
            [
                'public_key' => $payload['keys']['p256dh'] ?? null,
                'auth_token' => $payload['keys']['auth'] ?? null,
                'content_encoding' => $payload['contentEncoding'] ?? 'aesgcm',
            ]
        );
    }

    public function deleteSubscription(User $user, string $endpoint): void
    {
        PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('endpoint', $endpoint)
            ->delete();
    }

    public function notifyAuthorizationSubmitted(AuthorizationRequest $request): void
    {
        if (! $this->enabled()) {
            return;
        }

        $userIds = $this->recipientUserIds($request);
        if ($userIds === []) {
            return;
        }

        $moduleLabel = AuthorizationService::MODULES[$request->module]['label'] ?? $request->module;
        $url = route('authorizations.index', [
            'tab' => 'siap',
            'module' => $request->module,
        ]);

        $this->sendToUsers($userIds, [
            'title' => __('Otorisasi baru'),
            'body' => $moduleLabel.' · '.$request->document_number,
            'url' => $url,
            'module' => $request->module,
            'document' => $request->document_number,
            'request_id' => $request->id,
        ]);
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function sendToUsers(array $userIds, array $payload): void
    {
        if (! $this->enabled() || $userIds === []) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $userIds)
            ->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('webpush.vapid.subject'),
                    'publicKey' => (string) config('webpush.vapid.public_key'),
                    'privateKey' => (string) config('webpush.vapid.private_key'),
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('WebPush init failed: '.$e->getMessage());

            return;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        foreach ($subscriptions as $row) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $row->endpoint,
                        'publicKey' => $row->public_key,
                        'authToken' => $row->auth_token,
                        'contentEncoding' => $row->content_encoding ?: 'aesgcm',
                    ]),
                    $json ?: '{}'
                );
            } catch (Throwable $e) {
                Log::warning('WebPush queue failed: '.$e->getMessage());
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }
            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()->where('endpoint', $report->getEndpoint())->delete();
            } else {
                Log::info('WebPush failed: '.$report->getReason());
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function recipientUserIds(AuthorizationRequest $request): array
    {
        // Hanya user yang dipilih sebagai otorisator modul ini.
        $ids = AuthorizationAssignee::query()
            ->where('module', $request->module)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($request->requested_by) {
            $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $request->requested_by));
        }

        return $ids;
    }
}
