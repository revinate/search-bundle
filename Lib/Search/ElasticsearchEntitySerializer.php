<?php
namespace Revinate\SearchBundle\Lib\Search;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\ArrayCache;
use Revinate\SearchBundle\Lib\Search\Exception\InvalidArgumentException;
use Revinate\SearchBundle\Lib\Search\Mapping\Annotations\ElasticField;
use Elastica\Result;


class ElasticsearchEntitySerializer
{
    protected static $entityReflectionCaches = array();

    /** @var AnnotationReader */
    protected static $reader;

    /** @var string */
    protected $elasticFieldAnnotationClass = 'Revinate\SearchBundle\Lib\Search\Mapping\Annotations\ElasticField';

    /** @var string */
    protected $versionFieldAnnotationClass = 'Revinate\SearchBundle\Lib\Search\Mapping\Annotations\VersionField';

    /** @var ElasticsearchEntitySerializer */
    protected static $instance;

    /**
     * Constructor
     */
    private function __construct()
    {
        if (!self::$reader) {
            self::$reader = new CachedReader(new AnnotationReader(), new ArrayCache());
        }
    }

    /**
     * @return ElasticsearchEntitySerializer
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Build the ES document
     *
     * @param mixed $entity
     *
     * @return array
     * @throws \Exception
     */
    public function serialize(BaseElasticsearchEntity $entity)
    {
        $esDocument = array();

        $properties = $this->getAllClassProperties($entity);
        foreach ($properties as $property) {
            $elasticFieldAnnotation = self::$reader->getPropertyAnnotation($property, $this->elasticFieldAnnotationClass);
            if ($elasticFieldAnnotation) {
                $propertyValue = $this->getPropertyValueByName($entity, $property->name);
                switch ($elasticFieldAnnotation->type) {
                    // @todo[daiyi]: add more handler if necessary
                    case 'date':
                        if ($propertyValue instanceof \DateTime) {
                            switch ($elasticFieldAnnotation->format) {
                                case 'date':
                                    $propertyValue = $propertyValue ? $propertyValue->format('Y-m-d') : null;
                                    break;
                                default:
                                    $propertyValue = $propertyValue ? $propertyValue->format('c') : null;
                                    break;
                            }
                        }
                        break;
                    default:
                        break;
                }

                $esDocument[$property->name] = $propertyValue;
            }
        }
        return $esDocument;
    }

    /**
     * Helper method that get the value of a property from an entity
     *
     * @param mixed $entity
     * @param string $propertyName
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getPropertyValueByName($entity, $propertyName)
    {
        /** @var ElasticField $elasticFieldAnnotation */
        $getter = 'get' . ucfirst($propertyName);
        if (!is_callable(array($entity, $getter))) {
            throw new \Exception('Getter function is not callable: ' . $getter . ' for entity ' . get_class($entity));
        }
        return $entity->$getter();
    }

    /**
     * Helper method that
     *
     * @param mixed $entity
     * @param string $propertyName
     * @param mixed $value
     *
     * @throws \Exception
     */
    protected function setPropertyValueByName($entity, $propertyName, $value)
    {
        $setter = 'set' . ucfirst($propertyName);
        if (!is_callable(array($entity, $setter))) {
            throw new \Exception('Setter function is not callable: ' . $setter . ' for entity ' . get_class($entity));
        }

        $entity->$setter($value);
    }

    /**
     * @param string|Result|array $esDocument
     * @param BaseElasticsearchEntity $deserializingToEntity
     *
     * @return BaseElasticsearchEntity
     * @throws InvalidArgumentException
     * @throws \Exception
     */
    public function deserialize($esDocument, BaseElasticsearchEntity $deserializingToEntity)
    {
        // some pre process to convert the data into array for the ease of process
        if (is_string($esDocument)) {
            // if it's a string, assume it's a json sting
            $esDocument = json_decode($esDocument, true);
            if (!is_array($esDocument)) {
                throw new InvalidArgumentException(__METHOD__ . ' accepted a string and was not able to json decode the string into a valid array');
            }
        } elseif ($esDocument instanceof Result) {
            $esDocument = $esDocument->getData();
        }

        $properties = $this->getAllClassProperties($deserializingToEntity);
        foreach ($properties as $property) {
            /** @var ElasticField $elasticFieldAnnotation */
            $elasticFieldAnnotation = self::$reader->getPropertyAnnotation($property, $this->elasticFieldAnnotationClass);
            if ($elasticFieldAnnotation || $property->getName() == 'score') {
                $propertyValue = isset($esDocument[$property->name]) ? $esDocument[$property->name] : null;
                if ($elasticFieldAnnotation) {
                    switch ($elasticFieldAnnotation->type) {
                        // @todo[daiyi]: add more handler if necessary
                        case 'date':
                            if ($propertyValue) {
                                try {
                                    $propertyValue = new \DateTime($propertyValue);
                                } catch (\Exception $e) {
                                    $propertyValue = null;
                                }
                            }
                            break;
                        default:
                            break;
                    }
                }

                $this->setPropertyValueByName($deserializingToEntity, $property->name, $propertyValue);
            }

            $versionFieldAnnotation = self::$reader->getPropertyAnnotation($property, $this->versionFieldAnnotationClass);
            if ($versionFieldAnnotation) {
                $propertyValue = isset($esDocument[$property->name]) ? $esDocument[$property->name] : null;
                $this->setPropertyValueByName($deserializingToEntity, $property->name, $propertyValue);
            }
        }

        return $deserializingToEntity;
    }

    /**
     * Helper method that gets all the properties from a reflection obj
     *
     * @param $object
     *
     * @return \ReflectionProperty[]
     */
    protected function getAllClassProperties($object)
    {
        $reflectionObj = new \ReflectionObject($object);
        $properties = $reflectionObj->getProperties();
        while ($parent = $reflectionObj->getParentClass()) {
            $properties = array_merge($parent->getProperties(), $properties);
            $reflectionObj = $parent;
        }
        return $properties;
    }

}