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
    /**
     * جابه‌جایی واحد سازمانی
     *
     * @param OrganizationalUnit $unit
     * @param int|null $parentId - واحد والد (اختیاری)
     * @param int|null $parentPositionId - سمت والد (اختیاری)
     * @param int|null $sortOrder
     * @return OrganizationalUnit
     */
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
            // ── اعتبارسنجی اولیه ─────────────────────────

            // نمی‌تواند هم parent_id و هم parent_position_id داشته باشد
            if ($parentId !== null && $parentPositionId !== null) {
                throw new RuntimeException(
                    'یک واحد نمی‌تواند هم زیر واحد و هم زیر سمت قرار بگیرد.'
                );
            }

            // جلوگیری از والد خودش
            if ($parentId !== null && $unit->id === $parentId) {
                throw new RuntimeException(
                    'یک واحد سازمانی نمی‌تواند والد خودش باشد.'
                );
            }

            // ── تعیین والد جدید ─────────────────────────

            $parent = null;
            $parentPosition = null;

            if ($parentId !== null) {
                $parent = OrganizationalUnit::findOrFail($parentId);

                // جلوگیری از قرار گرفتن زیرمجموعه خودش
                if ($this->isDescendantOf($parent, $unit)) {
                    throw new RuntimeException(
                        'امکان انتقال واحد زیرمجموعه خودش وجود ندارد.'
                    );
                }
            } elseif ($parentPositionId !== null) {
                $parentPosition = OrganizationalPosition::findOrFail($parentPositionId);

                // جلوگیری از قرار گرفتن واحد زیر سمتی در خودش (سوال ۲)
                if ($parentPosition->organizational_unit_id === $unit->id) {
                    throw new RuntimeException(
                        'واحد نمی‌تواند زیر سمتی در خودش قرار بگیرد.'
                    );
                }

                // جلوگیری از حلقه: واحد A زیر سمت X، سمت X زیر واحد A
                $positionUnit = $parentPosition->unit;
                if ($this->isDescendantOf($positionUnit, $unit)) {
                    throw new RuntimeException(
                        'امکان ایجاد حلقه در ساختار سازمانی وجود ندارد.'
                    );
                }
            }

            // ── تغییر والد ─────────────────────────────

            $unit->parent_id = $parentId;
            $unit->parent_position_id = $parentPositionId;

            // ── محاسبه level و path ─────────────────────

            if ($parent) {
                // واحد زیر واحد دیگر
                $unit->level = $parent->level + 1;
                $unit->path = rtrim($parent->path, '/') . '/' . $unit->id . '/';
            } elseif ($parentPosition) {
                // واحد زیر سمت
                $positionUnit = $parentPosition->unit;
                $unit->level = $positionUnit->level + 2; // واحد + سمت + این واحد
                $unit->path = rtrim($positionUnit->path, '/') . '/' . $parentPosition->id . '/' . $unit->id . '/';
            } else {
                // واحد ریشه
                $unit->level = 1;
                $unit->path = '/' . $unit->id . '/';
            }

            // ── sort ───────────────────────────────────

            if ($sortOrder !== null) {
                $unit->sort_order = $sortOrder;
            }

            $unit->save();
            $unit->refresh();

            // ── بازسازی مسیر فرزندان ───────────────────

            $this->rebuildDescendants($unit);

            return $unit->fresh();
        });
    }

    /**
     * آیا candidate زیرمجموعه ancestor است؟
     */
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

    /**
     * بازسازی path و level فرزندان
     * حالا هم فرزندان با parent_id و هم فرزندان با parent_position_id را بررسی می‌کند
     */
    private function rebuildDescendants(OrganizationalUnit $parent): void
    {
        // ۱. فرزندان مستقیم (parent_id)
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

        // ۲. واحدهایی که زیر سمت‌های این واحد قرار گرفته‌اند
        $positions = $parent->positions;
        foreach ($positions as $position) {
            $unitsUnderPosition = OrganizationalUnit::query()
                ->where('parent_position_id', $position->id)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get();

            foreach ($unitsUnderPosition as $unitUnderPosition) {
                $unitUnderPosition->level = $parent->level + 2; // واحد + سمت + این واحد
                $unitUnderPosition->path = rtrim($parent->path, '/') . '/' . $position->id . '/' . $unitUnderPosition->id . '/';
                $unitUnderPosition->save();
                $this->rebuildDescendants($unitUnderPosition);
            }
        }
    }
}
