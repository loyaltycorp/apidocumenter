<?php
declare(strict_types=1);

namespace Tests\LoyaltyCorp\ApiDocumenter\Unit\Generator;

use DateTime;
use LoyaltyCorp\ApiDocumenter\Generator\ClassFinder;
use Tests\LoyaltyCorp\ApiDocumenter\TestCases\TestCase;
use Tests\LoyaltyCorp\ApiDocumenter\Unit\Generator\Fixtures\EmptyClass;
use Tests\LoyaltyCorp\ApiDocumenter\Unit\Generator\Fixtures\PrivateProperties;
use Tests\LoyaltyCorp\ApiDocumenter\Unit\Generator\Fixtures\PublicProperties;
use Tests\LoyaltyCorp\ApiDocumenter\Unit\Generator\Fixtures\SelfReference;
use Tests\LoyaltyCorp\ApiDocumenter\Unit\Generator\Fixtures\ValueObject;

/**
 * @covers \LoyaltyCorp\ApiDocumenter\Generator\ClassFinder
 */
final class ClassFinderTest extends TestCase
{
    /**
     * Returns data to test class finder.
     *
     * @return mixed[]
     */
    public function getClassData(): iterable
    {
        yield 'non existant class' => [
            'search' => ['PurpleElephants'],
            'expected' => [],
            'skip' => null,
        ];

        yield 'datetime' => [
            'search' => [DateTime::class],
            'expected' => [],
            'skip' => null,
        ];

        yield 'empty class' => [
            'search' => [EmptyClass::class],
            'expected' => [EmptyClass::class],
            'skip' => null,
        ];

        yield 'skipped class' => [
            'search' => [EmptyClass::class],
            'expected' => [],
            'skip' => [EmptyClass::class],
        ];

        yield 'public properties' => [
            'search' => [PublicProperties::class],
            'expected' => [PublicProperties::class, EmptyClass::class, ValueObject::class],
            'skip' => null,
        ];

        yield 'skipped related class' => [
            'search' => [PublicProperties::class],
            'expected' => [PublicProperties::class, ValueObject::class],
            'skip' => [EmptyClass::class],
        ];

        yield 'private properties' => [
            'search' => [PrivateProperties::class],
            'expected' => [PrivateProperties::class, EmptyClass::class, PublicProperties::class, ValueObject::class],
            'skip' => null,
        ];

        yield 'self reference' => [
            'search' => [SelfReference::class],
            'expected' => [SelfReference::class, EmptyClass::class],
            'skip' => null,
        ];
    }

    /**
     * Tests the class finder works.
     *
     * @param string[] $search
     * @param string[] $expected
     * @param string[]|null $skip
     *
     * @return void
     *
     * @dataProvider getClassData
     */
    public function testClassFinder(array $search, array $expected, ?array $skip = null): void
    {
        $finder = new ClassFinder($skip ?? []);

        $result = $finder->extract($search);

        self::assertSame($expected, $result);
    }
}