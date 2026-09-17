<?php
/**
 * Digital Visiting Card SaaS Platform
 * ----------------------------------------------------------------------
 * Single front controller. Every non-static request lands here.
 *
 * @package DigitalVisitingCard
 */

declare(strict_types=1);

define('DVC_START', microtime(true));
define('BASE_PATH', __DIR__);

require BASE_PATH . '/app/bootstrap.php';

App\Core\App::boot()->run();
