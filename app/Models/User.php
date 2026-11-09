<?php

namespace App\Models;

use App\Enums\CompanyMemberRole;
use App\Enums\CompanyMemberStatus;
use App\Enums\EmployerStatus;
use App\Enums\RoleSlug;
use App\Models\Concerns\HasRolesAndPermissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property EmployerStatus|null $status
 * @property-read EmployerOnboardingRequest|null $employerOnboardingRequest
 */
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRolesAndPermissions, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the user's employer status, tolerating legacy empty/invalid values.
     *
     * @return Attribute<EmployerStatus|null, EmployerStatus|string|null>
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null || $value === ''
                ? null
                : EmployerStatus::tryFrom((string) $value),
            set: fn ($value) => $value instanceof EmployerStatus ? $value->value : $value,
        );
    }

    /**
     * @return HasOne<EmployerOnboardingRequest, $this>
     */
    public function employerOnboardingRequest(): HasOne
    {
        return $this->hasOne(EmployerOnboardingRequest::class);
    }

    /**
     * @return HasOne<CandidateProfile, $this>
     */
    public function candidateProfile(): HasOne
    {
        return $this->hasOne(CandidateProfile::class);
    }

    /**
     * @return HasOne<CandidateResume, $this>
     */
    public function candidateResume(): HasOne
    {
        return $this->hasOne(CandidateResume::class);
    }

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_user_id');
    }

    /**
     * @return HasMany<CompanyMember, $this>
     */
    public function companyMemberships(): HasMany
    {
        return $this->hasMany(CompanyMember::class, 'user_id');
    }

    /**
     * @return HasMany<SavedJob, $this>
     */
    public function savedJobs(): HasMany
    {
        return $this->hasMany(SavedJob::class);
    }

    /**
     * @return HasMany<ScreeningQuestion, $this>
     */
    public function screeningQuestions(): HasMany
    {
        return $this->hasMany(ScreeningQuestion::class, 'owner_user_id');
    }

    /**
     * @return HasMany<ScreeningQuestionTemplate, $this>
     */
    public function screeningQuestionTemplates(): HasMany
    {
        return $this->hasMany(ScreeningQuestionTemplate::class, 'owner_user_id');
    }

    /**
     * Determine whether the user holds the employer role and is approved.
     */
    public function isApprovedEmployer(): bool
    {
        return $this->status === EmployerStatus::Approved
            && $this->hasRole(RoleSlug::Employer);
    }

    /**
     * Resolve the user's company-scoped role for the given company.
     */
    public function companyRole(?Company $company): ?string
    {
        if ($company === null) {
            return null;
        }

        if ((int) $company->owner_user_id === (int) $this->id) {
            return CompanyMemberRole::Owner->value;
        }

        $membership = $this->companyMemberships()
            ->where('company_id', $company->id)
            ->where('status', CompanyMemberStatus::Active->value)
            ->first(['company_role']);

        $role = $membership?->company_role;

        return $role?->value;
    }

    /**
     * Determine whether the user has the given company-scoped permission.
     */
    public function hasCompanyPermission(Company $company, string $permission): bool
    {
        $role = $this->companyRole($company);

        if ($role === null) {
            return false;
        }

        $enum = CompanyMemberRole::tryFrom($role);

        if ($enum === null) {
            return false;
        }

        return in_array($permission, $enum->permissions(), true);
    }

    /**
     * Scope a query to users who are employer applicants or employers.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeEmployerApplicants(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNotNull('users.status')
                ->orWhereHas('roles', function (Builder $query): void {
                    $query->where('roles.slug', RoleSlug::Employer->value);
                });
        });
    }
}
