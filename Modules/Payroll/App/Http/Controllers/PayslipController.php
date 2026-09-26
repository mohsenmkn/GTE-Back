<?php

// Modules/Payroll/App/Http/Controllers/PayslipController.php

namespace Modules\Payroll\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Payroll\App\Repositories\PayrollRepository;
use Modules\Payroll\App\Services\PayrollService;
use Modules\Payroll\App\Http\Resources\PayslipResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class PayslipController extends Controller
{
    public function __construct(
        private PayrollService $payrollService
    ) {}

    /**
     * GET /api/payroll/months
     * لیست ماه‌های موجود برای کاربر فعلی
     */
    public function months(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->personnel_code)) {
            return response()->json([
                'message' => 'کد پرسنلی برای حساب کاربری شما تعریف نشده است.',
                'data' => [],
            ], 404);
        }

        $months = $this->payrollService->getAvailableMonths($user->personnel_code);

        return response()->json([
            'data' => $months,
        ]);
    }

    /**
     * GET /api/payroll/payslip/{yearMonth}
     * فیش حقوقی یک ماه خاص برای کاربر فعلی
     */
    public function show(Request $request, int $yearMonth): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('Payroll.view')){
            return response()->json([
                'message' => 'دسترسی ندارید.',
            ], 403);
        }

        if (empty($user->personnel_code)) {
            return response()->json([
                'message' => 'کد پرسنلی برای حساب کاربری شما تعریف نشده است.',
            ], 404);
        }

        // اعتبارسنجی فرمت سال‌ماه
        if (!$this->isValidYearMonth($yearMonth)) {
            return response()->json([
                'message' => 'فرمت سال‌ماه نامعتبر است. مثال صحیح: 140503',
            ], 422);
        }

        $payslip = $this->payrollService->getPayslip($user->personnel_code, $yearMonth);

        if (!$payslip) {
            return response()->json([
                'message' => 'فیش حقوقی برای این ماه یافت نشد.',
            ], 404);
        }

        // لاگ دسترسی
        Log::channel('payroll')->info('Payslip viewed', [
            'user_id' => $user->id,
            'personnel_code' => $user->personnel_code,
            'year_month' => $yearMonth,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'data' => new PayslipResource($payslip),
        ]);
    }

    /**
     * GET /api/payroll/payslip/{yearMonth}/pdf
     * دانلود فیش حقوقی به صورت PDF
     */
    public function downloadPdf(Request $request, int $yearMonth): \Illuminate\Http\Response
    {
        $user = $request->user();

        if (empty($user->personnel_code)) {
            abort(404, 'کد پرسنلی تعریف نشده است.');
        }

        $payslip = $this->payrollService->getPayslip($user->personnel_code, $yearMonth);

        if (!$payslip) {
            abort(404, 'فیش حقوقی یافت نشد.');
        }

        $pdf = Pdf::loadView('Payroll::pdf.payslip', [
            'payslip' => $payslip,
        ]);

        // تنظیمات فونت فارسی
        $pdf->setOptions([
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        $fileName = "fish-hoghooghi-{$payslip->personnelCode}-{$yearMonth}.pdf";

        return $pdf->download($fileName);
    }

    /**
     * GET /api/payroll/employees/{employeeId}/payslip/{yearMonth}
     * مشاهده فیش حقوقی دیگران (مخصوص مدیران/HR)
     */
    public function showForEmployee(Request $request, int $employeeId, int $yearMonth): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('AdminPayroll.view')){
            return response()->json([
                'message' => 'دسترسی ندارید.',
            ], 403);
        }

        $payslip = $this->payrollService->getPayslipByEmployeeId($employeeId, $yearMonth);

        if (!$payslip) {
            return response()->json([
                'message' => 'فیش حقوقی یافت نشد.',
            ], 404);
        }

        // لاگ دسترسی مدیر
        Log::channel('payroll')->info('Payslip viewed by manager', [
            'viewer_id' => $user->id,
            'target_employee_id' => $employeeId,
            'year_month' => $yearMonth,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'data' => new PayslipResource($payslip),
        ]);
    }

    /**
     * GET /api/payroll/employees
     * لیست کارمندان (برای جستجو - مخصوص مدیران/HR)
     */
    public function employees(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('AdminPayroll.view')){
            return response()->json([
                'message' => 'دسترسی ندارید.',
            ], 403);
        }

        $search = $request->query('search');
        $employees = app(PayrollRepository::class)->getEmployeesList($search);

        return response()->json([
            'data' => $employees,
        ]);
    }

    /**
     * اعتبارسنجی فرمت سال‌ماه
     */
    private function isValidYearMonth(int $yearMonth): bool
    {
        $year = intdiv($yearMonth, 100);
        $month = $yearMonth % 100;

        return $year >= 1380 && $year <= 1500 && $month >= 1 && $month <= 12;
    }

    /**
     * GET /api/payroll/employees/{employeeId}/payslip/{yearMonth}/pdf
     * دانلود PDF فیش حقوقی یک کارمند (برای مدیران)
     */
    public function downloadEmployeePdf(Request $request, int $employeeId, int $yearMonth): \Illuminate\Http\Response
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('AdminPayroll.view')){
            return response()->json([
                'message' => 'دسترسی ندارید.',
            ], 403);
        }

        $payslip = $this->payrollService->getPayslipByEmployeeId($employeeId, $yearMonth);

        if (!$payslip) {
            abort(404, 'فیش حقوقی یافت نشد.');
        }

        $pdf = Pdf::loadView('Payroll::pdf.payslip', [
            'payslip' => $payslip,
            'isAdmin' => true,  // 🔑 برای نمایش لوگو و نام شرکت
        ]);

        $pdf->setOptions([
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        $fileName = "fish-hoghooghi-{$payslip->personnelCode}-{$yearMonth}.pdf";

        return $pdf->download($fileName);
    }
}
