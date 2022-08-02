<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Sentry\Serializer;

use ReflectionClass;

final class NamespaceJsonSerializer
{
    /**
     * @param string[] $namespaces
     */
    public function __construct(
        private array $namespaces,
        private ObjectJsonSerializer $objectJsonSerializer
    ) {
    }

    /**
     * @param object $object
     *
     * @return array{json: string}
     */
    public function __invoke($object): array
    {
        if (!$this->inNamespace($object)) {
            throw new \RuntimeException(sprintf('\'%s\' is not in serializable namespace', $object::class));
        }

        return ($this->objectJsonSerializer)($object);
    }

    /**
     * @param mixed $object
     */
    public function inNamespace($object): bool
    {
        if (!\is_object($object)) {
            return false;
        }

        try {
            return \in_array(
                (new ReflectionClass($object))->getNamespaceName(),
                $this->namespaces,
                true
            );
        } catch (\ReflectionException) {
            return false;
        }
    }
}
