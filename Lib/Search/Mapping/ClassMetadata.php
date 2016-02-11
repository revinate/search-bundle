<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Revinate\SearchBundle\Lib\Search\Mapping;

use Doctrine\Common\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Revinate\SearchBundle\Lib\Search\Exception\DoctrineSearchException;
use Revinate\SearchBundle\Lib\Search\Mapping\Annotations\ElasticField;
use Revinate\SearchBundle\Lib\Search\Mapping\Annotations\ElasticRoot;

/**
 * A <tt>ClassMetadata</tt> instance holds all the object-document mapping metadata
 * of a document and it's references.
 *
 * Once populated, ClassMetadata instances are usually cached in a serialized form.
 *
 * <b>IMPORTANT NOTE:</b>
 *
 * The fields of this class are only public for 2 reasons:
 * 1) To allow fast READ access.
 * 2) To drastically reduce the size of a serialized instance (public/protected members
 *    get the whole class name, namespace inclusive, prepended to every property in
 *    the serialized representation).
 *
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Mike Lohmann <mike.h.lohmann@googlemail.com>
 */
class ClassMetadata implements ClassMetadataInterface
{
    const TIME_SERIES_YEARLY = 'yearly';
    const TIME_SERIES_MONTHLY = 'monthly';

    /**
     * @var string
     */
    public $index;

    /**
     * @var string
     */
    public $type;

    /**
     * @var int
     */
    public $numberOfShards = 1;

    /**
     * @var int
     */
    public $numberOfReplicas = 0;

    /**
     * @var int
     */
    public $opType = 1;

    /**
     * @var string
     */
    public $parent;

    /**
     * @var int
     */
    public $timeToLive = 1;

    /**
     * @var int
     */
    public $value = 1;

    /**
     * @var boolean
     */
    public $source = true;

    /**
     * @var float
     */
    public $boost;

    /**
     * @var string
     */
    public $className;

    /**
     * The ReflectionProperty instances of the mapped class.
     *
     * @var array|ElasticField[]
     */
    public $fieldMappings = array();

    /**
     *  Additional root annotations of the mapped class.
     *
     * @var array|ElasticRoot[]
     */
    public $rootMappings = array();

    /**
     * The ReflectionProperty parameters of the mapped class.
     *
     * @var array
     */
    public $parameters = array();

    /**
     * The ReflectionClass instance of the mapped class.
     *
     * @var \ReflectionClass
     */
    public $reflClass;

    /**
     * The ReflectionProperty instances of the mapped class.
     *
     * @var \ReflectionProperty[]
     */
    public $reflFields;

    /**
     * @var string
     */
    public $repository;

    /**
     * @var string possible values: yearly, monthly
     */
    public $timeSeriesScale;

    /**
     * @var string
     */
    public $timeSeriesField;

    /**
     * @var string
     */
    public $parentField;

    /**
     * @var string
     */
    public $versionField;

    /**
     * @var string
     */
    public $versionType;

    /**
     * @var string
     */
    public $dynamic;

    /**
     * READ-ONLY: The field names of all fields that are part of the identifier/primary key
     * of the mapped entity class.
     *
     * @var mixed
     */
    public $identifier = array();


    public function __construct($documentName)
    {
        $this->className = $documentName;
        $this->reflClass = new \ReflectionClass($documentName);
    }

    /** Determines which fields get serialized.
     *
     * It is only serialized what is necessary for best unserialization performance.
     *
     * Parts that are also NOT serialized because they can not be properly unserialized:
     *      - reflClass (ReflectionClass)
     *      - reflFields (ReflectionProperty array)
     *
     * @return array The names of all the fields that should be serialized.
     */
    public function __sleep()
    {
        // This metadata is always serialized/cached.
        return array(
            'boost',
            'className',
            'fieldMappings',
            'parameters',
            'index',
            'numberOfReplicas',
            'numberOfShards',
            'opType',
            'parent',
            'repository',
            'timeToLive',
            'type',
            'value',
            'identifier',
            'rootMappings',
            'timeSeriesScale',
            'timeSeriesField',
            'versionField',
            'versionType',
            'parentField',
            'dynamic'
        );
    }

    /**
     * Get fully-qualified class name of this persistent class.
     *
     * @return string
     */
    public function getName()
    {
        return $this->className;

    }

