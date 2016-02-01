<?php
namespace Revinate\SearchBundle\Lib\Search\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class VersionField extends Annotation
{
    /**
     * Version type to use, check https://www.elastic.co/guide/en/elasticsearch/reference/1.4/docs-index_.html#index-versioning
     *
     * @var string
     */
    public $versionType = 'external';
}