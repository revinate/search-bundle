<?php
namespace Revinate\SearchBundle\Test\TestCase;

use Revinate\SearchBundle\Test\Entity\View;

class IndexTest extends BaseTestCase
{
    protected function debug($results)
    {
        return "Results: " . print_r($results, true);
    }

    public function testIndex()
    {
        $view = new View();
        $view->setId('1');
        $view->setBrowser('Chrome');
    }
}
