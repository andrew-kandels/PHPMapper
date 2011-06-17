<?php
/* From here forward, default path is the application's root */
$base = realpath(dirname(__FILE__));

//error_reporting(E_ALL | E_STRICT);
error_reporting(E_ALL);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
date_default_timezone_set('America/Chicago');

define('APPLICATION_PATH', "$base/application");
define('APPLICATION_ENV', getenv('APPLICATION_ENV')
    ? getenv('APPLICATION_ENV')
    : 'development'
);

define('APPLICATION_ROOT', $base);

/* Autoload Paths */
set_include_path(implode(PATH_SEPARATOR, array(
    APPLICATION_PATH,
    "$base/library",
    realpath(APPLICATION_ROOT . '/../..'),
    "/usr/share/php"
)));

/* Autoloader */
require_once "/usr/share/php/Zend/Loader/Autoloader.php";
$loader = Zend_Loader_Autoloader::getInstance();
$loader->setFallbackAutoloader(true);
$loader->suppressNotFoundWarnings(false);

$config = APPLICATION_ROOT . '/config/config.ini';

$application = new Zend_Application(APPLICATION_ENV, $config);
$application->bootstrap();
