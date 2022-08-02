<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\DependencyInjection\CompilerPass;

use Lingoda\SentryBundle\Sentry\RepresentationSerializer;
use Lingoda\SentryBundle\Sentry\Serializer\NamespaceJsonSerializer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Webmozart\Assert\Assert;

/**
 * Override serializer with our own
 */
class SerializerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $representationSerializerDefinition = $container->getDefinition(RepresentationSerializer::class);

        $clientDef = $container->getDefinition('sentry.client');
        $clientFactory = $clientDef->getFactory();
        Assert::isArray($clientFactory);

        $clientBuilderDef = $clientFactory[0];
        $clientBuilderDef->addMethodCall('setRepresentationSerializer', [$representationSerializerDefinition]);

        $this->configureNamespaceJsonSerializer($container);
    }

    private function configureNamespaceJsonSerializer(ContainerBuilder $container): void
    {
        /** @var array{namespace_serialize: string[]} $extensionConfig */
        $extensionConfig = $container->getParameter('lingoda_sentry.config');

        $namespaceSerializer = $container->getDefinition(NamespaceJsonSerializer::class);
        $namespaceSerializer->setArgument('$namespaces', $extensionConfig['namespace_serialize']);
    }
}
