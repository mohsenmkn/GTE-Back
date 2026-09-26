<?php

// Modules/Contract/App/Models/Contractor.php

namespace Modules\Contract\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\App\Models\Company;
use Modules\Auth\App\Models\User;

class Contractor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'registration_number',
        'economic_code',
        'national_id',
        'phone',
        'mobile',
        'email',
        'website',
        'address',
        'city',
        'province',
        'postal_code',
        'bank_name',
        'bank_account_number',
        'bank_card_number',
        'shaba_number',
        'type',
        'expertise',
        'certificates',
        'description',
        'status',
        'created_by',
    ];

    protected $casts = [
        'expertise' => 'array',
        'certificates' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        return $this->type === 'legal' ? 'حقوقی' : 'حقیقی';
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'active' => 'فعال',
            'inactive' => 'غیرفعال',
            'suspended' => 'تعلیق',
        ];
        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusSeverityAttribute()
    {
        $severities = [
            'active' => 'success',
            'inactive' => 'secondary',
            'suspended' => 'danger',
        ];
        return $severities[$this->status] ?? 'secondary';
    }

    // Helper
    public function getFullAddressAttribute()
    {
        $parts = array_filter([$this->address, $this->city, $this->province]);
        return implode(' - ', $parts);
    }
}
