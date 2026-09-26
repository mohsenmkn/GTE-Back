<?php

namespace Modules\Complaint\App\Enums;

enum ComplaintStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Answered = 'answered';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار بررسی',
            self::InProgress => 'در حال رسیدگی',
            self::Answered => 'پاسخ داده شده',
            self::Resolved => 'بسته شده',
            self::Rejected => 'رد شده',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
