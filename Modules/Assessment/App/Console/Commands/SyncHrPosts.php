<?php


namespace Modules\Assessment\App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Services\JobFamilyClassifier;
use Modules\HR\App\Models\EmployeePosition;

class SyncHrPosts extends Command
{
    protected $signature = 'assessment:sync-posts {--dry-run : فقط گزارش، بدون ذخیره}';
    protected $description = 'ساخت خودکار شناسنامه برای پست‌های سازمانی راهکاران';

    public function handle(JobFamilyClassifier $classifier): int
    {
        $dryRun = (bool)$this->option('dry-run');

        // پست‌های متمایز از راهکاران
        $positions = EmployeePosition::query()
            ->whereNotNull('post_title')
            ->where('post_title', '!=', '')
            ->with('unit')
            ->get(['post_title', 'organizational_unit_id'])
            ->unique(fn($p) => $p->post_title . '|' . $p->organizational_unit_id)
            ->values();

        $this->info("📋 {$positions->count()} پست متمایز در راهکاران یافت شد.");

        $created = 0;
        $exists = 0;
        $rows = [];

        foreach ($positions as $p) {
            $title = trim($p->post_title);
            $unitName = $p->unit?->title;
            $family = $classifier->classify($title);

            $already = AssessmentPost::where('title', $title)->exists();

            if ($already) {
                $exists++;
                continue;
            }

            if (!$dryRun) {
                AssessmentPost::create([
                    'title' => $title,
                    'grade' => $family,
                    'unit' => $unitName,
                    'domain' => null,
                    'source' => 'auto',
                    'is_active' => true,
                ]);
            }

            $created++;
            $rows[] = [$title, $family, $unitName ?? '—'];
        }

        // گزارش خلاصه
        $this->table(['پست سازمانی', 'خانواده شغلی', 'واحد'], $rows);
        $this->newLine();
        $this->info("➕ جدید: {$created} | ⏸️ موجود: {$exists}");

        if ($dryRun) {
            $this->warn('⚠️ حالت dry-run بود — چیزی ذخیره نشد. برای ذخیره، بدون --dry-run اجرا کنید.');
        }

        return self::SUCCESS;
    }
}
