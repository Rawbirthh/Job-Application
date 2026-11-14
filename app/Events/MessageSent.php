<?php

namespace App\Events;

use App\Models\ConversationMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ConversationMessage $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversation.'.$this->message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array{message: array{id: int, conversation_id: int, sender_id: int, sender_name: string, body: string|null, created_at: string|null}}
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender:id,name');

        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'sender_name' => $this->message->sender->name,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
        ];
    }
}
