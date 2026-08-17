<?php

declare(strict_types=1);

namespace LifeLines\Lookup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fixed-window request throttle for the public lookup endpoint.
 *
 * The search is unauthenticated and nonce-free by design (see
 * {@see LookupController}), and every call runs a `LIKE '%term%'` scan across
 * every searchable column — no index helps a leading wildcard. That is cheap
 * once and expensive in a loop, so this bounds how fast one caller can ask.
 *
 * <b>The limit is deliberately loose.</b> This endpoint backs a helpline
 * finder: someone using it may be in a bad way, and a lookup that refuses to
 * answer is a worse outcome than a database working harder than it needs to.
 * The ceiling is set to catch a script hammering the endpoint, not to police
 * ordinary use, and everything below errs towards letting the request through:
 *
 *   - The window is generous relative to the front end, which debounces input
 *     at 200ms — a continuous minute of typing produces well under the cap.
 *   - {@see clientIp()} reads REMOTE_ADDR only, never X-Forwarded-For, which a
 *     caller can set to anything. Behind a CDN or reverse proxy that means the
 *     edge's address rather than the visitor's, so a whole town can share one
 *     bucket. The cap is sized on the assumption that it might be.
 *   - A transient backend that is missing or failing degrades to allowing the
 *     request rather than refusing it.
 *
 * Precise per-visitor limiting belongs at the CDN or WAF, which can see the
 * real client. This is a floor, not that.
 */
final class RateLimiter
{
    /** Requests allowed per window, per client IP. */
    public const MAX_REQUESTS = 300;

    /** Window length in seconds. */
    public const WINDOW_SECONDS = 60;

    private const PREFIX = 'lifelines_rl_';

    /**
     * Record one hit for this client and report whether it has now exceeded
     * the window's allowance.
     *
     * The first hit of a window seeds the counter and the transient's own TTL
     * retires it, so there is nothing to clean up.
     */
    public function overLimit(string $key, int $max = self::MAX_REQUESTS, int $windowSeconds = self::WINDOW_SECONDS): bool
    {
        $max = max(1, $max);
        $windowSeconds = max(1, $windowSeconds);

        $window = (int) floor(time() / $windowSeconds);
        $bucket = self::PREFIX . md5($key . '|' . $window);

        $count = (int) get_transient($bucket);
        if ($count >= $max) {
            return true;
        }

        // Held a little past the window so a burst straddling the boundary is
        // still counted rather than being split across two fresh buckets.
        set_transient($bucket, $count + 1, $windowSeconds * 2);

        return false;
    }

    /**
     * The client address from REMOTE_ADDR, or 'unknown' when it is absent or
     * not an IP.
     *
     * X-Forwarded-For is deliberately not consulted: it is caller-supplied, so
     * trusting it would let anyone mint a fresh bucket per request and opt out
     * of the limit entirely.
     */
    public function clientIp(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
            : '';

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
    }
}
