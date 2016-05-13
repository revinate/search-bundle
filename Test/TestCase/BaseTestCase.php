<?php
namespace Revinate\SearchBundle\Test\TestCase;

use AppKernel;
use Elastica\Query;
use Revinate\SearchBundle\Lib\Search\ElasticSearch\MappingManager;
use Revinate\SearchBundle\Lib\Search\SearchManager;
use Revinate\SearchBundle\Service\ElasticaService;
use Revinate\SearchBundle\Test\Entity\View;
use Revinate\SearchBundle\Test\Entity\StatusLog;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BaseTestCase extends WebTestCase
{
    /** @var  AppKernel */
    protected static $kernel;
    /** @var ContainerInterface */
    protected static $container;
    /** @var string ES index prefix */
    protected static $indexPrefix;
    /** @var bool Defining if it's initialize (like some stuff we just need to run once like setting up the mysql schema, es mapping, etc) */
    private static $initialized = false;
    /** @var  \Elastica\Client */
    protected $elasticaClient;
    /** @var  \Elastica\Index */
    protected $index;
    /** @var  \Elastica\Index */
    protected $timeSeriesIndex;
    /** @var  \Elastica\Type */
    protected $type;
    /** @var  \Elastica\Type */
    protected $timeSeriesType;
    /** @var MappingManager */
    protected static $mappingManager;
    /** @var SearchManager */
    protected static $searchManager;

    const TIME_SERIES_TEST_DATE_SUFFIX = "_2016_05";

    /**
     * Initialize function, which will only be run once
     */
    private static function initialize()
    {
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        if (!self::$initialized) {
            self::$kernel = new AppKernel('test', true);
            self::$kernel->boot();
            self::$initialized = true;
        }

        self::$mappingManager = self::$kernel->getContainer()->get('revinate_search.mapping_manager');
        self::$searchManager = self::$kernel->getContainer()->get('revinate_search.search_manager');

        $shutDownCallable = 'Revinate\SearchBundle\Test\TestCase\BaseTestCase::cleanupElasticsearch';
        register_shutdown_function($shutDownCallable);
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, $shutDownCallable);
            pcntl_signal(SIGINT, $shutDownCallable);
        }

    }

    /**
     * Cleanup when SIGINT/SIGTERM is received
     *
     * @param int $signal
     */
    public static function cleanupElasticsearch($signal = null)
    {
        // delete all indices after test
        self::$mappingManager->deleteAllIndices();
        self::$mappingManager->deleteAllTemplates();
        if (in_array($signal, [SIGINT, SIGTERM])) {
            exit();
        }
    }

    /**
     * @inheritdoc
     */
    public static function setUpBeforeClass()
    {
        self::initialize();
    }

    protected function setUp()
    {
        /** @var ElasticaService elasticaService */
        $elasticaService = $this->getContainer()->get("revinate_search.elasticsearch_service");
        $this->elasticaClient = $elasticaService->getInstance();
        $this->index = new \Elastica\Index($this->elasticaClient, View::INDEX_NAME);
        if (!$this->index->exists()) {
            $this->index->create(array("index.number_of_replicas" => "0", "index.number_of_shards" => "1"));
            $this->type = new \Elastica\Type($this->index, View::INDEX_TYPE);
            $mappingJson = json_decode(file_get_contents(__DIR__ . "/../data/es/mapping.json"), true);
            $mapping = new \Elastica\Type\Mapping($this->type, $mappingJson['properties']);
            $this->type->setMapping($mapping);
        } else {
            $this->type = new \Elastica\Type($this->index, View::INDEX_TYPE);
        }

        $this->timeSeriesIndex = new \Elastica\Index($this->elasticaClient, StatusLog::INDEX_NAME . BaseTestCase::TIME_SERIES_TEST_DATE_SUFFIX);
        if (! $this->timeSeriesIndex->exists()) {
            $this->timeSeriesIndex->create(array("index.number_of_replicas" => "0", "index.number_of_shards" => "1"));
            $this->timeSeriesType = new \Elastica\Type($this->timeSeriesIndex, StatusLog::INDEX_TYPE);
            $mappingJson = json_decode(file_get_contents(__DIR__ . "/../data/es/statusLogMapping.json"), true);
            $mapping = new \Elastica\Type\Mapping($this->timeSeriesType, $mappingJson['properties']);
            $this->timeSeriesType->setMapping($mapping);
        } else {
            $this->timeSeriesType = new \Elastica\Type($this->timeSeriesIndex, StatusLog::INDEX_TYPE);
        }
    }

    protected function teardown()
    {
        self::cleanupElasticsearch();
        if (extension_loaded('pcntl')) {
            pcntl_signal_dispatch();
        }
    }

    /**
     * Get the service container
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return self::$kernel->getContainer();
    }

    /**
     * @return SearchManager
     */
    protected function getSearchManager()
    {
        return self::$searchManager;
    }

    /**
     * @return MappingManager
     */
    protected function getMappingManager()
    {
        return self::$mappingManager;
    }

    /**
     * Returns a random string of given length
     * @param int $length length of random string
     * @return string
     */
    protected static function getRandomString($length)
    {
        $string = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($string), 0, $length);
    }
}