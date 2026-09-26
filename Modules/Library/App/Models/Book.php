<?php

namespace Modules\Library\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use SoftDeletes;

    protected $table = 'library_books';

    protected $fillable = [
        'title', 'author', 'translator', 'publisher', 'isbn',
        'publish_year', 'category_id', 'pages', 'language',
        'cover_image', 'description', 'is_active',
    ];

    protected $casts = [
        'publish_year' => 'integer',
        'pages' => 'integer',
        'is_active' => 'boolean',
    ];

    // 🔑 این مقادیر را همیشه در JSON برگردان
    protected $appends = ['available_copies_count', 'total_copies_count'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class, 'book_id');
    }

    // 🔑 Accessor به صورت fallback (فقط اگر withCount استفاده نشد)
    public function getAvailableCopiesCountAttribute(): int
    {
        // اگر با withCount لود شده باشد، از attribute استفاده کن
        if (array_key_exists('available_copies_count', $this->attributes)) {
            return $this->attributes['available_copies_count'];
        }
        return $this->copies()->where('status', 'available')->count();
    }

    public function getTotalCopiesCountAttribute(): int
    {
        if (array_key_exists('total_copies_count', $this->attributes)) {
            return $this->attributes['total_copies_count'];
        }
        return $this->copies()->count();
    }
}
