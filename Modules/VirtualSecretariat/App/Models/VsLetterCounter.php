<?php


namespace Modules\VirtualSecretariat\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VsLetterCounter extends Model
{
    protected $table = 'vs_letter_counters';

    protected $fillable = [
        'template_id',
        'prefix',
        'year',
        'current_number',
    ];

    protected $casts = [
        'current_number' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(VsTemplate::class);
    }
}
