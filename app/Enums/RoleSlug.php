<?php

namespace App\Enums;

enum RoleSlug: string
{
    case PlatformAdmin = 'platform_admin';
    case Employer = 'employer';
    case JobSeeker = 'job_seeker';
}
