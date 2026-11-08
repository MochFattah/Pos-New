<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database\Connection;
use App\Core\Database\Migrator;
use App\Core\Database\Seeders\ReferenceDataSeeder;

final class Db
{
    public static function memory(): Connection
    {
        return Connection::sqliteMemory();
    }

    /** SQLite memori dengan skema fondasi dan data referensi; FK ditegakkan. */
    public static function fresh(): Connection
    {
        $db = Connection::sqliteMemory();
        $db->execute('PRAGMA foreign_keys = ON');
        $sql = (string) file_get_contents(__DIR__ . '/schema.sqlite.sql');
        foreach (Migrator::split($sql) as $statement) {
            $db->execute($statement);
        }
        ReferenceDataSeeder::run($db);
        return $db;
    }
}
