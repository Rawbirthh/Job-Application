<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-user read cursor for a conversation. This table is used only to track
 * how far each participant has read; authorization is never derived from it
 * (see ConversationPolicy).
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property int|null $last_read_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConversationParticipant extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_read_message_id',
    ];

    /**
     * @return BelongsTo<ApplicationConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ApplicationConversation::class, 'conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<ConversationMessage, $this>
     */
    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'last_read_message_id');
    }
}
