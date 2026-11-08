<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database\Connection;

final class Db
{
    public static function memory(): Connection
    {
        return Connection::sqliteMemory();
    }
}
