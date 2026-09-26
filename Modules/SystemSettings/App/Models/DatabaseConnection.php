<?php

// Modules/SystemSettings/Entities/DatabaseConnection.php

namespace Modules\SystemSettings\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DatabaseConnection extends Model
{
    use SoftDeletes;

    protected $table = 'system_database_connections';

    protected $fillable = [
        'name', 'title', 'driver', 'host', 'port', 'database',
        'username', 'password', 'charset', 'collation', 'options',
        'is_active', 'description'
    ];

    protected $casts = [
        'password'  => 'encrypted', // 🔐 رمزنگاری خودکار با APP_KEY
        'options'   => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['password'];

    protected $appends = ['password_masked', 'display_title'];

    public function getPasswordMaskedAttribute(): string
    {
        return $this->password ? '••••••••' : '';
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?: $this->name;
    }

    /**
     * تبدیل مدل به آرایه کانفیگ لاراول
     */
    public function toLaravelConfig(): array
    {
        return [
            'driver'   => $this->driver,
            'host'     => $this->host,
            'port'     => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'charset'  => $this->charset,
            'collation'=> $this->collation,
            'prefix'   => '',
            'prefix_indexes' => true,
            'TrustServerCertificate' => $this->options['TrustServerCertificate'] ?? true,
            'Encrypt'  => $this->options['Encrypt'] ?? true,
            'options'  => [
                \PDO::SQLSRV_ATTR_QUERY_TIMEOUT => $this->options['query_timeout'] ?? 30,
                \PDO::SQLSRV_ATTR_ENCODING => \PDO::SQLSRV_ENCODING_UTF8,
            ],
        ];
    }
}
