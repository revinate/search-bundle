<?php
namespace Revinate\SearchBundle\Test\TestCase;

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
        $docHelper->createView('chrome', 'ios', '-2 month', 6)
            ->createView('opera', 'ios', '-3 month', 5)
            ->createView('opera', 'ios', '-1 week', 2)
            ->createView('chrome', 'android', '+0 day', 10);
        $docHelper->refresh();
    }

    public function testPresenseOfServices()
    {
        $this->createData();
        $this->assertInstanceOf('Revinate\SearchBundle\Lib\Search\SearchManager', $this->getSearchManager());
        $this->assertInstanceOf('Revinate\SearchBundle\Lib\Search\ElasticSearch\MappingManager', $this->getMappingManager());
    }

    public function testFind()
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
