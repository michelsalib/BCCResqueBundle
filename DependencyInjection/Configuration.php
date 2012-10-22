<?php

namespace BCC\ResqueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('bcc_resque');

        $rootNode
            ->children()
                ->scalarNode('vendor_dir')
                    ->info('Set the vendor dir')

                ->end()
                ->arrayNode('redis')
                    ->info('Redis configuration')
                    ->children()
                        ->scalarNode('host')
                            ->info('The redis hostname')
                        ->end()
                        ->scalarNode('port')
                            ->info('The redis port')
                        ->end()
                        ->scalarNode('database')
                            ->info('The redis database')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
