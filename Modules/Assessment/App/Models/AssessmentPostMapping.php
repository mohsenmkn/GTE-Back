<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\OrganizationalUnit;

class AssessmentPostMapping extends Model
{
    protected $table = 'assessment_post_mappings';

    protected $fillable = [
        'mapping_type',
        'post_title_pattern',
        'organizational_unit_id',
        'assessment_post_id',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'organizational_unit_id' => 'integer',
        'assessment_post_id' => 'integer',
        'created_by' => 'integer',
    ];

    const TYPE_TITLE_PATTERN = 'title_pattern';
    const TYPE_UNIT = 'unit';

    /* ──────────────────────────────── Relations ─────────────────────────────── */

    public function assessmentPost(): BelongsTo
    {
        return $this->belongsTo(AssessmentPost::class, 'assessment_post_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ──────────────────────────────── Scopes ─────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('mapping_type', $type);
    }

    /* ─────────────────────────────── Query Methods ─────────────────────────────── */

    /**
     * یافتن نگاشت دستی برای یک پست سازمانی
     *
     * @param string $postTitle عنوان پست
     * @param int|null $unitId واحد سازمانی
     * @return AssessmentPost|null
     */
    public static function findMappingForPosition(string $postTitle, ?int $unitId = null): ?AssessmentPost
    {
        // ۱. جستجو بر اساس الگوی عنوان (با اولویت بالاتر)
        $titleMapping = self::active()
            ->byType(self::TYPE_TITLE_PATTERN)
            ->whereNotNull('post_title_pattern')
            ->get()
            ->first(function ($mapping) use ($postTitle) {
                // تبدیل الگوی SQL LIKE به regex
                $pattern = str_replace('%', '.*', $mapping->post_title_pattern);
                $pattern = '/^' . $pattern . '$/u';
                return preg_match($pattern, $postTitle) === 1;
            });

        if ($titleMapping) {
            return $titleMapping->assessmentPost;
        }

        // ۲. جستجو بر اساس واحد سازمانی
        if ($unitId) {
            $unitMapping = self::active()
                ->byType(self::TYPE_UNIT)
                ->where('organizational_unit_id', $unitId)
                ->first();

            if ($unitMapping) {
                return $unitMapping->assessmentPost;
            }
        }

        return null;
    }
}
