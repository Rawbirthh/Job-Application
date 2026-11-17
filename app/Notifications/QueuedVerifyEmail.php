<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Verification email dispatched through the queue (database driver locally)
 * instead of inline with the request, so registration and resend respond
 * immediately. Same template and signed link as the framework notification.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue {}
