<?php
namespace Revinate\SearchBundle\Lib\Search\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class ParentField extends Annotation
{
}