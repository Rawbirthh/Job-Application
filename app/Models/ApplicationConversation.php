<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One private conversation per job application.
 *
 * @property int $id
 * @property int $application_id
 * @property int|null $last_message_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $sort_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JobApplication $application
 * @property-read ConversationMessage|null $latestMessage
 */
class ApplicationConversation extends Model
{
    protected $fillable = [
        'application_id',
        'last_message_id',
        'last_message_at',
        'sort_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'sort_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'application_id');
    }

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_id');
    }

    /**
     * The newest non-deleted message, used for the inbox preview.
     *
     * @return HasOne<ConversationMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class, 'conversation_id')
            ->ofMany('id', 'max');
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }
}
