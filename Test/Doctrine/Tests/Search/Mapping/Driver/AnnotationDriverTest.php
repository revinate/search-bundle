<?php
namespace Unit\Revinate\SearchBundle\Lib\Search\Mapping\Driver;

use Revinate\SearchBundle\Lib\Search\Mapping\Driver\AnnotationDriver;
use Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata;

/**
 * Test class for AnnotationDriver.
 * Generated by PHPUnit on 2011-12-13 at 08:34:04.
 */
class AnnotationDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Revinate\SearchBundle\Lib\Search\Mapping\Driver\AnnotationDriver
     */
    private $annotationDriver;

    /**
     * @var \Doctrine\Common\Annotations\Reader|\PHPUnit_Framework_MockObject_MockObject
     */
    private $reader;

    /**
     * @var \Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata|\PHPUnit_Framework_MockObject_MockObject
     */
    private $classMetadata;

    /**
     * @var \ReflectionClass|\PHPUnit_Framework_MockObject_MockObject
     */
    private $reflectionClass;

    protected function setUp()
    {
        $this->reader = $this->getMock('Lib\\Common\\Annotations\\Reader');

        $this->classMetadata = $this->getMockBuilder('Revinate\\SearchBundle\\Lib\\Search\\Mapping\\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $this->reflectionClass = $this->getMockBuilder('\ReflectionClass')
            ->disableOriginalConstructor()
            ->getMock();

        $this->annotationDriver = new AnnotationDriver($this->reader);
    }

    public function testLoadMetadataForClass()
    {
        $this->reader->expects($this->once())
            ->method('getClassAnnotations')
            ->will($this->returnValue(array(0, new TestSearchable2(array()))));

        $this->reader->expects($this->atLeastOnce())
            ->method('getPropertyAnnotations')
            ->will($this->returnValue(array(0, new TestField(array()))));

        $this->reader->expects($this->atLeastOnce())
            ->method('getMethodAnnotations')
            ->will($this->returnValue(array()));

        $classMetadata = new ClassMetadata('Lib\Tests\Models\Blog\BlogPost');

        $this->annotationDriver->loadMetadataForClass('Lib\Tests\Models\Blog\BlogPost', $classMetadata);

        $this->assertInstanceOf('Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata', $this->classMetadata);
        $this->assertEquals('Lib\Tests\Models\Blog\BlogPost', $classMetadata->getName());
    }

    /**
     * @expectedException \Revinate\SearchBundle\Lib\Search\Exception\Driver\ClassIsNotAValidDocumentException
     */
    public function testLoadMetadataForClassExtractClassAnnotationsException()
    {

        $this->reader->expects($this->once())
            ->method('getClassAnnotations')
            ->will($this->returnValue(array()));

        $this->reflectionClass->expects($this->once())
            ->method('getProperties')
            ->will($this->returnValue(array()));

        $this->classMetadata->expects($this->once())
            ->method('getReflectionClass')
            ->will($this->returnValue($this->reflectionClass));

        $this->annotationDriver->loadMetadataForClass('Lib\Tests\Models\Blog\BlogPost', $this->classMetadata);
    }

    /**
     * @expectedException \ReflectionException
     */
    public function testLoadMetadataForReflectionErrorClassNotFound()
    {
        $this->classMetadata->expects($this->once())
            ->method('getReflectionClass')
            ->will($this->returnValue(false));

        $this->annotationDriver->loadMetadataForClass('NotExistingClass', $this->classMetadata);
    }

    /**
     * @expectedException \Revinate\SearchBundle\Lib\Search\Exception\Driver\PropertyDoesNotExistsInMetadataException
     */
    public function testLoadMetadataForClassAddValuesToMetadata()
    {
        $this->reflectionClass->expects($this->once())
            ->method('getProperties')
            ->will($this->returnValue(array()));

        $this->reader->expects($this->once())
            ->method('getClassAnnotations')
            ->will($this->returnValue(array(0, new TestSearchable(array()))));

        $this->classMetadata->expects($this->once())
            ->method('getReflectionClass')
            ->will($this->returnValue($this->reflectionClass));

        $this->annotationDriver->loadMetadataForClass('Lib\Tests\Models\Blog\BlogPost', $this->classMetadata);
    }


    /**
     * @expectedException \PHPUnit_Framework_Error
     */
    public function testLoadMetadataForClassWrongParameterClassName()
    {
        $this->annotationDriver->loadMetadataForClass(new \StdClass(), $this->classMetadata);
    }

    /**
     * @expectedException \PHPUnit_Framework_Error
     */
    public function testLoadMetadataForClassWrongParameterClassMetadata()
    {
        $this->annotationDriver->loadMetadataForClass('test', new \StdClass());
    }
}

use Revinate\SearchBundle\Lib\Search\Mapping\Annotations\Searchable;

class TestSearchable extends Searchable
{
    public $typeNotDefined;
}


class TestSearchable2 extends Searchable {}

use Revinate\SearchBundle\Lib\Search\Mapping\Annotations\Field;

class TestField extends Field {}
