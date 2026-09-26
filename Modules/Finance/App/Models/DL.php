<?php


namespace Modules\Finance\App\Models;

use Illuminate\Database\Eloquent\Model;

class DL extends Model
{
    protected $connection = 'gtarabar'; // ← این خط اضافه شود

    protected $table = 'FIN3.DL';
    protected $primaryKey = 'DLID';

    protected $fillable = ['Code', 'Title', 'DLTypeRef', 'ReferenceID'];

    public $timestamps = false;

    public function type()
    {
        return $this->belongsTo(DLType::class, 'DLTypeRef', 'DLTypeID');
    }
}
