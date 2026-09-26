<?php

namespace Modules\Library\App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'library_settings';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    // دریافت مقدار یک تنظیم
    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    // ذخیره یا بروزرسانی یک تنظیم
    public static function set(string $key, string $value, ?string $description = null): void
    {
        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'description' => $description,
            ]
        );
    }
}
