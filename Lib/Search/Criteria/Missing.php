<?php

namespace Revinate\SearchBundle\Lib\Search\Criteria;

class Missing
{
    /** @var bool */
    protected $existence;
    /** @var bool */
    protected $nullValue;

    /**
     * @param bool $existence
     * @param bool $nullValue
     */
    public function __construct($existence = null, $nullValue = null)
    {
        $this->existence = $existence;
        $this->nullValue = $nullValue;
    }

    /**
     * @return boolean
     */
    public function getExistence()
    {
        return $this->existence;
    }

    /**
     * @param boolean $existence
     */
    public function setExistence($existence)
    {
        $this->existence = $existence;
    }

    /**
     * @return boolean
     */
    public function getNullValue()
    {
        return $this->nullValue;
    }

    /**
     * @param boolean $nullValue
     */
    public function setNullValue($nullValue)
    {
        $this->nullValue = $nullValue;
    }
}