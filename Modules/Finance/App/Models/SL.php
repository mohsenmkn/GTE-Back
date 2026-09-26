<?php


namespace Modules\Finance\App\Models;

use Illuminate\Database\Eloquent\Model;

class SL extends Model
{
    protected $connection = 'gtarabar'; // ← این خط اضافه شود

    protected $table = 'FIN3.SL';
    protected $primaryKey = 'SLID';

    protected $fillable = ['Code', 'Title', 'Nature'];

    public $timestamps = false;
}

