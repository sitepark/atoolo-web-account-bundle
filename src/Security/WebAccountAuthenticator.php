<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Security;

use Atoolo\Security\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

#[AsAlias(id: 'atoolo_webaccount.authenticator')]
class WebAccountAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        return $request->getSession()->has('webaccount_profile');
    }

    public function authenticate(Request $request): Passport
    {
        /** @var array{login:string} $profile */
        $profile = $request->getSession()->get('webaccount_profile');

        return new SelfValidatingPassport(new UserBadge($profile['login'], function ($userIdentifier) {
            return new User($userIdentifier, ['ROLE_WEB_ACCOUNT']);
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }

}
