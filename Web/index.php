<?php

/*
 * Autoload By Composer
 */
require('vendor/autoload.php');

use MyDropper\Helpers\Twig;
use MyDropper\Helpers\Database;

/*
 * Fat Free Framework
 */
$f3 = Base::instance();

$f3->config('app/Config/config.ini');
$f3->config('app/Config/routes.ini');

/*
 * Twig
 */
new Twig($f3->get('UI'), [
    'debug'         => $f3->get('TWIG_DEBUG'),
    'cache'         => $f3->get('CACHE'),
    'auto_reload'   => $f3->get('TWIG_AUTORELOAD')
]);

/*
 * Eloquent
 */
$capsule = new Database();

/*
 * Run
 */
$f3->run();