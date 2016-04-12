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

use Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata;
use Elastica\Filter\BoolAnd;
use InvalidArgumentException;

/**
 * Interface for a Lib SearchManager class to implement.
 *
 * @author  Mike Lohmann <mike.h.lohmann@googlemail.com>
 */
interface SearchClientInterface
{
    /**
     * Finds document by id using the realtime API
     *
     * @param ClassMetadata $class
     * @param mixed $id
     * @param array $options
     * @param string $index
     */
    public function get(ClassMetadata $class, $id, $options = array(), $index = null);

    /**
     * Finds document by id.
     *
     * @param ClassMetadata $class
     * @param mixed $id
     * @param array $options
     */
    public function find(ClassMetadata $class, $id, $options = array());

    /**
     * Finds document by specified field and value.
     *
     * @param ClassMetadata $class
     * @param string $field
     * @param mixed $value
     */
    public function findOneBy(ClassMetadata $class, $field, $value);

    /**
     * Finds document by specified criteria.
     *
     * @param ClassMetadata $class
     * @param array $criteria
     * @param array $orderBy
     * @param int $limit
     * @param int $offset
     */
    public function findBy(ClassMetadata $class, array $criteria, array $orderBy = null, $limit = null, $offset = null);

    /**
     * Finds all documents
     *
     * @param array $classes
     */
    public function findAll(array $classes);

    /**
     * Finds documents by a specific query.
     *
     * @param object $query
     * @param array $classes
     */
    public function search($query, array $classes);

    /**
     * Creates a document index
     *
     * @param string $name The name of the index.
     * @param array $config The configuration of the index.
     */
    public function createIndex($name, array $config = array());

    /**
     * Gets a document index reference
     *
     * @param string $name The name of the index.
     */
    public function getIndex($name);

    /**
     * Deletes an index and its types and documents
     *
     * @param string $index
     */
    public function deleteIndex($index);

    /**
     * Refresh the index to make documents available for search
     *
     * @param string $index
     */
    public function refreshIndex($index);

    /**
     * Create a document type mapping as defined in the
     * class annotations
     *
     * @param ClassMetadata $metadata
     */
    public function createType(ClassMetadata $metadata);

    /**
     * Delete a document type
     *
     * @param ClassMetadata $metadata
     */
    public function deleteType(ClassMetadata $metadata);

    /**
     * Adds documents of a given type to the specified index
     *
     * @param ClassMetadata $class
     * @param array $documents Indexed by document id
     */
    public function addDocuments(ClassMetadata $class, array $documents);

    /**
     * Remove documents of a given type from the specified index
     *
     * @param ClassMetadata $class
     * @param array $documents Indexed by document id
     */
    public function removeDocuments(ClassMetadata $class, array $documents);

    /**
     * Remove all documents of a given type from the specified index
     * without deleting the index itself
     *
     * @param ClassMetadata $class
     * @param object $query
     */
    public function removeAll(ClassMetadata $class, $query = null);

    /**
     * Generate query used by findBy()
     *
     * @param array $criteria
     * @param array $orderBy
     * @param int $limit
     * @param int $offset
     *
     * @return Query
     */
    public function generateQueryBy(array $criteria, array $orderBy = null, $limit = null, $offset = null);

    /**
     * Generate filter used by generateQueryBy()
     *
     * @param array $criteria
     *
     * @return BoolAnd
     * @throws InvalidArgumentException
     * @throws \Exception
     */
    public function generateFilterBy(array $criteria);

    /**
     * Update the existing field mapping
     *
     * @param ClassMetadata $class
     *
     * @return boolean|string
     */
    public function updateMapping(ClassMetadata $class);

    /**
     * Create templates for all the given indices
     *
     * @param array $indexToMetadatas
     *
     * @return mixed
     */
    public function createTemplates($indexToMetadatas);

    /**
     * Get an existing template
     *
     * @param ClassMetadata $class
     *
     * @return array
     */
    public function getTemplate(ClassMetadata $class);

    /**
     * Delete an existing template
     *
     * @param ClassMetadata $class
     *
     * @return boolean
     */
    public function deleteTemplate(ClassMetadata $class);

    /**
     * Set the search client to force accepting documents with a lower version
     *
     * @param bool $forceVersion
     */
    public function setForceVersion($forceVersion);

    /**
     * Get the search client setting whether or not to force accepting documents with a lower version
     *
     * @return bool
     */
    public function getForceVersion();
}
