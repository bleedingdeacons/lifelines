<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\LookupController;
use LifeLines\Lookup\TownRepository;
use BleedingDeacons\WpMocks\Exceptions\JsonResponseException;
use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;

/**
 * Covers LookupController: hook/asset registration, the shortcode HTML render,
 * and the public AJAX endpoint's short-term and search branches.
 *
 * @covers \LifeLines\Lookup\LookupController
 */
class LookupControllerTest extends TestCase
{
    private LookupController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        WpState::$options = [];
        unset($GLOBALS['lifelines_test_rows'], $_GET['q']);
        $this->controller = new LookupController(new TownRepository());
    }

    protected function tearDown(): void
    {
        WpState::$options = [];
        unset($_GET['q']);
        parent::tearDown();
    }

    public function testRegisterAndRegisterAssetsRunWithoutError(): void
    {
        $this->controller->register();
        $this->controller->registerAssets();
        $this->assertTrue(true);
    }

    public function testRenderShortcodeProducesTheSearchWidget(): void
    {
        $html = $this->controller->renderShortcode(['placeholder' => 'Type here']);

        $this->assertStringContainsString('lifelines-lookup__input', $html);
        $this->assertStringContainsString('Type here', $html);
        $this->assertStringContainsString('data-role="results"', $html);
    }

    public function testRenderShortcodeAcceptsANonArrayAttribute(): void
    {
        $html = $this->controller->renderShortcode('');
        $this->assertStringContainsString('lifelines-lookup', $html);
    }

    public function testAjaxReturnsEmptyWhenTheTermIsTooShort(): void
    {
        $_GET['q'] = 'a'; // below the default 2-char minimum

        try {
            $this->controller->handleAjax();
            $this->fail('Expected wp_send_json_success to be signalled.');
        } catch (JsonResponseException $response) {
            $this->assertSame([], $response->data['rows']);
            $this->assertNotEmpty($response->data['columns']);
        }
    }

    public function testAjaxSearchesAndReturnsRows(): void
    {
        $_GET['q'] = 'bath';
        $GLOBALS['lifelines_test_rows'] = [
            ['Place' => 'Bath', 'County' => 'Somerset'],
        ];

        try {
            $this->controller->handleAjax();
            $this->fail('Expected wp_send_json_success to be signalled.');
        } catch (JsonResponseException $response) {
            $this->assertCount(1, $response->data['rows']);
            $this->assertSame('Bath', $response->data['rows'][0]['Place']);
        }
    }
}
