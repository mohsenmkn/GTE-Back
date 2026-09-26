<?php

namespace Modules\Finance\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\App\Services\EquipmentCostService;

class EquipmentCostController extends Controller
{
    protected $service;

    public function __construct(EquipmentCostService $service)
    {
        $this->middleware('permission:equipment_costs.view');
        $this->service = $service;
    }

    public function getTypes()
    {
        try {
            $types = $this->service->getAccessibleEquipmentTypes();
            return response()->json([
                'success' => true,
                'data' => $types,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت انواع تجهیزات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getEquipments(Request $request)
    {
        try {
            $request->validate(['type_id' => 'required|integer']);

            $equipments = $this->service->getEquipmentsByType($request->type_id);

            return response()->json([
                'success' => true,
                'data' => $equipments,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت لیست تجهیزات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCostReport(Request $request)
    {
        try {
            $request->validate([
                'equipment_code' => 'required|string',
                'type_id' => 'required|integer',
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ]);

            $report = $this->service->getEquipmentCostReport(
                $request->equipment_code,
                $request->type_id,
                $request->from_date,
                $request->to_date
            );

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت گزارش هزینه‌ها',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
