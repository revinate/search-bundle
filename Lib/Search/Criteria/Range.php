<?php

namespace Revinate\SearchBundle\Lib\Search\Criteria;

use Revinate\SearchBundle\Lib\Search\Exception\InvalidArgumentException;

class Range
{
    /** @var string comparators */
    const LTE = 'lte';
    const LT = 'lt';
    const GTE = 'gte';
    const GT = 'gt';

    protected $comparator1;
    protected $value1;
    protected $comparator2;
    protected $value2;

    /**
     * @param string $comparator1
     * @param string $value1
     * @param string $comparator2
     * @param string $value2
     *
     * @throws InvalidArgumentException
     */
    public function __construct($comparator1, $value1, $comparator2 = null, $value2 = null)
    {
        $this->comparator1 = $comparator1;
        $this->value1 = $value1;

        if ($comparator2 && !$value2) {
            throw new InvalidArgumentException('A second comparator was specified without a value');
        }
        $this->comparator2 = $comparator2;
        $this->value2 = $value2;
    }

    /**
     * @return string
     */
    public function getComparator1()
    {
        return $this->comparator1;
    }

    /**
     * @return string
     */
    public function getValue1()
    {
        return $this->value1;
    }

    /**
     * @return string
     */
    public function getComparator2()
    {
        return $this->comparator2;
    }

    /**
     * @return string
     */
    public function getValue2()
    {
        return $this->value2;
    }
}