<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    use AuthorizesRequests;

    public function markAsRead(DatabaseNotification $notification): RedirectResponse
    {
        $this->authorize('view', $notification);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $data = $notification->data;
        $url = $data['url'] ?? null;

        $isSafeRelative = is_string($url) && $url !== ''
            && str_starts_with($url, '/') && ! str_starts_with($url, '//');

        $parsedHost = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
        $isSafeAbsolute = is_string($url) && $url !== ''
            && is_string($parsedHost) && $parsedHost === request()->getHost();

        if ($isSafeRelative || $isSafeAbsolute) {
            return redirect()->to($url);
        }

        return redirect()->back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->back();
    }
}
