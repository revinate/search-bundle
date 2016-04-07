<?php
use Doctrine\Common\Annotations\AnnotationRegistry;

ini_set('error_reporting', E_ALL); // or error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');


$loader = require __DIR__ . "/../../vendor/autoload.php";
AnnotationRegistry::registerLoader('class_exists');
require __DIR__ . "/AppKernel.php";