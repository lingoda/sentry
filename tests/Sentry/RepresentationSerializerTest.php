<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry;

use Lingoda\SentryBundle\Sentry\RepresentationSerializer;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity\Address;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity\Author;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RepresentationSerializerTest extends KernelTestCase
{
    private RepresentationSerializer $serializer;

    protected function setUp(): void
    {
        self::bootKernel();

        $serializer = self::getContainer()->get('test.representation_serializer');
        self::assertInstanceOf(RepresentationSerializer::class, $serializer);
        $this->serializer = $serializer;
    }

    public function testAnEmbeddableIsSerializedWithItsFields(): void
    {
        self::assertSame(
            ['json' => '{"street":"Main Street 1","city":"Berlin"}'],
            $this->serializer->representationSerialize(new Address('Main Street 1', 'Berlin')),
        );
    }

    public function testAnEntityIsSerializedAsItsClassAndIdentifiers(): void
    {
        self::assertSame(
            ['class' => Author::class, 'data' => ['id' => 7]],
            $this->serializer->representationSerialize(new Author(7, 'John')),
        );
    }
}
