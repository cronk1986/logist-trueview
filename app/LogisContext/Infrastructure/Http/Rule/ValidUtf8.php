<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Rule;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidUtf8 implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string(value: $value) && !mb_check_encoding(value: $value, encoding: 'UTF-8')) {
            $fail('The :attribute field must be valid UTF-8 text.');
        }
    }
}
