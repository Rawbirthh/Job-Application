<?php

namespace App\Enums;

enum CompanyMemberStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Revoked = 'revoked';
}
