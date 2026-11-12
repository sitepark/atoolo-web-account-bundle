<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Exception;

use Atoolo\WebAccount\Service\ServiceException;

/**
 * The message is deliberately generic: it must not reveal whether a user
 * or email address exists.
 *
 * @codeCoverageIgnore
 */
class RateLimitExceededException extends ServiceException
{
    public function __construct()
    {
        parent::__construct('Too many requests. Please try again later.');
    }
}
