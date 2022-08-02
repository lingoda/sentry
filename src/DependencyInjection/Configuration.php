<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('lingoda_sentry');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode('dsn')->isRequired()->end()
            ->scalarNode('environment')->isRequired()->end()
            ->scalarNode('namespace')->defaultNull()->end()
            ->scalarNode('release')->defaultValue('none')->end()
            ->floatNode('traces_sample_rate')->defaultValue(0.0)->end()
            ->arrayNode('json_serialize')->defaultValue([])
            ->scalarPrototype()->end()
            ->end()
            ->arrayNode('namespace_serialize')->defaultValue([])
            ->scalarPrototype()->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
