<?php

namespace Modules\HR\App\Listeners;

use Illuminate\Auth\Events\Login;
use Modules\HR\App\Jobs\SyncUserHRDataJob;
use Modules\HR\App\Models\EmployeePosition;

class SyncUserAfterLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        // فقط کاربرانی که کد پرسنلی دارند
        if (empty($user->personnel_code)) return;

        // اگر داده وجود ندارد یا قدیمی شده → sync در پس‌زمینه
        $position = EmployeePosition::where('user_id', $user->id)->first();

        if (!$position || $position->isStale(15)) {
            dispatch(new SyncUserHRDataJob($user, 'login'))->afterResponse();
        }
    }
}
