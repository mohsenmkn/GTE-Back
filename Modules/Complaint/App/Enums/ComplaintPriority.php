<?php

namespace Modules\Complaint\App\Enums;

enum ComplaintPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'کم',
            self::Medium => 'متوسط',
            self::High => 'زیاد',
            self::Critical => 'بحرانی',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
