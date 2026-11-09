<?php

namespace App\Notifications;

use App\Models\SavedSearch;
use Illuminate\Notifications\Notification;

class SavedSearchMatchedNotification extends Notification
{
    /**
     * @param  array<int, array{id: int, title: string, company: string|null}>  $jobs
     */
    public function __construct(public SavedSearch $search, public array $jobs) {}

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
        $titles = collect($this->jobs)
            ->take(3)
            ->pluck('title')
            ->implode(', ');

        if (count($this->jobs) > 3) {
            $titles .= '…';
        }

        return [
            'event' => 'saved-search.new_jobs',
            'title' => __('New jobs matching your search'),
            'message' => __(':count new jobs match :what. Top: :titles', [
                'count' => count($this->jobs),
                'what' => $this->search->name ?: __('your saved search'),
                'titles' => $titles,
            ]),
            'url' => route('jobs.index', $this->search->criteria()),
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
