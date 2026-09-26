<?php

namespace Modules\Library\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookCopy extends Model
{
    protected $table = 'library_book_copies';

    protected $fillable = [
        'book_id',
        'copy_code',
        'status',
        'condition_note',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'book_copy_id');
    }

    // آیا نسخه آزاد است؟
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    // آیا رزرو شده است؟
    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }

    // آیا امانت داده شده است؟
    public function isLent(): bool
    {
        return $this->status === 'lent';
    }
}
