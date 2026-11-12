<?php

namespace App\Enums;

enum CompanyMemberRole: string
{
    case Owner = 'owner';
    case Recruiter = 'recruiter';
    case Reviewer = 'reviewer';

    /**
     * Company-scoped permissions granted by this role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => [
                'company.view',
                'company.update',
                'jobs.view',
                'jobs.create',
                'jobs.update',
                'jobs.submit',
                'jobs.close',
                'applicants.view',
                'applicants.manage',
                'screening.manage',
                'members.manage',
                'resumes.download',
                'analytics.view',
            ],
            self::Recruiter => [
                'company.view',
                'jobs.view',
                'jobs.create',
                'jobs.update',
                'jobs.submit',
                'jobs.close',
                'applicants.view',
                'applicants.manage',
                'screening.manage',
                'resumes.download',
                'analytics.view',
            ],
            self::Reviewer => [
                'company.view',
                'jobs.view',
                'applicants.view',
                'resumes.download',
                'analytics.view',
            ],
        };
    }
}
