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

use Revinate\SearchBundle\Lib\Search\Exception\DoctrineSearchException;
use Elastica;
use Elastica\Aggregation\AbstractAggregation;

/**
 * Class Query
 *
 * @method Query addAggregation(AbstractAggregation $aggregation)
 * @method Query setLimit($limit = 10)
 * @method Query setFrom($from)
 * @method Query setSort($sort)
 */
class Query
{
    const HYDRATE_BYPASS = -1;
    const HYDRATE_INTERNAL = -2;
    const HYDRATE_AGGREGATION = -3;

    const HYDRATION_PARAMETER = 'ids';

    /**
     * @var SearchManager
     */
    protected $sm;

    /**
     * @var object
     */
    protected $query;

    /**
     * @var object
     */
    protected $hydrationQuery;

    /**
     * @var string
     */
    protected $hydrationParameter = self::HYDRATION_PARAMETER;

    /**
     * @var array
     */
    protected $entityClasses;

    /**
     * @var integer
     */
    protected $hydrationMode = self::HYDRATE_INTERNAL;

    /**
     * @var boolean
     */
    protected $useResultCache;

    /**
     * @var integer
     */
    protected $cacheLifetime;

    /**
     * @var integer
     */
    protected $count;

    /**
     * @var array
     */
    protected $facets;

    public function __construct(SearchManager $sm)
    {
        $this->sm = $sm;
    }

    /**
     * Get the query body in array format
     *
     * @return array
     */
    public function toArray() {
        return $this->query->toArray();
    }

    /**
     * Magic method to pass query building to the underlying query
     * object, saving the need to abstract.
     *
     * @param string $method
     * @param array $arguments
     * @return $this
     * @throws DoctrineSearchException
     */
    public function __call($method, $arguments)
    {
        if (!$this->query) {
            throw new DoctrineSearchException('No client query has been provided using Query#searchWith().');
        }

        call_user_func_array(array($this->query, $method), $arguments);
        return $this;
    }

    /**
     * Specifies the searchable entity class to search against.
     *
     * @param mixed $entityClasses
     * @return $this
     */
    public function from($entityClasses)
    {
        $this->entityClasses = (array)$entityClasses;
        return $this;
    }

    /**
     * Set the query object to be executed on the search engine
     *
     * @param mixed $query
     * @return $this
     */
    public function searchWith($query)
    {
        $this->query = $query;
        return $this;
    }

    protected function getSearchManager()
    {
        return $this->sm;
    }

    /**
     * Set the hydration mode from the underlying query modes
     * or bypass and return search result directly from the client
     *
     * @param integer $mode
     * @return $this
     */
    public function setHydrationMode($mode)
    {
        $this->hydrationMode = $mode;
        return $this;
    }

    /**
     * If hydrating with Lib then you can use the result cache
     * on the default or provided query
     *
     * @param boolean $useCache
     * @param integer $cacheLifetime
     * @return $this
     */
    public function useResultCache($useCache, $cacheLifetime = null)
    {
        $this->useResultCache = $useCache;
        $this->cacheLifetime = $cacheLifetime;
        return $this;
    }

    /**
     * Return the total hit count for the given query as provided by
     * the search engine.
     */
    public function count()
    {
        return $this->count;
    }

    /**
     * @return array
     */
    public function getFacets()
    {
        return $this->facets;
    }

    /**
     * Set a custom Lib Query to execute in order to hydrate the search
     * engine results into required entities. The assumption is made the the
     * search engine result id is correlated to the entity id. An optional
     * query parameter override can be specified.
     *
     * @param object $hydrationQuery
     * @param string $parameter
     * @return $this
     */
    public function hydrateWith($hydrationQuery, $parameter = null)
    {
        $this->hydrationQuery = $hydrationQuery;
        if ($parameter) {
            $this->hydrationParameter = $parameter;
        }
        return $this;
    }

    /**
     * Return a provided hydration query
     *
     * @return object
     */
    protected function getHydrationQuery()
    {
        return $this->hydrationQuery;
    }

    /**
     * @param int $sizePerShard Size of documents to be returned per shard
     * @param string $expiryTime Expiration time of the scroll
     * @param string $hydrationMode Hydration mode, either self::HYDRATE_BYPASS or self::HYDRATE_INTERNAL
     *
     * @return \Generator|Elastica\ScanAndScroll
     */
    public function scan($sizePerShard = 100, $expiryTime = '1m', $hydrationMode = null)
    {
        $classes = [];
        foreach ($this->entityClasses as $entityClass) {
            $classes[] = $this->sm->getClassMetadata($entityClass);
        }

        $scanAndScrollIterator = $this->getSearchManager()->getClient()->scan($this->query, $classes, $sizePerShard, $expiryTime);

        if ($hydrationMode == self::HYDRATE_BYPASS) {
            return $scanAndScrollIterator;
        }
        return $this->sm->getUnitOfWork()->hydrateScanAndScrollIterator($classes, $scanAndScrollIterator);
    }

    /**
     * Execute search and hydrate results if required.
     *
     * @param integer $hydrationMode
     * @throws DoctrineSearchException
     * @return mixed
     */
    public function getResult($hydrationMode = null)
    {
        if ($hydrationMode) {
            $this->hydrationMode = $hydrationMode;
        }

        $classes = array();
        foreach ($this->entityClasses as $entityClass) {
            $classes[] = $this->sm->getClassMetadata($entityClass);
        }

        $resultSet = $this->getSearchManager()->getClient()->search($this->query, $classes);

        // TODO: abstraction of support for different result sets
        if ($resultSet instanceof Elastica\ResultSet) {
            $this->count = $resultSet->getTotalHits();
            $this->facets = $resultSet->getFacets();
            $results = $resultSet->getResults();
        } else {
            $resultClass = get_class($resultSet);
            throw new DoctrineSearchException("Unexpected result set class '$resultClass'");
        }

        // If having the hydration query set, hydrate using the hydrate query
        if ($this->getHydrationQuery()) {
            // Document ids are used to lookup dbms results
            $fn = function ($result) {
                return $result->getId();
            };
            $ids = array_map($fn, $results);

            return $this->getHydrationQuery()
                ->setParameter($this->hydrationParameter, $ids ?: null)
                ->useResultCache($this->useResultCache, $this->cacheLifetime)
                ->getResult($this->hydrationMode);
        }

        // Return results depending on hydration mode
        switch ($this->hydrationMode) {
            case self::HYDRATE_BYPASS:
                return $resultSet;
            case self::HYDRATE_AGGREGATION:
                return $resultSet->getAggregations();
        }

        return $this->sm->getUnitOfWork()->hydrateCollection($classes, $resultSet);
    }
}
