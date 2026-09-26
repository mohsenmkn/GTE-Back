<?php

namespace Modules\HR\App\Services;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\HR\App\Models\OrganizationalUnit;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;

class OrganizationalUnitService
{
    public function move(
        OrganizationalUnit $unit,
        ?int $parentId,
        ?int $sortOrder = null
    ): OrganizationalUnit {

        return DB::transaction(function () use (
            $unit,
            $parentId,
            $sortOrder
        ) {

            // جلوگیری از والد خودش
            if ($parentId !== null && $unit->id === $parentId) {
                throw new RuntimeException(
                    'یک واحد سازمانی نمی‌تواند والد خودش باشد.'
                );
            }

            $parent = null;

            if ($parentId !== null) {

                $parent = OrganizationalUnit::findOrFail($parentId);

                // جلوگیری از قرار گرفتن زیرمجموعه خودش
                if ($this->isDescendantOf($parent, $unit)) {
                    throw new RuntimeException(
                        'امکان انتقال واحد زیرمجموعه خودش وجود ندارد.'
                    );
                }
            }

            // -------------------------
            // تغییر والد
            // -------------------------

            $unit->parent_id = $parentId;

            // -------------------------
            // level + path
            // -------------------------

            if ($parent) {

                $unit->level = $parent->level + 1;

                $unit->path =
                    rtrim($parent->path, '/') .
                    '/' .
                    $unit->id .
                    '/';

            } else {

                $unit->level = 1;

                $unit->path =
                    '/' . $unit->id . '/';
            }

            // -------------------------
            // sort
            // -------------------------

            if ($sortOrder !== null) {
                $unit->sort_order = $sortOrder;
            }

            $unit->save();

            // خیلی مهم
            $unit->refresh();

            // بازسازی مسیر فرزندان
            $this->rebuildDescendants($unit);

            // مجدداً از DB بخوان
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
     */
    private function rebuildDescendants(
        OrganizationalUnit $parent
    ): void {

        $children = OrganizationalUnit::query()
            ->where('parent_id', $parent->id)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        foreach ($children as $child) {

            $child->level = $parent->level + 1;

            $child->path =
                rtrim($parent->path, '/') .
                '/' .
                $child->id .
                '/';

            $child->save();

            $this->rebuildDescendants($child);
        }
    }
}
