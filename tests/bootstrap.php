<?php

$_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    die("Could not find WordPress test suite at {$_tests_dir}.\n");
}

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load WordPress test suite
require_once $_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/newsapi-plugin.php';
});

require_once $_tests_dir . '/includes/bootstrap.php';

// Start Brain Monkey before loading WordPress
\Brain\Monkey\setUp();
