<?php

declare(strict_types=1);

namespace Atoolo\WebAccount\Test\Service\RateLimit;

use Atoolo\Resource\ResourceChannel;
use Atoolo\WebAccount\Dto\Config\EmailConfiguration;
use Atoolo\WebAccount\Dto\Config\RateLimitConfiguration;
use Atoolo\WebAccount\Dto\Config\RateLimitsConfiguration;
use Atoolo\WebAccount\Dto\Config\RegistrationConfiguration;
use Atoolo\WebAccount\Dto\Config\WebAccountConfiguration;
use Atoolo\WebAccount\Exception\RateLimitExceededException;
use Atoolo\WebAccount\Service\RateLimit\RateLimitedOperation;
use Atoolo\WebAccount\Service\RateLimit\RequestRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Address;

#[CoversClass(RequestRateLimiter::class)]
class RequestRateLimiterTest extends TestCase
{
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    public function testAcceptsRequestsUpToTheLimit(): void
    {
        $limiter = $this->createLimiter();

        $limiter->consume(RateLimitedOperation::START_REGISTRATION);
        $limiter->consume(RateLimitedOperation::START_REGISTRATION);

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Too many requests. Please try again later.');
        $limiter->consume(RateLimitedOperation::START_REGISTRATION);
    }

    public function testStartOperationsHaveSeparateCounters(): void
    {
        $limiter = $this->createLimiter();

        $limiter->consume(RateLimitedOperation::START_REGISTRATION);
        $limiter->consume(RateLimitedOperation::START_REGISTRATION);
        $limiter->consume(RateLimitedOperation::START_PASSWORD_RECOVERY);
        $limiter->consume(RateLimitedOperation::START_PASSWORD_RECOVERY);

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume(RateLimitedOperation::START_PASSWORD_RECOVERY);
    }

    public function testUsesLimitOfTheOperationGroup(): void
    {
        $limiter = $this->createLimiter();

        for ($i = 0; $i < 3; $i++) {
            $limiter->consume(RateLimitedOperation::FINISH_REGISTRATION);
            $limiter->consume(RateLimitedOperation::FINISH_PASSWORD_RECOVERY);
        }
        $limiter->consume(RateLimitedOperation::AUTHENTICATION);

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume(RateLimitedOperation::AUTHENTICATION);
    }

    public function testDifferentClientIpsHaveSeparateCounters(): void
    {
        $this->createLimiter('192.0.2.1')->consume(RateLimitedOperation::AUTHENTICATION);
        $this->createLimiter('192.0.2.2')->consume(RateLimitedOperation::AUTHENTICATION);

        $this->expectException(RateLimitExceededException::class);
        $this->createLimiter('192.0.2.1')->consume(RateLimitedOperation::AUTHENTICATION);
    }

    public function testDifferentResourceChannelsHaveSeparateCounters(): void
    {
        $this->createLimiter(channelId: 'site-a')->consume(RateLimitedOperation::AUTHENTICATION);
        $this->createLimiter(channelId: 'site-b')->consume(RateLimitedOperation::AUTHENTICATION);

        $this->expectException(RateLimitExceededException::class);
        $this->createLimiter(channelId: 'site-a')->consume(RateLimitedOperation::AUTHENTICATION);
    }

    public function testWithoutRequestAllRequestsShareOneCounter(): void
    {
        $limiter = $this->createLimiter(null);

        $limiter->consume(RateLimitedOperation::AUTHENTICATION);

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume(RateLimitedOperation::AUTHENTICATION);
    }

    public function testConfigurationOverridesStartLimit(): void
    {
        $limiter = $this->createLimiter();
        $config = $this->createConfig(new RateLimitsConfiguration(
            start: new RateLimitConfiguration(limit: 1, interval: '1 hour'),
        ));

        $limiter->consume(RateLimitedOperation::START_REGISTRATION, $config);

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume(RateLimitedOperation::START_REGISTRATION, $config);
    }

    public function testConfigurationOverridesFinishLimit(): void
    {
        $limiter = $this->createLimiter();
        $config = $this->createConfig(new RateLimitsConfiguration(
            finish: new RateLimitConfiguration(limit: 1, interval: '1 hour'),
        ));

        $limiter->consume(RateLimitedOperation::FINISH_REGISTRATION, $config);

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume(RateLimitedOperation::FINISH_REGISTRATION, $config);
    }

    public function testConfigurationWithoutOverrideUsesDefault(): void
    {
        $limiter = $this->createLimiter();
        $config = $this->createConfig(new RateLimitsConfiguration(
            finish: new RateLimitConfiguration(limit: 1, interval: '1 hour'),
        ));

        $limiter->consume(RateLimitedOperation::START_REGISTRATION, $config);
        $limiter->consume(RateLimitedOperation::START_REGISTRATION, $config);

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume(RateLimitedOperation::START_REGISTRATION, $config);
    }

    public function testDifferentConfigurationsHaveSeparateCounters(): void
    {
        $limiter = $this->createLimiter();

        $limiter->consume(RateLimitedOperation::AUTHENTICATION, $this->createConfig(null, 'a'));
        $limiter->consume(RateLimitedOperation::AUTHENTICATION, $this->createConfig(null, 'b'));

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume(RateLimitedOperation::AUTHENTICATION, $this->createConfig(null, 'a'));
    }

    public function testDisabledLimiterNeverThrows(): void
    {
        $limiter = $this->createLimiter(enabled: false);

        for ($i = 0; $i < 5; $i++) {
            $limiter->consume(RateLimitedOperation::AUTHENTICATION);
        }

        $this->assertSame([], $this->cache->getValues(), 'No limiter state should be stored');
    }

    private function createLimiter(
        ?string $clientIp = '192.0.2.1',
        string $channelId = 'site',
        bool $enabled = true,
    ): RequestRateLimiter {
        $requestStack = new RequestStack();
        if ($clientIp !== null) {
            $requestStack->push(Request::create('/', 'POST', server: ['REMOTE_ADDR' => $clientIp]));
        }

        return new RequestRateLimiter(
            ResourceChannel::create(['id' => $channelId]),
            $requestStack,
            $this->cache,
            [
                'enabled' => $enabled,
                'start' => ['limit' => 2, 'interval' => '15 minutes'],
                'finish' => ['limit' => 3, 'interval' => '15 minutes'],
                'authentication' => ['limit' => 1, 'interval' => '15 minutes'],
            ],
        );
    }

    private function createConfig(?RateLimitsConfiguration $rateLimit, string $name = 'test'): WebAccountConfiguration
    {
        return new WebAccountConfiguration(
            name: $name,
            apiKey: 'test-api-key',
            registration: new RegistrationConfiguration(['ROLE_USER']),
            email: new EmailConfiguration(
                theme: 'default',
                from: new Address('noreply@example.com', 'Example'),
                replyTo: new Address('support@example.com', 'Support'),
            ),
            signedConfig: 'signed-config',
            rateLimit: $rateLimit,
        );
    }
}
