<?php


namespace Modules\HR\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\App\Models\OrganizationalPosition;
use Modules\HR\App\Services\OrganizationalPositionService;
use Throwable;

class OrganizationalPositionController
{
    public function __construct(
        private OrganizationalPositionService $service
    )
    {
    }

    /**
     * لیست سمت‌ها
     */
    public function index(Request $request): JsonResponse
    {
        $positions = $this->service->index(
            $request->integer('unit_id') ?: null
        );

        return response()->json([
            'success' => true,
            'data' => $positions,
        ]);
    }

    /**
     * درخت ساختار سازمانی
     */
    public function tree(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->tree(),
        ]);
    }

    /**
     * ایجاد سمت
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organizational_unit_id' => [
                'required',
                'integer',
                'exists:organizational_units,id',
            ],

            'parent_id' => [
                'nullable',
                'integer',
                'exists:organizational_positions,id',
            ],

            'gt_post_ref' => [
                'nullable',
                'integer',
            ],

            'post_code' => [
                'nullable',
                'string',
                'max:50',
            ],

            'post_title' => [
                'required',
                'string',
                'max:255',
            ],

            'gt_job_ref' => [
                'nullable',
                'integer',
            ],

            'job_code' => [
                'nullable',
                'string',
                'max:50',
            ],

            'job_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_custom' => [
                'nullable',
                'boolean',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ]);

        try {

            $position = $this->service->create($validated);

            return response()->json([
                'success' => true,
                'message' => 'سمت با موفقیت ایجاد شد.',
                'data' => $position,
            ], 201);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * نمایش یک سمت
     */
    public function show(
        OrganizationalPosition $organizationalPosition
    ): JsonResponse
    {

        $organizationalPosition->load([
            'unit',
            'parent',
            'children',
            'employees.user:id,name,mobile',
        ]);

        return response()->json([
            'success' => true,
            'data' => $organizationalPosition,
        ]);
    }

    /**
     * ویرایش
     */
    public function update(
        Request                $request,
        OrganizationalPosition $organizationalPosition
    ): JsonResponse
    {

        $validated = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                'exists:organizational_positions,id',
            ],

            'post_code' => [
                'nullable',
                'string',
                'max:50',
            ],

            'post_title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'job_code' => [
                'nullable',
                'string',
                'max:50',
            ],

            'job_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ]);

        try {

            $position = $this->service->update(
                $organizationalPosition,
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'سمت با موفقیت ویرایش شد.',
                'data' => $position,
            ]);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * جابه‌جایی سمت
     */
    public function move(
        Request                $request,
        OrganizationalPosition $organizationalPosition
    ): JsonResponse
    {

        $validated = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                'exists:organizational_positions,id',
            ],

            'organizational_unit_id' => [
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

        try {

            $position = $this->service->move(
                $organizationalPosition,
                $validated['parent_id'] ?? null,
                $validated['sort_order'] ?? null,
                $validated['organizational_unit_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'ساختار سمت با موفقیت تغییر کرد.',
                'data' => $position,
            ]);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * حذف
     */
    public function destroy(
        OrganizationalPosition $organizationalPosition
    ): JsonResponse
    {

        try {

            $this->service->delete(
                $organizationalPosition
            );

            return response()->json([
                'success' => true,
                'message' => 'سمت با موفقیت حذف شد.',
            ]);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

}
