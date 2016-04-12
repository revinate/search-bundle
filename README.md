# Revinate Search Bundle ![Build Status](https://travis-ci.org/revinate/search-bundle.svg?branch=master) #
A package that wraps the [doctrine/search](https://github.com/doctrine/search) prototype into a [Symfony2](https://symfony.com) bundle, allowing [Doctrine 2 ORM](https://github.com/doctrine/doctrine2)-like interactions with [Elasticsearch](https://www.elastic.co).

# Usage #
## Installation ##
Include this in your `composer.json`
```json
{
    "require": {
	    "revinate/search-bundle": "dev-master"
    }
}
```

Fill in your configuration settings in `config.yml`
```yaml
revinate_search:
    connection:
        host: 127.0.0.1
        port: 9200
    paths: ["%kernel.root_dir%/../src/Revinate/SharedBundle/Elasticsearch/Entity", "%kernel.root_dir%/../src/Inguest/GRMBundle"]
    env: dev
```
For `paths`, specify the location of all your Elasticsearch entities within your project.

You can now access the features of this bundle through the predefined services.
```php
$searchManager = $this->getContainer()->get('revinate_search.search_manager');
$mappingManager = $this->getContainer()->get('revinate_search.mapping_manager');
```

## Mappings ##
Basic entity mappings for index and type generation can be annotated as shown in the following example. Mappings
can be rendered into a format suitable for automatically generating indexes and templates.
```php
<?php
namespace Revinate\SharedBundle\Elasticsearch\Entity;

use Revinate\SearchBundle\Lib\Search\Mapping\Annotations\ElasticField;
use Revinate\SearchBundle\Lib\Search\Mapping\Annotations\ElasticSearchable;
use Revinate\SearchBundle\Lib\Search\Mapping\Annotations\Id;
use Revinate\SearchBundle\Lib\Search\Mapping\Annotations\TimeSeriesField;

/**
 * @ElasticSearchable(
 *      index="index_name",
 *      type="index_type",
 *      timeSeriesScale="monthly",
 *      dynamic="strict",
 *      parent="parent_type"
 *      numberOfShards=4,
 *      numberOfReplicas=1
 * )
 */
class Post extends \Revinate\SearchBundle\Lib\Search\BaseElasticsearchEntity {
  const INDEX_TYPE = 'index_type';

  /**
   * The id field used by this index type
   * @Id
   * @ElasticField(type="string", index="not_analyzed")
   */
  protected $id;

  /**
   * An integer field
   * @ElasticField(type="integer")
   */
  protected $views;

  /**
   * A string field without an analyzer
   * @ElasticField(type="string", index="not_analyzed")
   */
  protected $title;

  /**
   * A date field, also used for the time series indexing
   * @ElasticField(type="date", format="dateOptionalTime")
   * @TimeSeriesField
   */
  protected $date;

  /**
   * A dynamic object, with some predefined mappings
   * @ElasticField(type="object", dynamic=true, properties={
   *    @ElasticField(name="userId", type="integer"),
   *    @ElasticField(name="type", type="string", index="not_analyzed")
   * })
   */
  protected $source;

  /**
   * A nested object, with some predefined mappings
   * @ElasticField(type="nested", dynamic=true, properties={
   *    @ElasticField(name="userId", type="integer"),
   *    @ElasticField(name="type", type="string", index="not_analyzed")
   * })
   */
   protected $metadata;
}
```
As you can see the `@ElasticField` can be nested.

### Managing Mappings ###
Use the mapping manager to manage all the mappings and templates related to your entities.
```php
// Create/update all defined mappings
$mappingManager->updateMappings();

// Create/update all templates (used for timeseries indices)
$mappingManager->updateTemplates();

// Combines the above operations
$mappingManager->update();

// Delete all defined indices
$mappingManager->deleteAllIndices();

// Delete all defined templates
$mappingManager->deleteAllTemplates();
```

Typically, only the create and update operations are used. They are wrapped in a predefined command `revinate:search:schema-update` for convenience.
The deletion operations are useful for data tear down in unit testing.

### Time Series Index ###
One common indexing strategy uses a time series index, which partitions an index into multiple time series based indices. Here's an example of a time series index:
```php
* @ElasticSearchable(
 *      index="post",
 *      type="post",
 *      timeSeriesScale="monthly",
 * )
 */
class Post extends \Revinate\SearchBundle\Lib\Search\BaseElasticsearchEntity {
  /**
   * @ElasticField(type="date", format="dateOptionalTime")
   * @TimeSeriesField
   */
  protected $createdAt;
}
```
First define the scale of the time series in the `@ElasticSearchable` annotation which can be yearly, monthly, or daily. Then define a date field as a time series field by adding the annotation `@TimeSeriesField`.

For a time series index, every time you run the command `revinate:search:schema-update`, this search bundle will automatically create the index of the next time period if necessary and also update the index template based on the mapping. When you index a document to a time series index, the field with `@TimeSeriesField` will be used to determine the index the document will be indexed into, for example a `Post` created at 2015-01-01 will be indexed into the index `post_2015_01`.

### Parent and Child Index ###
Sometimes we will need a parent-child structure into an index, as the example below:
```php
/*
 * @ElasticSearchable(
 *      index="blog",
 *      type="post"
 * )
 */
class Post extends \Revinate\SearchBundle\Lib\Search\BaseElasticsearchEntity {
}

/*
 * @ElasticSearchable(
 *      index="blog",
 *      type="comment",
 *      parent="post"
 * )
 */
class Comment extends \Revinate\SearchBundle\Lib\Search\BaseElasticsearchEntity {
    /**
     * @var string
     * @ElasticField(type="string", index="not_analyzed")
     * @ParentField
     * @Column(type="string")
     */
     $postId;
}
```
You can define the parent type in the `@ElasticSearchable` annotation by adding `parent="parent_type"`. The code above will create an index called `blog` with two types `post` and `comment`, and type `comment` will be the child of type `blog`. When an `comment` entity is being indexed, the value of the field with annotation `@ParentField` (which is `$postId` in this case) will be used to specify the parent ID for the document.

## Indexing ##
There are two ways of indexing an entity to be stored as an Elasticsearch document.

### Single Entity ###
Directly index a single newly created or updated entity:
```php
use Revinate\SharedBundle\Elasticsearch\Entity\Post;

// Create a new Post
$post = new Post();
$post->setViews(3);
$post->setTitle('Hello world');
$post->setDate(new \DateTime());

$entityRepo = $searchManager->getRepository('Post::class');

// Save (persist and flush) the entity. Because Elasticsearch is near realtime, the document
// may not be available immediately for searching.
$entityRepo->save($post);
// If realtime is required, pass true to the second argument to force a refresh.
$entityRepo->save($post, true);
// Alternatively, you can persist and flush the entity in the normal doctrine way (persist and flush)
$searchManager->persist($post);
$searchManager->flush($post);
```

### Batch Processing ###
Batch index or update a set of entities:
```php
$posts = [];
$posts[] = new Post();
$posts[] = new Post();
$posts[] = new Post();

$authors = [];
$authors[] = new Author();
$authors[] = new Author();

$searchManager->persist($posts);
$searchManager->persist($authors);
$searchManager->flush(); // If you want to refresh the index after the flushing, you can do $searchManager->flush(null, true);
```
As you can see from the example above, you can flush entities belonging to different indices and types in one call, and the library will issue one batch request for each type/index.

### Index a Document with Version###
Elasticsearch provides [versioning support](https://www.elastic.co/blog/elasticsearch-versioning-support), which can be used as optimistic locking strategy for concurrency control. In the search bundle, you can use the annotation `@VersionField` to provide a version together with a version type when indexing a document like the example below:
```php
    /**
     * @var int
     * @VersionField(versionType="external_gte")
     * @ElasticField(type="integer")
     */
    protected $esVersion = 1;
```

## Fetching ##
There are several ways to fetch entities and other results from Elasticsearch.

### By ID ###
```php
// de305d54-75b4-431b-adb2-eb6b9e546014 is the ID of the document, defined in the mapping by annotation @ID
$entity = $entityRepo->find('de305d54-75b4-431b-adb2-eb6b9e546014');
```

### By Criteria ###
An array of key-value pairs:
```php
// array of key => value pairs
$entity = $entityRepo->findOneBy(array(
    'userId'    => 323,
    'type'      => 'Admin'
));
```

Several helpers below are available for convenience.
#### Range ####
```php
use Revinate\SearchBundle\Lib\Search\Criteria\Range

$entity = $entityRepo->findOneBy(array(
    'createdAt' => Range(Range::GTE, '2015-09-27', Range::LTE, '2015-10-27')
));
```

#### Not ####
```php
use Revinate\SearchBundle\Lib\Search\Criteria\Not

$entity = $entityRepo->findOneBy(array(
    'userId' => Not(323)
));
```

#### Exists ####
```php
use Revinate\SearchBundle\Lib\Search\Criteria\Exists

$entity = $entityRepo->findOneBy(array(
    'middleName' => Exists()
));
```

#### Missing ####
```php
use Revinate\SearchBundle\Lib\Search\Criteria\Missing

$entity = $entityRepo->findONeBy(array(
    'middleName' => Missing()
));
```

#### Or ####
```php
$entity = $entityRepo->findOneBy(array(
    SearchManager::CRITERIA_OR => array(
        'userId' => 323,
        'type'   => 'Admin'
    )
));
```

#### Fetching Entities with Total Count ####
```pph
/** Doctrine\Search\ElasticsearchEntityCollection $entityCollection **/
$entityCollection = $entityRepo->findBy(['type' => 'Admin'], ['id' => 'desc'], 10, 0);
$totalHit = $entityCollection->getTotal();
$entities = $entityCollection->toArray();
```

#### Parent ####
Find documents based on a field from the parent type.
```php
$entity = $entityRepo->findOneBy(array(
    '_parent.guest.id' => 'value' // the criteria key format is '_parent.type.field'
));
```

#### Child ####
Find documents based on a field from the child type.
```php
$entity = $entityRepo->findOneBy(array(
    '_child.guestStay.id' => 'value' // the criteria key format is '_child.type.field'
));
```

## Queries ##
### Direct Elastica Search ###
```php
$termQuery = new \Elastica\Query\Term(array('id' => 323));
$query = new \Elastica\Query($termQuery);
$entityCollection = $entityRepo->search($query);
```
Also there are two helpers to help you generate an Elastica query/filter from an array of criteria
```php
$query = $searchManager->generateQueryBy(['id' => 1], ['dob' => 'desc'], 10, 0);
$filter = $searchManager->generateFilterBy(['id' => 1, 'dob' => '2000-01-01']);
$query = new \Elastica\Query\Filtered($filter);
```

### Custom Query Builder ###
```php
$esQuery = new \Elastica\Query(new \Elastica\Query\Terms(array('type' => 'admin')));
$query = $searchManager->createQuery()
    ->from('\Revinate\SharedBundle\Elasticsearch\Entity\Post')
    ->searchWith($esQuery)
    ->setLimit(10)
    ->setSize(20)
    ->setSort(array('id' => 'asc'));
/** ElasticsearchEntityCollection $results **/
$results = $query->getResult();
```
By default, the results will be hydrated as `\Revinate\SearchBundle\Lib\Search\ElasticsearchEntityCollection` which contains a `getTotal()` method for retrieving the total output size, which is useful for pagination. You can bypass the hydration and get an Elastica result set by setting `$query->setHydrationMode(\Revinate\SearchBundle\Lib\Search\Query::HYDRATE_BYPASS)`.

### Aggregation Results ###
```php
$userIdAggregation = new \Elastica\Aggregation\Terms('id');
$userIdAggregation->setField('id);

$query = $searchManager->generateQueryBy([]);

$aggregationResult = $searchManager->createQuery()
    ->from('\Revinate\SharedBundle\Elasticsearch\Entity\Post')
    ->searchWith($query)
    ->addAggregation($userIdAggregation)
    ->getResult(\Revinate\SearchBundle\Lib\Search\Query::HYDRATE_AGGREGATION);
```

### Scan and Scroll ###
```php
// from query builder
$query = $searchManager->createQuery()
    ->from('Post')
    ->searchWith($esQuery)
    ->setLimit(10)
    ->setSize(20)
    ->setSort(array('id' => 'asc'));
$result = $query->scan($sizePerShard, $expiryTime);
// scan-and-scroll is per shard based, so the actual number of document returned per scroll
// will be $sizePerShard * totalNumberOfShards, also the expiry time here is the time to keep
// a scroll open, check https://www.elastic.co/guide/en/elasticsearch/guide/current/scan-scroll.html
// for more details

// from repository
$repo = $searchManager->getRepository('Post');
$result = $repo->scanBy(['id' => [1,2,3], $sizePerShard, $expiryTime);

if (! $result->current()) {
    // no result has been find
}
$total = $result->current()->getTotal(); // fetch the total matched records
/** ElasticsearchEntityCollection $collection**/
foreach ($result as $collection) {
    // loop through the result, each time it iterate to the next $collection, it will be a call to scroll
}
```

## Serializers ##
Entities should extend `\Revinate\SearchBundle\Lib\Search\BaseElasticsearchEntity` which uses a default serializer `\Revinate\SearchBundle\Lib\Search\ElasticsearchEntitySerializer` that provides basic enhancements, such as automatically converting date fields to the `\DateTime` type.

# Contributing #
Clone the repository and install the dependencies:
```
git clone https://github.com/revinate/search-bundle.git
composer install
```
Run unit tests
```
./vendor/bin/phpunit
```
