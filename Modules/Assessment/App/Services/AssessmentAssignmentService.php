<?php

namespace Modules\Assessment\App\Services;

use Illuminate\Support\Collection;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Models\AssessmentPostMapping;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Services\OrganizationHierarchyService;

class AssessmentAssignmentService
{
    private const EVALUATOR_CHAIN = [
        'معاون' => 'مدیرعامل',
        'مدیر' => 'معاون',
        'رئیس' => 'مدیر',
        'سرپرست/کارشناس ارشد' => 'رئیس',
        'کارشناس' => 'سرپرست/کارشناس ارشد',
        'کاردان/تکنسین/مسئول' => 'سرپرست/کارشناس ارشد',
        'متصدی' => 'سرپرست/کارشناس ارشد',
        'راننده/اپراتور' => 'سرپرست/کارشناس ارشد',
        'کارگر' => 'سرپرست/کارشناس ارشد',
    ];

    public function __construct(
        private OrganizationHierarchyService $hierarchy,
        private JobFamilyClassifier $classifier
    ) {
    }

    public function plan(?int $cycleId = null, ?int $periodId = null): array
    {
        $snapshot = $this->hierarchy->snapshot();
        $profiles = AssessmentPost::where('is_active', true)->orderBy('id')->get();
        $mappings = AssessmentPostMapping::active()->orderBy('id')->get();
        $existing = Assessment::query()
            ->when($periodId !== null, fn($query) => $query->where('period_id', $periodId))
            ->when($periodId === null, fn($query) => $query->where('cycle_id', $cycleId))
            ->pluck('employee_user_id')->flip();
        $rows = [];
        $summary = array_fill_keys(['total', 'ready', 'exists', 'no_profile', 'no_evaluator', 'out_of_scope'], 0);

        foreach ($snapshot['employees'] as $employee) {
            $family = $this->classifier->classify($employee->post_title);
            $profile = $this->profileFor($employee, $profiles, $mappings);
            $candidates = $this->evaluatorsFor($employee, $snapshot);
            $evaluator = $candidates->first();
            $status = $existing->has($employee->user_id) ? 'exists'
                : (!$profile ? ($family ? 'no_profile' : 'out_of_scope')
                    : ($evaluator ? 'ready' : 'no_evaluator'));
            $summary['total']++;
            $summary[$status]++;
            $rows[] = [
                'user_id' => $employee->user_id, 'name' => $employee->user?->name,
                'personnel_code' => $employee->personnel_code,
                'unit' => $employee->unit?->title, 'unit_id' => $employee->organizational_unit_id,
                'post_title' => $employee->post_title, 'family' => $family,
                'post_id' => $profile?->id, 'profile_title' => $profile?->title,
                'evaluator_id' => $evaluator?->user_id, 'evaluator_name' => $evaluator?->user?->name,
                'evaluator_source' => $employee->organizational_position_id ? 'org_chart' : 'legacy_family',
                'evaluator_family_needed' => self::EVALUATOR_CHAIN[$family] ?? null,
                'suggested_evaluators' => $candidates->map(fn($candidate) => [
                    'id' => $candidate->user_id, 'name' => $candidate->user?->name,
                    'post_title' => $candidate->post_title,
                ])->values()->all(),
                'status' => $status,
            ];
        }

        return compact('rows', 'summary');
    }

    public function evaluatorsFor(EmployeePosition $employee, array $snapshot): Collection
    {
        if ($employee->organizational_position_id) {
            return $this->hierarchy->managerCandidates($employee, $snapshot);
        }

        $family = self::EVALUATOR_CHAIN[$this->classifier->classify($employee->post_title)] ?? null;
        if (!$family) {
            return collect();
        }

        return $this->hierarchy->legacyCandidates($employee, $snapshot)
            ->filter(fn($candidate) => $this->classifier->classify($candidate->post_title) === $family)->values();
    }

    public function profileFor(EmployeePosition $employee, ?Collection $profiles = null, ?Collection $mappings = null): ?AssessmentPost
    {
        $profiles ??= AssessmentPost::where('is_active', true)->orderBy('id')->get();
        $mappings ??= AssessmentPostMapping::active()->orderBy('id')->get();
        foreach ($mappings->where('mapping_type', AssessmentPostMapping::TYPE_TITLE_PATTERN) as $mapping) {
            $pattern = str_replace('%', '.*', preg_quote($mapping->post_title_pattern ?? '', '/'));
            if ($mapping->post_title_pattern !== null
                && preg_match('/^' . $pattern . '$/u', $employee->post_title ?? '') === 1
                && $profile = $profiles->firstWhere('id', $mapping->assessment_post_id)) {
                return $profile;
            }
        }
        foreach ($mappings->where('mapping_type', AssessmentPostMapping::TYPE_UNIT) as $mapping) {
            if ((int) $mapping->organizational_unit_id === (int) $employee->organizational_unit_id
                && $profile = $profiles->firstWhere('id', $mapping->assessment_post_id)) {
                return $profile;
            }
        }

        $family = $this->classifier->classify($employee->post_title);
        if (!$family) {
            return null;
        }

        return $profiles->first(fn($profile) => $profile->grade === $family && $profile->unit === $employee->unit?->title)
            ?? $profiles->first(fn($profile) => $profile->grade === $family && $profile->domain === $employee->unit?->title);
    }
}
