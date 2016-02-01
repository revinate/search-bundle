<?php

namespace Revinate\SearchBundle\Test\Entity;

use JMS\Serializer\Annotation as JMS;

class Tag {
    /**
     * @var  string
     * @JMS\Type("string")
     * @JMS\Expose @JMS\Groups({"privateapi", "store"})
     */
    protected $name;

    /**
     * @var  float
     * @JMS\Type("float")
     * @JMS\Expose @JMS\Groups({"privateapi", "store"})
     */
    protected $weightage;

    /**
     * @param string $name
     * @param float  $weightage
     */
    function __construct($name, $weightage)
    {
        $this->name = $name;
        $this->weightage = $weightage;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return float
     */
    public function getWeightage()
    {
        return $this->weightage;
    }

    /**
     * @param float $weightage
     */
    public function setWeightage($weightage)
    {
        $this->weightage = $weightage;
    }

    public function toArray() {
        return array(
            "name" => $this->getName(),
            "weightage" => $this->getWeightage(),
        );
    }
}