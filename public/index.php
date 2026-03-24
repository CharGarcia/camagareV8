<?php
/**
 * Punto de entrada único - Front Controller
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\core\Application;

$app = new Application('/sistema/public');
$app->run();
