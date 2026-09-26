<?php

namespace Modules\HR\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\HR\App\Models\OrganizationalUnit;
use Modules\HR\App\Services\OrganizationalUnitService;

class OrganizationalUnitController
{
    public function __construct(
        private OrganizationalUnitService $service
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
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $newParentId = $validated['parent_id'] ?? null;

        // جلوگیری از قرار گرفتن واحد زیر خودش
        if ($newParentId === $organizationalUnit->id) {
            return response()->json([
                'success' => false,
                'message' => 'یک واحد نمی‌تواند زیرمجموعه خودش قرار بگیرد.',
            ], 422);
        }

        // جلوگیری از ایجاد چرخه
        if (
            $newParentId &&
            $this->wouldCreateCycle(
                $organizationalUnit,
                (int) $newParentId
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'این جابه‌جایی باعث ایجاد حلقه در ساختار سازمانی می‌شود.',
            ], 422);
        }

        // تغییر والد
        $organizationalUnit->parent_id = $newParentId;
        $organizationalUnit->sort_order =
            $validated['sort_order'] ?? $organizationalUnit->sort_order;

        $organizationalUnit->save();

        // بازسازی مسیر خود واحد
        $this->recalculateUnit($organizationalUnit);

        // بازسازی مسیر تمام فرزندان
        $this->recalculateDescendants($organizationalUnit);

        return response()->json([
            'success' => true,
            'message' => 'ساختار واحد سازمانی با موفقیت تغییر کرد.',
            'data' => $organizationalUnit->fresh(),
        ]);
    }

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

    private function recalculateUnit(
        OrganizationalUnit $unit
    ): void {

        if ($unit->parent_id) {

            $parent = OrganizationalUnit::findOrFail(
                $unit->parent_id
            );

            $unit->level = $parent->level + 1;

            $unit->path =
                rtrim($parent->path, '/') .
                '/' .
                $unit->id .
                '/';

        } else {

            $unit->level = 1;
            $unit->path = '/' . $unit->id . '/';
        }

        $unit->save();
    }

    private function recalculateDescendants(
        OrganizationalUnit $unit
    ): void {

        $children = OrganizationalUnit::where(
            'parent_id',
            $unit->id
        )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($children as $child) {

            $child->level = $unit->level + 1;

            $child->path =
                rtrim($unit->path, '/') .
                '/' .
                $child->id .
                '/';

            $child->save();

            $this->recalculateDescendants($child);
        }
    }

}
