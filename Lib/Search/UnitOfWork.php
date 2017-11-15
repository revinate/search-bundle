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

namespace Revinate\SearchBundle\Lib\Search;

use Doctrine\Common\EventManager;
use Doctrine\Common\Collections\ArrayCollection;
use Revinate\SearchBundle\Lib\Search\Exception\DoctrineSearchException;
use Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata;
use Revinate\SearchBundle\Lib\Search\SearchManager;
use Elastica\Query;
use Elastica\ResultSet;
use Elastica\ScanAndScroll;
use Traversable;

class UnitOfWork
{
    /**
     * The SearchManager that "owns" this UnitOfWork instance.
     *
     * @var SearchManager
     */
    private $sm;

    /**
     * The EventManager used for dispatching events.
     *
     * @var EventManager
     */
    private $evm;

    /**
     * @var array
     */
    private $scheduledForPersist = array();

    /**
     * @var array
     */
    private $scheduledForDelete = array();

    /**
     * @var array
     */
    private $updatedIndexes = array();

    /**
     * Initializes a new UnitOfWork instance, bound to the given SearchManager.
     *
     * @param SearchManager $sm
     */
    public function __construct(SearchManager $sm)
    {
        $this->sm = $sm;
        $this->evm = $sm->getEventManager();
    }

    /**
     * Persists an entity as part of the current unit of work.
     *
     * @param object $entity The entity to persist.
     */
    public function persist($entity)
    {
        if ($this->evm->hasListeners(Events::prePersist)) {
            $this->evm->dispatchEvent(Events::prePersist, new Event\LifecycleEventArgs($entity, $this->sm));
        }

        $oid = spl_object_hash($entity);
        $this->scheduledForPersist[$oid] = $entity;

        if ($this->evm->hasListeners(Events::postPersist)) {
            $this->evm->dispatchEvent(Events::postPersist, new Event\LifecycleEventArgs($entity, $this->sm));
        }
    }

    /**
     * Deletes an entity as part of the current unit of work.
     *
     * @param object $entity The entity to remove.
     */
    public function remove($entity)
    {
        if ($this->evm->hasListeners(Events::preRemove)) {
            $this->evm->dispatchEvent(Events::preRemove, new Event\LifecycleEventArgs($entity, $this->sm));
        }

        $oid = spl_object_hash($entity);
        $this->scheduledForDelete[$oid] = $entity;

        if ($this->evm->hasListeners(Events::postRemove)) {
            $this->evm->dispatchEvent(Events::postRemove, new Event\LifecycleEventArgs($entity, $this->sm));
        }
    }

    /**
     * Clears the UnitOfWork.
     *
     * @param string $entityName if given, only entities of this type will get detached
     */
    public function clear($entityName = null)
    {
        if ($entityName === null) {
            $this->scheduledForDelete =
            $this->scheduledForPersist =
            $this->updatedIndexes = array();
        } else {
            //TODO: implement for named entity classes
        }

        if ($this->evm->hasListeners(Events::onClear)) {
            $this->evm->dispatchEvent(Events::onClear, new Event\OnClearEventArgs($this->sm, $entityName));
        }
    }

    /**
     * Commits the UnitOfWork, executing all operations that have been postponed
     * up to this point.
     *
     * The operations are executed in the following order:
     *
     * 1) All entity inserts
     * 2) All entity deletions
     *
     * @param mixed $entity
     * @param bool $refresh
     *
     * @throws DoctrineSearchException
     */
    public function commit($entity = null, $refresh = false)
    {
        if ($this->evm->hasListeners(Events::preFlush)) {
            $this->evm->dispatchEvent(Events::preFlush, new Event\PreFlushEventArgs($this->sm));
        }

        //TODO: single/array entity commit handling
        try {
            $this->commitRemoved($entity);
            $this->commitPersisted($entity);
        } catch (\Exception $e) {
            throw new DoctrineSearchException(__METHOD__ . ': Error while committing: ' . $e->getMessage());
        }

        //Force refresh of updated indexes
        if ($refresh === true) {
            $client = $this->sm->getClient();
            foreach ($this->updatedIndexes as $index) {
                $client->refreshIndex($index);
            }
        }

        $this->clear();

        if ($this->evm->hasListeners(Events::postFlush)) {
            $this->evm->dispatchEvent(Events::postFlush, new Event\PostFlushEventArgs($this->sm));
        }
    }

