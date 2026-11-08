<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Seeders\ReferenceDataSeeder;

/** Pemuat tipis untuk installer: `(require ...)($connection)`. */
return static function (Connection $db): void {
    ReferenceDataSeeder::run($db);
};
