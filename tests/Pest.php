<?php

declare(strict_types=1);

// Pest configuration.
//
// Every test in the suite lives under tests/Lookup and every one of them ran
// on wp-mocks' TestCase when they were PHPUnit classes — even the pure ones
// such as Columns, since the whole directory shares the one bootstrap. Closure-
// based Pest files have no class to put an `extends` on, so this binds the
// directory to that TestCase, which is what runs Brain Monkey's setUp/tearDown
// and resets WpState between tests.
//
// A new test file placed under tests/Lookup is bound automatically; one placed
// anywhere else runs on Pest's default, plain PHPUnit, and has to be named here
// if it touches WordPress.

use BleedingDeacons\WpMocks\TestCase;

pest()->extend(TestCase::class)->in('Lookup');
