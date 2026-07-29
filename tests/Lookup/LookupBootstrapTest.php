<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\LookupBootstrap;
use PHPUnit\Framework\TestCase;

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
        $GLOBALS['lifelines_test_options'] = [];
        unset($GLOBALS['lifelines_test_is_admin'], $GLOBALS['lifelines_test_post_status'], $GLOBALS['lifelines_test_insert_id']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['lifelines_test_options'] = [];
        unset($GLOBALS['lifelines_test_is_admin']);
        parent::tearDown();
    }

    public function testRegisterOnTheFrontEndOnly(): void
    {
        $GLOBALS['lifelines_test_is_admin'] = false;
        (new LookupBootstrap())->register();
        $this->assertTrue(true);
    }

    public function testRegisterInAdminAlsoRegistersTheSettingsPage(): void
    {
        $GLOBALS['lifelines_test_is_admin'] = true;
        (new LookupBootstrap())->register();
        $this->assertTrue(true);
    }

    public function testActivateCreatesTheLookupPageWhenNoneExists(): void
    {
        $GLOBALS['lifelines_test_insert_id'] = 77;

        LookupBootstrap::activate();

        $this->assertSame(77, $GLOBALS['lifelines_test_options'][LookupBootstrap::PAGE_OPTION]);
    }

    public function testActivateSkipsCreationWhenAPublishedPageExists(): void
    {
        $GLOBALS['lifelines_test_options'][LookupBootstrap::PAGE_OPTION] = 5;
        $GLOBALS['lifelines_test_post_status'] = 'publish';
        $GLOBALS['lifelines_test_insert_id'] = 999; // must not be used

        LookupBootstrap::activate();

        $this->assertSame(5, $GLOBALS['lifelines_test_options'][LookupBootstrap::PAGE_OPTION]);
    }
}
