<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Logis\LogisContext\Application\Command\LogIn\LogIn;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;

final class LoginRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function toCommand(PlainTextToken $plainTextToken): LogIn
    {
        return new LogIn(
            email: $this->string(key: 'email')->toString(),
            password: $this->string(key: 'password')->toString(),
            tokenHash: $plainTextToken->hash()->value(),
            ipAddress: (string) $this->ip(),
            userAgent: (string) $this->userAgent(),
        );
    }
}
