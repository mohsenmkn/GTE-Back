<?php

namespace Modules\HR\App\Services;

use Illuminate\Support\Collection;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\OrganizationalPosition;
use Modules\HR\App\Models\OrganizationalUnit;
use RuntimeException;

class OrganizationHierarchyService
{
    public function snapshot(): array
    {
        $units = OrganizationalUnit::where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');
        $positions = OrganizationalPosition::where('is_active', true)
            ->whereIn('organizational_unit_id', $units->keys())
            ->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');
        $employees = EmployeePosition::with(['user', 'unit', 'organizationalPosition'])
            ->whereIn('organizational_unit_id', $units->keys())
            ->whereHas('user', fn($query) => $query->where('is_active', true))
            ->orderBy('id')->get();

        $this->assertAcyclic($units);
        $this->assertAcyclic($positions);

        return compact('units', 'positions', 'employees');
    }

    public function managerCandidates(EmployeePosition $employee, array $snapshot): Collection
    {
        $positions = $snapshot['positions'];
        $position = $positions->get($employee->organizational_position_id);
        if (!$position) {
            return collect();
        }

        $candidates = collect();
        $parentId = $position->parent_id;
        $visited = [(int) $position->id => true];
        while ($parentId && $parent = $positions->get($parentId)) {
            if (isset($visited[(int) $parent->id])) {
                throw new RuntimeException('حلقه در ساختار سمت‌های سازمانی وجود دارد.');
            }
            $visited[(int) $parent->id] = true;
            $candidates = $candidates->merge($this->occupants($parent->id, $employee, $snapshot));
            $parentId = $parent->parent_id;
        }

        $units = $snapshot['units'];
        $unitId = $units->get($position->organizational_unit_id)?->parent_id;
        $visitedUnits = [];
        while ($unitId && $unit = $units->get($unitId)) {
            if (isset($visitedUnits[(int) $unit->id])) {
                throw new RuntimeException('حلقه در ساختار واحدهای سازمانی وجود دارد.');
            }
            $visitedUnits[(int) $unit->id] = true;
            foreach ($positions->where('organizational_unit_id', $unit->id)->whereNull('parent_id') as $root) {
                $candidates = $candidates->merge($this->occupants($root->id, $employee, $snapshot));
            }
            $unitId = $unit->parent_id;
        }

        return $candidates->unique('user_id')->values();
    }

    public function legacyCandidates(EmployeePosition $employee, array $snapshot): Collection
    {
        if ($employee->organizational_position_id) {
            return collect();
        }

        $candidates = collect();
        $unitId = $employee->organizational_unit_id;
        $visited = [];
        while ($unitId && $unit = $snapshot['units']->get($unitId)) {
            if (isset($visited[(int) $unit->id])) {
                throw new RuntimeException('حلقه در ساختار واحدهای سازمانی وجود دارد.');
            }
            $visited[(int) $unit->id] = true;
            $candidates = $candidates->merge($snapshot['employees']
                ->where('organizational_unit_id', $unit->id)
                ->where('user_id', '!=', $employee->user_id)
                ->filter(fn($candidate) => !$candidate->organizational_position_id
                    || $snapshot['positions']->has($candidate->organizational_position_id)));
            $unitId = $unit->parent_id;
        }

        return $candidates->unique('user_id')->values();
    }

    public function buildTree(bool $includeEmployeeNodes = false): array
    {
        $snapshot = $this->snapshot();
        $units = $snapshot['units'];
        $unitNodes = [];
        foreach ($units as $unit) {
            $unitNodes[$unit->id] = [
                'key' => 'unit-' . $unit->id,
                'data' => [
                    'id' => $unit->id, 'type' => 'unit', 'title' => $unit->title,
                    'code' => $unit->code, 'level' => $unit->level, 'parent_id' => $unit->parent_id,
                    'is_custom' => $unit->is_custom, 'is_active' => $unit->is_active,
                    'sort_order' => $unit->sort_order,
                    'employee_count' => $snapshot['employees']->where('organizational_unit_id', $unit->id)->count(),
                ],
                'children' => [],
            ];
            $positions = $snapshot['positions']->where('organizational_unit_id', $unit->id);
            foreach ($positions as $position) {
                if (!$position->parent_id || !$positions->has($position->parent_id)) {
                    $unitNodes[$unit->id]['children'][] = $this->positionNode($position, $positions, $snapshot, $includeEmployeeNodes);
                }
            }
        }

        $tree = [];
        foreach ($units as $unit) {
            if ($unit->parent_id && isset($unitNodes[$unit->parent_id])) {
                $unitNodes[$unit->parent_id]['children'][] = &$unitNodes[$unit->id];
            } else {
                $tree[] = &$unitNodes[$unit->id];
            }
        }

        return $tree;
    }

    private function occupants(int $positionId, EmployeePosition $employee, array $snapshot): Collection
    {
        return $snapshot['employees']->where('organizational_position_id', $positionId)
            ->where('user_id', '!=', $employee->user_id)->values();
    }

    private function positionNode(OrganizationalPosition $position, Collection $positions, array $snapshot, bool $includeEmployees): array
    {
        $employees = $snapshot['employees']->where('organizational_position_id', $position->id)->map(fn($employee) => [
            'id' => $employee->id, 'user_id' => $employee->user_id,
            'name' => $employee->user?->name, 'personnel_code' => $employee->personnel_code,
            'mobile' => $employee->user?->mobile, 'post_title' => $employee->post_title,
            'job_title' => $employee->job_title,
        ])->values()->all();
        $node = [
            'key' => 'position-' . $position->id,
            'data' => [
                'id' => $position->id, 'type' => 'position', 'parent_id' => $position->parent_id,
                'post_title' => $position->post_title, 'post_code' => $position->post_code,
                'job_title' => $position->job_title, 'job_code' => $position->job_code,
                'unit_id' => $position->organizational_unit_id,
                'unit_title' => $snapshot['units']->get($position->organizational_unit_id)?->title,
                'is_custom' => $position->is_custom, 'is_active' => $position->is_active,
                'sort_order' => $position->sort_order,
                'employee_count' => count($employees), 'employees' => $employees,
            ],
            'children' => [],
        ];
        foreach ($positions->where('parent_id', $position->id) as $child) {
            $node['children'][] = $this->positionNode($child, $positions, $snapshot, $includeEmployees);
        }
        if ($includeEmployees) {
            foreach ($employees as $employee) {
                $node['children'][] = ['key' => 'emp-' . $employee['id'], 'data' => ['type' => 'employee'] + $employee];
            }
        }

        return $node;
    }

    private function assertAcyclic(Collection $nodes): void
    {
        $checked = [];
        foreach ($nodes as $node) {
            $visited = [];
            $current = $node;
            while ($current && !isset($checked[(int) $current->id])) {
                if (isset($visited[(int) $current->id])) {
                    throw new RuntimeException('حلقه در چارت سازمانی وجود دارد.');
                }
                $visited[(int) $current->id] = true;
                $current = $nodes->get($current->parent_id);
            }
            $checked += $visited;
        }
    }
}
