<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\GraphQL;

use Overblog\GraphQLBundle\Annotation as GQL;

#[GQL\Provider]
class Account
{
    public function __construct(
        private readonly \Atoolo\WebAccount\Service\Login $loginService,
    ) {}
    /*
    #[GQL\Mutation(name: 'webaccountRegistration', type: 'Boolean!')]
    public function registration(): bool
    {
        return true;
    }

    #[GQL\Mutation(name: 'webaccountRemove', type: 'Boolean!')]
    public function remove(): bool
    {
        return true;
    }
    */

    #[GQL\Mutation(name: 'webaccountLogin', type: 'String!')]
    public function login(string $login, string $password): string
    {
        return $this->loginService->login($login, $password);
    }

    /*
    #[GQL\Mutation(name: 'webaccountPasswordReset', type: 'Boolean!')]
    public function passwordReset(): bool
    {
        return true;
    }

    #[GQL\Mutation(name: 'updateProfile', type: 'Boolean!')]
    public function updateProfile(): bool
    {
        return true;
    }
    */

}
