<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\DependencyInjection;

use Lingoda\SentryBundle\Sentry\Serializer\ObjectJsonSerializer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\Yaml\Yaml;

class LingodaSentryExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    /**
     * @param array<string, mixed> $mergedConfig
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(
            __DIR__ . '/../Resources/config'
        ));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $sentryConfig = Yaml::parseFile(__DIR__ . '/../Resources/config/sentry.yaml');
        $sentryConfig = $sentryConfig['sentry'];

        // Load config of this bundle
        $configs = $container->getExtensionConfig($this->getAlias());

        // resolve config parameters e.g. %kernel.debug% to its boolean value
        $resolvingBag = $container->getParameterBag();
        $configs = $resolvingBag->resolveValue($configs);

        $config = $this->processConfiguration(new Configuration(), $configs);

        $sentryConfig['dsn'] = $config['dsn'];
        $sentryConfig['options']['environment'] = $config['environment'];
        $sentryConfig['options']['release'] = $config['release'];
        $sentryConfig['options']['tags']['namespace'] = $config['namespace'];
        $sentryConfig['options']['traces_sample_rate'] = $config['traces_sample_rate'];

        $sentryConfig['options']['class_serializers'] = array_fill_keys(
            $config['json_serialize'],
            ObjectJsonSerializer::class
        );

        $container->setParameter('lingoda_sentry.config', $config);
        $container->prependExtensionConfig('sentry', $sentryConfig);
    }
}