    /**
     * Gets the mapped identifier field name.
     *
     * The returned structure is an array of the identifier field names.
     *
     * @return array
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * INTERNAL:
     * Sets the mapped identifier key field of this class.
     * Mainly used by the ClassMetadataFactory to assign inherited identifiers.
     *
     * @param mixed $identifier
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Get the field used to determine the time series index
     *
     * @return string
     */
    public function getTimeSeriesField()
    {
        return $this->timeSeriesField;
    }

    /**
     * Set the field used to determine the time series index
     *
     * @param $timeSeriesField
     */
    public function setTimeSeriesField($timeSeriesField)
    {
        $this->timeSeriesField = $timeSeriesField;
    }

    /**
     * Get the field of the _parent value
     *
     * @return string
     */
    public function getParentField()
    {
        return $this->parentField;
    }

    /**
     * @param $parentField
     */
    public function setParentField($parentField)
    {
        $this->parentField = $parentField;
    }

    /**
     * @return string
     */
    public function getVersionField()
    {
        return $this->versionField;
    }

    /**
     * @param string $versionField
     */
    public function setVersionField($versionField)
    {
        $this->versionField = $versionField;
    }

    /**
     * @return string
     */
    public function getVersionType()
    {
        return $this->versionType;
    }

    /**
     * @param string $versionType
     */
    public function setVersionType($versionType)
    {
        $this->versionType = $versionType;
    }

    /**
     * Whether the field is used to determine the time series index
     *
     * @param string $fieldName
     *
     * @return bool
     */
    public function isTimeSeriesField($fieldName)
    {
        return $this->timeSeriesField === $fieldName;
    }

    /**
     * Gets the ReflectionClass instance for this mapped class.
     *
     * @return \ReflectionClass
     */
    public function getReflectionClass()
    {
        return $this->reflClass;
    }

    /**
     * Gets a ReflectionProperty for a specific field of the mapped class.
     *
     * @param string $name
     *
     * @return \ReflectionProperty
     */
    public function getReflectionProperty($name)
    {
        return isset($this->reflFields[$name]) ? $this->reflFields[$name] : null;
    }

    /**
     * Checks if the given field name is a mapped identifier for this class.
     *
     * @param string $fieldName
     * @return boolean
     */
    public function isIdentifier($fieldName)
    {
        return $this->identifier === $fieldName;
    }

    /**
     * Checks if the given field is a mapped property for this class.
     *
     * @param string $fieldName
     * @return boolean
     */
    public function hasField($fieldName)
    {
        return false;
    }

    /**
     * This mapping is used in the _wakeup-method to set the reflFields after _sleep.
     *
     * @param \Reflector $field
     * @param array $mapping
     */
    public function addFieldMapping(\Reflector $field, $mapping = array())
    {
        $fieldName = $field->getName();
        $this->fieldMappings[$fieldName] = $mapping;
    }

    /**
     * @param array $mapping
     */
    public function addRootMapping($mapping = array())
    {
        $this->rootMappings[] = $mapping;
    }

    /**
     * This mapping is used in the _wakeup-method to set the parameters after _sleep.
     *
     * @param \Reflector $field
     * @param array $mapping
     */
    public function addParameterMapping(\Reflector $field, $mapping = array())
    {
        $fieldName = $field->getName();
        $this->parameters[$fieldName] = $mapping;
    }

    /**
     * @param \ReflectionProperty $field
     */
    /*public function addField(\ReflectionProperty $field)
    {
        $fieldName = $field->getName();
        $this->reflFields[] = $field;
    }*/


    /**
     * Checks if the given field is a mapped association for this class.
     *
     * @param string $fieldName
     * @return boolean
     */
    public function hasAssociation($fieldName)
    {
        return false;
    }

    /**
     * Checks if the given field is a mapped single valued association for this class.
     *
     * @param string $fieldName
     * @return boolean
     */
    public function isSingleValuedAssociation($fieldName)
    {
        return false;
    }

    /**
     * Checks if the given field is a mapped collection valued association for this class.
     *
     * @param string $fieldName
     * @return boolean
     */
    public function isCollectionValuedAssociation($fieldName)
    {
        return false;
    }

    /**
     * A numerically indexed list of field names of this persistent class.
     *
     * This array includes identifier fields if present on this class.
     *
     * @return array
     */
    public function getFieldNames()
    {
        return array_keys($this->reflFields);
    }

    /**
     * Currently not necessary but needed by Interface
     *
     * @return array
     */
    public function getAssociationNames()
    {
        return array();
    }

