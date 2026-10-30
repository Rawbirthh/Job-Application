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
}
