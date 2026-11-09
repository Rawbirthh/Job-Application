<?php

namespace App\Models;

use App\Enums\CompanyMemberRole;
use App\Enums\CompanyMemberStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CompanyMemberRole $company_role
 * @property CompanyMemberStatus $status
 */
class CompanyMember extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'company_role',
        'status',
        'invited_by_user_id',
        'invited_at',
        'accepted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'company_role' => CompanyMemberRole::class,
            'status' => CompanyMemberStatus::class,
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
