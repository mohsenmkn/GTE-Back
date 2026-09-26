<?php

namespace Modules\Library\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Library\App\Services\LibraryService;
use Modules\Library\App\Services\Sms\SmsManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    private SmsManager $smsManager;

    public function __construct(
        private LibraryService $libraryService,
        SmsManager $smsManager
    ) {
        $this->smsManager = $smsManager;
    }

    /**
     * GET /api/library/my-reservations
     */
    public function myReservations(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status');

        $reservations = $this->libraryService->getUserReservations($user->id, $status);

        return response()->json(['data' => $reservations]);
    }

    /**
     * POST /api/library/reservations
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_copy_id' => 'required|exists:library_book_copies,id',
            'expected_pickup_date' => 'required|date|after_or_equal:today',
        ], [
            // 🔑 پیام‌های واضح فارسی
            'expected_pickup_date.required' => 'لطفاً تاریخ مراجعه برای تحویل کتاب را انتخاب کنید.',
            'expected_pickup_date.date' => 'تاریخ وارد شده معتبر نیست. لطفاً یک تاریخ درست انتخاب کنید.',
            'expected_pickup_date.after_or_equal' => 'تاریخ تحویل نمی‌تواند قبل یا امروز باشد. لطفاً  یک تاریخ بعد از امروز انتخاب کنید.',
            'book_copy_id.required' => 'لطفاً یک نسخه از کتاب را انتخاب کنید.',
            'book_copy_id.exists' => 'نسخه انتخاب‌شده معتبر نیست یا حذف شده است.',
        ]);

        try {
            $reservation = $this->libraryService->createReservation(
                $request->user()->id,
                $validated['book_copy_id'],
                $validated['expected_pickup_date']
            );

            return response()->json([
                'message' => 'رزرو با موفقیت ثبت شد. در انتظار تایید مدیر.',
                'data' => $reservation,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/library/reservations/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $reservation = $this->libraryService->cancelReservation($id, $request->user()->id);

            return response()->json([
                'message' => 'رزرو با موفقیت لغو شد.',
                'data' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ========== Admin Endpoints ==========

    /**
     * GET /api/library/admin/reservations
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'search', 'from_date', 'to_date']);
        $reservations = $this->libraryService->getAllReservations($filters);

        return response()->json([
            'data' => $reservations->items(),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'total' => $reservations->total(),
            ],
        ]);
    }

    /**
     * PUT /api/library/admin/reservations/{id}/approve
     * 🔑 با ارسال پیامک تایید
     */
    public function approve(int $id): JsonResponse
    {
        try {
            $reservation = $this->libraryService->approveReservation($id);

            // ارسال پیامک تایید به کاربر
            $this->smsManager->sendApprovalNotification($reservation);

            return response()->json([
                'message' => 'رزرو تایید شد و پیامک ارسال گردید.',
                'data' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/library/admin/reservations/{id}/reject
     */
    public function reject(int $id): JsonResponse
    {
        try {
            $reservation = $this->libraryService->rejectReservation($id);

            return response()->json([
                'message' => 'رزرو رد شد.',
                'data' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/library/admin/reservations/{id}/pickup
     * 🔑 ثبت تحویل کتاب
     */
    public function pickup(int $id): JsonResponse
    {
        try {
            $reservation = $this->libraryService->pickupBook($id);

            return response()->json([
                'message' => 'تحویل کتاب ثبت شد. دوره امانت آغاز شد.',
                'data' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/library/admin/reservations/{id}/return
     *  ثبت بازگشت کتاب
     */
    public function returnBook(int $id): JsonResponse
    {
        try {
            $reservation = $this->libraryService->returnBook($id);

            return response()->json([
                'message' => 'بازگشت کتاب ثبت شد.',
                'data' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
