<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Logis\LogisContext\Infrastructure\Persistence\AccessToken\AccessTokenModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;

final class CurrentAccessToken
{
    public static function of(Request $request): AccessTokenModel
    {
        $accessToken = self::find(request: $request);

        if ($accessToken === null) {
            throw new AuthenticationException();
        }

        return $accessToken;
    }

    public static function find(Request $request): ?AccessTokenModel
    {
        $user = $request->user();

        if (!$user instanceof UserModel) {
            return null;
        }

        $accessToken = $user->currentAccessToken();

        if (!$accessToken instanceof AccessTokenModel) {
            return null;
        }

        return $accessToken;
    }
}
