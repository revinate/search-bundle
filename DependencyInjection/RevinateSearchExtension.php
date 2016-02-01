<?php
namespace Revinate\SearchBundle\DependencyInjection;

use Revinate\SearchBundle\Service\RevinateSearch;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
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
        $this->container->setParameter("revinate_search.config", $this->config);
        $this->container->setParameter("revinate_search.config.connections", $this->config['connection']);
        $this->container->setParameter("revinate_search.config.paths", $this->config['paths']);
    }
}