    /**
     * Commit persisted entities to the database
     *
     * @param mixed $entity
     *
     * @throws \Exception
     */
    private function commitPersisted($entity = null)
    {
        $scheduledForPersist = $this->scheduledForPersist;
        if ($entity) {
            // if entity is presented, we need for first make sure if the entity
            // is in the scheduled for persist
            if ($this->isInScheduledForPersist($entity)) {
                $scheduledForPersist = array($entity);
            } else {
                return;
            }
        }
        $sortedDocuments = $this->sortObjects($scheduledForPersist);
        $client = $this->sm->getClient();

        foreach ($sortedDocuments as $entityName => $documents) {
            $classMetadata = $this->sm->getClassMetadata($entityName);
            foreach ($documents as $document) {
                $index = $classMetadata->getIndexForWrite($document);
                $this->updatedIndexes[$index] = $index;
            }
            $client->addDocuments($classMetadata, $documents);
        }

        // after the persisting, clear the scheduled for persist if to persist
        // all, or remove the entity from the scheduled for persist if presented
        if ($entity) {
            unset($this->scheduledForPersist[spl_object_hash($entity)]);
        } else {
            $this->scheduledForPersist = array();
        }
    }

    /**
     * Commit deleted entities to the database
     *
     * @param mixed $entity
     *
     * @throws \Exception
     */
    private function commitRemoved($entity = null)
    {
        $scheduledForDelete = $this->scheduledForDelete;
        if ($entity) {
            // if entity is presented, we need for first make sure if the entity
            // is in the scheduled for delete
            if ($this->isInScheduledForDelete($entity)) {
                $scheduledForDelete = array($entity);
            } else {
                return;
            }
        }
        $sortedDocuments = $this->sortObjects($scheduledForDelete);
        $client = $this->sm->getClient();

        foreach ($sortedDocuments as $entityName => $documents) {
            $classMetadata = $this->sm->getClassMetadata($entityName);
            foreach ($documents as $document) {
                $index = $classMetadata->getIndexForWrite($document);
                $this->updatedIndexes[$index] = $index;
            }
            $client->removeDocuments($classMetadata, $documents);
        }

        if ($entity) {
            unset($this->scheduledForDelete[spl_object_hash($entity)]);
        } else {
            $this->scheduledForDelete = array();
        }
    }

    /**
     * Prepare entities for commit. Entities scheduled for deletion do not need
     * to be serialized.
     *
     * @param array $objects
     * @param boolean $serialize
     * @throws DoctrineSearchException
     * @return array
     */
    private function sortObjects(array $objects, $serialize = true)
    {
        $documents = array();
        $serializer = $this->sm->getSerializer();

        foreach ($objects as $object) {
            $document = $serialize ? $serializer->serialize($object) : $object;
            $documents[get_class($object)][] = $document;
        }

        return $documents;
    }

    /**
     * Load and hydrate a document model
     *
     * @param ClassMetadata $class
     * @param mixed $value
     * @param array $options
     * @param string $index
     */
    public function load(ClassMetadata $class, $value, $options = array(), $index = null)
    {
        $client = $this->sm->getClient();

        if (!empty($options['useRealtime'])) {
            $document = $client->get($class, $value, $options, $index);
        } else {
            $document = $client->find($class, $value, $options);
        }

        if ($document) {
            return $this->hydrateEntity($class, $document);
        }
        return null;
    }

    /**
     * Load and hydrate documents based on critera
     *
     * @param ClassMetadata $class
     * @param array         $criteria
     * @param array|null    $orderBy
     * @param int           $limit
     * @param int           $offset
     * @param array         $extraParams
     *
     * @return ArrayCollection
     */
    public function loadBy(ClassMetadata $class, array $criteria, array $orderBy = null, $limit = null, $offset = null, array $extraParams = [])
    {
        $results = $this->sm->getClient()->findBy($class, $criteria, $orderBy, $limit, $offset, $extraParams);
        return $this->hydrateCollection(array($class), $results);
    }

