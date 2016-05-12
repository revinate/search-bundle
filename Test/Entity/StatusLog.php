<?php
namespace Revinate\SearchBundle\Test\Entity;

use JMS\Serializer\Annotation as JMS;
use Revinate\SearchBundle\Lib\Search\BaseElasticsearchEntity;
use Revinate\SearchBundle\Lib\Search\Mapping\Annotations as MAP;

/**
 * @JMS\ExclusionPolicy("all")
 * @MAP\ElasticSearchable(
 *     index="test_revinate_search_bundle_time_series",
 *     type="status_logs",
 *     timeSeriesScale="monthly",
 *     source=true,
 *     numberOfReplicas=0,
 *     numberOfShards=2
 * )
 */

class StatusLog extends BaseElasticsearchEntity {
    const INDEX_NAME = "test_revinate_search_bundle_time_series";
    const INDEX_TYPE = "status_logs";

    /**
     * @MAP\Id
     * @MAP\ElasticField(type="string", index="not_analyzed")
     * @JMS\Type("string")
     * @JMS\Expose @JMS\Groups({"api", "store"})
     *
     * Using Serialization groups allows us to provide a version of serialized object
     * for storage, and a different one for passing into a document output renderer, such
     * as might be useful for an api.
     */
    private $id;

    /**
     * @var
     * @JMS\Type("DateTime")
     * @JMS\Expose @JMS\Groups({"api", "store"})
     * @MAP\ElasticField(type="date")
     * @MAP\TimeSeriesField
     */
    protected $date;

    /**
     * @var
     * @JMS\Type("string")
     * @JMS\Expose @JMS\Groups({"api", "store"})
     * @MAP\ElasticField(type="string", includeInAll=false)
     */
    protected $status;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param \DateTime $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return array
     */
    public function toArray() {
        return array(
            'id' => $this->getId(),
            'status' => $this->getStatus(),
            'date'    => $this->getDate()->format('c'),
        );
    }
}