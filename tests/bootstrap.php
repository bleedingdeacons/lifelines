<?php

declare(strict_types=1);

// PHPUnit bootstrap.
//
// LifeLines is standalone — it has no Unity-ecosystem dependencies — so the
// suite needs nothing but the plugin's own autoloader plus the small amount of
// WordPress surface its classes touch at load time.

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Every LifeLines class begins with `if (!defined('ABSPATH')) { exit; }` to
// block direct web access, so the constant has to exist before any of them is
// loaded or the file simply exits and the class is never declared.
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/lifelines-test-wp/');
}
