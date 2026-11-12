<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Dto\Config;

/**
 * @codeCoverageIgnore
 */
class RateLimitConfiguration
{
    /**
     * @param int $limit maximum number of requests per client IP within the interval
     * @param string $interval sliding window size, e.g. '15 minutes'
     */
    public function __construct(
        public readonly int $limit,
        public readonly string $interval,
    ) {}
}
