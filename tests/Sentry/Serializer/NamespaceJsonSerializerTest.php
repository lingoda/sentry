<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Serializer;

use Lingoda\SentryBundle\Sentry\RepresentationSerializer;
use Lingoda\SentryBundle\Tests\TestNamespaceObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NamespaceJsonSerializerTest extends KernelTestCase
{
    private RepresentationSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $serializer = self::$container->get('test.representation_serializer');
        self::assertInstanceOf(RepresentationSerializer::class, $serializer);

        $this->serializer = $serializer;
    }

    public function testNamespaceSerializer(): void
    {
        self::assertSame(
            ['json' => '{"id":1}'],
            $this->serializer->representationSerialize(new TestDTO(1)),
            'In defined namespaces'
        );

        self::assertSame(
            'Object Lingoda\SentryBundle\Tests\TestNamespaceObject',
            $this->serializer->representationSerialize(new TestNamespaceObject(1)),
            'Not in defined namespaces'
        );
    }
}

/**
 * @internal
 */
final class TestDTO
{
    public function __construct(
        private int $id
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
