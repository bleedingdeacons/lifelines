<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\LookupController;
use LifeLines\Lookup\RateLimiter;
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
        // The endpoint now counts requests per client into a transient, so
        // buckets have to be cleared between tests or they accumulate.
        WpState::$transients = [];
        unset($GLOBALS['lifelines_test_rows'], $_GET['q']);
        $this->controller = new LookupController(new TownRepository());
    }

    protected function tearDown(): void
    {
        WpState::$options = [];
        WpState::$transients = [];
        unset($_GET['q']);
        parent::tearDown();
    }

    /**
     * A caller over the cap is refused before the term is even read, so the
     * wildcard scan is never reached.
     *
     * @test
     */
    public function a_caller_over_the_rate_limit_is_refused_with_429(): void
    {
        $limiter = new RateLimiter();
        $controller = new LookupController(new TownRepository(), $limiter);

        // Exhaust the window for this client.
        for ($i = 0; $i < RateLimiter::MAX_REQUESTS; $i++) {
            $limiter->overLimit('lookup:' . $limiter->clientIp());
        }

        $_GET['q'] = 'Bath';

        try {
            $controller->handleAjax();
            $this->fail('Expected the request to be refused.');
        } catch (JsonResponseException $response) {
            $this->assertSame(429, $response->status);
            $this->assertFalse($response->success);
        }
    }

    /** @test */
    public function an_ordinary_search_is_not_refused(): void
    {
        $GLOBALS['lifelines_test_rows'] = [['Place' => 'Bath']];
        $_GET['q'] = 'Bath';

        try {
            $this->controller->handleAjax();
            $this->fail('Expected wp_send_json_success to be signalled.');
        } catch (JsonResponseException $response) {
            $this->assertTrue($response->success);
        }
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
