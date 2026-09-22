<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\LookupBootstrap;
use BleedingDeacons\WpMocks\WpState;

/*
 * Covers LookupBootstrap: hook registration (front-end only vs. admin), and
 * activation creating the lookup page only when one does not already exist.
 */

covers(LookupBootstrap::class);

// The wp-mocks TestCase's setUp() clears WpState: options, post statuses and
// the next-post-id counter all start fresh, so there is nothing to unset here.

describe('register', function () {
    it('registers on the front end only', function () {
        WpState::$isAdmin = false;
        (new LookupBootstrap())->register();
    })->throwsNoExceptions();

    it('also registers the settings page in admin', function () {
        WpState::$isAdmin = true;
        (new LookupBootstrap())->register();
    })->throwsNoExceptions();
});

describe('activate', function () {
    it('creates the lookup page when none exists', function () {
        // wp_insert_post() hands back the next id in sequence.
        WpState::$nextPostId = 77;

        LookupBootstrap::activate();

        expect(WpState::$options[LookupBootstrap::PAGE_OPTION])->toBe(77);
    });

    it('skips creation when a published page exists', function () {
        WpState::$options[LookupBootstrap::PAGE_OPTION] = 5;
        WpState::$postStatuses[5] = 'publish';
        WpState::$nextPostId = 999; // must not be used

        LookupBootstrap::activate();

        expect(WpState::$options[LookupBootstrap::PAGE_OPTION])->toBe(5);
    });
});
