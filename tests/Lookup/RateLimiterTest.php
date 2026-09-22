<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use BleedingDeacons\WpMocks\WpState;
use LifeLines\Lookup\RateLimiter;

/*
 * The throttle in front of the public, unauthenticated lookup endpoint.
 */

covers(RateLimiter::class);

beforeEach(function () {
    WpState::$transients = [];
    unset($_SERVER['REMOTE_ADDR']);
    $this->limiter = new RateLimiter();
});

afterEach(function () {
    unset($_SERVER['REMOTE_ADDR']);
});

describe('overLimit', function () {
    it('allows a request under the cap', function () {
        expect($this->limiter->overLimit('k', 3, 60))->toBeFalse();
    });

    it('reaches the cap only after that many requests', function () {
        expect($this->limiter->overLimit('k', 3, 60))->toBeFalse()
            ->and($this->limiter->overLimit('k', 3, 60))->toBeFalse()
            ->and($this->limiter->overLimit('k', 3, 60))->toBeFalse()
            ->and($this->limiter->overLimit('k', 3, 60))->toBeTrue();
    });

    it('does not share a bucket between separate keys', function () {
        $this->limiter->overLimit('a', 1, 60);

        expect($this->limiter->overLimit('a', 1, 60))->toBeTrue()
            ->and($this->limiter->overLimit('b', 1, 60))->toBeFalse();
    });

    // A window of zero would otherwise divide by zero when picking a bucket.
    it('clamps a nonsensical window or cap rather than failing', function () {
        expect($this->limiter->overLimit('k', 0, 0))->toBeFalse()
            ->and($this->limiter->overLimit('k', 0, 0))->toBeTrue();
    });
});

describe('clientIp', function () {
    it('comes from REMOTE_ADDR', function () {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';

        expect($this->limiter->clientIp())->toBe('203.0.113.9');
    });

    it('becomes unknown when REMOTE_ADDR is absent or malformed', function () {
        expect($this->limiter->clientIp())->toBe('unknown');

        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        expect($this->limiter->clientIp())->toBe('unknown');
    });

    // X-Forwarded-For is caller-supplied. Honouring it would let anyone mint a
    // fresh bucket per request and opt out of the limit entirely.
    it('ignores a forwarded-for header', function () {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';

        expect($this->limiter->clientIp())->toBe('203.0.113.9');

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    });
});

// The shipped ceiling has to clear ordinary use by a wide margin: the
// front end debounces at 200ms, so even continuous typing for the whole
// window stays well under it, and behind a CDN the whole site may share
// one REMOTE_ADDR.
it('ships a cap that leaves room for real use', function () {
    expect(RateLimiter::MAX_REQUESTS)->toBeGreaterThanOrEqual(300)
        ->and(RateLimiter::WINDOW_SECONDS)->toBe(60);
});
