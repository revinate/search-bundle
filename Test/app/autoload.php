<?php
ini_set('error_reporting', E_ALL); // or error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');


$loader = require __DIR__ . "/../../vendor/autoload.php";
\Doctrine\Common\Annotations\AnnotationRegistry::registerAutoloadNamespace(
    'JMS\Serializer\Annotation', __DIR__.'/../../vendor/jms/serializer/src'
);
//\Doctrine\Common\Annotations\AnnotationRegistry::registerAutoloadNamespace(
//    'Revinate\SearchBundle\Lib\Search\Mapping\Annotations', __DIR__.'/../../vendor/doctrine/search/lib'
//);
require __DIR__ . "/AppKernel.php";