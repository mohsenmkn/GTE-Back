<?php

namespace Modules\Library\App\Repositories;

use Modules\Library\App\Models\Reservation;
use Modules\Library\App\Models\BookCopy;
use Carbon\Carbon;

class ReservationRepository
{
    /**
     * دریافت رزروهای کاربر فعلی
     */
    public function getUserReservations(int $userId, ?string $status = null)
    {
        $query = Reservation::with(['bookCopy.book', 'bookCopy.book.category'])
            ->where('user_id', $userId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * دریافت همه رزروها (برای مدیر)
     */
    public function getAll(array $filters = [])
    {
        $query = Reservation::with(['user', 'bookCopy.book', 'bookCopy.book.category']);

        // فیلتر وضعیت
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // فیلتر تاریخ
        if (!empty($filters['from_date'])) {
            $query->where('reservation_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('reservation_date', '<=', $filters['to_date']);
        }

        // جستجو
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('mobile', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * ثبت رزرو جدید
     */
    public function create(int $userId, int $bookCopyId, string $expectedPickupDate): Reservation
    {
        $maxLoanDays = (int) \Modules\Library\App\Models\Setting::get('max_loan_days', 14);

        return Reservation::create([
            'user_id' => $userId,
            'book_copy_id' => $bookCopyId,
            'reservation_date' => Carbon::now(),
            'expected_pickup_date' => $expectedPickupDate,
            'expected_return_date' => Carbon::parse($expectedPickupDate)->addDays($maxLoanDays),
            'status' => 'pending',
        ]);
    }

    /**
     * تایید رزرو
     */
    public function approve(int $reservationId): Reservation
    {
        $reservation = Reservation::findOrFail($reservationId);

        if ($reservation->status !== 'pending') {
            throw new \Exception('این رزرو قابل تایید نیست.');
        }

        $reservation->update(['status' => 'approved']);

        // تغییر وضعیت نسخه به رزرو شده
        $reservation->bookCopy->update(['status' => 'reserved']);

        return $reservation;
    }

    /**
     * رد رزرو
     */
    public function reject(int $reservationId): Reservation
    {
        $reservation = Reservation::findOrFail($reservationId);

        $reservation->update(['status' => 'cancelled']);

        // آزادسازی نسخه
        $reservation->bookCopy->update(['status' => 'available']);

        return $reservation;
    }

    /**
     * ثبت تحویل کتاب (توسط مدیر)
     */
    public function pickup(int $reservationId): Reservation
    {
        $reservation = Reservation::findOrFail($reservationId);

        if ($reservation->status !== 'approved') {
            throw new \Exception('این رزرو تایید نشده است.');
        }

        $reservation->update([
            'actual_pickup_date' => Carbon::now(),
            'status' => 'picked_up',
        ]);

        // تغییر وضعیت نسخه به امانت داده شده
        $reservation->bookCopy->update(['status' => 'lent']);

        return $reservation;
    }

    /**
     * ثبت بازگشت کتاب
     */
    public function returnBook(int $reservationId): Reservation
    {
        $reservation = Reservation::findOrFail($reservationId);

        if ($reservation->status !== 'picked_up') {
            throw new \Exception('این کتاب تحویل داده نشده است.');
        }

        $reservation->update([
            'actual_return_date' => Carbon::now(),
            'status' => 'returned',
        ]);

        // آزادسازی نسخه
        $reservation->bookCopy->update(['status' => 'available']);

        return $reservation;
    }

    /**
     * لغو رزرو (توسط کاربر)
     */
    public function cancel(int $reservationId, int $userId): Reservation
    {
        $reservation = Reservation::findOrFail($reservationId);

        if ($reservation->user_id !== $userId) {
            throw new \Exception('شما مجاز به لغو این رزرو نیستید.');
        }

        if (!in_array($reservation->status, ['pending', 'approved'])) {
            throw new \Exception('این رزرو قابل لغو نیست.');
        }

        $reservation->update(['status' => 'cancelled']);

        // آزادسازی نسخه
        $reservation->bookCopy->update(['status' => 'available']);

        return $reservation;
    }

    /**
     * بررسی اینکه آیا کاربر رزرو فعال دارد
     */
    public function hasActiveReservation(int $userId): bool
    {
        return Reservation::where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved', 'picked_up'])
            ->exists();
    }
}
