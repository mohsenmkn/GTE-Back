<?php
// Modules/SystemSettings/Facades/DynamicDB.php

namespace Modules\SystemSettings\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool apply(string $connectionName)
 * @method static \Illuminate\Database\Query\Builder connection(string $name)
 */
class DynamicDB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'dynamic-db';
    }
}
