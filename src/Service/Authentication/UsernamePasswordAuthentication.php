<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Service\Authentication;

use Atoolo\Resource\ResourceChannel;
use Atoolo\WebAccount\Dto\AuthenticationResult;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class UsernamePasswordAuthentication
{
    public function __construct(
        #[Autowire(service: 'atoolo_resource.resource_channel')]
        private readonly ResourceChannel $resourceChannel,
        private readonly HttpClientInterface $client,
        private readonly DenormalizerInterface $denormalizer,
    ) {}

    /**
     * @throws \JsonException
     * @throws TransportExceptionInterface
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function authenticate(string $username, string $password): AuthenticationResult
    {

        $query = <<<'GRAPHQL'
mutation authenticate($username: String!, $password: String!) {
  security {
    authenticate {
      withPassword(
        username: $username
        password: $password
        purpose: "atoolo_webaccount"
      ) {
        status
        user {
          id
          username
          firstName
          lastName
          email
          roles
        }
      }
    }
  }
}
GRAPHQL;

        $variables = [
            'username' => $username,
            'password' => $password,
        ];

        $payload = ['query' => $query, 'variables' => $variables];


        $response = $this->client->request(
            'POST',
            'https://' . $this->resourceChannel->tenant->host . '/api/graphql',
            [
                'json' => $payload,
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
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Response from '
                . $this->resourceChannel->tenant->host
                . ' could not be decoded: '
                . $e->getMessage(),
            );
        }

        if (!empty($responseData['errors'] ?? [])) {
            throw new RuntimeException($responseData['errors'][0]['message'] ?? 'Login failed');
        }

        $data = $responseData['data']['security']['authenticate']['withPassword'] ?? [];

        /** @var AuthenticationResult $result */
        $result = $this->denormalizer->denormalize($data, AuthenticationResult::class);
        return $result;

    }
}
