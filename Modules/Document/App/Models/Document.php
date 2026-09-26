<?php

// Modules/Document/Entities/Document.php
namespace Modules\Document\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\App\Models\Company;
use Modules\Auth\App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    //use HasFactory, SoftDeletes;
    use  SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'documentable_id',
        'documentable_type',
        'title',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'type',
        'description',
        'uploaded_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the parent documentable model (polymorphic).
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the company that owns the document.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getFileUrlAttribute(): string
    {
        // ✅ بررسی وجود فایل
        if (Storage::disk('public')->exists($this->file_path)) {
            return asset('storage/' . $this->file_path);
        }
        return '';
    }

    /**
     * Get the file size in human-readable format.
     */
    public function getHumanFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }


    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope a query to filter documents by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter documents by company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the document type label in Persian.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'drawing' => 'نقشه',
            'invoice' => 'فاکتور',
            'contract' => 'قرارداد',
            'report' => 'گزارش',
            'photo' => 'تصویر',
            'other' => 'سایر',
            default => $this->type,
        };
    }

    /**
     * Get all of the available document types.
     */
    public static function getTypes(): array
    {
        return ['drawing', 'invoice', 'contract', 'report', 'photo', 'other'];
    }
}
