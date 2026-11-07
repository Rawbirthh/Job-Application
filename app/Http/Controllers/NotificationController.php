<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->select(['id', 'type', 'data', 'read_at', 'created_at'])
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('notifications/index', [
            'notifications' => $notifications->through(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data ?? [],
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'prev_page_url' => $notifications->previousPageUrl(),
                'next_page_url' => $notifications->nextPageUrl(),
            ],
        ]);
    }

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
