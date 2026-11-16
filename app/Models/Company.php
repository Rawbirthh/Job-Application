<?php

namespace App\Models;

use App\Enums\CompanyMemberRole;
use App\Enums\CompanyMemberStatus;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'overview',
        'industry',
        'website',
        'size_band',
        'location',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<CompanyMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(CompanyMember::class);
    }

    /**
     * @return HasMany<CompanyMember, $this>
     */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(CompanyMember::class)->where('status', 'active');
    }

    /**
     * Scope a query to companies owned by the given user.
     *
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeForOwner(Builder $query, User $owner): Builder
    {
        return $query->where('owner_user_id', $owner->id);
    }

    /**
     * Scope a query to companies managed by the given user (owned or active member).
     *
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeForManagingUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('owner_user_id', $user->id)
                ->orWhereHas('members', function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->where('status', 'active');
                });
        });
    }

    /**
     * Scope a query to active companies.
     *
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to companies where the given user holds the given company-scoped permission.
     *
     * The role -> permission mapping is resolved from the CompanyMemberRole enum so it stays a
     * single source of truth, and it is applied in SQL so listing the authorized companies does
     * not require a permission lookup per company.
     *
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeForUserWithPermission(Builder $query, User $user, string $permission): Builder
    {
        $roles = collect(CompanyMemberRole::cases())
            ->filter(fn (CompanyMemberRole $role): bool => in_array($permission, $role->permissions(), true))
            ->map(fn (CompanyMemberRole $role): string => $role->value)
            ->values()
            ->all();

        if ($roles === []) {
            return $query;
        }

        $ownerAllowed = in_array($permission, CompanyMemberRole::Owner->permissions(), true);

        return $query->where(function (Builder $query) use ($user, $roles, $ownerAllowed): void {
            if ($ownerAllowed) {
                $query->where('owner_user_id', $user->id);
            }

            $members = function (Builder $members) use ($user, $roles): void {
                $members->where('user_id', $user->id)
                    ->where('status', CompanyMemberStatus::Active->value)
                    ->whereIn('company_role', $roles);
            };

            if ($ownerAllowed) {
                $query->orWhereHas('members', $members);
            } else {
                $query->whereHas('members', $members);
            }
        });
    }

    /**
     * Get the public URL for the company logo, if any.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo_path === null || $this->logo_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }
}
