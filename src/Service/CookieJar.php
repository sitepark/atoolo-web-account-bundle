<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Service;

use Symfony\Component\HttpFoundation\Cookie;

class CookieJar
{

    public const WEBACCOUNT_TOKEN_NAME = 'WEBACCOUNT_TOKEN';

    /** @var Cookie[] */
    private array $cookies = [];

    public function add(Cookie $cookie): void
    {
        $this->cookies[] = $cookie;
    }

    /**
     * @return Cookie[]
     */
    public function all(): array
    {
        return $this->cookies;
    }
}
