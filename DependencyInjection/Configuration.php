<?php
namespace Revinate\SearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
/**
 * Class Configuration
 * @package Revinate\SearchBundle\DependencyInjection
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('revinate_search');
        $rootNode
            ->children()
                ->arrayNode('connection')
                    ->children()
                        ->scalarNode('host')->end()
                        ->scalarNode('port')->end()
                    ->end()
                ->end()
                ->arrayNode('paths')
                    ->prototype('scalar')->end()->defaultValue(array())
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }
}