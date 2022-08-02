<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle;

use Lingoda\SentryBundle\DependencyInjection\CompilerPass\SerializerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle for handling sentry events and loggings
 */
class LingodaSentryBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new SerializerPass());
    }
}
