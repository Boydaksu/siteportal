<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use SitePortal\Core\App;

$app = new App();
$app->run();
