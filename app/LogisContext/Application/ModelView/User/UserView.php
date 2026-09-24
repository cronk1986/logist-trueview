<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\User;

use Logis\LogisContext\Domain\Model\User\User;

final readonly class UserView
{
    private function __construct(public int $id, public string $name, public string $email)
    {
    }

    public static function from(User $user): self
    {
        return new self(id: $user->id()->value(), name: $user->name(), email: $user->email()->value());
    }

    /** @return array{id: int, name: string, email: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
