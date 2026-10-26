<?php

namespace App\Http\Requests\Auth;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest as FortifySendPasswordResetLinkRequest;

class SendPasswordResetLinkRequest extends FortifySendPasswordResetLinkRequest
{
    /**
     * The maximum number of reset-link requests allowed per minute.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * The number of seconds before the rate limit window resets.
     */
    private const DECAY_SECONDS = 60;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @throws ThrottleRequestsException
     */
    public function authorize(): bool
    {
        $email = $this->input('email');
        $key = $this->throttleKey(is_string($email) ? $email : '');

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new ThrottleRequestsException(
                'Too Many Attempts.',
                null,
                ['Retry-After' => RateLimiter::availableIn($key)],
            );
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        return true;
    }

    /**
     * Build the rate-limiting key for the request.
     */
    private function throttleKey(string $email): string
    {
        return 'password-reset|'.Str::transliterate(Str::lower($email).'|'.(string) $this->ip());
    }
}
