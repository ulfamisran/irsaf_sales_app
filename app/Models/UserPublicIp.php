<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPublicIp extends Model
{
    public const ANY = '*';

    protected $fillable = [
        'user_id',
        'ip_address',
        'note',
        'created_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAnyDevice(): bool
    {
        return $this->ip_address === self::ANY;
    }
}
