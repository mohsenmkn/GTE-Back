<?php

namespace Modules\Complaint\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class ComplaintReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'user_id',
        'content',
        'is_internal',
        'sent_sms',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'sent_sms' => 'boolean',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
