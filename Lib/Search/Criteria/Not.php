<?php

namespace Revinate\SearchBundle\Lib\Search\Criteria;

use InvalidArgumentException;

class Not
{
    protected $value;

    /**
     * @param int|string|array $value
     *
     * @throws InvalidArgumentException
     */
    public function __construct($value)
    {
        if (!$value) {
            throw new InvalidArgumentException('A value must be provided');
        }
        $this->value = $value;
    }

    /**
     * @return int|string|array
     */
    public function getValue()
    {
        return $this->value;
    }
}