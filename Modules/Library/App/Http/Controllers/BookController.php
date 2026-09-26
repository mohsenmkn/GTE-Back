<?php

namespace Modules\Library\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Library\App\Services\LibraryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookController extends Controller
{
    public function __construct(
        private LibraryService $libraryService
    ) {}

    /**
     * GET /api/library/books
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category_id', 'availability']);
        $books = $this->libraryService->getBooks($filters);

        return response()->json([
            'data' => $books->items(),
            'meta' => [
                'current_page' => $books->currentPage(),
                'total' => $books->total(),
                'per_page' => $books->perPage(),
                'last_page' => $books->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/library/books/{id}
     */
    public function show(int $id): JsonResponse
    {
        $book = $this->libraryService->getBook($id);

        if (!$book) {
            return response()->json(['message' => 'کتاب یافت نشد.'], 404);
        }

        return response()->json(['data' => $book]);
    }

    /**
     * پیام‌های فارسی ولیدیشن کتاب
     */
    private function bookValidationMessages(): array
    {
        return [
            'title.required' => 'عنوان کتاب الزامی است.',
            'title.max' => 'عنوان کتاب نباید بیشتر از 255 کاراکتر باشد.',
            'author.required' => 'نام نویسنده الزامی است.',
            'author.max' => 'نام نویسنده نباید بیشتر از 255 کاراکتر باشد.',
            'category_id.required' => 'انتخاب دسته‌بندی الزامی است.',
            'category_id.exists' => 'دسته‌بندی انتخاب‌شده معتبر نیست.',
            'isbn.unique' => 'این شابک (ISBN) قبلاً برای کتاب دیگری ثبت شده است.',
            'publish_year.min' => 'سال انتشار معتبر نیست.',
            'publish_year.max' => 'سال انتشار معتبر نیست.',
            'pages.min' => 'تعداد صفحات معتبر نیست.',
            'cover_image.image' => 'فایل انتخاب‌شده باید تصویر باشد.',
            'cover_image.max' => 'حجم تصویر جلد نباید بیشتر از 2 مگابایت باشد.',
            'cover_image.mimes' => 'فرمت تصویر باید jpg، png یا gif باشد.',
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'translator' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:50|unique:library_books,isbn',
            'publish_year' => 'nullable|integer|min:1900|max:2100',
            'category_id' => 'required|exists:library_categories,id',
            'pages' => 'nullable|integer|min:1',
            'language' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], $this->bookValidationMessages());

        $book = $this->libraryService->createBook(
            $validated,
            $request->file('cover_image')
        );

        return response()->json([
            'message' => 'کتاب با موفقیت ثبت شد.',
            'data' => $book,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'translator' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:50|unique:library_books,isbn,' . $id,
            'publish_year' => 'nullable|integer|min:1900|max:2100',
            'category_id' => 'required|exists:library_categories,id',
            'pages' => 'nullable|integer|min:1',
            'language' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], $this->bookValidationMessages());

        $book = $this->libraryService->updateBook(
            $id,
            $validated,
            $request->file('cover_image')
        );

        return response()->json([
            'message' => 'کتاب با موفقیت ویرایش شد.',
            'data' => $book,
        ]);
    }

    /**
     * DELETE /api/library/admin/books/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->libraryService->deleteBook($id);

        return response()->json(['message' => 'کتاب با موفقیت حذف شد.']);
    }



    /**
     * POST /api/library/admin/books/{id}/copies
     * افزودن نسخه جدید به کتاب
     */
    public function storeCopy(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'copy_code' => 'required|string|max:50|unique:library_book_copies,copy_code',
            'status' => 'nullable|in:available,reserved,lent,maintenance',
            'condition_note' => 'nullable|string',
        ]);

        $copy = $this->libraryService->addCopy($id, $validated);

        return response()->json([
            'message' => 'نسخه با موفقیت اضافه شد.',
            'data' => $copy,
        ], 201);
    }

    /**
     * PUT /api/library/admin/copies/{id}
     */
    public function updateCopy(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'copy_code' => 'required|string|max:50|unique:library_book_copies,copy_code,' . $id,
            'status' => 'required|in:available,reserved,lent,maintenance',
            'condition_note' => 'nullable|string',
        ]);

        $this->libraryService->updateCopy($id, $validated);

        return response()->json(['message' => 'نسخه با موفقیت ویرایش شد.']);
    }

    /**
     * DELETE /api/library/admin/copies/{id}
     */
    public function destroyCopy(int $id): JsonResponse
    {
        try {
            $this->libraryService->deleteCopy($id);
            return response()->json(['message' => 'نسخه با موفقیت حذف شد.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }


}
