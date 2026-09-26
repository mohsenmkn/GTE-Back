<?php


namespace Modules\Finance\App\Models;

use Illuminate\Database\Eloquent\Model;

class DLType extends Model
{
    protected $connection = 'gtarabar'; // ← این خط اضافه شود

    protected $table = 'FIN3.DLType';
    protected $primaryKey = 'DLTypeID';

    protected $fillable = ['Title', 'Title_En'];

    public $timestamps = false;
}
