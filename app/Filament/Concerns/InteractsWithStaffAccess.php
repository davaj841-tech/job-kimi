<?php

namespace App\Filament\Concerns;

use App\Support\StaffRoles;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;

trait InteractsWithStaffAccess
{
    protected static function superAdminOnly(): bool
    {
        return StaffRoles::isSuperAdmin(auth()->user());
    }

    protected static function staffAdminOnly(): bool
    {
        return StaffRoles::isStaffAdmin(auth()->user());
    }

    protected static function secureDeleteBulkAction(string $label = 'حذف'): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->label($label)
            ->requiresConfirmation()
            ->modalHeading('تأیید حذف')
            ->modalDescription('این عملیات غیرقابل بازگشت است. از حذف موارد انتخاب‌شده مطمئن هستید؟')
            ->modalSubmitActionLabel('بله، حذف شود')
            ->modalCancelActionLabel('انصراف');
    }

    protected static function secureBulkAction(BulkAction $action): BulkAction
    {
        return $action
            ->requiresConfirmation()
            ->modalHeading('تأیید عملیات')
            ->modalDescription('این عملیات روی تمام ردیف‌های انتخاب‌شده اعمال می‌شود. ادامه می‌دهید؟')
            ->modalSubmitActionLabel('تأیید')
            ->modalCancelActionLabel('انصراف');
    }
}
