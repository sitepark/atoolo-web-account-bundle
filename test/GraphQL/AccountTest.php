<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Test\GraphQL;

use Atoolo\WebAccount\GraphQL\Account;
use Atoolo\WebAccount\Service\Login;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(Account::class)]
class AccountTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testLogin(): void
    {
        $loginService = $this->createMock(Login::class);
        $loginService->expects($this->once())
            ->method('login')
            ->with('testuser', 'testpassword')
            ->willReturn('123');

        $account = new Account($loginService);
        $result = $account->login('testuser', 'testpassword');

        $this->assertSame('123', $result);
    }
}
