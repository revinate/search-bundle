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

namespace Revinate\SearchBundle\Lib\Search\ElasticSearch;

use Revinate\SearchBundle\Lib\Search\Criteria\Exists;
use Revinate\SearchBundle\Lib\Search\Criteria\Missing;
use Revinate\SearchBundle\Lib\Search\Criteria\Not;
use Revinate\SearchBundle\Lib\Search\Criteria\Range;
use Revinate\SearchBundle\Lib\Search\ElasticSearch\RevinateElastica\Template;
use Revinate\SearchBundle\Lib\Search\Exception\InvalidArgumentException;
use Revinate\SearchBundle\Lib\Search\SearchClientInterface;
use Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata;
use Revinate\SearchBundle\Lib\Search\SearchManager;
use Elastica\Client as ElasticaClient;
use Elastica\Exception\NotFoundException;
use Elastica\Filter\AbstractMulti;
use Elastica\Filter\BoolAnd;
use Elastica\Filter\BoolNot;
use Elastica\Filter\BoolOr;
use Elastica\Filter\HasChild;
use Elastica\Filter\HasParent;
use Elastica\Filter\Terms;
use Elastica\ScanAndScroll;
use Elastica\Type;
use Elastica\Type\Mapping;
use Elastica\Document;
use Elastica\Index;
use Elastica\Query\MatchAll;
use Elastica\Filter\Term;
use Elastica\Search;
use Elastica\Query;
use Elastica\Query\Filtered;

/**
 * SearchManager for ElasticSearch-Backend
 *
 * @author  Mike Lohmann <mike.h.lohmann@googlemail.com>
 * @author  Markus Bachmann <markus.bachmann@bachi.biz>
 */
class Client implements SearchClientInterface
{
    /**
     * @var ElasticaClient
     */
    private $client;

    /**
     * If set to true, use versionType 'force' to allow documents with a lower es_version to overwrite documents with a higher es_version
     * @var bool
     */
    private $forceVersion;

    /**
     * @param ElasticaClient $client
     */
    public function __construct(ElasticaClient $client)
    {
        $this->forceVersion = false;
        $this->client = $client;
    }

