<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 */
class EmployerOnboardingRequest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_name',
        'company_website',
        'job_title',
        'message',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_id',
        'review_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
