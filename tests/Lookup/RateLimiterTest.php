<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;
use LifeLines\Lookup\RateLimiter;

/**
 * The throttle in front of the public, unauthenticated lookup endpoint.
 *
 * @covers \LifeLines\Lookup\RateLimiter
 */
class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        WpState::$transients = [];
        unset($_SERVER['REMOTE_ADDR']);
        $this->limiter = new RateLimiter();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    /** @test */
    public function a_request_under_the_cap_is_allowed(): void
    {
        $this->assertFalse($this->limiter->overLimit('k', 3, 60));
    }

    /** @test */
    public function the_cap_is_reached_only_after_that_many_requests(): void
    {
        $this->assertFalse($this->limiter->overLimit('k', 3, 60));
        $this->assertFalse($this->limiter->overLimit('k', 3, 60));
        $this->assertFalse($this->limiter->overLimit('k', 3, 60));

        $this->assertTrue($this->limiter->overLimit('k', 3, 60));
    }

    /** @test */
    public function separate_keys_do_not_share_a_bucket(): void
    {
        $this->limiter->overLimit('a', 1, 60);

        $this->assertTrue($this->limiter->overLimit('a', 1, 60));
        $this->assertFalse($this->limiter->overLimit('b', 1, 60));
    }

    /**
     * A window of zero would otherwise divide by zero when picking a bucket.
     *
     * @test
     */
    public function a_nonsensical_window_or_cap_is_clamped_rather_than_fatal(): void
    {
        $this->assertFalse($this->limiter->overLimit('k', 0, 0));
        $this->assertTrue($this->limiter->overLimit('k', 0, 0));
    }

    /** @test */
    public function the_client_ip_comes_from_remote_addr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';

        $this->assertSame('203.0.113.9', $this->limiter->clientIp());
    }

    /** @test */
    public function an_absent_or_malformed_remote_addr_becomes_unknown(): void
    {
        $this->assertSame('unknown', $this->limiter->clientIp());

        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        $this->assertSame('unknown', $this->limiter->clientIp());
    }

    /**
     * X-Forwarded-For is caller-supplied. Honouring it would let anyone mint a
     * fresh bucket per request and opt out of the limit entirely.
     *
     * @test
     */
    public function a_forwarded_for_header_is_ignored(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';

        $this->assertSame('203.0.113.9', $this->limiter->clientIp());

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    /**
     * The shipped ceiling has to clear ordinary use by a wide margin: the
     * front end debounces at 200ms, so even continuous typing for the whole
     * window stays well under it, and behind a CDN the whole site may share
     * one REMOTE_ADDR.
     *
     * @test
     */
    public function the_shipped_cap_leaves_room_for_real_use(): void
    {
        $this->assertGreaterThanOrEqual(300, RateLimiter::MAX_REQUESTS);
        $this->assertSame(60, RateLimiter::WINDOW_SECONDS);
    }
}
