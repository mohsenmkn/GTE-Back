<?php


namespace Modules\VirtualSecretariat\App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\VirtualSecretariat\App\Services\WorkflowStatisticsService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WorkflowDashboardController extends Controller
{
    protected WorkflowStatisticsService $statsService;

    public function __construct(WorkflowStatisticsService $statsService)
    {
        $this->statsService = $statsService;
    }

    /**
     * بررسی دسترسی کاربر
     */
    private function checkAccess(Request $request): array
    {
        $user = Auth::user();
        $viewAll = $user->hasRole('admin') || $user->hasRole('super-admin') || $user->hasPermission('view_all_workflows');

        // دریافت DepartmentID کاربر
        $departmentId = DB::connection('sqlsrv_automation')
            ->table('Actors as a')
            ->join('Roles as r', 'a.RoleID', '=', 'r.Role_ID')
            ->where('a.UserID', $user->id) // یا فیلد مناسب در دیتابیس محلی
            ->whereNotNull('r.DepartmentID')
            ->value('r.DepartmentID');

        return [
            'userId' => $user->id,
            'departmentId' => $departmentId,
            'viewAll' => $viewAll,
        ];
    }

    /**
     * دریافت تمام آمارها
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $access = $this->checkAccess($request);

        try {
            $data = [
                'overall' => $this->statsService->getOverallStatistics(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll']
                ),
                'by_type' => $this->statsService->getStatisticsByType(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll']
                ),
                'by_user' => $this->statsService->getStatisticsByUser(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll']
                ),
                'by_department' => $this->statsService->getStatisticsByDepartment(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll']
                ),
                'trends' => $this->statsService->getTrends(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll']
                ),
                'delayed' => $this->statsService->getDelayedProcesses(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll']
                ),
                'avg_completion' => $this->statsService->getAverageCompletionTime(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll']
                ),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'access' => [
                    'view_all' => $access['viewAll'],
                    'department_id' => $access['departmentId'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت آمار: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * خروجی Excel
     */
    public function exportToExcel(Request $request)
    {
        $access = $this->checkAccess($request);
        $type = $request->get('type', 'overall'); // overall, by_type, by_user, delayed

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            if ($type === 'overall' || $type === 'all') {
                $sheet->setCellValue('A1', 'آمار کلی فرآیندها');
                $sheet->setCellValue('A2', 'عنوان');
                $sheet->setCellValue('B2', 'مقدار');

                $overall = $this->statsService->getOverallStatistics(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll']
                );

                $row = 3;
                foreach ($overall as $key => $value) {
                    $sheet->setCellValue('A' . $row, $this->translateKey($key));
                    $sheet->setCellValue('B' . $row, $value);
                    $row++;
                }
            } elseif ($type === 'by_type') {
                $sheet->setCellValue('A1', 'نوع فرآیند');
                $sheet->setCellValue('B1', 'تعداد کل');
                $sheet->setCellValue('C1', 'تکمیل شده');
                $sheet->setCellValue('D1', 'در حال انجام');

                $data = $this->statsService->getStatisticsByType(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll']
                );

                $row = 2;
                foreach ($data as $item) {
                    $sheet->setCellValue('A' . $row, $item->WFName);
                    $sheet->setCellValue('B' . $row, $item->count);
                    $sheet->setCellValue('C' . $row, $item->completed);
                    $sheet->setCellValue('D' . $row, $item->in_progress);
                    $row++;
                }
            } elseif ($type === 'by_user') {
                $sheet->setCellValue('A1', 'نام کاربر');
                $sheet->setCellValue('B1', 'نام خانوادگی');
                $sheet->setCellValue('C1', 'نام کاربری');
                $sheet->setCellValue('D1', 'تعداد فرآیند');
                $sheet->setCellValue('E1', 'تکمیل شده');

                $data = $this->statsService->getStatisticsByUser(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll'],
                    100
                );

                $row = 2;
                foreach ($data as $item) {
                    $sheet->setCellValue('A' . $row, $item->FirstName);
                    $sheet->setCellValue('B' . $row, $item->LastName);
                    $sheet->setCellValue('C' . $row, $item->UserName);
                    $sheet->setCellValue('D' . $row, $item->count);
                    $sheet->setCellValue('E' . $row, $item->completed);
                    $row++;
                }
            } elseif ($type === 'delayed') {
                $sheet->setCellValue('A1', 'شناسه فرآیند');
                $sheet->setCellValue('B1', 'نوع فرآیند');
                $sheet->setCellValue('C1', 'نام کاربر');
                $sheet->setCellValue('D1', 'تاریخ شروع');
                $sheet->setCellValue('E1', 'روزهای سپری شده');

                $data = $this->statsService->getDelayedProcesses(
                    $access['userId'],
                    $access['departmentId'],
                    $access['viewAll'],
                    100
                );

                $row = 2;
                foreach ($data as $item) {
                    $sheet->setCellValue('A' . $row, $item->ExecuteID);
                    $sheet->setCellValue('B' . $row, $item->WFName);
                    $sheet->setCellValue('C' . $row, $item->FirstName . ' ' . $item->LastName);
                    $sheet->setCellValue('D' . $row, $item->ExecutionDate);
                    $sheet->setCellValue('E' . $row, $item->days_elapsed);
                    $row++;
                }
            }

            // تنظیمات RTL و فونت
            $sheet->setRightToLeft(true);
            $sheet->getStyle('A1:Z1')->getFont()->setBold(true);

            $writer = new Xlsx($spreadsheet);
            $fileName = 'workflow_statistics_' . date('Y-m-d_H-i-s') . '.xlsx';

            return response()->stream(function () use ($writer) {
                $writer->save('php://output');
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ایجاد فایل Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ترجمه کلیدها به فارسی
     */
    private function translateKey(string $key): string
    {
        $translations = [
            'total' => 'کل فرآیندها',
            'completed' => 'تکمیل شده',
            'in_progress' => 'در حال انجام',
            'avg_days' => 'میانگین روز',
            'today' => 'امروز',
            'this_week' => 'این هفته',
            'delayed' => 'معوق',
        ];

        return $translations[$key] ?? $key;
    }
}
