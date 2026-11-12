<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Service\RateLimit;

use Atoolo\Resource\ResourceChannel;
use Atoolo\WebAccount\Dto\Config\RateLimitConfiguration;
use Atoolo\WebAccount\Dto\Config\WebAccountConfiguration;
use Atoolo\WebAccount\Exception\RateLimitExceededException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Limits the public web-account endpoints per client IP. The IES only sees
 * the IP of the website server, so the limit has to be enforced here.
 *
 * Counters are kept separately per resource channel, web-account
 * configuration, operation and client IP.
 *
 * @phpstan-type Limit array{limit: int, interval: string}
 * @phpstan-type Settings array{
 *     enabled: bool,
 *     start: Limit,
 *     finish: Limit,
 *     authentication: Limit,
 * }
 */
class RequestRateLimiter
{
    private readonly StorageInterface $storage;

    /**
     * @param Settings $settings
     */
    public function __construct(
        #[Autowire(service: 'atoolo_resource.resource_channel')]
        private readonly ResourceChannel $resourceChannel,
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'atoolo_web_account.rate_limit.cache')]
        CacheItemPoolInterface $cache,
        #[Autowire('%atoolo_web_account.rate_limit%')]
        private readonly array $settings,
    ) {
        $this->storage = new CacheStorage($cache);
    }

    /**
     * @throws RateLimitExceededException
     */
    public function consume(RateLimitedOperation $operation, ?WebAccountConfiguration $config = null): void
    {
        if (!$this->settings['enabled']) {
            return;
        }

        $limit = $this->resolveLimit($operation, $config);

        $factory = new RateLimiterFactory(
            [
                'id' => 'web_account_' . $operation->value,
                'policy' => 'sliding_window',
                'limit' => $limit['limit'],
                'interval' => $limit['interval'],
            ],
            $this->storage,
        );

        $key = implode('|', [
            $this->resourceChannel->id,
            $config->name ?? '-',
            $this->getClientIp(),
        ]);

        if (!$factory->create($key)->consume()->isAccepted()) {
            throw new RateLimitExceededException();
        }
    }

    /**
     * @return Limit
     */
    private function resolveLimit(RateLimitedOperation $operation, ?WebAccountConfiguration $config): array
    {
        [$group, $override] = match ($operation) {
            RateLimitedOperation::START_REGISTRATION,
            RateLimitedOperation::START_PASSWORD_RECOVERY => ['start', $config?->rateLimit?->start],
            RateLimitedOperation::FINISH_REGISTRATION,
            RateLimitedOperation::FINISH_PASSWORD_RECOVERY => ['finish', $config?->rateLimit?->finish],
            RateLimitedOperation::AUTHENTICATION => ['authentication', null],
        };

        if ($override instanceof RateLimitConfiguration) {
            return ['limit' => $override->limit, 'interval' => $override->interval];
        }

        return $this->settings[$group];
    }

    private function getClientIp(): string
    {
        return $this->requestStack->getMainRequest()?->getClientIp() ?? 'unknown';
    }
}
