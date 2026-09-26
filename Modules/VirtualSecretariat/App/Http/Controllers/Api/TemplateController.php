<?php


namespace Modules\VirtualSecretariat\App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\VirtualSecretariat\App\Http\Requests\StoreTemplateRequest;
use Modules\VirtualSecretariat\App\Http\Resources\TemplateResource;
use Modules\VirtualSecretariat\App\Models\VsTemplate;

class TemplateController extends Controller
{
    /**
     * لیست قالب‌ها
     */
    public function index(): JsonResponse
    {
        $templates = VsTemplate::withCount('requests')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => TemplateResource::collection($templates),
        ]);
    }

    /**
     * ذخیره قالب جدید
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $template = VsTemplate::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'قالب با موفقیت ایجاد شد',
            'data' => new TemplateResource($template),
        ], 201);
    }

    /**
     * نمایش جزئیات قالب
     */
    public function show(VsTemplate $template): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new TemplateResource($template),
        ]);
    }

    /**
     * به‌روزرسانی قالب
     */
    public function update(StoreTemplateRequest $request, VsTemplate $template): JsonResponse
    {
        // ✅ اینجا $template باید object باشد
        $template->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'قالب با موفقیت به‌روزرسانی شد',
            'data' => new TemplateResource($template),
        ]);
    }

    /**
     * حذف قالب
     */
    public function destroy(VsTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'قالب با موفقیت حذف شد',
        ]);
    }

    /**
     * فعال/غیرفعال کردن قالب
     */
    public function toggleStatus(VsTemplate $template): JsonResponse
    {
        $template->update(['is_active' => !$template->is_active]);

        return response()->json([
            'success' => true,
            'message' => $template->is_active ? 'قالب فعال شد' : 'قالب غیرفعال شد',
        ]);
    }
}
