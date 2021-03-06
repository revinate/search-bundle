<?php

namespace Revinate\SearchBundle\Test\Elastica;

use Revinate\SearchBundle\Test\Entity\Tag;
use Revinate\SearchBundle\Test\Entity\View;
use Revinate\SearchBundle\Test\Entity\StatusLog;

class DocumentHelper {

    /** @var  \Elastica\Type */
    protected $type;

    function __construct(\Elastica\Type $type)
    {
        $this->type = $type;
    }


    /**
     * @param $browser
     * @param $device
     * @param null $dateString
     * @param int $views
     * @return $this
     */
    public function createView($browser, $device, $dateString = null, $views = 1) {
        $date = $dateString ? new \DateTime($dateString) : new \DateTime('now');
        $view = new View();
        $view->setId(uniqid());
        $view->setBrowser($browser);
        $view->setDevice($device);
        $view->setViews($views);
        $view->setDate($date);
        $tags = array();
        $tags[] = new Tag("vip", 4.0);
        $tags[] = new Tag("new", 3.0);
        $view->setTags($tags);
        $this->type->addDocument(new \Elastica\Document("", $view->toArray()));
        return $this;
    }

    /**
     * @param $status
     * @param null $dateString
     * @return $this
     */
    public function createStatusLog($status, $dateString = null) {
        $date = $dateString ? new \DateTime($dateString) : new \DateTime('05/05/2016');
        $statusLog = new StatusLog();
        $statusLog->setId(uniqid());
        $statusLog->setStatus($status);
        $statusLog->setDate($date);
        $this->type->addDocument(new \Elastica\Document("", $statusLog->toArray()));
        return $this;
    }

    /**
     *
     */
    public function refresh() {
        $this->type->getIndex()->refresh(true);
    }
}