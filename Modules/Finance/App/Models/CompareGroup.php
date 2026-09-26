<?php


namespace Modules\Finance\App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\App\Models\User;

class CompareGroup extends Model
{
    protected $table = 'equipment_compare_groups';

    protected $fillable = [
        'user_id', 'name', 'from_date', 'to_date',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(CompareItem::class, 'compare_group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
