<?php


namespace Modules\Assessment\Console\Commands;

use Illuminate\Console\Command;
use Modules\Assessment\App\Services\ExcelProfileImporter;

class ImportJobProfiles extends Command
{
    protected $signature = 'assessment:import-profiles
                            {--dir= : پوشه فایل‌های اکسل}
                            {--file= : فقط یک فایل}
                            {--dry-run : بدون ذخیره}';

    protected $description = 'Import شناسنامه شایستگی پست‌ها از فایل‌های اکسل';

    public function handle(ExcelProfileImporter $importer): int
    {
        $files = [];

        if ($file = $this->option('file')) {
            $files[] = $file;
        } elseif ($dir = $this->option('dir')) {
            $files = glob(rtrim($dir, '/') . '/*.xlsx');
        } else {
            $this->error('یکی از --dir یا --file را مشخص کنید.');
            return self::FAILURE;
        }

        if (empty($files)) {
            $this->error('فایلی پیدا نشد.');
            return self::FAILURE;
        }

        $dryRun = (bool)$this->option('dry-run');
        if ($dryRun) $this->warn('⚠️ حالت Dry-Run: چیزی ذخیره نمی‌شود.');

        foreach ($files as $file) {
            $this->info('📄 ' . basename($file));
            $result = $importer->importFile($file, $dryRun);
        }

        $stats = $result['stats'];
        $this->newLine();
        $this->table(
            ['فایل', 'شیت', 'سوال', 'ایجاد', 'به‌روزرسانی'],
            [[$stats['files'], $stats['sheets'], $stats['questions'], $stats['created'], $stats['updated']]]
        );

        if (!empty($result['anomalies'])) {
            $this->newLine();
            $this->warn('⚠️ ناهنجاری‌ها:');
            foreach ($result['anomalies'] as $a) $this->line('  - ' . $a);
        }

        return self::SUCCESS;
    }
}
