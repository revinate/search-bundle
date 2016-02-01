<?php

namespace Revinate\SearchBundle\Lib\Search\ElasticSearch;

use Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata;
use Revinate\SearchBundle\Lib\Search\SearchManager;
use Elastica\Exception\ResponseException;
use Elastica\Index;
use Elastica\Type;

class MappingManager
{
    /** @var SearchManager */
    protected $sm;
    /** @var \Revinate\SearchBundle\Lib\Search\SearchClientInterface */
    protected $client;
    /** @var string */
    protected $env;

    public function __construct(SearchManager $sm, $env)
    {
        $this->sm = $sm;
        $this->client = $sm->getClient();
        $this->env = $env;
    }

    /**
     * Refreshes all the templates and mappings
     */
    public function update()
    {
        $this->updateTemplates();
        $this->updateMappings();
    }

    /**
     * Update existing templates
     */
    public function updateTemplates()
    {
        $metadatas = $this->sm->getMetadataFactory()->getAllMetadata();
        $indexToMetadatas = array();

        // Refresh the templates
        /** @var ClassMetadata $metadata */
        foreach ($metadatas as $metadata) {
            if ($metadata->index && $metadata->type) {
                $indexToMetadatas[$metadata->index][] = $metadata;
            }
        }

        if (!empty($indexToMetadatas)) {
            $this->client->createTemplates($indexToMetadatas);
        }
    }

    /**
     * Create new mappings or update existing mappings
     */
    public function updateMappings()
    {
        /** @var ClassMetadata[] $metadatas */
        $metadatas = $this->sm->getMetadataFactory()->getAllMetadata();

        // Refresh all the mappings
        foreach ($metadatas as $metadata) {
            // if we're in the dev env, set the number of replica to be 0
            if ($this->env == 'dev' || $this->env == 'test_local') {
                $metadata->numberOfReplicas = 0;
            }

            // create the index if it doesn't exist yet
            $indexName = $metadata->timeSeriesScale ? $metadata->getCurrentTimeSeriesIndex() : $metadata->index;
            /** @var Index $index */
            $index = $this->client->getIndex($indexName);
            if (!$index->exists()) {
                $this->client->createIndex($indexName, $metadata->getSettings());
            }

            // create the type if it doesn't exist yet
            if ($metadata->type) {
                $type = new Type($index, $metadata->type);
                if (!$type->exists()) {
                    $this->client->createType($metadata);
                }

                // update the mapping
                $result = $this->client->updateMapping($metadata);
                if (true !== $result) {
                    echo "Warning: Failed to update mapping for index '$indexName', type '$metadata->type'. Reason: $result\n";
                }
            }
        }
    }

    /**
     * Delete all templates
     */
    public function deleteAllTemplates()
    {
        /** @var ClassMetadata[] $metadatas */
        $metadatas = $this->sm->getMetadataFactory()->getAllMetadata();
        foreach ($metadatas as $metadata) {
            $this->client->deleteTemplate($metadata);
        }
    }

    /**
     * Delete all indices
     */
    public function deleteAllIndices()
    {
        /** @var ClassMetadata[] $metadatas */
        $metadatas = $this->sm->getMetadataFactory()->getAllMetadata();
        foreach ($metadatas as $metadata) {
            try {
                $this->client->deleteIndex($metadata->getIndexForRead());
            } catch (ResponseException $e) {
                if (strpos($e->getResponse()->getError(), 'IndexMissingException') === false) {
                    // The original error from ES is not "IndexMissingException". We shouldn't swallow it.
                    throw $e;
                }
                // The index has been deleted already, skip it.
            }
        }
    }
}