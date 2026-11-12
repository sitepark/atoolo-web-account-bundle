<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Dto\Config;

/**
 * Overrides the bundle-wide rate limits for a single web-account configuration.
 *
 * @codeCoverageIgnore
 */
class RateLimitsConfiguration
{
    public function __construct(
        public readonly ?RateLimitConfiguration $start = null,
        public readonly ?RateLimitConfiguration $finish = null,
    ) {}
}
