<?php

namespace App\Support;

use App\Models\User;

final class StaffRoles
{
    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN = 'admin';

    public const OPERATOR = 'operator';

    /** @return list<string> */
    public static function staffRoles(): array
    {
        return [self::SUPER_ADMIN, self::ADMIN, self::OPERATOR];
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return $user?->role === self::SUPER_ADMIN;
    }

    public static function isAdmin(?User $user): bool
    {
        return $user?->role === self::ADMIN;
    }

    public static function isStaffAdmin(?User $user): bool
    {
        return in_array($user?->role, [self::SUPER_ADMIN, self::ADMIN], true);
    }

    public static function isOperator(?User $user): bool
    {
        return $user?->role === self::OPERATOR;
    }

    public static function isStaff(?User $user): bool
    {
        return in_array($user?->role, self::staffRoles(), true);
    }

    /** Accounts that only super admins may mutate. */
    public static function isProtectedStaffAccount(?User $user): bool
    {
        return in_array($user?->role, [self::SUPER_ADMIN, self::ADMIN], true);
    }

    public static function canManageStaffAccounts(?User $actor): bool
    {
        return self::isSuperAdmin($actor);
    }
}
