<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DEFAULT_QUERY_PARAM_NAMES = [
        'key',
        'api_key',
        'apikey',
        'access_token',
        'token',
        'secret',
        'password',
        'auth',
        'signature',
    ];

    public const DEFAULT_VALUE_PATTERNS = [
        '#AIza[0-9A-Za-z\-_]{20,}#',
        '#sk_(?:live|test)_[0-9a-zA-Z]{16,}#',
        '#\bBearer\s+[A-Za-z0-9\-._~+/]{16,}=*#i',
        '#\beyJ[A-Za-z0-9_\-]{4,}\.[A-Za-z0-9_\-]{4,}\.[A-Za-z0-9_\-]{4,}\b#',
    ];

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
                ->scalarNode('before_send')
                    ->defaultNull()
                    ->info('Service ID of a callable __invoke(Event, ?EventHint): ?Event. Overrides the default SensitiveDataScrubber. Mirror upstream sentry-symfony: no @ prefix.')
                ->end()
                ->scalarNode('before_breadcrumb')
                    ->defaultNull()
                    ->info('Service ID of a callable __invoke(Breadcrumb, ?BreadcrumbHint): ?Breadcrumb. No @ prefix.')
                ->end()
                ->arrayNode('json_serialize')->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('namespace_serialize')->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('scrubber')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->arrayNode('query_param_names')
                            ->info('Query-string parameter names to redact (case-insensitive). Overrides defaults.')
                            ->defaultValue(self::DEFAULT_QUERY_PARAM_NAMES)
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('extra_query_param_names')
                            ->info('Extra query-string parameter names to redact in addition to the defaults.')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('value_patterns')
                            ->info('PCRE patterns (delimited) for credential shapes. Overrides defaults.')
                            ->defaultValue(self::DEFAULT_VALUE_PATTERNS)
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('extra_value_patterns')
                            ->info('Extra PCRE patterns (delimited) to redact in addition to the defaults.')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
