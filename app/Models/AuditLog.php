<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string|null $actor_email
 * @property string $action
 * @property string $target_type
 * @property int|null $target_id
 * @property string|null $target_label
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $request_id
 * @property CarbonImmutable $occurred_at
 * @property-read User|null $actor
 */
class AuditLog extends Model
{
    use AppendOnly;

    /**
     * The model is written once and never updated; there is no updated_at column.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_id',
        'actor_email',
        'action',
        'target_type',
        'target_id',
        'target_label',
        'metadata',
        'ip_address',
        'request_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
