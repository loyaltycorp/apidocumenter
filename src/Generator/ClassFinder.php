<?php
declare(strict_types=1);

namespace LoyaltyCorp\ApiDocumenter\Generator;

use DateTime as BaseDatetime;
use DateTimeInterface;
use EoneoPay\Utils\DateTime;
use EoneoPay\Utils\UtcDateTime;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

final class ClassFinder
{
    /**
     * @var PropertyInfoExtractorInterface
     */
    private $propertyInfo;

    /**
     * @var string[]
     */
    private $skipClasses;

    /**
     * Constructor.
     *
     * @param string[] $skipClasses
     */
    public function __construct(
        array $skipClasses
    ) {
        // This is constructed and not injected because it is not intended to be customisable.
        // The extractors are configured explicitly for the purposes of finding classes.
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor(
            null,
            null,
            null,
            true,
            ReflectionExtractor::ALLOW_PRIVATE |
                ReflectionExtractor::ALLOW_PROTECTED |
                ReflectionExtractor::ALLOW_PUBLIC
        );

        $this->propertyInfo = new PropertyInfoExtractor(
            [$reflectionExtractor],
            [$phpDocExtractor, $reflectionExtractor],
            [$phpDocExtractor],
            [],
            []
        );

        $this->skipClasses = $skipClasses;
    }

    /**
     * Extracts classes out of an array of class strings.
     *
     * @param string[] $classes
     *
     * @return string[]
     */
    public function extract(array $classes): array
    {
        $found = [];

        foreach ($classes as $class) {
            $this->extractClasses($found, $class);
        }

        return $found;
    }

    /**
     * Looks for related classes.
     *
     * @param string[] $found
     * @param string $class
     *
     * @return string[]
     */
    private function extractClasses(array &$found, string $class): array
    {
        // If what we've found isnt a real class there is nothing to find.
        if (\class_exists($class) === false &&
            \interface_exists($class) === false) {
            return [];
        }

        // We've already seen the class or it should be skipped based on configuration.
        if (\in_array($class, $found, true) === true ||
            $this->shouldSkip($class)
        ) {
            return [];
        }

        $found[] = $class;

        $properties = $this->getProperties($class);

        $discovered = [];

        foreach ($properties as $property) {
            $types = $this->propertyInfo->getTypes($class, $property);

            // No types available.
            if ($types === null) {
                continue;
            }

            foreach ($types as $type) {
                $foundClass = $this->findClass($type);

                // Type didnt have a class.
                if ($foundClass === null) {
                    continue;
                }

                // Add a new found class and related classes to the stack.
                $this->extractClasses($found, $foundClass);
            }
        }

        return $discovered;
    }

    /**
     * Finds a class for a type, if one exists.
     *
     * @param Type $type
     *
     * @return string|null
     */
    private function findClass(Type $type): ?string
    {
        // If we dont have an object builtin and it isnt a collection type we cant find
        // any classes.
        if ($type->getBuiltinType() !== Type::BUILTIN_TYPE_OBJECT &&
            $type->isCollection() === false
        ) {
            return null;
        }

        // Use the annotated class..
        $class = $type->getClassName();

        // Unless we've got a collection type, then use the collection's type
        if ($type->isCollection() === true &&
            $type->getCollectionValueType() instanceof Type === true &&
            $type->getCollectionValueType()->getBuiltinType() === Type::BUILTIN_TYPE_OBJECT
        ) {
            $class = $type->getCollectionValueType()->getClassName();
        }

        return $class;
    }

    /**
     * Returns properties for the supplied class.
     *
     * @param string $class
     *
     * @return string[]
     */
    private function getProperties(string $class): array
    {
        $properties = $this->propertyInfo->getProperties($class) ?? [];

        $properties = \array_filter($properties, static function (string $name): bool {
            // Remove anything that starts with an underscore from being used in the Schema.
            return $name[0] !== '_' &&
                // Remove the statusCode property as a temporary measure to avoid
                // the getStatusCode on responses from being added to every
                // typed response.
                $name !== 'statusCode';
        });

        return $properties;
    }

    /**
     * Checks if the class should be skipped based on configuration.
     *
     * @param string $class
     *
     * @return bool
     */
    private function shouldSkip(string $class): bool
    {
        // Classes that we do not consider as objects, but scalars
        static $scalarClasses = [
            BaseDatetime::class,
            DateTime::class,
            DateTimeInterface::class,
            UtcDateTime::class,
        ];

        $check = \array_merge($scalarClasses, $this->skipClasses);
        // Builds an array of all classes to search for.
        $implements = \array_merge(
            \class_implements($class),
            \class_parents($class)
        );

        // The class should be skipped if it matches one of the $check classes.
        return \in_array($class, $check, true) === true ||
            \count(\array_intersect($implements, $check)) > 0;
    }
}