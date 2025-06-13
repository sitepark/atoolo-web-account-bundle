<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Service;

use Atoolo\Resource\ResourceChannel;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Login
{
    public function __construct(
        #[Autowire(service: 'atoolo_resource.resource_channel')]
        private readonly ResourceChannel $resourceChannel,
        private readonly RequestStack $requestStack,
        private readonly HttpClientInterface $client,
    ) {}

    /**
     * @throws \JsonException
     * @throws TransportExceptionInterface
     */
    public function login(string $login, string $password): string
    {

        $response = $this->client->request(
            'POST',
            'https://' . $this->resourceChannel->tenant->host . '/ies/auth/rpc',
            [
                'json' => [
                    "tid" => 1,
                    "action" => "AuthServer",
                    "method" => "login",
                    "data" => [
                        [
                            "login" => $login,
                            "password" => $password,
                            "client" => $this->resourceChannel->tenant->anchor,
                        ],
                    ],
                ],
            ],
        );


        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(
                'HTTP Status Code from '
                . $this->resourceChannel->tenant->host
                . ': '
                . $response->getStatusCode(),
            );
        }

        try {
            $responseData = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new RuntimeException(
                'Response from '
                . $this->resourceChannel->tenant->host
                . ' could not be decoded: '
                . $e->getMessage(),
            );
        }

        if (($responseData["result"]['success'] ?? false) !== true) {
            throw new RuntimeException($responseData["result"]['error'] ?? 'Login failed');
        }

        $data = $responseData["result"]["data"] ?? [];
        $account = $data['account'] ?? [];

        $session = $this->requestStack->getSession();
        $session->set('webaccount_profile', $data['account'] ?? []);

        return $account['uuid'];
    }
}