    /**
     * Returns a type name of this field.
     *
     * This type names can be implementation specific but should at least include the php types:
     * integer, string, boolean, float/double, datetime.
     *
     * @param string $fieldName
     * @return string
     */
    public function getTypeOfField($fieldName)
    {
        //@todo: check if $field exists
        return gettype($this->$fieldName);
    }

    /**
     * Currently not necessary but needed by Interface
     *
     *
     * @param string $assocName
     * @return string
     */
    public function getAssociationTargetClass($assocName)
    {
        return '';
    }

    public function isAssociationInverseSide($assocName)
    {
        return '';
    }

    public function getAssociationMappedByTargetField($assocName)
    {
        return '';
    }

    /**
     * Return the identifier of this object as an array with field name as key.
     *
     * Has to return an empty array if no identifier isset.
     *
     * @param object $object
     * @return array
     */
    public function getIdentifierValues($object)
    {
        // TODO: Implement getIdentifierValues() method.
    }

    /**
     * Gets the specified field's value off the given entity.
     *
     * @param object $entity
     * @param string $field
     *
     * @return mixed
     */
    public function getFieldValue($entity, $field)
    {
        return isset($this->reflFields[$field]) ? $this->reflFields[$field]->getValue($entity) : null;
    }

    /**
     * Returns an array of identifier field names numerically indexed.
     *
     * @return array
     */
    public function getIdentifierFieldNames()
    {
        // TODO: Implement getIdentifierFieldNames() method.
    }

    /**
     * Sets the specified field to the specified value on the given entity.
     *
     * @param object $entity
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    public function setFieldValue($entity, $field, $value)
    {
        if (isset($this->reflFields[$field])) {
            $this->reflFields[$field]->setValue($entity, $value);
        }
    }

    /**
     * Restores some state that can not be serialized/unserialized.
     *
     * @param \Doctrine\Common\Persistence\Mapping\ReflectionService $reflService
     *
     * @return void
     */
    public function wakeupReflection($reflService)
    {
        // Restore ReflectionClass and properties
        $this->reflClass = $reflService->getClass($this->className);

        foreach ($this->fieldMappings as $field => $mapping) {
            $this->reflFields[$field] = $reflService->getAccessibleProperty($this->className, $field);
        }
    }

    /**
     * Initializes a new ClassMetadata instance that will hold the object-relational mapping
     * metadata of the class with the given name.
     *
     * @param \Doctrine\Common\Persistence\Mapping\ReflectionService $reflService The reflection service.
     *
     * @return void
     */
    public function initializeReflection($reflService)
    {
        $this->reflClass = $reflService->getClass($this->className);
        $this->className = $this->reflClass->getName(); // normalize classname
    }

    /**
     * @return string
     */
    public function getIndexForRead()
    {
        $indexName = $this->index;
        if ($this->timeSeriesScale) {
            $indexName .= '_*';
        }
        return $indexName;
    }

    /**
     * @param array $document
     *
     * @return string
     * @throws \Exception
     */
    public function getIndexForWrite($document)
    {
        $indexName = $this->index;
        if ($this->timeSeriesScale) {
            if (!isset($document[$this->getTimeSeriesField()])) {
                throw new DoctrineSearchException(__METHOD__ . ': TimeSeriesField must be defined for a time series index!');
            }
            $indexName .= $this->getTimeSeriesSuffix($document[$this->getTimeSeriesField()]);
        }
        return $indexName;
    }

    /**
     * @return string
     */
    public function getCurrentTimeSeriesIndex()
    {
        return $this->index . $this->getTimeSeriesSuffix(date('c'));
    }

    /**
     * @param string $isoDateTime ISO 8601 date
     *
     * @return string
     * @throws \Exception
     */
    protected function getTimeSeriesSuffix($isoDateTime)
    {
        $datetime = new \DateTime($isoDateTime);
        switch ($this->timeSeriesScale) {
            case self::TIME_SERIES_YEARLY:
                return '_' . $datetime->format('Y');
            case self::TIME_SERIES_MONTHLY:
                return '_' . $datetime->format('Y') . '_' . $datetime->format('m');
            default:
                throw new DoctrineSearchException(__METHOD__ . ': Invalid time series scale! Must be set to ' . self::TIME_SERIES_YEARLY . ' or ' . self::TIME_SERIES_MONTHLY);
        }
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return array(
            'index.number_of_replicas' => $this->numberOfReplicas,
            'index.number_of_shards' => $this->numberOfShards
        );
    }
}
