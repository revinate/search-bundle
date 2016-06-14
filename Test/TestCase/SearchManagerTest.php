<?php
namespace Revinate\SearchBundle\Test\TestCase;

use Revinate\SearchBundle\Lib\Search\Criteria\Not;
use Revinate\SearchBundle\Lib\Search\Criteria\Range;
use Revinate\SearchBundle\Lib\Search\ElasticsearchEntityCollection;
use Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata;
use Revinate\SearchBundle\Lib\Search\Query;
use Revinate\SearchBundle\Lib\Search\SearchManager;
use Revinate\SearchBundle\Test\Elastica\DocumentHelper;
use Revinate\SearchBundle\Test\Entity\Tag;
use Revinate\SearchBundle\Test\Entity\View;
use Revinate\SearchBundle\Test\Entity\StatusLog;

class SearchManagerTest extends BaseTestCase
{
    protected function debug($results)
    {
        return "Results: " . print_r($results, true);
    }

    protected function createData()
    {
        $docHelper = new DocumentHelper($this->type);
        $docHelper->createView('chrome', 'ios', '-2 month', 6)
            ->createView('opera', 'ios', '-3 month', 5)
            ->createView('opera', 'ios', '-1 week', 2)
            ->createView('chrome', 'android', '+0 day', 10);
        $docHelper->refresh();
    }

    protected function createTimeSeriesData()
    {
        $docHelper = new DocumentHelper($this->timeSeriesType);
        $docHelper->createStatusLog('ok')
            ->createStatusLog('error');
        $docHelper->refresh();
    }

    public function testPresenceOfService()
    {
        $this->createData();
        $this->assertInstanceOf(SearchManager::class, $this->getSearchManager());
    }

    public function testFindOneBy()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $views = $viewRepo->search(new \Elastica\Filter\Term(array('device' => 'ios')));
        $this->assertCount(3, $views, $this->debug($views));
        $view = $viewRepo->findOneBy(array('device' => 'android'));
        $this->assertNotNull($view, $this->debug($view));

