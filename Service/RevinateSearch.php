<?php
namespace Revinate\SearchBundle\Service;

use Doctrine\Common\EventManager;
use Revinate\SearchBundle\Lib\Search\Configuration;
use Revinate\SearchBundle\Lib\Search\ElasticSearch\MappingManager;
use Revinate\SearchBundle\Lib\Search\SearchManager;
use Elastica\Type\Mapping;
use Revinate\SearchBundle\Lib\Search\Serializer\CallbackSerializer;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RevinateSearch
{
    /** @var SearchManager */
    protected $searchManager;

    /** @var MappingManager */
    protected $mappingManager;

    /** @var \AppKernel */
    protected $kernel;

    /**
     *
     * @param array $connection
     * @param array $paths Array of Paths where Search Entities are found
     * @param \AppKernel $kernel
     */
    public function __construct(Array $connection, Array $paths = array(), \AppKernel $kernel)
    {
        $cacheProvider = 'Doctrine\Common\Cache\ArrayCache';

        //Annotation metadata driver
        $config = new Configuration();
        $md = $config->newDefaultAnnotationDriver($paths);
        $config->setMetadataDriverImpl($md);
        $config->setMetadataCacheImpl(new $cacheProvider());
        $config->setEntitySerializer(new CallbackSerializer('toESDocument', 'fromESDocument'));

        $bundles = $kernel->getBundles();
        $entityNamespaces = array();
        foreach ($bundles as $bundle) {
            $nameSpace = $bundle->getNamespace();
            $name = $bundle->getName();
            $entityNamespaces[$name] = $nameSpace;
        }
        $config->setEntityNamespaces($entityNamespaces);

        $client = new \Elastica\Client(array('connections' => array($connection)));
        $this->searchManager = new SearchManager(
            $config,
            new \Revinate\SearchBundle\Lib\Search\ElasticSearch\Client($client),
            new EventManager()
        );
        $this->mappingManager = new MappingManager($this->searchManager, $kernel->getEnvironment());
    }

    /**
     * @return SearchManager
     */
    public function getSearchManager()
    {
        return $this->searchManager;
    }

    /**
     * @return \Revinate\SearchBundle\Lib\Search\SearchClientInterface
     */
    public function getClient()
    {
        return $this->searchManager->getClient();
    }

    /**
     * @return MappingManager
     */
    public function getMappingManager()
    {
        return $this->mappingManager;
    }
}