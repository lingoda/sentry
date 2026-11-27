<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Sentry\Serializer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Proxy;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webmozart\Assert\Assert;

final class EntityReferenceNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public const string CONTEXT_KEY = 'lingoda_sentry.entity_reference';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if ($data instanceof PersistentCollection) {
            return ['class' => $data->getTypeClass()->getName(), 'initialized' => false];
        }

        Assert::object($data);
        $metadata = $this->entityManager->getClassMetadata($data::class);

        return [
            'class' => $metadata->getName(),
            'id' => $this->normalizer->normalize($metadata->getIdentifierValues($data), $format, $context),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!isset($context[self::CONTEXT_KEY]) || !\is_object($data)) {
            return false;
        }

        if ($data instanceof PersistentCollection) {
            return !$data->isInitialized();
        }

        $class = $data instanceof Proxy ? get_parent_class($data) : $data::class;

        if ($class === false || $this->entityManager->getMetadataFactory()->isTransient($class)) {
            return false;
        }

        return !$this->entityManager->getClassMetadata($class)->isEmbeddedClass;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }
}
