<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Sentry\Serializer;

use Symfony\Component\Serializer\SerializerInterface;

/**
 * User Symfony serializer to serializer objects to json
 */
class ObjectJsonSerializer
{
    private SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @return array{json: string}
     */
    public function __invoke(object $message): array
    {
        return [
            'json' => $this->serializer->serialize($message, 'json'),
        ];
    }
}
