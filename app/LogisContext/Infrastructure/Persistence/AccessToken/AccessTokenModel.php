<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\AccessToken;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property int $id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $token
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property Carbon $expires_at
 * @property ?Carbon $rotated_at
 * @property ?string $rotated_from
 */
final class AccessTokenModel extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'ip_address',
        'user_agent',
        'expires_at',
        'rotated_at',
        'rotated_from',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'rotated_at' => 'datetime',
    ];
}
