<?php

namespace Modules\Finance\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\App\Models\EquipmentTypeAccess;
use Modules\Finance\App\Repositories\EquipmentCostRepository;

class EquipmentTypeAccessController extends Controller
{
    protected $repository;

    public function __construct(EquipmentCostRepository $repository)
    {
        $this->middleware('permission:equipment_costs.manage');
        $this->repository = $repository;
    }

    public function index()
    {
        $accesses = EquipmentTypeAccess::with(['user:id,name', 'role:id,name'])
            ->orderBy('dl_type_ref')
            ->get()
            ->map(function ($access) {
                return [
                    'id' => $access->id,
                    'user' => $access->user?->name,
                    'role' => $access->role?->name,
                    'dl_type_ref' => $access->dl_type_ref,
                    'dl_type_title' => $access->dl_type_title,
                    'can_view' => $access->can_view,
                    'can_export' => $access->can_export,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $accesses,
        ]);
    }

    public function getAllTypes()
    {
        return response()->json([
            'success' => true,
            'data' => $this->repository->getAllEquipmentTypesFromDB(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'role_id' => 'nullable|exists:roles,id',
            'dl_type_ref' => 'required|integer',
            'dl_type_title' => 'nullable|string',
            'can_view' => 'boolean',
            'can_export' => 'boolean',
        ]);

        if (empty($validated['user_id']) && empty($validated['role_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'حداقل یکی از کاربر یا نقش باید انتخاب شود',
            ], 422);
        }

        $access = EquipmentTypeAccess::create([
            'user_id' => $validated['user_id'] ?? null,
            'role_id' => $validated['role_id'] ?? null,
            'dl_type_ref' => $validated['dl_type_ref'],
            'dl_type_title' => $validated['dl_type_title'],
            'can_view' => $validated['can_view'] ?? true,
            'can_export' => $validated['can_export'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'دسترسی با موفقیت اعطا شد',
            'data' => $access,
        ], 201);
    }

    public function destroy($id)
    {
        $access = EquipmentTypeAccess::findOrFail($id);
        $access->delete();

        return response()->json([
            'success' => true,
            'message' => 'دسترسی حذف شد',
        ]);
    }

    public function myAccesses()
    {
        $types = $this->repository->getAccessibleEquipmentTypes();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }
}
