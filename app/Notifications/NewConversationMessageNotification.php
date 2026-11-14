<?php

namespace App\Notifications;

use App\Models\ConversationMessage;
use Illuminate\Notifications\Notification;

class NewConversationMessageNotification extends Notification
{
    public function __construct(public ConversationMessage $message) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Payload deliberately contains no message body — only the sender, the
     * job title, and a link to the conversation (safe and concise).
     *
     * @return array{event: string, title: string, message: string, url: string, conversation_id: int}
     */
    public static function dataFor(ConversationMessage $message): array
    {
        $message->loadMissing(['sender:id,name', 'conversation.application.jobPosting:id,title']);

        $conversation = $message->conversation;
        $jobTitle = $conversation->application->jobPosting->title;

        return [
            'event' => 'message.received',
            'title' => __('New message'),
            'message' => __(':sender sent you a message about :job.', [
                'sender' => $message->sender->name,
                'job' => $jobTitle,
            ]),
            'url' => route('messages.show', ['conversation' => $conversation->id]),
            'conversation_id' => $conversation->id,
        ];
    }

    /**
     * @return array{event: string, title: string, message: string, url: string, conversation_id: int}
     */
    public function toDatabase(object $notifiable): array
    {
        return self::dataFor($this->message);
    }

    /**
     * @return array{event: string, title: string, message: string, url: string, conversation_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
