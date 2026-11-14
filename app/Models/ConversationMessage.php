<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A single chat message. Soft-deleted messages become tombstones: the row is
 * retained (pagination and the per-participant read cursor stay aligned) but
 * the body is destroyed server-side.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string|null $body
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ApplicationConversation $conversation
 * @property-read User $sender
 */
class ConversationMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