    /**
     * @param ClassMetadata $class
     * @param array         $criteria
     * @param int           $sizePerShard
     * @param string        $expiryTime
     * @param array         $extraParams
     *
     * @return \Generator
     */
    public function scanBy(ClassMetadata $class, array $criteria, $sizePerShard = 100, $expiryTime = '1m', array $extraParams = [])
    {
        $query = $this->sm->generateQueryBy($criteria, [], null, null, $extraParams);
        $iterator = $this->sm->getClient()->scan($query, [$class], $sizePerShard, $expiryTime);
        return $this->hydrateScanAndScrollIterator([$class], $iterator);
    }

    /**
     * Load and hydrate a document collection
     *
     * @param array $classes
     * @param unknown $query
     */
    public function loadCollection(array $classes, $query)
    {
        $results = $this->sm->getClient()->search($query, $classes);
        return $this->hydrateCollection($classes, $results);
    }

    /**
     * @param ClassMetadata[] $classes
     * @param ScanAndScroll $scanAndScrollIterator
     *
     * @return \Generator
     */
    public function hydrateScanAndScrollIterator($classes, ScanAndScroll $scanAndScrollIterator)
    {
        foreach ($scanAndScrollIterator as $resultSet) {
            yield $this->hydrateCollection($classes, $resultSet);
        }
    }

    /**
     * Construct an entity collection
     *
     * @param array $classes
     * @param Traversable $resultSet
     */
    public function hydrateCollection(array $classes, Traversable $resultSet)
    {
        if ($resultSet instanceof ResultSet) {
            $collection = new ElasticsearchEntityCollection();
            $collection->setTotal($resultSet->getTotalHits());
        } else {
            $collection = new ArrayCollection();
        }

        foreach ($resultSet as $document) {
            foreach ($classes as $class) {
                if ($document->getIndex() == $class->index && $document->getType() == $class->type) {
                    break;
                }
            }
            $collection[] = $this->hydrateEntity($class, $document);
        }

        return $collection;
    }

    /**
     * Construct an entity object
     *
     * @param ClassMetadata $class
     * @param object $document
     */
    public function hydrateEntity(ClassMetadata $class, $document)
    {
        // TODO: add support for different result set types from different clients
        // perhaps by wrapping documents in a layer of abstraction
        $data = $document->getData();
        $fields = array_merge(
            $document->hasFields() ? $document->getFields() : array(),
            array('_version' => $document->getVersion())
        );

        foreach ($fields as $name => $value) {
            if (isset($class->parameters[$name])) {
                $data[$name] = $value;
            } else {
                foreach ($class->parameters as $param => $mapping) {
                    if ($mapping->name == $name) {
                        $data[$param] = $value;
                        break;
                    }
                }
            }
        }

        $data[$class->getIdentifier()] = $document->getId();
        if (method_exists($document, 'getScore')) {
            $data['score'] = $document->getScore();
        }

        $entity = $this->sm->getSerializer()->deserialize($class->className, json_encode($data));

        if ($this->evm->hasListeners(Events::postLoad)) {
            $this->evm->dispatchEvent(Events::postLoad, new Event\LifecycleEventArgs($entity, $this->sm));
        }

        return $entity;
    }

    /**
     * Checks whether an entity is registered in the identity map of this UnitOfWork.
     *
     * @param object $entity
     *
     * @return boolean
     */
    public function isInIdentityMap($entity)
    {
        $oid = spl_object_hash($entity);
        return isset($this->scheduledForPersist[$oid]) || isset($this->scheduledForDelete[$oid]);
    }

    /**
     * Helper method to check if an entity is in scheduled for persist
     *
     * @param mixed $entity
     *
     * @return bool
     */
    public function isInScheduledForPersist($entity)
    {
        $oid = spl_object_hash($entity);
        return isset($this->scheduledForPersist[$oid]);
    }

    /**
     * Helper method to check if an entity is in scheduled for delete
     *
     * @param mixed $entity
     *
     * @return bool
     */
    public function isInScheduledForDelete($entity)
    {
        $oid = spl_object_hash($entity);
        return isset($this->scheduledForDelete[$oid]);
    }
}
