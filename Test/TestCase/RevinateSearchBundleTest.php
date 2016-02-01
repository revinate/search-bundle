<?php
namespace Revinate\SearchBundle\Test\TestCase;

use Revinate\SearchBundle\Service\RevinateSearch;
use Revinate\SearchBundle\Test\Elastica\DocumentHelper;
use Revinate\SearchBundle\Test\Entity\Tag;
use Revinate\SearchBundle\Test\Entity\View;

class RevinateSearchBundleTest extends BaseTestCase
{
    protected function debug($results)
    {
        return "Results: " . print_r($results, true);
    }

    protected function createData()
    {
        $docHelper = new DocumentHelper($this->type);
        $docHelper->createView("chrome", "ios", "-2 month", 6)
            ->createView("opera", "ios", "-3 month", 5)
            ->createView("opera", "ios", "-1 week", 2)
            ->createView("chrome", "android", "+0 day", 10);
        $docHelper->refresh();
    }

    protected function setUp()
    {
        parent::setUp();
    }

    protected function teardown()
    {
        parent::teardown();
    }

    public function testPresenseOfService()
    {
        $this->createData();
        /** @var RevinateSearch $revinateSearchManager */
        $revinateSearchManager = $this->getContainer()->get("revinate_search");
        $this->assertNotNull($revinateSearchManager);

        $searchManager = $revinateSearchManager->getSearchManager();
        $this->assertInstanceOf('Revinate\SearchBundle\Lib\Search\SearchManager', $searchManager);
    }

    public function testFind()
    {
        $this->createData();
        /** @var RevinateSearch $revinateSearchManager */
        $revinateSearchManager = $this->getContainer()->get("revinate_search");
        $searchManager = $revinateSearchManager->getSearchManager();
        $viewRep = $searchManager->getRepository('\Revinate\SearchBundle\Test\Entity\View');

        $views = $viewRep->search(new \Elastica\Filter\Term(array('device' => 'ios')));
        $this->assertCount(3, $views, $this->debug($views));
        $view = $viewRep->findOneBy(array("device" => "android"));
        $this->assertNotNull($view, $this->debug($view));

        $view = null;
        try {
            $view = $viewRep->find("Something");
        } catch (\Revinate\SearchBundle\Lib\Search\Exception\NoResultException $exception) {
        }
        $this->assertNull($view);
    }

    public function testCreate()
    {
        $this->createData();
        /** @var RevinateSearch $revinateSearchManager */
        $revinateSearchManager = $this->getContainer()->get("revinate_search");
        $searchManager = $revinateSearchManager->getSearchManager();
        $viewRep = $searchManager->getRepository('\Revinate\SearchBundle\Test\Entity\View');

        $view = new View();
        $view->setId("1");
        $view->setBrowser("safari");
        $view->setDevice("ios");
        $view->setViews(10);
        $view->setTags(array(new Tag("pro", 10.0)));
        $view->setDate(new \DateTime('c'));

        $searchManager->persist($view);
        $searchManager->flush();
        sleep(1); // Refresh

        $view = null;
        try {
            $view = $viewRep->findOneBy(array("browser" => "safari"));
        } catch (\Revinate\SearchBundle\Lib\Search\Exception\NoResultException $exception) {
        }
        $this->assertNotNull($view, $this->debug($view));
        $this->assertSame($view->getId(), "1", $this->debug($view));
    }

    public function testCreateWithNoId()
    {
        $this->createData();
        /** @var RevinateSearch $revinateSearchManager */
        $revinateSearchManager = $this->getContainer()->get("revinate_search");
        $searchManager = $revinateSearchManager->getSearchManager();
        $viewRep = $searchManager->getRepository('\Revinate\SearchBundle\Test\Entity\View');

        $view = new View();
        $view->setBrowser("safari");
        $view->setDevice("ios");
        $view->setViews(10);
        $view->setTags(array(new Tag("pro", 10.0)));
        $view->setDate(new \DateTime('c'));

        $searchManager->persist($view);
        $searchManager->flush();
        sleep(1); // Refresh

        $view = null;
        try {
            $view = $viewRep->findOneBy(array("browser" => "safari"));
        } catch (\Revinate\SearchBundle\Lib\Search\Exception\NoResultException $exception) {
        }
        $this->assertNotNull($view, $this->debug($view));
    }

    public function testRemove()
    {
        $this->createData();
        /** @var RevinateSearch $revinateSearchManager */
        $revinateSearchManager = $this->getContainer()->get("revinate_search");
        $searchManager = $revinateSearchManager->getSearchManager();
        $viewRep = $searchManager->getRepository('\Revinate\SearchBundle\Test\Entity\View');

        $view = new View();
        $view->setBrowser("safari");
        $view->setDevice("ios");
        $view->setViews(10);
        $view->setTags(array(new Tag("pro", 10.0)));
        $view->setDate(new \DateTime('c'));

        $searchManager->persist($view);
        $searchManager->flush();
        sleep(1); // Refresh

        $view = $viewRep->findOneBy(array("browser" => "safari"));
        $id = $view->getId();
        $searchManager->remove($view);
        $searchManager->flush();

        $view = null;
        try {
            $view = $viewRep->find($id);
        } catch (\Revinate\SearchBundle\Lib\Search\Exception\NoResultException $exception) {
        }
        $this->assertNull($view);
    }

    public function testIndexCreateAndDelete()
    {
        /** @var RevinateSearch $revinateSearchManager */
        $revinateSearchManager = $this->getContainer()->get("revinate_search");
        $searchManager = $revinateSearchManager->getSearchManager();
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
