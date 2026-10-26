<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;

class GenericFailedPasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponseContract
{
    /**
     * Create a new response instance.
     *
     * The Fortify failure status is intentionally ignored when rendering so an
     * unknown email cannot be distinguished from a successful request.
     */
    public function __construct(
        protected readonly string $status,
    ) {}

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        $message = trans('passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}
