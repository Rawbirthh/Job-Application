<?php

namespace App\Enums;

enum PermissionSlug: string
{
    case AdminAccess = 'admin.access';
    case UsersView = 'users.view';
    case UsersRolesManage = 'users.roles.manage';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case PermissionsView = 'permissions.view';
    case PermissionsManage = 'permissions.manage';
    case CategoriesView = 'categories.view';
    case CategoriesManage = 'categories.manage';
    case EmploymentTypesView = 'employment_types.view';
    case EmploymentTypesManage = 'employment_types.manage';
    case WorkplaceArrangementsView = 'workplace_arrangements.view';
    case WorkplaceArrangementsManage = 'workplace_arrangements.manage';
    case ExperienceLevelsView = 'experience_levels.view';
    case ExperienceLevelsManage = 'experience_levels.manage';
    case PayPeriodsView = 'pay_periods.view';
    case PayPeriodsManage = 'pay_periods.manage';
    case SalaryBandsView = 'salary_bands.view';
    case SalaryBandsManage = 'salary_bands.manage';
    case EmployersView = 'employers.view';
    case EmployersManage = 'employers.manage';
    case JobsModerate = 'jobs.moderate';
    case ReportsModerate = 'reports.moderate';
    case AuditLogsView = 'audit_logs.view';
    case PlatformAnalyticsView = 'platform_analytics.view';
}
