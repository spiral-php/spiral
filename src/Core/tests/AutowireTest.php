<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Tests\Core;

use PHPUnit\Framework\TestCase;
use Spiral\Core\Container;
use Spiral\Core\Exception\Container\NotFoundException;
use Spiral\Core\Exception\Resolver\ArgumentResolvingException;
use Spiral\Core\Exception\Resolver\WrongTypeException;
use Spiral\Tests\Core\Fixtures\Bucket;
use Spiral\Tests\Core\Fixtures\DependedClass;
use Spiral\Tests\Core\Fixtures\ExtendedSample;
use Spiral\Tests\Core\Fixtures\SampleClass;
use Spiral\Tests\Core\Fixtures\SoftDependedClass;
use Spiral\Tests\Core\Fixtures\TypedClass;
use Spiral\Tests\Core\Fixtures\UnionTypes;

/**
 * The most fun test.
 */
class AutowireTest extends TestCase
{
    public function testSimple(): void
    {
        $container = new Container();

        $this->assertInstanceOf(SampleClass::class, $container->get(SampleClass::class));
        $this->assertInstanceOf(SampleClass::class, $container->make(SampleClass::class, []));
    }

    public function testGet(): void
    {
        $container = new Container();

        $container->bind(SampleClass::class, ExtendedSample::class);
        $this->assertInstanceOf(ExtendedSample::class, $container->get(SampleClass::class));
    }

    public function testMake(): void
    {
        $container = new Container();

        $container->bind(SampleClass::class, ExtendedSample::class);
        $this->assertInstanceOf(ExtendedSample::class, $container->make(SampleClass::class, []));
    }

    public function testArgumentException(): void
    {
        $expected = 'Unable to resolve required argument `name` when resolving';
        $this->expectExceptionMessage($expected);
        $this->expectException(ArgumentResolvingException::class);

        $container = new Container();
        $container->get(Bucket::class);
    }

    public function testDefaultValue(): void
    {
        $container = new Container();

        $bucket = $container->make(Bucket::class, ['name' => 'abc']);

        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertSame('abc', $bucket->getName());
        $this->assertSame('default-data', $bucket->getData());
    }

    public function testCascade(): void
    {
        $container = new Container();

        $object = $container->make(
            DependedClass::class,
            [
                'name' => 'some-name',
            ]
        );

        $this->assertInstanceOf(DependedClass::class, $object);
        $this->assertSame('some-name', $object->getName());
        $this->assertInstanceOf(SampleClass::class, $object->getSample());
    }

    public function testRemoveBinding(): void
    {
        $container = new Container();

        $container->bind('alias', $this);

        $this->assertTrue($container->has('alias'));
        $this->assertTrue($container->hasInstance('alias'));

        $this->assertNotEmpty($container->getBindings());

        $container->removeBinding('alias');

        $this->assertFalse($container->has('alias'));
        $this->assertFalse($container->hasInstance('alias'));

        $container->bind('alias-b', 'alias');
        $this->assertFalse($container->hasInstance('alias-b'));
    }

    public function testCascadeFollowBindings(): void
    {
        $container = new Container();

        $container->bind(SampleClass::class, ExtendedSample::class);

        $object = $container->make(
            DependedClass::class,
            [
                'name' => 'some-name',
            ]
        );

        $this->assertInstanceOf(DependedClass::class, $object);
        $this->assertSame('some-name', $object->getName());
        $this->assertInstanceOf(ExtendedSample::class, $object->getSample());
    }

    public function testAutowireException(): void
    {
        $this->expectExceptionMessage('Undefined class or binding `WrongClass`');
        $this->expectException(NotFoundException::class);
        $container = new Container();

        $container->bind(SampleClass::class, \WrongClass::class);
        $container->make(
            DependedClass::class,
            [
                'name' => 'some-name',
            ]
        );
    }

    /**
     * See line 218 in Container, this behaviour allows system to pass on classes which can not be
     * automatically constructured or missing but ONLY when default value is set to NULL.
     */
    public function testAutowireWithDefaultOnWrongClass(): void
    {
        $container = new Container();

        $container->bind(SampleClass::class, \WrongClass::class);

        $object = $container->make(
            SoftDependedClass::class,
            [
                'name' => 'some-name',
            ]
        );

        $this->assertInstanceOf(SoftDependedClass::class, $object);
        $this->assertSame('some-name', $object->getName());
        $this->assertNull($object->getSample());
    }

    public function testAutowireTypecastingAndValidatingWrongString(): void
    {
        $this->expectExceptionMessage('An argument resolved with wrong type');
        $this->expectException(WrongTypeException::class);

        $container = new Container();

        $object = $container->make(
            TypedClass::class,
            [
                'string' => null,
                'int'    => 123,
                'float'  => 123.00,
                'bool'   => true,
            ]
        );

        $this->assertInstanceOf(TypedClass::class, $object);
    }

    public function testCallMethodWithNullValueOnNullableScalar(): void
    {
        $container = new Container();

        $result = $container->invoke(
            [SampleClass::class, 'nullableScalar'],
            [
                'nullable' => null,
            ]
        );

        $this->assertNull($result);
    }

    /**
     * @requires PHP >= 8.0
     */
    public function testCallMethodWithNullValueOnScalarUnionNull(): void
    {
        $container = new Container();

        $result = $container->invoke(
            [UnionTypes::class, 'unionNull'],
            [
                'nullable' => null,
            ]
        );

        $this->assertNull($result);
    }

