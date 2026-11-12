<?php

declare(strict_types=1);

namespace Atoolo\WebAccount;

use Exception;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\GlobFileLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @codeCoverageIgnore
 */
class AtooloWebAccountBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->integerNode('token_ttl')
            ->defaultValue(60 * 60 * 24 * 30)
            ->min(60 * 60)
            ->end()
            ->integerNode('registration_token_ttl')
            ->defaultValue(60 * 60 * 2)
            ->min(60 * 10)
            ->end()
            ->integerNode('password_reset_token_ttl')
            ->defaultValue(60 * 60 * 2)
            ->min(60 * 10)
            ->end()
            ->stringNode('unauthorized_entry_point')
            ->defaultValue('/account')
            ->end()
            ->arrayNode('rate_limit')
            ->info('Limits the public endpoints per client IP (sliding window)')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultTrue()
            ->end()
            ->scalarNode('cache_pool')
            ->info('Cache pool service that stores the rate limiter state')
            ->defaultValue('cache.app')
            ->cannotBeEmpty()
            ->end()
            ->append($this->createRateLimitNode('start', 5))
            ->append($this->createRateLimitNode('finish', 10))
            ->append($this->createRateLimitNode('authentication', 10))
            ->end()
            ->end()
            ->end();
    }

    private function createRateLimitNode(string $name, int $defaultLimit): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);
        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('limit')
            ->defaultValue($defaultLimit)
            ->min(1)
            ->end()
            ->scalarNode('interval')
            ->defaultValue('15 minutes')
            ->cannotBeEmpty()
            ->end()
            ->end();
        return $node;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()->set('atoolo_web_account.token_ttl', $config['token_ttl']);
        $container->parameters()->set('atoolo_web_account.registration_token_ttl', $config['registration_token_ttl']);
        $container->parameters()->set(
            'atoolo_web_account.password_reset_token_ttl',
            $config['password_reset_token_ttl'],
        );
        $container->parameters()->set(
            'atoolo_web_account.unauthorized_entry_point',
            $config['unauthorized_entry_point'],
        );

        /** @var array{cache_pool: string, enabled: bool} $rateLimit */
        $rateLimit = $config['rate_limit'];
        $container->services()->alias('atoolo_web_account.rate_limit.cache', $rateLimit['cache_pool']);
        unset($rateLimit['cache_pool']);
        $container->parameters()->set('atoolo_web_account.rate_limit', $rateLimit);
    }

    /**
     * @throws Exception
     */
    public function build(ContainerBuilder $container): void
    {

        $container->setParameter(
            'atoolo_web_account.src_dir',
            __DIR__,
        );

        $configDir = __DIR__ . '/../config';

        $container->setParameter(
            'atoolo_web_account.config_dir',
            $configDir,
        );

        $loader = new GlobFileLoader(new FileLocator($configDir));
        $loader->setResolver(
            new LoaderResolver(
                [
                    new YamlFileLoader($container, new FileLocator($configDir)),
                ],
            ),
        );

        $loader->load('graphql.yaml');
        $loader->load('services.yaml');
    }
}
