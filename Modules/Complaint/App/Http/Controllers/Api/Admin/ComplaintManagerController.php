<?php

namespace Modules\Complaint\App\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Auth\App\Models\User;
use Modules\Complaint\App\Http\Resources\ComplaintManagerResource;
use Modules\Complaint\App\Models\ComplaintManager;
use Symfony\Component\HttpFoundation\Response;

class ComplaintManagerController extends Controller
{
    /**
     * لیست مسئولین پیگیری با pagination
     */
    public function index(Request $request)
    {
        $managers = ComplaintManager::query()
            ->with([
                'organizationalUnit:id,title,level',
                'user:id,name,mobile,email,personnel_code'
            ])
            ->when($request->input('is_active') !== null, function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ComplaintManagerResource::collection($managers);
    }

    /**
     * لیست کاربران برای انتخاب مسئول پیگیری (با جستجو)
     * ✅ بدون شرط is_active روی User (چون ستون وجود ندارد)
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query()
            ->select('id', 'name', 'mobile', 'email', 'personnel_code');

        // جستجو
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('mobile', 'LIKE', "%{$search}%")
                    ->orWhere('personnel_code', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // فیلتر بر اساس معاونت (اختیاری)
        if ($unitId = $request->input('organizational_unit_id')) {
            $query->whereHas('employeePosition', function ($q) use ($unitId) {
                $q->where('organizational_unit_id', $unitId);
            });
        }

        $users = $query
            ->orderBy('name')
            ->limit($request->integer('per_page', 50))
            ->get();

        return response()->json([
            'data' => $users->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile,
                'email' => $user->email,
                'personnel_code' => $user->personnel_code,
                'display_name' => $user->name . ($user->personnel_code ? " ({$user->personnel_code})" : ''),
            ]),
        ]);
    }

    /**
     * تعیین مسئول پیگیری جدید برای یک معاونت
     * ✅ منطق جایگزینی: اگر مسئول فعال وجود دارد، فقط user_id را آپدیت می‌کنیم
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organizational_unit_id' => [
                'required',
                'integer',
                Rule::exists('organizational_units', 'id')->where('is_active', true),
            ],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ]);

        // بررسی می‌کنیم آیا برای این معاونت، مسئول فعالی وجود دارد یا خیر
        $manager = ComplaintManager::query()
            ->where('organizational_unit_id', $validated['organizational_unit_id'])
            ->where('is_active', true)
            ->first();

        if ($manager) {
            // ✅ اگر وجود دارد، فقط کاربر را جایگزین می‌کنیم (بدون دستکاری is_active)
            $manager->update([
                'user_id' => $validated['user_id'],
            ]);
        } else {
            // ✅ اگر وجود ندارد، یک رکورد جدید می‌سازیم
            $manager = ComplaintManager::create([
                'organizational_unit_id' => $validated['organizational_unit_id'],
                'user_id' => $validated['user_id'],
                'is_active' => true,
            ]);
        }

        return response()->json([
            'message' => 'مسئول پیگیری با موفقیت تعیین/ویرایش شد.',
            'data' => new ComplaintManagerResource(
                $manager->load('organizationalUnit:id,title', 'user:id,name,mobile,personnel_code')
            ),
        ], Response::HTTP_CREATED);
    }

    /**
     * ویرایش مسئول پیگیری
     * ✅ بدون شرط is_active برای User
     */
    public function update(Request $request, ComplaintManager $complaintManager): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        // اگر کاربر تغییر می‌کند، مسئول قبلی غیرفعال شود
        if (isset($validated['user_id']) && $validated['user_id'] !== $complaintManager->user_id) {
            ComplaintManager::query()
                ->where('organizational_unit_id', $complaintManager->organizational_unit_id)
                ->where('is_active', true)
                ->where('id', '!=', $complaintManager->id)
                ->update(['is_active' => false]);
        }

        $complaintManager->update($validated);

        return response()->json([
            'message' => 'مسئول پیگیری با موفقیت ویرایش شد.',
            'data' => new ComplaintManagerResource(
                $complaintManager->fresh()->load('organizationalUnit:id,title', 'user:id,name,mobile,personnel_code')
            ),
        ]);
    }

    /**
     * حذف مسئول پیگیری (soft delete - غیرفعال کردن)
     */
    public function destroy(ComplaintManager $complaintManager): JsonResponse
    {
        $complaintManager->update(['is_active' => false]);

        return response()->json([
            'message' => 'مسئول پیگیری با موفقیت حذف شد.',
        ]);
    }
}
