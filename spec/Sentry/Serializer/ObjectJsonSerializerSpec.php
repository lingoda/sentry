<?php

declare(strict_types = 1);

namespace spec\Lingoda\SentryBundle\Sentry\Serializer;

use Lingoda\SentryBundle\Sentry\Serializer\ObjectJsonSerializer;
use PhpSpec\ObjectBehavior;
use stdClass;
use Symfony\Component\Serializer\SerializerInterface;

class ObjectJsonSerializerSpec extends ObjectBehavior
{
    function let(SerializerInterface $serializer)
    {
        $this->beConstructedWith($serializer);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ObjectJsonSerializer::class);
    }

    function it_can_serialize_object(SerializerInterface $serializer)
    {
        $object = new stdClass();

        $serializer->serialize($object, 'json')->willReturn('serializedObject')->shouldBeCalledOnce();

        $this($object)->shouldBe(['json' => 'serializedObject']);
    }
}