    /**
     * @return ElasticaClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * {@inheritDoc}
     */
    public function addDocuments(ClassMetadata $class, array $documents)
    {
        $parameters = $this->getParameters($class->parameters);
        $documentsByIndex = array();

        foreach ($documents as $document) {
            $elasticaDoc = new Document(isset($document["id"]) ? $document["id"] : '');
            foreach ($parameters as $name => $value) {
                if (isset($document[$value])) {
                    if (method_exists($elasticaDoc, "set{$name}")) {
                        $elasticaDoc->{"set{$name}"}($document[$value]);
                    } else {
                        $elasticaDoc->setParam($name, $document[$value]);
                    }
                    unset($document[$value]);
                }
            }
            $elasticaDoc->setData($document);
            if ($parentField = $class->getParentField()) {
                if (empty($document[$parentField])) {
                    throw new InvalidArgumentException('Document (index: ' . $elasticaDoc->getIndex() . ' type: ' . $elasticaDoc->getType() . ' id: ' . $document['id'] . ') misses the value for the parent');
                }
                $elasticaDoc->setParent($document[$parentField]);
            }

            if ($versionField = $class->getVersionField()) {
                $version = isset($document[$versionField]) ? $document[$versionField] : null;
                if (empty($version)) {
                    throw new InvalidArgumentException('Document (index: ' . $elasticaDoc->getIndex() . ' type: ' . $elasticaDoc->getType() . ' id: ' . $document['id'] . ') misses the value for the version');
                }
                if ($this->getForceVersion() == true) {
                    $elasticaDoc->setVersionType('force');
                } else {
                    $elasticaDoc->setVersionType($class->getVersionType());
                }
                $elasticaDoc->setVersion($version);
            }
            $documentsByIndex[$class->getIndexForWrite($document)][] = $elasticaDoc;
        }

        foreach ($documentsByIndex as $index => $documents) {
            $type = $this->getIndex($index)->getType($class->type);

            if (count($documents) > 1) {
                $type->addDocuments($documents);
            } else {
                $type->addDocument(reset($documents));
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function removeDocuments(ClassMetadata $class, array $documents)
    {
        $idsByIndex = array();

        foreach ($documents as $document) {
            if (!isset($document['id'])) {
                throw new \RuntimeException(__METHOD__ . ": Unable to remove document with no id");
            }
            $idsByIndex[$class->getIndexForWrite($document)][] = $document['id'];
        }

        foreach ($idsByIndex as $index => $ids) {
            $type = $this->getIndex($index)->getType($class->type);
            $type->deleteByQuery(new Query\Terms('id', $ids));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function removeAll(ClassMetadata $class, $query = null)
    {
        $type = $this->getIndex($class->getIndexForRead())->getType($class->type);
        $query = $query ?: new MatchAll();
        $type->deleteByQuery($query);
    }

    /**
     * {@inheritDoc}
     */
    public function get(ClassMetadata $class, $id, $options = array(), $index = null)
    {
        if (!$index) {
            $index = $class->index;
        }
        $type = $this->getIndex($index)->getType($class->type);
        try {
            $document = $type->getDocument($id, $options);
        } catch (NotFoundException $e) {
            return null;
        }
        return $document;
    }

    /**
     * {@inheritDoc}
     */
    public function find(ClassMetadata $class, $id, $options = array())
    {
        return $this->findOneBy($class, $class->getIdentifier(), $id);
    }

    /**
     * {@inheritDoc}
     */
    public function findOneBy(ClassMetadata $class, $field, $value)
    {
        $filter = new Term(array($field => $value));

        $query = new Query(new Filtered(null, $filter));
        $query->setVersion(true);
        $query->setSize(1);

        $results = $this->search($query, array($class));
        if (!$results->count()) {
            return null;
        }

        return $results[0];
    }

    /**
     * {@inheritdoc}
     */
    public function generateQueryBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $query = new Query();

        if (empty($criteria)) {
            $query->setQuery(new MatchAll());
        } else {
            $query->setQuery(new Filtered(null, $this->generateFilterBy($criteria)));
        }

        if ($orderBy) {
            $query->setSort($orderBy);
        }

        if ($limit) {
            $query->setSize($limit);
        }

        if ($offset) {
            $query->setFrom($offset);
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function generateFilterBy(array $criteria)
    {
        return $this->generateAndFilterBy($criteria);
    }

    /**
     * @param array $criteria
     *
     * @return AbstractMulti
     */
    protected function generateAndFilterBy(array $criteria)
    {
        return $this->generateFilterHelper(new BoolAnd(), $criteria);
    }

    /**
     * @param array $criteria
     *
     * @return AbstractMulti
     */
    protected function generateOrFilterBy(array $criteria)
    {
        return $this->generateFilterHelper(new BoolOr(), $criteria);
    }

    /**
     * @param AbstractMulti $filter
     * @param array $criteria
     *
     * @return AbstractMulti
     * @throws InvalidArgumentException
     * @throws \Exception
     */
    protected function generateFilterHelper($filter, $criteria)
    {
        foreach ($criteria as $key => $value) {
            if ($this->isHasChild($key) || $this->isHasParent($key)) {
                $filter->addFilter($this->getFilterForHasParentOrHasChild($key, $value));
            } elseif ($key == SearchManager::CRITERIA_OR) {
                $filter->addFilter($this->generateOrFilterBy($value));
            } elseif ($key == SearchManager::CRITERIA_AND) {
                $filter->addFilter($this->generateAndFilterBy($value));
            } elseif ($value instanceof Range) {
                $filter->addFilter($this->getRangeFilter($key, $value));
            } elseif ($value instanceof Not) {
                $filter->addFilter($this->getNotFilter($key, $value));
            } elseif ($value instanceof Exists) {
                $filter->addFilter($this->getExistsFilter($key));
            } elseif ($value instanceof Missing) {
                $filter->addFilter($this->getMissingFilter($key, $value));
            } else {
                $filter->addFilter($this->getTermOrTermsFilter($key, $value));
            }
        }
        return $filter;
    }

    /**
     * {@inheritDoc}
     */
    public function findBy(ClassMetadata $class, array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $query = $this->generateQueryBy($criteria, $orderBy, $limit, $offset);
        return $this->search($query, array($class));
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(array $classes)
    {
        return $this->buildQuery($classes)->search();
    }

    /**
     * @param ClassMetadata[] $classes
     *
     * @return Search
     */
    protected function buildQuery(array $classes)
    {
        $searchQuery = new Search($this->client);
        $searchQuery->setOption(Search::OPTION_VERSION, true);
        /** @var ClassMetadata $class */
        foreach ($classes as $class) {
            if ($class->getIndexForRead()) {
                $indexObject = $this->getIndex($class->getIndexForRead());
                $searchQuery->addIndex($indexObject);
                if ($class->type) {
                    $searchQuery->addType($indexObject->getType($class->type));
                }
            }
        }
        return $searchQuery;
    }

    /**
     * {@inheritDoc}
     */
    public function search($query, array $classes)
    {
        return $this->buildQuery($classes)->search($query);
    }

    /**
     * @param Query $query
     * @param ClassMetadata[] $classes
     * @param int $sizePerShard Size of documents to be returned per shard
     * @param string $expiryTime Expiration time of the scroll
     *
     * @return ScanAndScroll
     */
    public function scan(Query $query, array $classes, $sizePerShard = 100, $expiryTime = '1m')
    {
        $elasticaSearch = $this->buildQuery($classes);
        $elasticaSearch->setQuery($query);
        return new ScanAndScroll($elasticaSearch, $expiryTime, $sizePerShard);
    }

    /**
     * {@inheritDoc}
     */
    public function createIndex($name, array $config = array())
    {
        $index = $this->getIndex($name);
        $index->create($config, true);
        return $index;
    }

    /**
     * {@inheritDoc}
     */
    public function getIndex($name)
    {
        return $this->client->getIndex($name);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteIndex($index)
    {
        $this->getIndex($index)->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function refreshIndex($index)
    {
        $this->getIndex($index)->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function createType(ClassMetadata $metadata)
    {
        $indexName = $metadata->timeSeriesScale ? $metadata->getCurrentTimeSeriesIndex() : $metadata->index;
        $type = $this->getIndex($indexName)->getType($metadata->type);
        $properties = $this->getMapping($metadata->fieldMappings);
        $rootProperties = $this->getRootMapping($metadata->rootMappings);

        $mapping = new Mapping($type, $properties);
        $mapping->disableSource($metadata->source);
        if (isset($metadata->boost)) {
            $mapping->setParam('_boost', array('name' => '_boost', 'null_value' => $metadata->boost));
        }
        if (isset($metadata->dynamic)) {
            $mapping->setParam('dynamic', $metadata->dynamic);
        }
        if (isset($metadata->parent)) {
            $mapping->setParent($metadata->parent);
        }

        foreach ($rootProperties as $key => $value) {
            $mapping->setParam($key, $value);
        }

        $mapping->send();

        return $type;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteType(ClassMetadata $metadata)
    {
        $type = $this->getIndex($metadata->getIndexForRead())->getType($metadata->type);
        return $type->delete();
    }

    /**
     * Generates property mapping from entity annotations
     *
     * @param array $mappings
     * @return array
     */
    protected function getMapping($mappings)
    {
        $properties = array();

        foreach ($mappings as $propertyName => $fieldMapping) {
            if (isset($fieldMapping->name)) {
                $propertyName = $fieldMapping->name;
            }

            $properties[$propertyName]['type'] = $fieldMapping->type;

            if (isset($fieldMapping->path)) {
                $properties[$propertyName]['path'] = $fieldMapping->path;
            }

            if (isset($fieldMapping->includeInAll)) {
                $properties[$propertyName]['include_in_all'] = $fieldMapping->includeInAll;
            }

            if (isset($fieldMapping->nullValue)) {
                $properties[$propertyName]['null_value'] = $fieldMapping->nullValue;
            }

            if (isset($fieldMapping->store)) {
                $properties[$propertyName]['store'] = $fieldMapping->store;
            }

            if (isset($fieldMapping->index)) {
                $properties[$propertyName]['index'] = $fieldMapping->index;
            }

            if (isset($fieldMapping->boost)) {
                $properties[$propertyName]['boost'] = $fieldMapping->boost;
            }

            if (isset($fieldMapping->analyzer)) {
                $properties[$propertyName]['analyzer'] = $fieldMapping->analyzer;
            }

            if (isset($fieldMapping->indexName)) {
                $properties[$propertyName]['index_name'] = $fieldMapping->indexName;
            }

            if (isset($fieldMapping->dynamic)) {
                $properties[$propertyName]['dynamic'] = $fieldMapping->dynamic;
            }

            if (isset($fieldMapping->latLon)) {
                $properties[$propertyName]['lat_lon'] = $fieldMapping->latLon;
            }

            if (isset($fieldMapping->format)) {
                $properties[$propertyName]['format'] = $fieldMapping->format;
            }

            if ($fieldMapping->type == 'attachment' && isset($fieldMapping->fields)) {
                $callback = function ($field) {
                    unset($field['type']);
                    return $field;
                };
                $properties[$propertyName]['fields'] = array_map($callback, $this->getMapping($fieldMapping->fields));
            }

            if ($fieldMapping->type == 'multi_field' && isset($fieldMapping->fields)) {
                $properties[$propertyName]['fields'] = $this->getMapping($fieldMapping->fields);
            }

            if (in_array($fieldMapping->type, array('nested', 'object')) && isset($fieldMapping->properties)) {
                $properties[$propertyName]['properties'] = $this->getMapping($fieldMapping->properties);
            }
        }

        return $properties;
    }

    /**
     * {@inheritdoc}
     */
    public function updateMapping(ClassMetadata $classMetadata)
    {
        try {
            $elasticaIndex = new Index($this->client, $classMetadata->getIndexForRead());
            $elasticaType = new Type($elasticaIndex, $classMetadata->type);
            $elasticaTypeMapping = new Mapping($elasticaType, $this->getMapping($classMetadata->fieldMappings));
            $elasticaTypeMapping->setParam('_id', array('path' => $classMetadata->getIdentifier()));
            if ($classMetadata->parent) {
                $elasticaTypeMapping->setParam('_parent', array('type' => $classMetadata->parent));
            }
            if ($classMetadata->dynamic) {
                $elasticaTypeMapping->setParam('dynamic', $classMetadata->dynamic);
            }
            $response = $elasticaType->setMapping($elasticaTypeMapping);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return (200 == $response->getStatus()) ? true : $response->getError();
    }

    /**
     * Generates parameter mapping from entity annotations
     *
     * @param array $paramMapping
     * @return array
     */
    protected function getParameters($paramMapping)
    {
        $parameters = array();
        foreach ($paramMapping as $propertyName => $mapping) {
            $paramName = isset($mapping->name) ? $mapping->name : $propertyName;
            $parameters[$paramName] = $propertyName;
        }
        return $parameters;
    }

    /**
     * Generates root mapping from entity annotations
     *
     * @param array $mappings
     * @return array
     */
    protected function getRootMapping($mappings)
    {
        $properties = array();

        foreach ($mappings as $rootMapping) {
            $propertyName = $rootMapping->name;
            $mapping = array();

            if (isset($rootMapping->value)) {
                $mapping = $rootMapping->value;
            }

            if (isset($rootMapping->match)) {
                $mapping['match'] = $rootMapping->match;
            }

            if (isset($rootMapping->pathMatch)) {
                $mapping['path_match'] = $rootMapping->pathMatch;
            }

            if (isset($rootMapping->unmatch)) {
                $mapping['unmatch'] = $rootMapping->unmatch;
            }

            if (isset($rootMapping->pathUnmatch)) {
                $mapping['path_unmatch'] = $rootMapping->pathUnmatch;
            }

            if (isset($rootMapping->matchPattern)) {
                $mapping['match_pattern'] = $rootMapping->matchPattern;
            }

            if (isset($rootMapping->matchMappingType)) {
                $mapping['match_mapping_type'] = $rootMapping->matchMappingType;
            }

            if (isset($rootMapping->mapping)) {
                $mapping['mapping'] = current($this->getMapping($rootMapping->mapping));
            }

            if (isset($rootMapping->id)) {
                $properties[$propertyName][][$rootMapping->id] = $mapping;
            } else {
                $properties[$propertyName] = $mapping;
            }
        }

        return $properties;
    }

    /**
     * @param string $field
     * @param Range $range
     *
     * @return \Elastica\Filter\Range
     */
    protected function getRangeFilter($field, Range $range)
    {
        $rangeArray = array();
        $rangeArray[$range->getComparator1()] = $range->getValue1();

        if ($range->getComparator2()) {
            $rangeArray[$range->getComparator2()] = $range->getValue2();
        }
        return new \Elastica\Filter\Range($field, $rangeArray);
    }

    /**
     * @param string $field
     * @param Not $not
     *
     * @return BoolNot
     */
    protected function getNotFilter($field, Not $not)
    {
        return new BoolNot($this->getTermOrTermsFilter($field, $not->getValue()));
    }

    /**
     * @param string $field
     *
     * @return \Elastica\Filter\Exists
     */
    protected function getExistsFilter($field)
    {
        return new \Elastica\Filter\Exists($field);
    }

    /**
     * @param string $field
     * @param Missing $missing
     *
     * @return \Elastica\Filter\Missing
     */
    protected function getMissingFilter($field, $missing)
    {
        $filter = new \Elastica\Filter\Missing($field);
        if (null !== $missing->getExistence()) {
            $filter->setParam('existence', $missing->getExistence());
        }
        if (null !== $missing->getNullValue()) {
            $filter->setParam('null_value', $missing->getNullValue());
        }
        return $filter;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    protected function isHasParent($key)
    {
        return $this->startsWith($key, '_parent.');
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    protected function isHasChild($key)
    {
        return $this->startsWith($key, '_child.');
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    protected function startsWith($haystack, $needle)
    {
        return strpos($haystack, $needle) === 0;
    }

    /**
     * If key is _child.type.field, the value should be the term value or not equal.
     * if key is _child.type, the value should be a criteria
     * @param string $key
     * @param string|Not|array $value
     *
     * @return HasChild|HasParent
     * @throws \Exception
     */
    protected function getFilterForHasParentOrHasChild($key, $value)
    {
        $parts = explode('.', $key);
        if (3 != count($parts) && 2 != count($parts)) {
            throw new InvalidArgumentException(__METHOD__ . ': Find by child or parent must be of the form "_child.type[.field]" or "_parent.type[.field]"');
        }

        $type = $parts[1];
        $field = isset($parts[2]) ? $parts[2] : null;

        if ($field) {
            $filter = $this->getTermOrTermsFilter($field, $value);
            if ($value instanceof Not) {
                $filter = $this->getNotFilter($field, $value);
            }
        } else {
            $filter = $this->generateAndFilterBy($value);
        }

        if ($this->isHasChild($key)) {
            return new HasChild($filter, $type);
        } elseif ($this->isHasParent($key)) {
            return new HasParent($filter, $type);
        } else {
            throw new \Exception(__METHOD__ . ': Programming error');
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return Term|Terms
     */
    protected function getTermOrTermsFilter($field, $value)
    {
        if (is_array($value)) {
            return new Terms($field, $value);
        } else {
            return new Term(array($field => $value));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTemplates($indexToMetadatas)
    {
        $elasticaTemplate = new Template($this->client);

        try {
            foreach ($indexToMetadatas as $index => $metadatas) {
                $mappings = array();
                /** @var ClassMetadata $metadata */
                foreach ($metadatas as $metadata) {
                    $mappings[$metadata->type]['_id']['path'] = $metadata->getIdentifier();
                    if ($metadata->dynamic) {
                        $mappings[$metadata->type]['dynamic'] = $metadata->dynamic;
                    }
                    $mappings[$metadata->type]['properties'] = $this->getMapping($metadata->fieldMappings);
                }

                $response = $elasticaTemplate->createTemplate($metadata->index, $metadata->getIndexForRead(), $metadata->getSettings(), $mappings);
            }
        } catch (\Exception $e) {
            return false;
        }

        return 200 == $response->getStatus();
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplate(ClassMetadata $classMetadata)
    {
        $elasticaTemplate = new Template($this->client);

        try {
            $template = $elasticaTemplate->getTemplate($classMetadata->index);
        } catch (\Exception $e) {
            return array();
        }
        return $template;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTemplate(ClassMetadata $classMetadata)
    {
        $elasticaTemplate = new Template($this->client);

        try {
            $response = $elasticaTemplate->deleteTemplate($classMetadata->index);
        } catch (\Exception $e) {
            return false;
        }
        return 200 == $response->getStatus();
    }

    /**
     * @return bool
     */
    public function getForceVersion()
    {
        return $this->forceVersion;
    }

    /**
     * @param bool $forceVersion
     */
    public function setForceVersion($forceVersion)
    {
        $this->forceVersion = $forceVersion;
    }
}
