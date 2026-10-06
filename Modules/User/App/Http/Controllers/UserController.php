<?php

namespace Modules\User\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Modules\Acl\App\Models\Group;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeePosition;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

public function index(Request $request)
{
    $perPage = max(1, min((int) $request->input('per_page', 15), 100));

    $query = EmployeePosition::query()
        ->with([
            // اطلاعات کاربر
            'user.roles',

            // واحد سازمانی
            'unit',
        ])

        // جستجو
        ->when($request->filled('search'), function ($q) use ($request) {
            $search = trim($request->input('search'));

            $q->where(function ($q) use ($search) {

                // اطلاعات پست
                $q->where('post_title', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%")
                    ->orWhere('personnel_code', 'like', "%{$search}%")

                    // اطلاعات کاربر
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->orWhere('national_code', 'like', "%{$search}%")
                            ->orWhere('personnel_code', 'like', "%{$search}%");
                    });
            });
        })

        // فیلتر واحد سازمانی
        ->when($request->filled('unit_id'), function ($q) use ($request) {
            $q->where(
                'organizational_unit_id',
                $request->input('unit_id')
            );
        })

        // فیلتر نوع کاربر
        ->when($request->filled('employee_type'), function ($q) use ($request) {
            $q->whereHas('user', function ($q) use ($request) {
                $q->where(
                    'employee_type',
                    $request->input('employee_type')
                );
            });
        })

        // فیلتر وضعیت فعال / غیرفعال
        ->when(
            $request->has('is_active')
            && $request->input('is_active') !== '',
            function ($q) use ($request) {

                $isActive = filter_var(
                    $request->input('is_active'),
                    FILTER_VALIDATE_BOOLEAN
                );

                $q->whereHas('user', function ($q) use ($isActive) {
                    $q->where('is_active', $isActive);
                });
            }
        )

        // جدیدترین‌ها ابتدا
        ->latest();

    $positions = $query->paginate($perPage);

    /*
     * خروجی را به شکلی برمی‌گردانیم که
     * Frontend بتواند مستقیماً اطلاعات User را مصرف کند.
     */
    $positions->getCollection()->transform(function ($position) {

        return [
            'id'              => $position->id,

            // اطلاعات پرسنلی
            'personnel_code'  => $position->personnel_code,

            // اطلاعات پست
            'post_title'      => $position->post_title,
            'job_title'       => $position->job_title,

            // واحد سازمانی
            'unit'            => $position->unit
                ? [
                    'id'    => $position->unit->id,
                    'title' => $position->unit->title,
                ]
                : null,

            // اطلاعات User
            'user'            => $position->user
                ? [
                    'id'             => $position->user->id,
                    'name'           => $position->user->name,
                    'mobile'         => $position->user->mobile,
                    'national_code'  => $position->user->national_code,
                    'personnel_code' => $position->user->personnel_code,
                    'email'          => $position->user->email,
                    'employee_type'  => $position->user->employee_type,
                    'is_active'      => (bool) $position->user->is_active,

                    // ⭐ نقش‌های کاربر
                    'roles'          => $position->user->roles
                        ->map(function ($role) {
                            return [
                                'id'   => $role->id,
                                'name' => $role->name,
                            ];
                        })
                        ->values(),
                ]
                : null,
        ];
    });

    return response()->json($positions);
}



    // ایجاد کاربر جدید
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => [
                'required',
                'regex:/^09\d{9}$/',
                Rule::unique('users', 'mobile'),
            ],
            'password' => ['required', 'string', 'min:6'],
            'national_code' => [
                'nullable',
                'digits:10',
                Rule::unique('users', 'national_code'),
            ],
            'personnel_code' => [
                'nullable',
                'digits_between:1,20',
                Rule::unique('users', 'personnel_code'),
            ],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name'           => $validated['name'],
            'mobile'         => $validated['mobile'],
            'password'       => Hash::make($validated['password']),
            'national_code'  => $validated['national_code'] ?? null,
            'personnel_code' => $validated['personnel_code'] ?? null,
            'employee_type'  => 'personnel',
            'group_ids'   => ['nullable', 'array'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'is_active'      => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : true,
        ]);
        if ($request->has('group_ids')) {
            $user->groups()->sync(
                collect($validated['group_ids'] ?? [])
                    ->mapWithKeys(fn ($id) => [
                        $id => [
                            'assigned_by' => auth()->id(),
                            'assigned_at' => now(),
                        ]
                    ])
            );
            app(\Modules\Acl\App\Services\GroupSyncService::class)
                ->syncUserRoles($user);
        }


        if ($request->has('roles')) {
            $user->syncRoles($validated['roles'] ?? []);
        }

        return response()->json([
            'message' => 'کاربر با موفقیت ایجاد شد',
            'user'    => $user->load('roles'),
        ], 201);
    }

    // نمایش یک کاربر خاص
    public function show(User $user)
    {
        return response()->json($user);
    }

    // ویرایش کاربر
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'name'           => ['sometimes', 'required', 'string', 'max:255'],
                'mobile'         => [
                    'sometimes',
                    'required',
                    'regex:/^09\d{9}$/',
                    Rule::unique('users', 'mobile')->ignore($user->id),
                ],
                'password'       => ['nullable', 'string', 'min:6'],
                'national_code'  => [
                    'nullable',
                    'digits:10',
                    Rule::unique('users', 'national_code')->ignore($user->id),
                ],
                'personnel_code' => [
                    'nullable',
                    'digits_between:1,20',
                    Rule::unique('users', 'personnel_code')->ignore($user->id),
                ],
                'roles'          => ['nullable', 'array'],
                'roles.*'        => ['string', 'exists:roles,name'],
                'is_active'      => ['nullable', 'boolean'],
            ]);

            /**
             * فقط فیلدهایی که ارسال شده‌اند آپدیت شوند
             */
            $dataToUpdate = collect($validated)
                ->only([
                    'name',
                    'mobile',
                    'national_code',
                    'personnel_code',
                ])
                ->toArray();
            if ($request->has('group_ids')) {
                $user->groups()->sync(
                    collect($validated['group_ids'] ?? [])
                        ->mapWithKeys(fn ($id) => [
                            $id => [
                                'assigned_by' => auth()->id(),
                                'assigned_at' => now(),
                            ]
                        ])
                );
                app(\Modules\Acl\App\Services\GroupSyncService::class)
                    ->syncUserRoles($user);
            }

            if (!empty($validated['password'])) {
                $dataToUpdate['password'] = Hash::make($validated['password']);
            }

            /**
             * مدیریت وضعیت فعال/غیرفعال
             */
            if (array_key_exists('is_active', $validated)) {
                $newStatus = (bool) $validated['is_active'];

                // پیمانکار نباید فعال شود
                if ($user->isContractor() && $newStatus) {
                    return response()->json([
                        'message' => 'امکان فعال‌سازی پیمانکار وجود ندارد',
                    ], 403);
                }

                // کاربر نباید بتواند خودش را غیرفعال کند
                if (
                    $user->id === auth()->id()
                    && !$newStatus
                ) {
                    return response()->json([
                        'message' => 'شما نمی‌توانید حساب کاربری خودتان را غیرفعال کنید',
                    ], 403);
                }

                $dataToUpdate['is_active'] = $newStatus;
            }

            /**
             * اگر پیمانکار است، وضعیت همیشه غیرفعال باقی بماند
             */
            if ($user->isContractor()) {
                $dataToUpdate['is_active'] = false;
            }

            $user->update($dataToUpdate);

            /**
             * فقط زمانی نقش‌ها را sync کن که فیلد roles ارسال شده باشد
             */
            if ($request->has('roles')) {
                $user->syncRoles($validated['roles'] ?? []);
            }

            /**
             * اگر کاربر غیرفعال شد، توکن‌های او حذف شود
             */
            if (isset($dataToUpdate['is_active']) && !$dataToUpdate['is_active']) {
                $user->tokens()->delete();
            }

            return response()->json([
                'message' => 'کاربر با موفقیت ویرایش شد',
                'user'    => $user->fresh()->load('roles'),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'خطای اعتبارسنجی',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // حذف کاربر
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'کاربر با موفقیت حذف شد'],200);
    }



    /**
     * دریافت لیست کاربران قابل انتخاب برای گروه‌ها
     * با قابلیت فیلتر بر اساس سمت، واحد و جستجوی عمومی
     */
    public function selectable(Request $request)
    {
        $request->validate([
            'search'         => ['nullable', 'string', 'max:100'],
            'position_search'=> ['nullable', 'string', 'max:100'],
            'unit_id'        => ['nullable', 'integer', 'exists:organizational_units,id'],
            'is_active'      => ['nullable', 'boolean'],
            'exclude_group'  => ['nullable', 'integer', 'exists:groups,id'],
            'per_page'       => ['nullable', 'integer', 'max:200'],
        ]);

        $perPage = min((int) $request->input('per_page', 100), 200);

        $query = EmployeePosition::query()
            ->with(['user', 'unit'])
            // فقط کاربران فعال و غیر پیمانکار
            ->whereHas('user', function ($q) use ($request) {
                $q->where('is_active', true)
                    ->where('employee_type', '!=', 'contractor');

                if ($request->filled('search')) {
                    $search = trim($request->input('search'));
                    $q->where(function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->orWhere('national_code', 'like', "%{$search}%")
                            ->orWhere('personnel_code', 'like', "%{$search}%");
                    });
                }
            })
            // فیلتر سمت (post_title یا job_title)
            ->when($request->filled('position_search'), function ($q) use ($request) {
                $pos = trim($request->input('position_search'));
                $q->where(function ($q2) use ($pos) {
                    $q2->where('post_title', 'like', "%{$pos}%")
                        ->orWhere('job_title', 'like', "%{$pos}%");
                });
            })
            // فیلتر واحد سازمانی
            ->when($request->filled('unit_id'), function ($q) use ($request) {
                $q->where('organizational_unit_id', $request->input('unit_id'));
            })
            // حذف اعضای گروه فعلی (اختیاری)
            ->when($request->filled('exclude_group'), function ($q) use ($request) {
                $q->whereDoesntHave('user.groups', function ($q2) use ($request) {
                    $q2->where('groups.id', $request->input('exclude_group'));
                });
            })
            ->latest();

        $positions = $query->paginate($perPage);

        // تبدیل به فرمت مناسب فرانت
        $positions->getCollection()->transform(function ($position) {
            $user = $position->user;
            if (!$user) return null;

            return [
                'id'             => $user->id,
                'name'           => $user->name,
                'mobile'         => $user->mobile,
                'personnel_code' => $user->personnel_code ?? $position->personnel_code,
                'post_title'     => $position->post_title,
                'job_title'      => $position->job_title,
                'unit_id'        => $position->organizational_unit_id,
                'unit_title'     => $position->unit?->title,
                // متن نمایشی برای MultiSelect
                'display_label'  => sprintf(
                    '%s — %s — %s',
                    $user->name,
                    $position->post_title ?: $position->job_title ?: 'بدون سمت',
                    $position->unit?->title ?: 'بدون واحد'
                ),
            ];
        });

        // حذف موارد null
        $positions->setCollection(
            $positions->getCollection()->filter()->values()
        );

        return response()->json($positions);
    }

    /**
     * دریافت لیست سمت‌های پرکاربرد (برای dropdown فیلتر)
     */
    public function positions()
    {
        $positions = EmployeePosition::query()
            ->select('post_title')
            ->whereNotNull('post_title')
            ->where('post_title', '!=', '')
            ->distinct()
            ->orderBy('post_title')
            ->limit(100)
            ->pluck('post_title');

        return response()->json(['data' => $positions]);
    }


    // در Modules/Acl/App/Http/Controllers/GroupController.php

    public function users(Group $group): JsonResponse
    {
        $users = $group->users()
            ->with('employeePosition.unit')
            ->get(['users.*'])
            ->map(function ($u) {
                $position = $u->employeePosition;
                return [
                    'id'             => $u->id,
                    'name'           => $u->name,
                    'mobile'         => $u->mobile,
                    'personnel_code' => $u->personnel_code,
                    'post_title'     => $position?->post_title,
                    'job_title'      => $position?->job_title,
                    'unit_title'     => $position?->unit?->title,
                    'is_active'      => (bool) $u->is_active,
                    'assigned_at'    => $u->pivot->assigned_at,
                    'assigned_by'    => $u->pivot->assigned_by,
                ];
            });

        return response()->json(['data' => $users]);
    }


    // در فایل UserController.php

    public function search(Request $request): JsonResponse
    {
        $query = User::query()->where('is_active', true);

        if ($request->filled('q')) {
            $search = trim($request->input('q'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('personnel_code', 'like', "%{$search}%")
                    ->orWhere('national_code', 'like', "%{$search}%");
            });
        }

        $users = $query->select('id', 'name', 'mobile', 'personnel_code', 'national_code')
            ->limit(20)
            ->get();

        return response()->json(['data' => $users]);
    }

}
