<?php

namespace App\Rules;

use App\Services\Security\TurnstileVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class TurnstileToken implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $token = is_string($value) ? $value : null;

        if (! app(TurnstileVerifier::class)->verify($token, request()->ip())) {
            $fail('We could not verify that you are a person. Please try again.');
        }
    }
}
