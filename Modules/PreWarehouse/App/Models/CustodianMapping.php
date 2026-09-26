<?php


namespace Modules\PreWarehouse\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;

class CustodianMapping extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'pre_warehouse_custodian_mappings';

    protected $fillable = [
        'organizational_unit_id', 'user_id', 'role_title',
        'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(\Modules\HR\App\Models\OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * دریافت متولی یک واحد سازمانی
     */
    public static function getCustodianForUnit(int $unitId): ?User
    {
        $mapping = self::active()
            ->where('organizational_unit_id', $unitId)
            ->first();

        return $mapping?->user;
    }
}
