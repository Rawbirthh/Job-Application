<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'roles' => $user instanceof User ? $user->roleSlugs() : [],
                'permissions' => $user instanceof User ? $user->permissionSlugs() : [],
                'employer_status' => $user instanceof User ? $user->status?->value : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notifications_unread_count' => $user instanceof User
                ? $user->unreadNotifications()->count()
                : 0,
            'notifications_recent' => $user instanceof User
                ? $this->recentNotifications($user)
                : [],
        ];
    }

    /**
     * Latest 10 notifications for the header bell panel. Bounded select;
     * the notifications table is indexed on the notifiable morph columns.
     *
     * @return array<int, array{id: string, type: string, data: array<string, mixed>, read_at: string|null, created_at: string|null}>
     */
    private function recentNotifications(User $user): array
    {
        return $user->notifications()
            ->latest()
            ->select(['id', 'type', 'data', 'read_at', 'created_at'])
            ->limit(10)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data ?? [],
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
