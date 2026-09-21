<?php

declare(strict_types=1);

// Pest configuration.
//
// Class-based tests extend wp-mocks' TestCase themselves and are unaffected by
// anything here. Closure-based Pest files have no class to put an `extends` on,
// so this binds them to the same TestCase — which is what runs Brain Monkey's
// setUp/tearDown and resets WpState between tests.

use BleedingDeacons\WpMocks\TestCase;

pest()->extend(TestCase::class)->in('Lookup');
