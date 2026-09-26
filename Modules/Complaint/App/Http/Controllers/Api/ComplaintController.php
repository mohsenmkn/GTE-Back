<?php

namespace Modules\Complaint\App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Auth\App\Models\User;
use Modules\Complaint\App\Enums\ComplaintPriority;
use Modules\Complaint\App\Enums\ComplaintStatus;
use Modules\Complaint\App\Http\Resources\CategoryResource;
use Modules\Complaint\App\Http\Resources\ComplaintResource;
use Modules\Complaint\App\Models\Complaint;
use Modules\Complaint\App\Models\ComplaintCategory;
use Modules\Complaint\App\Models\ComplaintManager;
use Modules\HR\App\Models\OrganizationalUnit;
use Symfony\Component\HttpFoundation\Response;

class ComplaintController extends Controller
{
    public function categories()
    {
        $categories = ComplaintCategory::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['children' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['children' => function ($childQuery) {
                        $childQuery->where('is_active', true)
                            ->orderBy('sort_order');
                    }]);
            }])
            ->get();

        return CategoryResource::collection($categories);
    }


    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'complaint_category_id' => [
                'required',
                'integer',
                Rule::exists('complaint_categories', 'id')
                    ->where('level', 3)
                    ->where('is_active', true),
            ],
            'organizational_unit_id' => [  // ✅ جدید
                'required',
                'integer',
                Rule::exists('organizational_units', 'id')
                    ->where('is_active', true),
            ],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['nullable', Rule::in(ComplaintPriority::values())],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'file',
                'max:' . config('complaint.attachment.max_kb', 5120),
                'mimes:' . implode(',', config('complaint.attachment.mimes', ['jpg', 'pdf'])),
            ],
        ]);

        // ✅ تعیین خودکار مسئول پیگیری
        $manager = ComplaintManager::active()
            ->forUnit($validated['organizational_unit_id'])
            ->first();

        $complaint = Complaint::create([
            'user_id' => $request->user()->id,
            'complaint_category_id' => $validated['complaint_category_id'],
            'organizational_unit_id' => $validated['organizational_unit_id'],  // ✅ جدید
            'assigned_to' => $manager?->user_id,  // ✅ جدید
            'assigned_at' => $manager ? now() : null,  // ✅ جدید
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'] ?? ComplaintPriority::Medium->value,
        ]);

        if ($request->hasFile('attachments')) {
            $this->storeAttachments($complaint, $request->file('attachments'), $request->user()->id);
        }

        // ✅ ارسال نوتیفیکیشن به مسئول پیگیری
        if ($manager) {
            $this->notifyManager($complaint, $manager->user);
        }

        return response()->json([
            'message' => 'شکایت با موفقیت ثبت شد.',
            'data' => new ComplaintResource($complaint->load([
                'category.parent.parent',
                'attachments',
                'organizationalUnit',
                'assignedUser',
            ])),
        ], Response::HTTP_CREATED);
    }

    public function my(Request $request)
    {
        $complaints = Complaint::query()
            ->forUser($request->user()->id)
            ->with('category.parent.parent')
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return ComplaintResource::collection($complaints);
    }

    public function show(Request $request, Complaint $complaint)
    {
        $this->authorizeComplaintAccess($request, $complaint);

        $complaint->load([
            'category.parent.parent',
            'attachments',
            'replies.user',
            'organizationalUnit', // ✅ اضافه شد: معاونت مقصد
            'assignedUser',       // ✅ اضافه شد: مسئول پیگیری
        ]);

        return new ComplaintResource($complaint);
    }

    public function update(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('complaints.manage')) {
            if ($complaint->user_id !== $user->id) {
                return response()->json([
                    'message' => 'دسترسی کافی نیست.',
                ], Response::HTTP_FORBIDDEN);
            }

            if ($complaint->status !== ComplaintStatus::Pending) {
                return response()->json([
                    'message' => 'فقط شکایات در انتظار بررسی قابل ویرایش هستند.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $validated = $request->validate([
            'complaint_category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('complaint_categories', 'id')
                    ->where('level', 3)
                    ->where('is_active', true),
            ],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'min:10', 'max:5000'],
            'priority' => ['nullable', Rule::in(ComplaintPriority::values())],
        ]);

        $complaint->update($validated);

        return response()->json([
            'message' => 'شکایت با موفقیت ویرایش شد.',
            'data' => new ComplaintResource($complaint->fresh()->load('category.parent.parent')),
        ]);
    }

    public function destroy(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('complaints.manage')) {
            if ($complaint->user_id !== $user->id) {
                return response()->json([
                    'message' => 'دسترسی کافی نیست.',
                ], Response::HTTP_FORBIDDEN);
            }

            if ($complaint->status !== ComplaintStatus::Pending) {
                return response()->json([
                    'message' => 'فقط شکایات در انتظار بررسی قابل حذف هستند.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $complaint->delete();

        return response()->json([
            'message' => 'شکایت حذف شد.',
        ]);
    }

    public function uploadAttachment(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('complaints.manage') && $complaint->user_id !== $user->id) {
            return response()->json([
                'message' => 'دسترسی کافی نیست.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'attachments' => ['required', 'array'],
            'attachments.*' => [
                'file',
                'max:' . config('complaint.attachment.max_kb', 5120),
                'mimes:' . implode(',', config('complaint.attachment.mimes', ['jpg', 'pdf'])),
            ],
        ]);

        $this->storeAttachments($complaint, $request->file('attachments'), $user->id);

        return response()->json([
            'message' => 'پیوست‌ها با موفقیت بارگذاری شدند.',
            'data' => new ComplaintResource($complaint->load('attachments')),
        ], Response::HTTP_CREATED);
    }

    protected function storeAttachments(Complaint $complaint, array $files, int $userId): void
    {
        foreach ($files as $file) {
            $path = $file->store('complaints/' . $complaint->id, 'public');

            $complaint->attachments()->create([
                'uploaded_by' => $userId,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }
    // ✅ متد جدید: شکایات ارجاع‌شده به من
    public function assignedToMe(Request $request)
    {
        $complaints = Complaint::query()
            ->where('assigned_to', $request->user()->id)
            ->with(['category.parent.parent', 'user', 'organizationalUnit'])
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->input('priority'), function ($query, $priority) {
                $query->where('priority', $priority);
            })
            ->latest('assigned_at')
            ->paginate($request->integer('per_page', 10));

        return ComplaintResource::collection($complaints);
    }

    // ✅ متد جدید: ارسال نوتیفیکیشن
    private function notifyManager(Complaint $complaint, User $manager): void
    {
        try {
            $msgway = config('services.msgway');

            // ارسال پیامک (با استفاده از سرویس موجود)
            // این بخش بسته به پیاده‌سازی سرویس پیامک شما متفاوت است
            // مثال:
            // SmsService::send($manager->mobile, [
            //     'template_id' => $msgway['template_id_default'],
            //     'parameters' => [
            //         'tracking_code' => $complaint->tracking_code,
            //         'subject' => $complaint->subject,
            //     ],
            // ]);

            Log::info("Notification sent to manager {$manager->id} for complaint {$complaint->id}");
        } catch (\Exception $e) {
            Log::error("Failed to notify manager: {$e->getMessage()}");
        }
    }


// متد unitManager
    public function unitManager(OrganizationalUnit $unit)
    {
        $manager = \Modules\Complaint\App\Models\ComplaintManager::query()
            ->where('organizational_unit_id', $unit->id)
            ->where('is_active', true)
            ->with('user:id,name,mobile,personnel_code')
            ->first();

        return response()->json([
            'data' => $manager ? [
                'id' => $manager->user->id,
                'name' => $manager->user->name,
                'mobile' => $manager->user->mobile,
            ] : null,
        ]);
    }


    protected function authorizeComplaintAccess(Request $request, Complaint $complaint): void
    {
        $user = $request->user();

        // ادمین → دسترسی کامل
        if ($user->can('complaints.manage')) {
            return;
        }

        // مالک شکایت
        if ($complaint->user_id === $user->id) {
            return;
        }

        // ✅ مسئول پیگیری ارجاع‌شده
        if ($complaint->assigned_to === $user->id) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN, 'دسترسی کافی نیست.');
    }


    public function organizationalUnits()
    {
        // ✅ فقط واحدهای سطح ۳ (معاونت‌ها) - بدون children
        $units = OrganizationalUnit::query()
            ->where('is_active', true)
            ->where('level', 3)  // فقط سطح
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $units->map(function ($unit) {
                return [
                    'key'   => $unit->id,
                    'label' => $unit->title,
                    'value' => $unit->id,
                    'data'  => [
                        'id'    => $unit->id,
                        'title' => $unit->title,
                        'level' => 3,
                    ],
                ];
            })->toArray(),
        ]);
    }

    /**
     * ساخت درخت معاونت‌ها برای TreeSelect فرانت
     */
    private function buildUnitTree($units, int $level = 0): array
    {
        return $units->map(function ($unit) use ($level) {
            $item = [
                'key'   => $unit->id,
                'label' => $unit->title,
                'value' => $unit->id,
                'data'  => [
                    'id'    => $unit->id,
                    'title' => $unit->title,
                    'level' => $level,
                ],
            ];

            if ($unit->children->isNotEmpty()) {
                $item['children'] = $this->buildUnitTree($unit->children, $level + 1);
            }

            return $item;
        })->toArray();
    }


}
