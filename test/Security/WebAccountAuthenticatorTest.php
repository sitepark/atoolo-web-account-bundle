<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Test\Security;

use Atoolo\WebAccount\Security\WebAccountAuthenticator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[CoversClass(WebAccountAuthenticator::class)]
class WebAccountAuthenticatorTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testSupports(): void
    {
        $authenticator = new WebAccountAuthenticator();
        $request = $this->createMock(Request::class);
        $session = $this->createMock(SessionInterface::class);
        $request->expects($this->once())
            ->method('getSession')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('has')
            ->with('webaccount_profile')
            ->willReturn(true);

        $this->assertTrue($authenticator->supports($request));
    }

    /**
     * @throws Exception
     */
    public function testAuthenticate(): void
    {
        $authenticator = new WebAccountAuthenticator();
        $request = $this->createMock(Request::class);
        $session = $this->createMock(SessionInterface::class);
        $request->expects($this->once())
            ->method('getSession')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('get')
            ->with('webaccount_profile')
            ->willReturn(['login' => 'testuser']);

        $passport = $authenticator->authenticate($request);
        $this->assertNotNull($passport);
        $this->assertSame('testuser', $passport->getUser()->getUserIdentifier());
    }

    public function testOnAuthenticationSuccess(): void
    {
        $authenticator = new WebAccountAuthenticator();
        $request = $this->createMock(Request::class);
        $token = $this->createMock(TokenInterface::class);
        $firewallName = 'main';

        $response = $authenticator->onAuthenticationSuccess($request, $token, $firewallName);
        $this->assertNull($response);
    }

    public function testOnAuthenticationFailure(): void
    {
        $authenticator = new WebAccountAuthenticator();
        $request = $this->createMock(Request::class);
        $exception = $this->createMock(AuthenticationException::class);

        $response = $authenticator->onAuthenticationFailure($request, $exception);
        $this->assertNull($response);
    }

}
