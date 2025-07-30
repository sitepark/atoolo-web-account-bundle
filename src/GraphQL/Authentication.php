<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\GraphQL;

use Atoolo\WebAccount\Dto\AuthenticationResult;
use Atoolo\WebAccount\Dto\AuthenticationStatus;
use Atoolo\WebAccount\Service\Authentication\UsernamePasswordAuthentication;
use Atoolo\WebAccount\Service\CookieJar;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Overblog\GraphQLBundle\Annotation as GQL;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[GQL\Provider]
class Authentication
{
    public function __construct(
        private readonly UsernamePasswordAuthentication $usernamePasswordAuthentication,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly NormalizerInterface $normalizer,
        private readonly CookieJar $cookieJar,
        #[Autowire('%atoolo_webaccount.token_ttl%')]
        private readonly int $tokenTtl,
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    #[GQL\Mutation(name: 'webaccountAuthenticationWithPassword', type: 'AuthenticationResult!')]
    public function authenticationWithPassword(
        string $username,
        string $password,
        bool $setJwtCookie
    ): AuthenticationResult {
        $result = $this->usernamePasswordAuthentication->authenticate($username, $password);

        if ($setJwtCookie && $result->status === AuthenticationStatus::SUCCESS && $result->user !== null) {
            $customPayload = array_merge($this->normalizer->normalize($result->user),
                [
                    'exp' => time() + $this->tokenTtl,
                    'roles' => array_merge(['WEB_ACCOUNT'], $result->user->getRoles()),
                ]);
            $jwt = $this->jwtManager->createFromPayload($result->user, $customPayload);
            $cookie = Cookie::create(CookieJar::WEBACCOUNT_TOKEN_NAME)
                ->withValue($jwt)
                ->withExpires(time() + $this->tokenTtl)
                ->withHttpOnly(true)
                ->withSecure(true)
                ->withPath('/')
                ->withSameSite('Strict');

            $this->cookieJar->add($cookie);
        }

        return $result;
    }

    #[GQL\Mutation(name: 'webaccountUnsetJwtCookie', type: 'Boolean!')]
    public function unsetJwtCookie(): bool
    {
        $cookie = Cookie::create(CookieJar::WEBACCOUNT_TOKEN_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withHttpOnly(true)
            ->withSecure(true)
            ->withPath('/')
            ->withSameSite('Strict');

        $this->cookieJar->add($cookie);
        return true;
    }

}