    public function testAutowireTypecastingAndValidatingWrongInt(): void
    {
        $this->expectExceptionMessage('Argument #2 ($int) must be of type int, string given');
        $this->expectException(WrongTypeException::class);

        $container = new Container();

        $object = $container->make(
            TypedClass::class,
            [
                'string' => '',
                'int'    => 'yo!',
                'float'  => 123.00,
                'bool'   => true,
            ]
        );

        $this->assertInstanceOf(TypedClass::class, $object);
    }

    public function testAutowireTypecastingAndValidatingWrongFloat(): void
    {
        $this->expectExceptionMessage('Argument #3 ($float) must be of type float, string given');
        $this->expectException(WrongTypeException::class);

        $container = new Container();

        $object = $container->make(
            TypedClass::class,
            [
                'string' => '',
                'int'    => 123,
                'float'  => '~',
                'bool'   => true,
            ]
        );

        $this->assertInstanceOf(TypedClass::class, $object);
    }

    public function testAutowireTypecastingAndValidatingWrongBool(): void
    {
        $this->expectExceptionMessage('An argument resolved with wrong type');
        $this->expectException(WrongTypeException::class);

        $container = new Container();

        $object = $container->make(
            TypedClass::class,
            [
                'string' => '',
                'int'    => 123,
                'float'  => 1.00,
                'bool'   => 'true',
            ]
        );

        $this->assertInstanceOf(TypedClass::class, $object);
    }

    public function testAutowireTypecastingAndValidatingWrongArray(): void
    {
        $this->expectExceptionMessage('An argument resolved with wrong type');
        $this->expectException(WrongTypeException::class);

        $container = new Container();

        $object = $container->make(
            TypedClass::class,
            [
                'string' => '',
                'int'    => 123,
                'float'  => 1.00,
                'bool'   => true,
                'array'  => 'not array',
            ]
        );

        $this->assertInstanceOf(TypedClass::class, $object);
    }

    public function testAutowireOptionalArray(): void
    {
        $container = new Container();

        $object = $container->make(
            TypedClass::class,
            [
                'string' => '',
                'int'    => 123,
                'float'  => 1.00,
                'bool'   => true,
            ]
        );

        $this->assertInstanceOf(TypedClass::class, $object);
    }

    public function testAutowireOptionalString(): void
    {
        $container = new Container();

        $object = $container->make(
            TypedClass::class,
            [
                'string' => '',
                'int'    => 123,
                'float'  => 1.00,
                'bool'   => true,
                'pong'   => null,
            ]
        );

        $this->assertInstanceOf(TypedClass::class, $object);
    }

    public function testAutowireDelegate(): void
    {
        $container = new Container();

        $container->bind('sample-binding', $s = new SampleClass());

        $object = $container->make(
            SoftDependedClass::class,
            [
                'name'   => 'some-name',
                'sample' => new Container\Autowire('sample-binding'),
            ]
        );

        $this->assertSame($s, $object->getSample());
    }

    public function testSerializeAutowire(): void
    {
        $wire = new Container\Autowire('sample-binding', ['a' => new Container\Autowire('b')]);

        $wireb = unserialize(serialize($wire));

        $this->assertEquals($wire, $wireb);
    }

    public function testBingToAutowire(): void
    {
        $container = new Container();
        $container->bind(
            'abc',
            new Container\Autowire(
                SoftDependedClass::class,
                [
                    'name' => 'Fixed',
                ]
            )
        );

        /**
         * @var SoftDependedClass $abc
         */
        $abc = $container->get('abc');

        $this->assertSame('Fixed', $abc->getName());
    }

    public function testGetAutowire(): void
    {
        $container = new Container();

        /**
         * @var SoftDependedClass $abc
         */
        $abc = $container->get(
            new Container\Autowire(
                SoftDependedClass::class,
                [
                    'name' => 'Fixed',
                ]
            )
        );

        $this->assertSame('Fixed', $abc->getName());
    }

    public function testBingToAutowireWithParameters(): void
    {
        $container = new Container();
        $container->bind(
            'abc',
            new Container\Autowire(
                SoftDependedClass::class,
                [
                    'name' => 'Fixed',
                ]
            )
        );

        /**
         * @var SoftDependedClass $abc
         */
        $abc = $container->make('abc', ['name' => 'Overwritten']);

        $this->assertSame('Overwritten', $abc->getName());
    }

    public function testBingToAutowireWithParametersViaArray(): void
    {
        $container = new Container();
        $container->bind(
            'abc',
            Container\Autowire::wire(
                [
                    'class'   => SoftDependedClass::class,
                    'options' => [
                        'name' => 'Fixed',
                    ],
                ]
            )
        );

        /**
         * @var SoftDependedClass $abc
         */
        $abc = $container->make('abc', ['name' => 'Overwritten']);

        $this->assertSame('Overwritten', $abc->getName());
    }


    public function testSerialize(): void
    {
        $a = new Container\Autowire(
            SoftDependedClass::class,
            [
                'name' => 'Fixed',
            ]
        );

        $b = Container\Autowire::__set_state(
            [
                'alias'      => SoftDependedClass::class,
                'parameters' => ['name' => 'Fixed'],
            ]
        );
        $this->assertEquals($a, $b);
    }
}
