<?php

namespace Modules\HR\App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\HR\App\Models\OrganizationalUnit;
use Modules\HR\App\Models\OrganizationalPosition;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;

class OrganizationalUnitService
{
    public function move(
        OrganizationalUnit $unit,
        ?int $parentId = null,
        ?int $parentPositionId = null,
        ?int $sortOrder = null
    ): OrganizationalUnit {
        return DB::transaction(function () use (
            $unit,
            $parentId,
            $parentPositionId,
            $sortOrder
        ) {
            if ($parentId !== null && $parentPositionId !== null) {
                throw new RuntimeException(
                    'یک واحد نمی‌تواند هم زیر واحد و هم زیر سمت قرار بگیرد.'
                );
            }

            if ($parentId !== null && $unit->id === $parentId) {
                throw new RuntimeException(
                    'یک واحد سازمانی نمی‌تواند والد خودش باشد.'
                );
            }

            $parent = null;
            $parentPosition = null;

            if ($parentId !== null) {
                $parent = OrganizationalUnit::findOrFail($parentId);

                if ($this->isDescendantOf($parent, $unit)) {
                    throw new RuntimeException(
                        'امکان انتقال واحد زیرمجموعه خودش وجود ندارد.'
                    );
                }
            } elseif ($parentPositionId !== null) {
                $parentPosition = OrganizationalPosition::findOrFail($parentPositionId);

                if ($parentPosition->organizational_unit_id === $unit->id) {
                    throw new RuntimeException(
                        'واحد نمی‌تواند زیر سمتی در خودش قرار بگیرد.'
                    );
                }

                $positionUnit = $parentPosition->unit;
                if ($this->isDescendantOf($positionUnit, $unit)) {
                    throw new RuntimeException(
                        'امکان ایجاد حلقه در ساختار سازمانی وجود ندارد.'
                    );
                }
            }

            $unit->parent_id = $parentId;
            $unit->parent_position_id = $parentPositionId;

            if ($parent) {
                $unit->level = $parent->level + 1;
                $unit->path = rtrim($parent->path, '/') . '/' . $unit->id . '/';
            } elseif ($parentPosition) {
                $positionUnit = $parentPosition->unit;
                $unit->level = $positionUnit->level + 2;
                $unit->path = rtrim($positionUnit->path, '/') . '/' . $parentPosition->id . '/' . $unit->id . '/';
            } else {
                $unit->level = 1;
                $unit->path = '/' . $unit->id . '/';
            }

            if ($sortOrder !== null) {
                $unit->sort_order = $sortOrder;
            }

            $unit->save();
            $unit->refresh();

            $this->rebuildDescendants($unit);

            return $unit->fresh();
        });
    }

    private function isDescendantOf(
        OrganizationalUnit $candidate,
        OrganizationalUnit $ancestor
    ): bool {
        if (!$ancestor->path) {
            return false;
        }
        return str_starts_with(
            rtrim($candidate->path, '/') . '/',
            rtrim($ancestor->path, '/') . '/'
        );
    }

    private function rebuildDescendants(OrganizationalUnit $parent): void
    {
        $children = OrganizationalUnit::query()
            ->where('parent_id', $parent->id)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        foreach ($children as $child) {
            $child->level = $parent->level + 1;
            $child->path = rtrim($parent->path, '/') . '/' . $child->id . '/';
            $child->save();
            $this->rebuildDescendants($child);
        }

        $positions = $parent->positions;
        foreach ($positions as $position) {
            $unitsUnderPosition = OrganizationalUnit::query()
                ->where('parent_position_id', $position->id)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get();

            foreach ($unitsUnderPosition as $unitUnderPosition) {
                $unitUnderPosition->level = $parent->level + 2;
                $unitUnderPosition->path = rtrim($parent->path, '/') . '/' . $position->id . '/' . $unitUnderPosition->id . '/';
                $unitUnderPosition->save();
                $this->rebuildDescendants($unitUnderPosition);
            }
        }
    }
}
