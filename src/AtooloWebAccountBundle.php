<?php

declare(strict_types=1);

namespace Atoolo\WebAccount;

use Exception;
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
        /** @phpstan-ignore-next-line */
        $definition->rootNode()
            ->children()
            ->integerNode('token_ttl')
            ->defaultValue(60 * 60 * 24 * 30)
            ->min(60 * 60)
            ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()->set('atoolo_webaccount.token_ttl', $config['token_ttl']);
    }

    /**
     * @throws Exception
     */
    public function build(ContainerBuilder $container): void
    {

        $container->setParameter(
            'atoolo_webaccount.src_dir',
            __DIR__,
        );

        $configDir = __DIR__ . '/../config';

        $container->setParameter(
            'atoolo_webaccount.config_dir',
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

    public function getPath(): string
    {
        return __DIR__;
    }
}
