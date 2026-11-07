<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class UserRegisteredNotification extends Notification
{
    public function __construct(public User $user) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'user.registered',
            'title' => __('New user registered'),
            'message' => __(':name (:email) has registered as a job seeker.', [
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'url' => route('admin.users.index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
