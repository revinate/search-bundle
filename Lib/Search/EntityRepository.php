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

use Doctrine\Common\Persistence\ObjectRepository;
use Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata;
use Revinate\SearchBundle\Lib\Search\SearchManager;
use PhpOption\Option;

class EntityRepository implements ObjectRepository
{
    /**
     * @var string
     */
    protected $_entityName;

    /**
     * @var \Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata
     */
    private $_class;

    /**
     * @var SearchManager
     */
    private $_sm;

    public function __construct(SearchManager $sm, ClassMetadata $class)
    {
        $this->_sm = $sm;
        $this->_entityName = $class->className;
        $this->_class = $class;
    }

    /**
     * Finds an object by its primary key / identifier using the realtime API
     *
     * @param mixed $id The identifier.
     * @param string $index The optional index to check, since wildcard is not supported for time series indices
     * @param string $routing The optional routing parameter
     *
     * @return object The object.
     */
    public function get($id, $index = null, $routing = null)
    {
        return $this->_sm->get($this->_entityName, $id, $index, $routing);
    }

    /**
     * Finds an object by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     * @return object The object.
     */
    public function find($id)
    {
        return $this->_sm->find($this->_entityName, $id);
    }

    /**
     * @see EntityRepository::find
     * @param $id
     * @return Option
     */
    public function findOption($id)
    {
        return Option::fromValue($this->find($id));
    }

    /**
     * Finds all objects in the repository.
     *
     * @return mixed The objects.
     */
    public function findAll()
    {
        return $this->findBy(array());
    }

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     * @param array      $extraParams
     *
     * @return mixed
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null, array $extraParams = [])
    {
        return $this->_sm->getUnitOfWork()->loadBy($this->_class, $criteria, $orderBy, $limit, $offset, $extraParams);
    }

    /**
     * @param ClassMetadata[] $criteria
     * @param int             $sizePerShard Size of documents to be returned per shard
     * @param string          $expiryTime   Expiration time of the scroll
     * @param array           $extraParams
     *
     * @return \Generator
     */
    public function scanBy(array $criteria, $sizePerShard = 100, $expiryTime = '1m', array $extraParams = [])
    {
        return $this->_sm->getUnitOfWork()->scanBy($this->_class, $criteria, $sizePerShard, $expiryTime, $extraParams);
    }

    /**
     * Finds a single object by a set of criteria.
     *
     * @param array $criteria
     * @param array $extraParams
     *
     * @return object The object.
     */
    public function findOneBy(array $criteria, array $extraParams = [])
    {
        $collection = $this->_sm->getUnitOfWork()->loadBy($this->_class, $criteria, null, null, null, $extraParams);
        return $collection->isEmpty() ? null : $collection->first();
    }

    /**
     * @see EntityRepository::findOneBy
     *
     * @param array $criteria
     * @param array $extraParams
     *
     * @return Option
     */
    public function findOneOptionBy(array $criteria, array $extraParams = [])
    {
        return Option::fromValue($this->findOneBy($criteria, $extraParams));
    }

    /**
     * Execute a direct search query on the associated index and type
     *
     * @param object $query
     */
    public function search($query)
    {
        return $this->_sm->getUnitOfWork()->loadCollection(array($this->_class), $query);
    }

    /**
     * Execute a delete on the associated index and type
     *
     * @param BaseElasticsearchEntity $entity
     * @param bool $refresh
     */
    public function delete($entity, $refresh = false)
    {
        $this->_sm->remove($entity);
        $this->_sm->flush($entity, $refresh);
    }

    /**
     * Execute a direct delete by query on the associated index and type
     *
     * @param object $query
     */
    public function deleteByQuery($query)
    {
        $this->_sm->getClient()->removeAll($this->_class, $query);
    }

    /**
     * Returns the class name of the object managed by the repository
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->_entityName;
    }

    /**
     * Returns the class metadata managed by the repository
     *
     * @return ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->_class;
    }

    /**
     * Returns the search manager
     *
     * @return SearchManager
     */
    public function getSearchManager()
    {
        return $this->_sm;
    }

    /**
     * @param BaseElasticsearchEntity $entity
     * @param bool $refresh
     * @param bool $forceVersion
     *
     * @throws \Exception
     */
    public function save($entity, $refresh = false, $forceVersion = false)
    {
        $this->_sm->persist($entity);
        $this->_sm->flush($entity, $refresh, $forceVersion);
    }
}
