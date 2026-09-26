<?php


namespace Modules\Finance\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Auth\App\Models;

class SavedReport extends Model
{
    use HasFactory;

    protected $table = 'equipment_saved_reports';

    protected $fillable = [
        'user_id',
        'name',
        'dl_type_ref',
        'equipment_code',
        'equipment_title',
        'from_date',
        'to_date',
        'is_default',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
