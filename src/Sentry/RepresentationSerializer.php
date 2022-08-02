<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Sentry;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Lingoda\SentryBundle\Sentry\Serializer\NamespaceJsonSerializer;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializer as BaseRepresentationSerializer;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Webmozart\Assert\Assert;

/*
 * Overwrites Sentry's RepresentationSerializer
 * We want to serialize objects (entities especially) in a way, that will help us for debugging
 * (sending objects's ids)
 */
class RepresentationSerializer extends BaseRepresentationSerializer
{
    private EntityManagerInterface $entityManager;
    private PropertyAccessor $propertyAccessor;
    private NamespaceJsonSerializer $namespaceJsonSerializer;

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
    protected function serializeValue($value)
    {
        switch (true) {
            case $this->isEntity($value):
                return $this->serializeEntity($value);
            case $value instanceof DateTimeInterface:
                return $this->serializeDateTime($value);
            case $this->namespaceJsonSerializer->inNamespace($value):
                return ($this->namespaceJsonSerializer)($value);
            default:
                return parent::serializeValue($value);
        }
    }

    private function serializeDateTime(DateTimeInterface $value): string
    {
        return $value->format(DateTimeInterface::ATOM);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEntity(object $value): array
    {
        $data = $this->extractIdentifiers($value);

        return [
            'class' => \get_class($value),
            'data' => $data,
        ];
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function extractIdentifiers($value)
    {
        if (!$this->isEntity($value)) {
            return $value;
        }

        $classString = \get_class($value);

        Assert::notFalse($classString);

        $metadata = $this->entityManager->getClassMetadata($classString);
        $identifiers = $metadata->getIdentifierFieldNames();

        $data = [];
        foreach ($identifiers as $identifier) {
            $identifierValue = $this->propertyAccessor->getValue($value, $identifier);
            $data[$identifier] = $this->extractIdentifiers($identifierValue);
        }

        return $data;
    }

    /**
     * @param mixed $value
     */
    private function isEntity($value): bool
    {
        return \is_object($value) &&
            (mb_strpos(\get_class($value), 'App\\Entity\\') === 0
                || mb_strpos(\get_class($value), 'Proxies\\__CG__\\App\Entity\\') === 0);
    }
}
