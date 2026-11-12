<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Service\RateLimit;

enum RateLimitedOperation: string
{
    case START_REGISTRATION = 'start_registration';
    case FINISH_REGISTRATION = 'finish_registration';
    case START_PASSWORD_RECOVERY = 'start_password_recovery';
    case FINISH_PASSWORD_RECOVERY = 'finish_password_recovery';
    case AUTHENTICATION = 'authentication';
}
