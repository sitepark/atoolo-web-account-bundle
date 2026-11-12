<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Dto\Config;

/**
 * @codeCoverageIgnore
 */
class WebAccountConfiguration
{
    public function __construct(
        public readonly string $name,
        public readonly string $apiKey,
        public readonly RegistrationConfiguration $registration,
        public readonly EmailConfiguration $email,
        /**
         * The configuration as signed by the IES when it published it. The IES takes sender,
         * reply-to, theme and roles only from this token, so it is passed on unchanged.
         */
        public readonly string $signedConfig,
        public readonly ?RateLimitsConfiguration $rateLimit = null,
    ) {}
}
