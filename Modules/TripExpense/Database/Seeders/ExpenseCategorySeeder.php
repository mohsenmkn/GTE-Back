<?php

namespace Modules\TripExpense\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\TripExpense\App\Models\ExpenseCategory;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'آب معدنی',
                'code' => 'water',
                'description' => 'هزینه آب معدنی و نوشیدنی',
                'sort_order' => 1,
            ],
            [
                'name' => 'ناهار',
                'code' => 'lunch',
                'description' => 'هزینه ناهار',
                'sort_order' => 2,
            ],
            [
                'name' => 'شام',
                'code' => 'dinner',
                'description' => 'هزینه شام',
                'sort_order' => 3,
            ],
            [
                'name' => 'صبحانه',
                'code' => 'breakfast',
                'description' => 'هزینه صبحانه',
                'sort_order' => 4,
            ],
            [
                'name' => 'سوخت',
                'code' => 'fuel',
                'description' => 'هزینه سوخت کامیون',
                'sort_order' => 5,
            ],
            [
                'name' => 'عوارض',
                'code' => 'toll',
                'description' => 'هزینه عوارض جاده',
                'sort_order' => 6,
            ],
            [
                'name' => 'پارکینگ',
                'code' => 'parking',
                'description' => 'هزینه پارکینگ',
                'sort_order' => 7,
            ],
            [
                'name' => 'تعمیرات',
                'code' => 'repair',
                'description' => 'هزینه تعمیرات اضطراری',
                'sort_order' => 8,
            ],
            [
                'name' => 'سایر',
                'code' => 'other',
                'description' => 'سایر هزینه‌ها',
                'sort_order' => 99,
            ],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::firstOrCreate(
                ['code' => $category['code']],
                $category
            );
        }
    }
}