        $view = $viewRepo->find('Something');
        $this->assertNull($view);
    }

    public function testFind()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $now = new \DateTime('c');
        $view = new View();
        $view->setId('8');
        $view->setBrowser('safari');
        $view->setDevice('ios');
        $view->setViews(10);
        $view->setTags(array(new Tag('pro', 10.0)));
        $view->setDate($now);
        $viewRepo->save($view, true);

        $foundView = $viewRepo->find('8');
        $this->assertSame('8', $foundView->getId());
        $this->assertSame('safari', $foundView->getBrowser());
        $this->assertSame('ios', $foundView->getDevice());
        $this->assertSame(10, $foundView->getViews());
        $this->assertEquals($now, $foundView->getDate());
    }

    public function testGet()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $now = new \DateTime('c');
        $view = new View();
        $view->setId('8');
        $view->setBrowser('safari');
        $view->setDevice('ios');
        $view->setViews(10);
        $view->setTags(array(new Tag('pro', 10.0)));
        $view->setDate($now);
        $viewRepo->save($view);

        $foundView = $viewRepo->find('8');
        $this->assertNull($foundView);

        $foundView = $viewRepo->get('8');
        $this->assertSame('8', $foundView->getId());
        $this->assertSame('safari', $foundView->getBrowser());
        $this->assertSame('ios', $foundView->getDevice());
        $this->assertSame(10, $foundView->getViews());
        $this->assertEquals($now, $foundView->getDate());
    }

    public function testCreate()
    {
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $view = new View();
        $view->setId('1');
        $view->setBrowser('safari');
        $view->setDevice('ios');
        $view->setViews(10);
        $view->setTags(array(new Tag('pro', 10.0)));
        $view->setDate(new \DateTime('c'));
        $viewRepo->save($view, true);

        $view = $viewRepo->findOneBy(array('browser' => 'safari'));
        $this->assertNotNull($view, $this->debug($view));
        $this->assertSame($view->getId(), '1', $this->debug($view));
    }

    public function testCreateWithNoId()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $view = new View();
        $view->setBrowser('safari');
        $view->setDevice('ios');
        $view->setViews(10);
        $view->setTags(array(new Tag('pro', 10.0)));
        $view->setDate(new \DateTime('c'));
        $viewRepo->save($view, true);

        $view = $viewRepo->findOneBy(array('browser' => 'safari'));
        $this->assertNotNull($view, $this->debug($view));
    }

    public function testRemove()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $view = new View();
        $view->setId(uniqid());
        $view->setBrowser('safari');
        $view->setDevice('ios');
        $view->setViews(10);
        $view->setTags(array(new Tag('pro', 10.0)));
        $view->setDate(new \DateTime('c'));
        $viewRepo->save($view, true);

        $view = $viewRepo->findOneBy(array('browser' => 'safari'));
        $id = $view->getId();
        $searchManager->remove($view);
        $searchManager->flush();
        sleep(1);

        $view = $viewRepo->find($id);
        $this->assertNull($view);
    }

    public function testRemoveTimeSeries()
    {
        $this->createTimeSeriesData();
        $searchManager = $this->getSearchManager();
        $statusLogRepo = $searchManager->getRepository(StatusLog::class);

        $statusLog = new StatusLog();
        $statusLog->setId(uniqid());
        $statusLog->setStatus('failure');
        $statusLog->setDate(new \DateTime('c'));
        $statusLogRepo->save($statusLog, true);

        $statusLog = $statusLogRepo->findOneBy(array('status' => 'failure'));
        $id = $statusLog->getId();
        $searchManager->remove($statusLog);
        $searchManager->flush();
        sleep(1);

        $statusLog = $statusLogRepo->find($id);
        $this->assertNull($statusLog);
    }

    public function testRemoveMultiple()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $viewIds = array('multiple1', 'multiple2', 'multiple3');
        $views = array();
        foreach($viewIds as $viewId) {
            $view = new View();
            $view->setId($viewId);
            $view->setBrowser('safari');
            $view->setDevice('ios');
            $view->setViews(10);
            $view->setTags(array(new Tag('pro', 10.0)));
            $view->setDate(new \DateTime('c'));
            $viewRepo->save($view, true);

            $view = $viewRepo->findOneBy(array('id' => $viewId));
            $this->assertNotNull($view);

            $views[] = $view;
        }

        $searchManager->remove($views);
        $searchManager->flush();
        sleep(1);

        foreach($viewIds as $viewId) {
            $view = $viewRepo->find($viewId);
            $this->assertNull($view);
        }
    }

    public function testRemoveMultipleTimeSeries()
    {
        $this->createTimeSeriesData();
        $searchManager = $this->getSearchManager();
        $statusLogRepo = $searchManager->getRepository(StatusLog::class);

        $statusLogIds = array('multiple1', 'multiple2', 'multiple3');
        $statusLogs = array();
        foreach($statusLogIds as $statusLogId) {
            $statusLog = new StatusLog();
            $statusLog->setId($statusLogId);
            $statusLog->setStatus('failure');
            $statusLog->setDate(new \DateTime('c'));
            $statusLogRepo->save($statusLog, true);

            $statusLog = $statusLogRepo->findOneBy(array('id' => $statusLogId));
            $this->assertNotNull($statusLog);

            $statusLogs[] = $statusLog;
        }

        $searchManager->remove($statusLogs);
        $searchManager->flush();
        sleep(1);

        foreach($statusLogIds as $statusLogId) {
            $statusLog = $statusLogRepo->find($statusLogId);
            $this->assertNull($statusLog);
        }
    }

    public function testRemoveAll()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        /** @var View[] $views */
        $views = array();
        $count = 0;
        while($count < 5) {
            $view = new View();
            $view->setId(uniqid());
            $view->setBrowser('safari');
            $view->setDevice('ios');
            $view->setViews(10);
            $view->setTags(array(new Tag('pro', 10.0)));
            $view->setDate(new \DateTime('c'));
            $viewRepo->save($view, true);
            $views[] = $view;
            $count++;
        }

        foreach($views as $originalView) {
            $view = $viewRepo->findOneBy(array('id' => $originalView->getId()));
            $this->assertNotNull($view);
        }

        $searchManager->removeAll($searchManager->getClassMetadata(View::class));
        $searchManager->flush();
        sleep(1);

        /** @var ElasticsearchEntityCollection $views */
        $views = $viewRepo->findAll();
        $this->assertTrue($views->getTotal() == 0);
    }

    public function testRemoveAllTimeSeriesByRemoveAll()
    {
        $this->createTimeSeriesData();
        $searchManager = $this->getSearchManager();
        $statusLogRepo = $searchManager->getRepository(StatusLog::class);
        $statusLogs = $statusLogRepo->findAll();
        $this->assertTrue($statusLogs->getTotal() > 0);

        $this->setExpectedException('RuntimeException');
        $searchManager->removeAll($searchManager->getClassMetadata(StatusLog::class));
    }

    public function testUpdate()
    {
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $view = new View();
        $view->setId(uniqid());
        $view->setBrowser('safari');
        $view->setDevice('ios');
        $view->setViews(10);
        $view->setTags(array(new Tag('pro', 10.0)));
        $view->setDate(new \DateTime('c'));
        $viewRepo->save($view, true);

        $views = $viewRepo->findBy(array('browser' => 'safari', 'device' => 'ios'));
        /** @var View $view */
        $view = $views->first();
        $id = $view->getId();
        $this->assertSame($view->getBrowser(), 'safari', $this->debug($view));
        $this->assertSame($view->getDevice(), 'ios', $this->debug($view));

        $view->setBrowser('firefox');
        $viewRepo->save($view, true);

        $view = $viewRepo->findOneBy(array('browser' => 'firefox'));
        $this->assertSame($id, $view->getId(), $this->debug($view));
    }

    public function testRange()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $views = $viewRepo->findBy(array('views' => new Range(Range::GT, 5)));
        $this->assertEquals(2, $views->getTotal());

        $views = $viewRepo->findBy(array('views' => new Range(Range::GTE, 7)));
        $this->assertEquals(1, $views->getTotal());

        $views = $viewRepo->findBy(array('views' => new Range(Range::LT, 10)));
        $this->assertEquals(3, $views->getTotal());

        $views = $viewRepo->findBy(array('views' => new Range(Range::LTE, 5)));
        $this->assertEquals(2, $views->getTotal());

        $oneMonthAgo = new \DateTime('-1 month');
        $views = $viewRepo->findBy(array('date' => new Range(Range::LT, $oneMonthAgo->format('c'))));
        $this->assertEquals(2, $views->getTotal());

        $oneMonthAgo = new \DateTime('-1 month');
        $twoDaysAgo = new \DateTime('-2 days');
        $views = $viewRepo->findBy(array('date' => new Range(Range::LT, $twoDaysAgo->format('c'), Range::GT, $oneMonthAgo->format('c'))));
        $this->assertEquals(1, $views->getTotal());
    }

    public function testNot()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $views = $viewRepo->findBy(array('device' => new Not('ios')));
        $this->assertEquals(1, $views->getTotal());

        $views = $viewRepo->findBy(array('device' => new Not('android')));
        $this->assertEquals(3, $views->getTotal());

        $views = $viewRepo->findBy(array('device' => new Not('windows')));
        $this->assertEquals(4, $views->getTotal());
    }

    public function testOr()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $views = $viewRepo->findBy(array(SearchManager::CRITERIA_OR => array('device' => 'android', 'browser' => 'chrome')));
        $this->assertEquals(2, $views->getTotal());

        $views = $viewRepo->findBy(array(SearchManager::CRITERIA_OR => array('device' => 'android', 'browser' => new Not('chrome'))));
        $this->assertEquals(3, $views->getTotal());
    }

    public function testAggregation()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();

        $deviceAggregation = new \Elastica\Aggregation\Terms('id');
        $deviceAggregation->setField('device');
        $query = $searchManager->generateQueryBy([]);

        $aggregationResult = $searchManager->createQuery()
            ->from(View::class)
            ->searchWith($query)
            ->addAggregation($deviceAggregation)
            ->getResult(Query::HYDRATE_AGGREGATION);

        $expectedResult = array(
            'id' =>
                array(
                    'buckets' =>
                        array(
                            0 =>
                                array(
                                    'key'       => 'ios',
                                    'doc_count' => 3,
                                ),
                            1 =>
                                array(
                                    'key'       => 'android',
                                    'doc_count' => 1,
                                ),
                        ),
                ),
        );
        $this->assertEquals($expectedResult, $aggregationResult);
    }

    public function testScanAndScroll()
    {
        $this->createData();
        $searchManager = $this->getSearchManager();

        $matchAll = $searchManager->generateQueryBy([]);
        $query = $searchManager->createQuery()
            ->from(View::class)
            ->searchWith($matchAll)
            ->setLimit(1)
            ->setSize(1)
            ->setSort(array('views' => 'asc'));
        $result = $query->scan(1);

        $numCollections = 0;
        foreach ($result as $collection) {
            $this->assertEquals(4, $collection->getTotal());
            $this->assertEquals(1, count($collection));
            ++$numCollections;
        }

        $this->assertEquals(4, $numCollections);
    }

    public function testIndexCreateAndDelete()
    {
        $searchManager = $this->getSearchManager();
        $client = $searchManager->getClient();
        $metadatas = $searchManager->getMetadataFactory()->getAllMetadata();

        $exceptionMessage = null;
        try {
            foreach ($metadatas as $metadata) {
                $indexName = $indexName = $this->getIndexNameForTimeSeriesTestDate($metadata);
                $this->assertTrue($client->getIndex($indexName)->exists());
                if ($client->getIndex($indexName)->exists()) {
                    $client->deleteIndex($indexName);
                }
            }
            // Recreate indexes and types
            foreach ($metadatas as $metadata) {
                $indexName = $this->getIndexNameForTimeSeriesTestDate($metadata);
                $this->assertTrue(! $client->getIndex($indexName)->exists());
                if (!$client->getIndex($indexName)->exists()) {
                    $client->createIndex($indexName);
                }
                $client->createType($metadata);
                $this->assertNotNull($client->getIndex($indexName)->getMapping());
            }
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();
        }
        $this->assertNull($exceptionMessage, $exceptionMessage);
    }

    /**
     * Appends a test date suffix to the base index name for a time series index
     * @param ClassMetadata $metadata
     * @return string
     */
    private function getIndexNameForTimeSeriesTestDate($metadata){
        $indexName = $metadata->index;
        if (isset($metadata->timeSeriesScale)) {
            $indexName = $metadata->index . self::$timeSeriesTestDateSuffix;
        }
        return $indexName;
    }
}
