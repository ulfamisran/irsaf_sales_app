<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPublicIp;
use Illuminate\Validation\ValidationException;

class UserPublicIpAccess
{
    public function allows(User $user, ?string $ip): bool
    {
        $allowed = $user->publicIps()->pluck('ip_address');
        if ($allowed->contains(UserPublicIp::ANY)) {
            return true;
        }

        $ip = $this->normalize($ip);
        if ($ip === null) {
            return false;
        }

        foreach ($allowed as $candidate) {
            if ($this->same($candidate, $ip)) {
                return true;
            }
        }

        return false;
    }

    public function register(User $user, string $ipAddress, ?string $note, ?int $actorId): UserPublicIp
    {
        $ipAddress = trim($ipAddress);
        if ($ipAddress !== UserPublicIp::ANY && filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw ValidationException::withMessages([
                'ip_address' => __('Isi alamat IP publik yang valid, atau * untuk semua perangkat.'),
            ]);
        }

        if ($ipAddress === UserPublicIp::ANY) {
            $user->publicIps()->where('ip_address', '!=', UserPublicIp::ANY)->delete();
        } else {
            $user->publicIps()->where('ip_address', UserPublicIp::ANY)->delete();
        }

        $note = trim((string) $note);

        return $user->publicIps()->updateOrCreate(
            ['ip_address' => $ipAddress],
            [
                'note' => $note !== '' ? mb_substr($note, 0, 100) : null,
                'created_by' => $actorId,
            ]
        );
    }

    private function normalize(?string $ip): ?string
    {
        $ip = trim((string) $ip);

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    private function same(string $allowed, string $current): bool
    {
        $left = @inet_pton($allowed);
        $right = @inet_pton($current);

        return $left !== false && $right !== false && $left === $right;
    }
}
