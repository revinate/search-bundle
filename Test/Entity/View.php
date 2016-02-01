<?php
namespace Revinate\SearchBundle\Test\Entity;

use JMS\Serializer\Annotation as JMS;
use Revinate\SearchBundle\Lib\Search\Mapping\Annotations as MAP;

/**
 * @JMS\ExclusionPolicy("all")
 * @MAP\ElasticSearchable(index="test_revinate_search_bundle", type="views", source=true)
 * @MAP\ElasticRoot(name="date_detection", value="false")
 */
class View {
    const INDEX_NAME = "test_revinate_search_bundle";
    const INDEX_TYPE = "views";

    /**
     * @MAP\Id
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
     */
    protected $date;

    /**
     * @var
     * @JMS\Type("string")
     * @JMS\Expose @JMS\Groups({"api", "store"})
     * @MAP\ElasticField(type="string", includeInAll=false)
     */
    protected $browser;

    /**
     * @var
     * @JMS\Type("string")
     * @JMS\Expose @JMS\Groups({"api", "store"})
     * @MAP\ElasticField(type="string", includeInAll=false)
     */
    protected $device;

    /**
     * @var
     * @JMS\Type("integer")
     * @JMS\Expose @JMS\Groups({"api", "store"})
     * @MAP\ElasticField(type="string")
     */
    protected $views;

    /**
     * @var Tag[]
     * @JMS\Type("array<Revinate\SearchBundle\Test\Entity\Tag>")
     * @JMS\Expose @JMS\Groups({"api", "store"})
     * @MAP\ElasticField(type="nested", properties={
     *    @MAP\ElasticField(name="name", type="string", includeInAll=false, index="not_analyzed"),
     *    @MAP\ElasticField(name="weightage", type="float")
     * })
     */
    protected $tags;

    /**
     * @return mixed
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param mixed $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * @return mixed
     */
    public function getBrowser()
    {
        return $this->browser;
    }

    /**
     * @param mixed $browser
     */
    public function setBrowser($browser)
    {
        $this->browser = $browser;
    }

    /**
     * @return mixed
     */
    public function getDevice()
    {
        return $this->device;
    }

    /**
     * @param mixed $device
     */
    public function setDevice($device)
    {
        $this->device = $device;
    }

    /**
     * @return mixed
     */
    public function getViews()
    {
        return $this->views;
    }

    /**
     * @param mixed $views
     */
    public function setViews($views)
    {
        $this->views = $views;
    }

    /**
     * @param Tag[] $tags
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
    }

    /**
     * @return Tag[]
     */
    public function getTags() {
        return $this->tags;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @return array
     */
    public function toArray() {
        $tagDocuments = array();
        foreach ($this->getTags() as $tag) {
            $tagDocuments[] = $tag->toArray();
        }
        return array(
            "id" => $this->getId(),
            "device" => $this->getDevice(),
            "browser" => $this->getBrowser(),
            "views" => $this->getViews(),
            "date" => $this->getDate()->format("c"),
            "tags" => $tagDocuments,
        );
    }
}