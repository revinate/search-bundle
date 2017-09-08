<?php

namespace Revinate\SearchBundle\Lib\Search\Criteria;

class BoolOr
{
    /** @var array */
    protected $criteria;

    /**
     * @param array $criteria
     */
    public function __construct($criteria = [])
    {
        $this->criteria = $criteria;
    }

    /**
     * @return array
     */
    public function getCriteria()
    {
        return $this->criteria;
    }

    /**
     * @param array $criteria
     */
    public function setCriteria($criteria)
    {
        $this->criteria = $criteria;
    }
}