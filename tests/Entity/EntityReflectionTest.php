<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Tests\Entity;

use MarekSkopal\ORM\Entity\EntityReflection;
use MarekSkopal\ORM\Tests\Fixtures\Entity\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

#[CoversClass(EntityReflection::class)]
final class EntityReflectionTest extends TestCase
{
    public function testGetProperties(): void
    {
        $entityReflection = new EntityReflection();
        $properties = $entityReflection->getProperties(UserFixture::class);

        self::assertNotEmpty($properties);
        self::assertContainsOnlyInstancesOf(ReflectionProperty::class, $properties);

        $propertyNames = array_map(fn(ReflectionProperty $p) => $p->getName(), $properties);
        self::assertContains('id', $propertyNames);
        self::assertContains('firstName', $propertyNames);
    }

    public function testGetPropertiesIsCached(): void
    {
        $entityReflection = new EntityReflection();

        $properties1 = $entityReflection->getProperties(UserFixture::class);
        $properties2 = $entityReflection->getProperties(UserFixture::class);

        self::assertSame($properties1, $properties2);
    }

    public function testGetConstructorParameters(): void
    {
        $entityReflection = new EntityReflection();
        $parameters = $entityReflection->getConstructorParameters(UserFixture::class);

        self::assertNotEmpty($parameters);
        self::assertContainsOnlyInstancesOf(ReflectionParameter::class, $parameters);

        $paramNames = array_map(fn(ReflectionParameter $p) => $p->getName(), $parameters);
        self::assertContains('firstName', $paramNames);
        self::assertContains('lastName', $paramNames);
        self::assertNotContains('id', $paramNames);
    }

    public function testGetConstructorParametersIsCached(): void
    {
        $entityReflection = new EntityReflection();

        $params1 = $entityReflection->getConstructorParameters(UserFixture::class);
        $params2 = $entityReflection->getConstructorParameters(UserFixture::class);

        self::assertSame($params1, $params2);
    }

    public function testGetConstructorParametersThrowsForClassWithoutConstructor(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entity must have constructor');

        $entityReflection = new EntityReflection();
        $entityReflection->getConstructorParameters(stdClass::class);
    }

    public function testGetPropertiesNotInConstructor(): void
    {
        $entityReflection = new EntityReflection();
        $properties = $entityReflection->getPropertiesNotInConstructor(UserFixture::class);

        $propertyNames = array_map(fn(ReflectionProperty $p) => $p->getName(), $properties);
        // 'id' is declared as a class property but not a constructor parameter
        self::assertContains('id', $propertyNames);
        self::assertNotContains('firstName', $propertyNames);
    }

    public function testGetPropertiesNotInConstructorIsCached(): void
    {
        $entityReflection = new EntityReflection();

        $props1 = $entityReflection->getPropertiesNotInConstructor(UserFixture::class);
        $props2 = $entityReflection->getPropertiesNotInConstructor(UserFixture::class);

        self::assertSame($props1, $props2);
    }
}
