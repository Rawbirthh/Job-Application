<?php

namespace App\Enums;

enum PermissionSlug: string
{
    case AdminAccess = 'admin.access';
    case UsersView = 'users.view';
    case UsersRolesManage = 'users.roles.manage';
}
