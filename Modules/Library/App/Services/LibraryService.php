<?php

namespace Modules\Library\App\Services;

use Modules\Library\App\Repositories\BookRepository;
use Modules\Library\App\Repositories\ReservationRepository;
use Modules\Library\App\Repositories\CategoryRepository;
use Modules\Library\App\Models\Setting;
use Modules\Library\App\Models\Reservation;
use Modules\Library\App\Services\Sms\SmsManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LibraryService
{
    private SmsManager $smsManager;
    public function __construct(
        private BookRepository $bookRepo,
        private ReservationRepository $reservationRepo,
        private CategoryRepository $categoryRepo,
        SmsManager $smsManager
    ) {
        $this->smsManager = $smsManager;
    }

    // ========== کتاب‌ها ==========

    public function getBooks(array $filters = [])
    {
        return $this->bookRepo->getAll($filters);
    }

    public function getBook(int $id)
    {
        return $this->bookRepo->find($id);
    }

    public function createBook(array $data, $coverImage = null)
    {
        if ($coverImage) {
            $data['cover_image'] = $this->bookRepo->uploadCoverImage($coverImage);
        }

        return $this->bookRepo->create($data);
    }

    public function updateBook(int $id, array $data, $coverImage = null)
    {
        $book = $this->bookRepo->find($id);

        if ($coverImage) {
            $data['cover_image'] = $this->bookRepo->uploadCoverImage($coverImage, $book->cover_image);
        }

        $this->bookRepo->update($id, $data);
        return $this->bookRepo->find($id);
    }

    public function deleteBook(int $id)
    {
        return $this->bookRepo->delete($id);
    }

    // ========== نسخه‌ها ==========

    public function addCopy(int $bookId, array $data)
    {
        return $this->bookRepo->addCopy($bookId, $data);
    }

    public function updateCopy(int $copyId, array $data)
    {
        return $this->bookRepo->updateCopy($copyId, $data);
    }

    public function deleteCopy(int $copyId)
    {
        return $this->bookRepo->deleteCopy($copyId);
    }

    // ========== رزروها ==========

    public function createReservation(int $userId, int $bookCopyId, string $expectedPickupDate)
    {
        // بررسی محدودیت: هر کاربر فقط ۱ رزرو فعال
        if ($this->reservationRepo->hasActiveReservation($userId)) {
            throw new \Exception('شما در حال حاضر یک رزرو فعال دارید. لطفاً ابتدا کتاب فعلی را بازگردانید.');
        }

        // بررسی وضعیت نسخه
        $bookCopy = \Modules\Library\App\Models\BookCopy::findOrFail($bookCopyId);
        if (!$bookCopy->isAvailable()) {
            throw new \Exception('این نسخه در حال حاضر آزاد نیست.');
        }

        // بررسی تاریخ تحویل مقرر
        $pickupDate = Carbon::parse($expectedPickupDate);
        if ($pickupDate->lt(Carbon::today())) {
            throw new \Exception('تاریخ تحویل مقرر نمی‌تواند در گذشته باشد.');
        }

        $reservation = $this->reservationRepo->create($userId, $bookCopyId, $expectedPickupDate);

        // 🔑 ارسال پیامک ثبت اولیه به کاربر
        try {
            $this->smsManager->sendPendingNotification($reservation);
        } catch (\Exception $e) {
            // لاگ خطا ولی مانع از ثبت رزرو نمی‌شود
            \Illuminate\Support\Facades\Log::channel('sms')->error(
                'خطا در ارسال پیامک ثبت رزرو',
                ['reservation_id' => $reservation->id, 'error' => $e->getMessage()]
            );
        }

        return $reservation;
    }

    public function approveReservation(int $reservationId)
    {
        $reservation = $this->reservationRepo->approve($reservationId);

        // 🔑 ارسال پیامک تایید رزرو به کاربر
        try {
            $this->smsManager->sendApprovalNotification($reservation);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::channel('sms')->error(
                'خطا در ارسال پیامک تایید رزرو',
                ['reservation_id' => $reservationId, 'error' => $e->getMessage()]
            );
        }

        return $reservation;
    }

    public function rejectReservation(int $reservationId)
    {
        return $this->reservationRepo->reject($reservationId);
    }

    public function pickupBook(int $reservationId)
    {
        return $this->reservationRepo->pickup($reservationId);
    }

    public function returnBook(int $reservationId)
    {
        return $this->reservationRepo->returnBook($reservationId);
    }

    public function cancelReservation(int $reservationId, int $userId)
    {
        return $this->reservationRepo->cancel($reservationId, $userId);
    }

    public function getUserReservations(int $userId, ?string $status = null)
    {
        return $this->reservationRepo->getUserReservations($userId, $status);
    }

    public function getAllReservations(array $filters = [])
    {
        return $this->reservationRepo->getAll($filters);
    }

    // ========== دسته‌بندی‌ها ==========

    public function getCategories()
    {
        return $this->categoryRepo->getAll();
    }

    public function createCategory(array $data)
    {
        return $this->categoryRepo->create($data);
    }

    public function updateCategory(int $id, array $data)
    {
        return $this->categoryRepo->update($id, $data);
    }

    public function deleteCategory(int $id)
    {
        return $this->categoryRepo->delete($id);
    }

    // ========== آمار (برای داشبورد مدیر) ==========

    public function getStatistics(): array
    {
        return [
            'total_books' => \Modules\Library\App\Models\Book::where('is_active', true)->count(),
            'total_copies' => \Modules\Library\App\Models\BookCopy::count(),
            'available_copies' => \Modules\Library\App\Models\BookCopy::where('status', 'available')->count(),
            'lent_copies' => \Modules\Library\App\Models\BookCopy::where('status', 'lent')->count(),
            'reserved_copies' => \Modules\Library\App\Models\BookCopy::where('status', 'reserved')->count(),
            'active_reservations' => Reservation::whereIn('status', ['pending', 'approved', 'picked_up'])->count(),
            'pending_reservations' => Reservation::where('status', 'pending')->count(),
            'overdue_reservations' => Reservation::where('status', 'picked_up')
                ->where('expected_return_date', '<', Carbon::today())
                ->count(),
        ];
    }
}
