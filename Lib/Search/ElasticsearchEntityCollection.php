<?php
namespace Revinate\SearchBundle\Lib\Search;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Class ElasticsearchEntityCollection
 *
 * This is a decorator to the array collection for collection of elasticsearch entities
 */
class ElasticsearchEntityCollection extends ArrayCollection
{

    /** @var integer */
    private $total = 0;

    /**
     * @return integer
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @param integer $total
     */
    public function setTotal($total)
    {
        $this->total = $total;
    }

    /**
     * @return array
     */
    public function toEntitiesAndTotal()
    {
        return array(
            'entities' => $this->toArray(),
            'total' => $this->getTotal()
        );
    }
}