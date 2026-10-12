<?php

namespace Modules\HR\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\HR\App\Models\OrganizationalUnit;
use Modules\HR\App\Models\OrganizationalPosition;
use Modules\HR\App\Services\OrganizationalUnitService;

class OrganizationalUnitController
{
    public function __construct(
        public OrganizationalUnitService $service
    ) {
    }

    public function move(
        Request $request,
        OrganizationalUnit $organizationalUnit
    ): JsonResponse {
        $validated = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                'exists:organizational_units,id',
            ],
            'parent_position_id' => [
                'nullable',
                'integer',
                'exists:organizational_positions,id',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);
        $newParentPositionId = $validated['parent_position_id'] ?? null;



        $newParentId = $validated['parent_id'] ?? null;
        $newParentPositionId = $validated['parent_position_id'] ?? null;

        if ($newParentId !== null && $newParentPositionId !== null) {
            return response()->json([
                'success' => false,
                'message' => 'یک واحد نمی‌تواند هم زیر واحد و هم زیر سمت قرار بگیرد.',
            ], 422);
        }

        // ── اعتبارسنجی: نمی‌تواند هم parent_id و هم parent_position_id داشته باشد ──

        if ($newParentId !== null && $newParentPositionId !== null) {
            return response()->json([
                'success' => false,
                'message' => 'یک واحد نمی‌تواند هم زیر واحد و هم زیر سمت قرار بگیرد.',
            ], 422);
        }

        // ── جلوگیری از قرار گرفتن واحد زیر خودش ──

        if ($newParentId === $organizationalUnit->id) {
            return response()->json([
                'success' => false,
                'message' => 'یک واحد نمی‌تواند زیرمجموعه خودش قرار بگیرد.',
            ], 422);
        }

        // ── جلوگیری از قرار گرفتن واحد زیر سمتی در خودش ──

        if ($newParentPositionId !== null) {
            $parentPosition = OrganizationalPosition::find($newParentPositionId);

            if ($parentPosition && $parentPosition->organizational_unit_id === $organizationalUnit->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'واحد نمی‌تواند زیر سمتی در خودش قرار بگیرد.',
                ], 422);
            }
        }

        // ── جلوگیری از ایجاد چرخه ──

        if ($newParentId && $this->wouldCreateCycle($organizationalUnit, (int) $newParentId)) {
            return response()->json([
                'success' => false,
                'message' => 'این جابه‌جایی باعث ایجاد حلقه در ساختار سازمانی می‌شود.',
            ], 422);
        }

        if ($newParentPositionId && $this->wouldCreateCycleWithPosition($organizationalUnit, (int) $newParentPositionId)) {
            return response()->json([
                'success' => false,
                'message' => 'این جابه‌جایی باعث ایجاد حلقه در ساختار سازمانی می‌شود.',
            ], 422);
        }

        // ── تغییر والد ──
        $organizationalUnit->parent_id = $newParentId;
        $organizationalUnit->parent_position_id = $newParentPositionId;
        $organizationalUnit->sort_order = $validated['sort_order'] ?? $organizationalUnit->sort_order;
        $organizationalUnit->save();

        // ── بازسازی مسیر خود واحد ──

        $this->recalculateUnit($organizationalUnit);

        // ── بازسازی مسیر تمام فرزندان ──

        $this->recalculateDescendants($organizationalUnit);

        return response()->json([
            'success' => true,
            'message' => 'ساختار واحد سازمانی با موفقیت تغییر کرد.',
            'data' => $organizationalUnit->fresh(),
        ]);
    }

    /**
     * بررسی چرخه با parent_id
     */
    private function wouldCreateCycle(
        OrganizationalUnit $unit,
        int $newParentId
    ): bool {
        if ($unit->id === $newParentId) {
            return true;
        }

        $current = OrganizationalUnit::find($newParentId);
        while ($current) {
            if ($current->id === $unit->id) {
                return true;
            }
            $current = $current->parent_id
                ? OrganizationalUnit::find($current->parent_id)
                : null;
        }

        return false;
    }

    /**
     * ✅ بررسی چرخه با parent_position_id
     */
    private function wouldCreateCycleWithPosition(
        OrganizationalUnit $unit,
        int $newParentPositionId
    ): bool {
        $parentPosition = OrganizationalPosition::find($newParentPositionId);

        if (!$parentPosition) {
            return false;
        }

        $positionUnitId = $parentPosition->organizational_unit_id;

        if ($positionUnitId === $unit->id) {
            return true;
        }

        $current = OrganizationalUnit::find($positionUnitId);
        while ($current) {
            if ($current->id === $unit->id) {
                return true;
            }
            $current = $current->parent_id
                ? OrganizationalUnit::find($current->parent_id)
                : null;
        }

        return false;
    }

    /**
     * ✅ بازسازی level و path واحد
     */
    private function recalculateUnit(OrganizationalUnit $unit): void
    {
        if ($unit->parent_id) {
            $parent = OrganizationalUnit::findOrFail($unit->parent_id);
            $unit->level = $parent->level + 1;
            $unit->path = rtrim($parent->path, '/') . '/' . $unit->id . '/';
        } elseif ($unit->parent_position_id) {
            $parentPosition = OrganizationalPosition::findOrFail($unit->parent_position_id);
            $positionUnit = OrganizationalUnit::findOrFail($parentPosition->organizational_unit_id);
            $unit->level = $positionUnit->level + 2;
            $unit->path = rtrim($positionUnit->path, '/') . '/' . $parentPosition->id . '/' . $unit->id . '/';
        } else {
            $unit->level = 1;
            $unit->path = '/' . $unit->id . '/';
        }
        $unit->save();
    }

    /**
     * ✅ بازسازی level و path فرزندان
     */
    private function recalculateDescendants(OrganizationalUnit $unit): void
    {
        // فرزندان مستقیم
        $children = OrganizationalUnit::where('parent_id', $unit->id)
            ->orderBy('sort_order')->orderBy('id')->get();

        foreach ($children as $child) {
            $child->level = $unit->level + 1;
            $child->path = rtrim($unit->path, '/') . '/' . $child->id . '/';
            $child->save();
            $this->recalculateDescendants($child);
        }

        // ✅ واحدهای زیر سمت‌های این واحد
        $positions = $unit->positions;
        foreach ($positions as $position) {
            $unitsUnderPosition = OrganizationalUnit::where('parent_position_id', $position->id)
                ->orderBy('sort_order')->orderBy('id')->get();

            foreach ($unitsUnderPosition as $u) {
                $u->level = $unit->level + 2;
                $u->path = rtrim($unit->path, '/') . '/' . $position->id . '/' . $u->id . '/';
                $u->save();
                $this->recalculateDescendants($u);
            }
        }
    }

    public function index(): JsonResponse
    {
        $units = OrganizationalUnit::where('is_active', true)
            ->orderBy('path')
            ->select(['id', 'title', 'code', 'level', 'parent_id', 'parent_position_id'])
            ->get()
            ->map(function ($unit) {
                $indent = str_repeat('— ', $unit->level - 1);
                return [
                    'id' => $unit->id,
                    'title' => $indent . $unit->title,
                    'raw_title' => $unit->title,
                    'code' => $unit->code,
                    'level' => $unit->level,
                    'parent_id' => $unit->parent_id,
                    'parent_position_id' => $unit->parent_position_id,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $units,
        ]);
    }
}
