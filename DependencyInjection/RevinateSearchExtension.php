<?php
namespace Revinate\SearchBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

/**
 * Class RevinateDoctrineSearchExtension
 * @package Revinate\SearchBundle\DependencyInjection
 */
class RevinateSearchExtension extends Extension
{
    /**
     * @var ContainerBuilder
     */
    private $container;
    /** @var array */
    private $config = array();

    /**
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->container = $container;
        $configuration = new Configuration();
        $this->config = $this->processConfiguration($configuration, $configs);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        // Set Default Host and Port
        if (!isset($this->config['connection']['host'])) {
            $this->config['connection']['host'] = '127.0.0.1';
        }
        if (!isset($this->config['connection']['port'])) {
            $this->config['connection']['port'] = 9200;
        }
        if (!isset($this->config['env'])) {
            $this->config['env'] = 'dev';
        }
        $this->container->setParameter("revinate_search.config", $this->config);
        $this->container->setParameter("revinate_search.config.connections", $this->config['connection']);
        $this->container->setParameter("revinate_search.config.paths", $this->config['paths']);
        $this->container->setParameter("revinate_search.config.env", $this->config['env']);

        $this->loadSearchManagerDependencies();
    }

    protected function loadSearchManagerDependencies() {
        // Configuration
        $bundles = $this->container->getParameter('kernel.bundles');
        $entityNamespaces = [];
        foreach ($bundles as $name => $namespace) {
            // namespaces also contains the bundle name, so trim it
            $entityNamespaces[$name] = substr($namespace, 0, strlen($namespace) - strlen($name) - 1);
        }

        $this->container->setDefinition('revinate_search.internal.configuration', new DefinitionDecorator('revinate_search.abstract.configuration'))
            ->addMethodCall('setPaths', [$this->config['paths']])
            ->addMethodCall('setMetadataCacheImpl', [new Reference('revinate_search.internal.cache_provider')])
            ->addMethodCall('setEntitySerializer', [new Reference('revinate_search.internal.elasticsearch_callback_serializer')])
            ->addMethodCall('setEntityNamespaces', [$entityNamespaces]);

        // Client
        $this->container->setDefinition('revinate_search.internal.client', new DefinitionDecorator('revinate_search.abstract.client'))
            ->addArgument(new Reference('revinate_search.internal.elastica.client'));
    }
}
