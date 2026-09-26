<?php

namespace Modules\Library\App\Repositories;

use Modules\Library\App\Models\Book;
use Modules\Library\App\Models\BookCopy;
use Illuminate\Support\Facades\Storage;

class BookRepository
{


    /**
     * ثبت کتاب جدید
     */
    public function create(array $data): Book
    {
        return Book::create($data);
    }

    /**
     * ویرایش کتاب
     */
    public function update(int $id, array $data): bool
    {
        $book = Book::findOrFail($id);
        return $book->update($data);
    }

    /**
     * حذف کتاب (soft delete)
     */
    public function delete(int $id): bool
    {
        $book = Book::findOrFail($id);

        // حذف تصویر جلد
        if ($book->cover_image) {
            Storage::disk('public')->delete($book->cover_image);
        }

        return $book->delete();
    }

    /**
     * آپلود تصویر جلد
     */
    public function uploadCoverImage($file, ?string $oldImage = null): string
    {
        // حذف تصویر قدیمی
        if ($oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        $path = $file->store('library/covers', 'public');
        return $path;
    }

    // ========== مدیریت نسخه‌ها ==========

    /**
     * افزودن نسخه جدید
     */
    public function addCopy(int $bookId, array $data): BookCopy
    {
        return BookCopy::create(array_merge($data, ['book_id' => $bookId]));
    }

    /**
     * ویرایش نسخه
     */
    public function updateCopy(int $copyId, array $data): bool
    {
        $copy = BookCopy::findOrFail($copyId);
        return $copy->update($data);
    }

    /**
     * حذف نسخه
     */
    public function deleteCopy(int $copyId): bool
    {
        $copy = BookCopy::findOrFail($copyId);

        // فقط نسخه‌های آزاد قابل حذف هستند
        if (!$copy->isAvailable()) {
            throw new \Exception('نسخه‌ای که رزرو یا امانت داده شده قابل حذف نیست.');
        }

        return $copy->delete();
    }

    /**
     * دریافت لیست کتاب‌ها با فیلتر و جستجو
     */
    public function getAll(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Book::with(['category', 'copies'])
            // 🔑 کلید حل مشکل: withCount با شرط
            ->withCount(['copies as available_copies_count' => function ($q) {
                $q->where('status', 'available');
            }])
            ->withCount('copies as total_copies_count')
            ->where('is_active', true);

        // جستجو
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('author', 'LIKE', "%{$search}%")
                    ->orWhere('isbn', 'LIKE', "%{$search}%");
            });
        }

        // فیلتر دسته‌بندی
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // فیلتر وضعیت نسخه
        if (!empty($filters['availability'])) {
            if ($filters['availability'] === 'available') {
                $query->whereHas('copies', function ($q) {
                    $q->where('status', 'available');
                });
            }
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * دریافت جزئیات یک کتاب
     */
    public function find(int $id): ?Book
    {
        return Book::with(['category', 'copies'])
            ->withCount(['copies as available_copies_count' => function ($q) {
                $q->where('status', 'available');
            }])
            ->withCount('copies as total_copies_count')
            ->find($id);
    }
}
