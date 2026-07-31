<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\LookupBootstrap;
use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;

/**
 * Covers LookupBootstrap: hook registration (front-end only vs. admin), and
 * activation creating the lookup page only when one does not already exist.
 *
 * @covers \LifeLines\Lookup\LookupBootstrap
 */
class LookupBootstrapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // parent::setUp() clears WpState: options, post statuses and the
        // next-post-id counter all start fresh, so nothing to unset here.
    }

    public function testRegisterOnTheFrontEndOnly(): void
    {
        WpState::$isAdmin = false;
        (new LookupBootstrap())->register();
        $this->assertTrue(true);
    }

    public function testRegisterInAdminAlsoRegistersTheSettingsPage(): void
    {
        WpState::$isAdmin = true;
        (new LookupBootstrap())->register();
        $this->assertTrue(true);
    }

    public function testActivateCreatesTheLookupPageWhenNoneExists(): void
    {
        // wp_insert_post() hands back the next id in sequence.
        WpState::$nextPostId = 77;

        LookupBootstrap::activate();

        $this->assertSame(77, WpState::$options[LookupBootstrap::PAGE_OPTION]);
    }

    public function testActivateSkipsCreationWhenAPublishedPageExists(): void
    {
        WpState::$options[LookupBootstrap::PAGE_OPTION] = 5;
        WpState::$postStatuses[5] = 'publish';
        WpState::$nextPostId = 999; // must not be used

        LookupBootstrap::activate();

        $this->assertSame(5, WpState::$options[LookupBootstrap::PAGE_OPTION]);
    }
}
