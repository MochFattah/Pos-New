<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Request;

require __DIR__ . '/../bootstrap/autoload.php';

/** @var Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$app->handle(Request::fromGlobals())->send();
