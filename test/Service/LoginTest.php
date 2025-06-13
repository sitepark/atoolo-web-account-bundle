<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Test\Service;

use Atoolo\Resource\DataBag;
use Atoolo\Resource\ResourceChannel;
use Atoolo\Resource\ResourceTenant;
use Atoolo\WebAccount\Service\Login;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Login::class)]
class LoginTest extends TestCase
{
    private Login $loginService;

    private ResponseInterface $httpResponse;

    private SessionInterface $session;

    public function setUp(): void
    {
        $resourceTenant = new ResourceTenant(
            "",
            "",
            "",
            "test.com",
            new DataBag([]),
        );

        $resourceChannel = new ResourceChannel(
            '',
            '',
            '',
            '',
            false,
            '',
            '',
            '',
            '',
            '',
            '',
            [],
            $resourceTenant,
        );

        $requestStack = $this->createMock(RequestStack::class);
        $this->session = $this->createMock(SessionInterface::class);
        $requestStack->method('getSession')
            ->willReturn($this->session);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->httpResponse = $this->createMock(ResponseInterface::class);
        $httpClient->method('request')->willReturn($this->httpResponse);

        $this->loginService = new Login($resourceChannel, $requestStack, $httpClient);
    }

    public function testLogin(): void
    {
        $this->httpResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $this->httpResponse->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'result' => [
                    'success' => true,
                    'data' => [
                        'account' => [
                            'uuid' => '123',
                            'login' => 'testuser',
                        ],
                    ],
                ],
            ]);
        $this->session->expects($this->once())
            ->method('set')
            ->with('webaccount_profile', [
                'uuid' => '123',
                'login' => 'testuser',
            ]);

        $result = $this->loginService->login('testuser', 'testpassword');
        $this->assertEquals('123', $result, "Unexpected login result");
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    public function testLoginWithWrongHttpStatusCode(): void
    {
        $this->httpResponse
            ->method('getStatusCode')
            ->willReturn(404);

        $this->expectException(RuntimeException::class);
        $this->loginService->login('testuser', 'testpassword');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    public function testLoginWithJsonException(): void
    {
        $this->httpResponse
            ->method('getStatusCode')
            ->willReturn(200);

        $this->httpResponse->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'result' => [
                    'success' => false,
                ],
            ]);

        $this->expectException(RuntimeException::class);
        $this->loginService->login('testuser', 'testpassword');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    public function testLoginSuccessIsFalse(): void
    {
        $this->httpResponse
            ->method('getStatusCode')
            ->willReturn(200);

        $this->httpResponse
            ->method('toArray')
            ->willThrowException(new JsonException());


        $this->expectException(RuntimeException::class);
        $this->loginService->login('testuser', 'testpassword');
    }


}
