<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    public const LOGIN = 'login';

    public const LOGIN_FAILED = 'login_failed';

    public const LOGOUT = 'logout';

    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    public const CANCEL = 'cancel';

    public const ACTIVATE = 'activate';

    public const DEACTIVATE = 'deactivate';

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'description',
        'route_name',
        'http_method',
        'reference_type',
        'reference_id',
        'ip_address',
        'user_agent',
        'occurred_at',
        'source',
        'source_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
