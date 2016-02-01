<?php

namespace Revinate\SearchBundle\Lib\Search\Exception;

/**
 * Exception thrown when an invalid argument is provided
 */
class InvalidArgumentException extends DoctrineSearchException
{
    public function __construct($message = null)
    {
        parent::__construct($message ?: 'An invalid argument was provided.');
    }
}
