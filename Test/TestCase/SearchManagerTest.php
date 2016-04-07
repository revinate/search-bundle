<?php
namespace Revinate\SearchBundle\Test\TestCase;

use Revinate\SearchBundle\Lib\Search\Criteria\Not;
use Revinate\SearchBundle\Lib\Search\Criteria\Range;
use Revinate\SearchBundle\Lib\Search\Query;
use Revinate\SearchBundle\Lib\Search\SearchManager;
use Revinate\SearchBundle\Test\Elastica\DocumentHelper;
use Revinate\SearchBundle\Test\Entity\Tag;
use Revinate\SearchBundle\Test\Entity\View;

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

        $view = $viewRepo->find($id);
        $this->assertNull($view);
    }

    public function testUpdate()
    {
        $searchManager = $this->getSearchManager();
        $viewRepo = $searchManager->getRepository(View::class);

        $view = new View();
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
                $this->assertTrue($client->getIndex($metadata->index)->exists());
                if ($client->getIndex($metadata->index)->exists()) {
                    $client->deleteIndex($metadata->index);
                }
            }
            // Recreate indexes and types
            foreach ($metadatas as $metadata) {
                $this->assertTrue(!$client->getIndex($metadata->index)->exists());
                if (!$client->getIndex($metadata->index)->exists()) {
                    $client->createIndex($metadata->index);
                }
                $client->createType($metadata);
                $this->assertNotNull($client->getIndex($metadata->index)->getMapping());
            }
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();
        }
        $this->assertNull($exceptionMessage, $exceptionMessage);
    }
}
