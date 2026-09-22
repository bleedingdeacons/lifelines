<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\LookupController;
use LifeLines\Lookup\RateLimiter;
use LifeLines\Lookup\TownRepository;
use BleedingDeacons\WpMocks\Exceptions\JsonResponseException;
use BleedingDeacons\WpMocks\WpState;

/*
 * Covers LookupController: hook/asset registration, the shortcode HTML render,
 * and the public AJAX endpoint's short-term and search branches.
 *
 * wp_send_json_success()/wp_send_json_error() are signalled by the shared
 * stubs as a JsonResponseException, so every endpoint test asserts inside
 * toThrow()'s callback — which also fails the test if nothing is thrown.
 */

covers(LookupController::class);

beforeEach(function () {
    WpState::$options = [];
    // The endpoint now counts requests per client into a transient, so
    // buckets have to be cleared between tests or they accumulate.
    WpState::$transients = [];
    unset($GLOBALS['lifelines_test_rows'], $_GET['q']);
    $this->controller = new LookupController(new TownRepository());
});

afterEach(function () {
    WpState::$options = [];
    WpState::$transients = [];
    unset($_GET['q']);
});

describe('handleAjax', function () {
    // A caller over the cap is refused before the term is even read, so the
    // wildcard scan is never reached.
    it('refuses a caller over the rate limit with 429', function () {
        $limiter = new RateLimiter();
        $controller = new LookupController(new TownRepository(), $limiter);

        // Exhaust the window for this client.
        for ($i = 0; $i < RateLimiter::MAX_REQUESTS; $i++) {
            $limiter->overLimit('lookup:' . $limiter->clientIp());
        }

        $_GET['q'] = 'Bath';

        expect(fn () => $controller->handleAjax())->toThrow(function (JsonResponseException $response) {
            expect($response->status)->toBe(429)
                ->and($response->success)->toBeFalse();
        });
    });

    it('does not refuse an ordinary search', function () {
        $GLOBALS['lifelines_test_rows'] = [['Place' => 'Bath']];
        $_GET['q'] = 'Bath';

        expect(fn () => $this->controller->handleAjax())->toThrow(function (JsonResponseException $response) {
            expect($response->success)->toBeTrue();
        });
    });

    it('returns empty when the term is too short', function () {
        $_GET['q'] = 'a'; // below the default 2-char minimum

        expect(fn () => $this->controller->handleAjax())->toThrow(function (JsonResponseException $response) {
            expect($response->data['rows'])->toBe([])
                ->and($response->data['columns'])->not->toBeEmpty();
        });
    });

    it('searches and returns rows', function () {
        $_GET['q'] = 'bath';
        $GLOBALS['lifelines_test_rows'] = [
            ['Place' => 'Bath', 'County' => 'Somerset'],
        ];

        expect(fn () => $this->controller->handleAjax())->toThrow(function (JsonResponseException $response) {
            expect($response->data['rows'])->toHaveCount(1)
                ->and($response->data['rows'][0]['Place'])->toBe('Bath');
        });
    });
});

it('runs register and registerAssets without error', function () {
    $this->controller->register();
    $this->controller->registerAssets();
})->throwsNoExceptions();

describe('renderShortcode', function () {
    it('produces the search widget', function () {
        $html = $this->controller->renderShortcode(['placeholder' => 'Type here']);

        expect($html)->toContain('lifelines-lookup__input', 'Type here', 'data-role="results"');
    });

    it('accepts a non-array attribute', function () {
        expect($this->controller->renderShortcode(''))->toContain('lifelines-lookup');
    });
});
