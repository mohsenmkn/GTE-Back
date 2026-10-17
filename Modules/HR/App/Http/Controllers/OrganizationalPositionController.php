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
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
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
        Request $request,
        OrganizationalPosition $organizationalPosition
    ): JsonResponse {
        $validated = $request->validate([
            'user_id' => [ // <--- این خط را اضافه کنید
                'nullable',
                'integer',
                'exists:users,id',
            ],
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


    /**
     * تخصیص گروهی پرسنل به سمت
     *
     * POST /api/organizational-positions/{position}/assign-employees
     * Body: { "user_ids": [1, 2, 3, 4, 5] }
     */
    public function assignEmployees(
        Request $request,
        OrganizationalPosition $organizationalPosition
         ): JsonResponse {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        try {
            $result = $this->service->assignEmployees(
                $organizationalPosition,
                $validated['user_ids']
            );

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    '%d نفر با موفقیت تخصیص یافتند.',
                    count($result['assigned'])
                ),
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }




    /**
     * حذف تخصیص پرسنل از سمت
     *
     * POST /organizational-positions/{position}/unassign-employees
     * Body: { "user_ids": [1, 2, 3] } یا {} برای حذف همه
     */
    public function unassignEmployees(
        Request $request,
        OrganizationalPosition $organizationalPosition
    ): JsonResponse {
        $validated = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        try {
            $result = $this->service->unassignEmployees(
                $organizationalPosition,
                $validated['user_ids'] ?? []
            );

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    '%d نفر از سمت حذف شدند.',
                    $result['removed_count']
                ),
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * لیست پرسنل قابل تخصیص به سمت
     *
     * GET /organizational-positions/{position}/available-employees?search=xxx
     */
    public function availableEmployees(
        Request $request,
        OrganizationalPosition $organizationalPosition
    ): JsonResponse {
        $employees = $this->service->availableEmployees(
            $organizationalPosition,
            $request->input('search')
        );

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    /**
     * پیش‌نمایش import از فایل اکسل
     *
     * POST /organizational-positions/{position}/excel/preview
     * Body: multipart/form-data with 'file'
     */
    public function previewExcelImport(
        Request $request,
        OrganizationalPosition $organizationalPosition
    ): JsonResponse {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // حداکثر 10 مگابایت
        ]);

        try {
            $result = $this->service->previewExcelImport(
                $organizationalPosition,
                $request->file('file')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * تایید import از فایل اکسل
     *
     * POST /organizational-positions/{position}/excel/confirm
     * Body: { "user_ids": [1, 2, 3] }
     */
    public function confirmExcelImport(
        Request $request,
        OrganizationalPosition $organizationalPosition
    ): JsonResponse {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        try {
            $result = $this->service->assignEmployees(
                $organizationalPosition,
                $validated['user_ids']
            );

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    '%d نفر از فایل اکسل با موفقیت تخصیص یافتند.',
                    count($result['assigned'])
                ),
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

}
