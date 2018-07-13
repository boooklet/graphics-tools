#!/usr/bin/php
<?php
define('IS_TEST_ENV', true);
// AUTOLOADER BASIC
// ==============================================================================
spl_autoload_register(function ($class) {
    if (file_exists('src/' . $class . '.php')) {
        include 'src/' . $class . '.php';
    }
});
require_once 'vendor/autoload.php';

echo "\nRun all tests\n";
$time_start = microtime(true);
$tests = new Tester(['db_connection' => null, 'tests_paths' => ['tests'], 'migrations_path' => '']);
$tests->run();
