<?php


namespace Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Services\GtarabarSyncService;
use Spatie\Permission\Models\Role;

class ReclassifyUsersEmployeeType extends Command
{
    protected $signature = 'users:reclassify {--dry-run : فقط گزارش بده و تغییری اعمال نکن}';

    protected $description = 'تشخیص و به‌روزرسانی نوع کاربران (پرسنل / پیمانکار) بر اساس آخرین حکم گستراب';

    public function handle(GtarabarSyncService $sync): int
    {
        $dryRun = (bool)$this->option('dry-run');

        $personnelRole = Role::firstOrCreate(['name' => 'پرسنل']);
        $contractorRole = Role::firstOrCreate(['name' => 'پیمانکار']);

        $stats = [
            'checked' => 0,
            'no_statute' => 0,
            'unchanged' => 0,
            'to_contractor' => 0,
            'to_personnel' => 0,
        ];

        if ($dryRun) {
            $this->warn('حالت dry-run فعال است. هیچ تغییری در دیتابیس اعمال نمی‌شود.');
        }

        User::query()
            ->whereNotNull('personnel_code')
            ->where('personnel_code', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($sync, $personnelRole, $contractorRole, $dryRun, &$stats) {
                foreach ($users as $user) {
                    $stats['checked']++;

                    $statute = $user->getEmployeeStatute();

                    if (!$statute) {
                        $stats['no_statute']++;
                        continue;
                    }

                    $isContractor = $sync->isContractorByTitles(
                        $statute->DepartmentTitle ?? null,
                        $statute->PostTitle ?? null,
                        $statute->JobTitle ?? null
                    );

                    $newType = $isContractor ? 'contractor' : 'personnel';

                    if ($user->employee_type === $newType) {
                        $stats['unchanged']++;
                        continue;
                    }

                    $this->line(sprintf(
                        '%s | %s | %s => %s',
                        $user->personnel_code,
                        $user->name,
                        $user->employee_type,
                        $newType
                    ));

                    if (!$dryRun) {
                        if ($newType === 'contractor') {
                            $user->update([
                                'employee_type' => 'contractor',
                                'is_active' => false,
                            ]);

                            if ($user->hasRole($personnelRole->name)) {
                                $user->removeRole($personnelRole->name);
                            }

                            if (!$user->hasRole($contractorRole->name)) {
                                $user->assignRole($contractorRole->name);
                            }

                            // حذف توکن‌های فعلی پیمانکار
                            $user->tokens()->delete();
                        } else {
                            $user->update([
                                'employee_type' => 'personnel',
                                'is_active' => true,
                            ]);

                            if ($user->hasRole($contractorRole->name)) {
                                $user->removeRole($contractorRole->name);
                            }

                            if (!$user->hasRole($personnelRole->name)) {
                                $user->assignRole($personnelRole->name);
                            }
                        }
                    }

                    if ($newType === 'contractor') {
                        $stats['to_contractor']++;
                    } else {
                        $stats['to_personnel']++;
                    }
                }
            });

        $this->info('نتیجه عملیات:');
        $this->table(
            ['شاخص', 'مقدار'],
            [
                ['کاربران بررسی شده', $stats['checked']],
                ['بدون حکم در گستراب', $stats['no_statute']],
                ['بدون تغییر', $stats['unchanged']],
                ['تبدیل به پیمانکار', $stats['to_contractor']],
                ['تبدیل به پرسنل', $stats['to_personnel']],
            ]
        );

        return self::SUCCESS;
    }
}
