<?php
namespace Revinate\SearchBundle\Test\TestCase;

use Elastica\Index\Settings;
use Revinate\SearchBundle\Lib\Search\ElasticSearch\MappingManager;
use Revinate\SearchBundle\Lib\Search\ElasticSearch\RevinateElastica\Template;
use Revinate\SearchBundle\Service\ElasticaService;
use Revinate\SearchBundle\Test\Entity\View;

class MappingManagerTest extends BaseTestCase {
    protected function setUp() {
        /** @var ElasticaService elasticaService */
        $elasticaService = $this->getContainer()->get("revinate_search.elasticsearch_service");
        $this->elasticaClient = $elasticaService->getInstance();
    }

    public function testPresenceOfService()
    {
        $this->assertInstanceOf(MappingManager::class, $this->getMappingManager());
    }

    public function testUpdate() {
        $index = $this->elasticaClient->getIndex(View::INDEX_NAME);
        $this->assertFalse($index->exists());

        self::$mappingManager->update();
        $this->assertTrue($index->exists());

        $this->verifySettings($index->getSettings());
        $this->verifyMapping($index->getMapping());

        $template = new Template($this->getSearchManager()->getClient()->getClient());
        $this->verifyTemplate($template->getTemplate(View::INDEX_NAME));

        self::$mappingManager->deleteAllIndices();
        $this->assertFalse($index->exists());
    }

    protected function verifySettings(Settings $settings) {
        $this->assertEquals('2', $settings->get('number_of_shards'));
        $this->assertEquals('0', $settings->get('number_of_replicas'));
    }

    protected function verifyMapping(array $mapping) {
        $expectedMapping = array(
            'views' =>
                array(
                    '_id'        =>
                        array(
                            'path' => 'id',
                        ),
                    'properties' =>
                        array(
                            'browser' =>
                                array(
                                    'type'           => 'string',
                                    'include_in_all' => false,
                                ),
                            'date'    =>
                                array(
                                    'type'   => 'date',
                                    'format' => 'dateOptionalTime',
                                ),
                            'device'  =>
                                array(
                                    'type'           => 'string',
                                    'include_in_all' => false,
                                ),
                            'tags'    =>
                                array(
                                    'type'       => 'nested',
                                    'properties' =>
                                        array(
                                            'name'      =>
                                                array(
                                                    'type'           => 'string',
                                                    'index'          => 'not_analyzed',
                                                    'include_in_all' => false,
                                                ),
                                            'weightage' =>
                                                array(
                                                    'type' => 'float',
                                                ),
                                        ),
                                ),
                            'views'   =>
                                array(
                                    'type' => 'string',
                                ),
                            'id'      =>
                                array(
                                    'type'  => 'string',
                                    'index' => 'not_analyzed'
                                ),
                        ),
                ),
        );
        $this->assertEquals($expectedMapping, $mapping);
    }

    protected function verifyTemplate(array $template) {
        $expectedTemplate = array(
            'order'    => 0,
            'template' => 'test_revinate_search_bundle',
            'settings' =>
                array(
                    'index.number_of_replicas' => '0',
                    'index.number_of_shards'   => '2',
                ),
            'mappings' =>
                array(
                    'views' =>
                        array(
                            '_id'        =>
                                array(
                                    'path' => 'id',
                                ),
                            'properties' =>
                                array(
                                    'date'    =>
                                        array(
                                            'type' => 'date',
                                        ),
                                    'browser' =>
                                        array(
                                            'include_in_all' => false,
                                            'type'           => 'string',
                                        ),
                                    'device'  =>
                                        array(
                                            'include_in_all' => false,
                                            'type'           => 'string',
                                        ),
                                    'views'   =>
                                        array(
                                            'type' => 'string',
                                        ),
                                    'tags'    =>
                                        array(
                                            'type'       => 'nested',
                                            'properties' =>
                                                array(
                                                    'weightage' =>
                                                        array(
                                                            'type' => 'float',
                                                        ),
                                                    'name'      =>
                                                        array(
                                                            'include_in_all' => false,
                                                            'index'          => 'not_analyzed',
                                                            'type'           => 'string',
                                                        ),
                                                ),
                                        ),
                                    'id'      =>
                                        array(
                                            'type'  => 'string',
                                            'index' => 'not_analyzed'
                                        ),
                                ),
                        ),
                ),
            'aliases'  =>
                array(),
        );
        $this->assertEquals($expectedTemplate, $template);
    }
}
