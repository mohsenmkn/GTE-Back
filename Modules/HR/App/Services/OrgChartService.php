<?php

namespace Modules\HR\App\Services;

use Modules\HR\App\Models\OrganizationalUnit;
use Modules\HR\App\Models\OrganizationalPosition;
use Modules\HR\App\Models\EmployeePosition;

class OrgChartService
{
    /**
     * ساخت درخت کامل چارت سازمانی
     *
     * Unit
     *   └── Position
     *         └── Position
     *               └── Employee
     */
    public function buildTree(): array
    {
        $units = OrganizationalUnit::query()
            ->with([
                'positions' => function ($q) {
                    $q->orderBy('sort_order')
                        ->with([
                            'employees.user:id,name,mobile',
                            'children',
                        ]);
                },
            ])
            ->where('is_active', true)
            ->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $tree = [];

        foreach ($units as $unit) {

            $node = [
                'key' => 'unit-' . $unit->id,

                'data' => [
                    'id'             => $unit->id,
                    'title'          => $unit->title,
                    'code'           => $unit->code,
                    'level'          => $unit->level,
                    'employee_count' => $unit->employees()->count(),
                    'type'           => 'unit',
                ],

                'children' => [],
            ];

            // سمت‌های این واحد
            $positions = $unit->positions
                ->whereNull('parent_id')
                ->sortBy('sort_order');

            foreach ($positions as $position) {
                $node['children'][] = $this->buildPositionNode(
                    $position
                );
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * ساخت Node مربوط به سمت
     */
    private function buildPositionNode(
        OrganizationalPosition $position
    ): array {

        $node = [
            'key' => 'position-' . $position->id,

            'data' => [
                'id'             => $position->id,
                'type'           => 'position',

                'post_title'     => $position->post_title,
                'post_code'      => $position->post_code,

                'job_title'      => $position->job_title,
                'job_code'       => $position->job_code,

                'is_custom'      => $position->is_custom,
                'is_active'      => $position->is_active,

                'employee_count' => $position->employees->count(),
            ],

            'children' => [],
        ];

        // ابتدا سمت‌های زیرمجموعه
        foreach (
            $position->children
                ->where('is_active', true)
                ->sortBy('sort_order')
            as $child
        ) {

            $node['children'][] = $this->buildPositionNode(
                $child->load([
                    'employees.user:id,name,mobile',
                    'children',
                ])
            );
        }

        // سپس کارکنان این سمت
        foreach ($position->employees as $employee) {

            $node['children'][] = [
                'key' => 'emp-' . $employee->id,

                'data' => [
                    'type'           => 'employee',
                    'user_id'        => $employee->user_id,

                    'name'           => $employee->user?->name ?? '—',

                    'personnel_code' => $employee->personnel_code,

                    'post_title'     => $employee->post_title,
                    'job_title'      => $employee->job_title,

                    'mobile'         => $employee->user?->mobile,
                ],
            ];
        }

        return $node;
    }

    /**
     * آمار کلی چارت
     */
    public function getStats(): array
    {
        return [
            'total_units'     => OrganizationalUnit::count(),

            'total_positions' => OrganizationalPosition::count(),

            'total_employees' => EmployeePosition::count(),

            'active_units'    => OrganizationalUnit::where(
                'is_active',
                true
            )->count(),

            'active_positions' => OrganizationalPosition::where(
                'is_active',
                true
            )->count(),

            'last_sync' => EmployeePosition::max('synced_at'),
        ];
    }
}
