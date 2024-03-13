<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Sentry;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\MappingException;
use Lingoda\SentryBundle\Sentry\Serializer\NamespaceJsonSerializer;
use ReflectionException;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializer as BaseRepresentationSerializer;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/*
 * Overwrites Sentry's RepresentationSerializer
 * We want to serialize objects in a way, that will help us with debugging
 */
class RepresentationSerializer extends BaseRepresentationSerializer
{
    private EntityManagerInterface $entityManager;
    private PropertyAccessor $propertyAccessor;
    private NamespaceJsonSerializer $namespaceJsonSerializer;

    /**
     * @var array<string, ClassMetadata>
     */
    private array $loadedClassMetadata = [];

    public function __construct(
        Options $options,
        EntityManagerInterface $entityManager,
        NamespaceJsonSerializer $namespaceJsonSerializer
    ) {
        parent::__construct($options);

        $this->entityManager = $entityManager;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->namespaceJsonSerializer = $namespaceJsonSerializer;
    }

    /**
     * @return string|array<string, mixed>
     */
    protected function serializeValue($value): array|string
    {
        return match (true) {
            $this->isEntity($value) => $this->serializeEntity($value),
            $value instanceof DateTimeInterface => $this->serializeDateTime($value),
            $this->namespaceJsonSerializer->inNamespace($value) => ($this->namespaceJsonSerializer)($value),
            default => parent::serializeValue($value),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeEntity(object $value): array
    {
        $data = $this->extractIdentifiers($value);

        return [
            'class' => \get_class($value),
            'data' => $data,
        ];
    }

    protected function extractIdentifiers(mixed $value): mixed
    {
        if (!$this->isEntity($value)) {
            return $value;
        }

        $metadata = $this->getClassMetadataFor(\get_class($value));
        $identifiers = $metadata->getIdentifierFieldNames();

        $data = [];
        foreach ($identifiers as $identifier) {
            $identifierValue = $this->propertyAccessor->getValue($value, $identifier);
            $data[$identifier] = $this->extractIdentifiers($identifierValue);
        }

        return $data;
    }

    protected function isEntity(mixed $value): bool
    {
        return \is_object($value)
            && $this->getClassMetadataFor(\get_class($value)) !== null;
    }

    private function serializeDateTime(DateTimeInterface $value): string
    {
        return $value->format(DateTimeInterface::ATOM);
    }

    private function getClassMetadataFor(string $className): ?ClassMetadata
    {
        $className = ltrim($className, '\\');
        if (isset($this->loadedClassMetadata[$className])) {
            return $this->loadedClassMetadata[$className];
        }

        try {
            $metadata = $this->entityManager->getClassMetadata($className);
        } catch (MappingException|ReflectionException) {
            $metadata = null;
        }
        $this->loadedClassMetadata[$className] = $metadata;

        return $metadata;
    }
}
