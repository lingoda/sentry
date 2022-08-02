<?php

declare(strict_types = 1);

namespace spec\Lingoda\SentryBundle\Sentry\Serializer;

use Lingoda\SentryBundle\Sentry\Serializer\NamespaceJsonSerializer;
use Lingoda\SentryBundle\Sentry\Serializer\ObjectJsonSerializer;
use PhpSpec\ObjectBehavior;

class NamespaceJsonSerializerSpec extends ObjectBehavior
{
    function let(ObjectJsonSerializer $objectJsonSerializer)
    {
        $this->beConstructedWith([], $objectJsonSerializer);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(NamespaceJsonSerializer::class);
    }

    function it_throws_exception_with_invalid_namespace()
    {
        $this
            ->shouldThrow(
                new \RuntimeException(sprintf('\'%s\' is not in serializable namespace', TestClass::class))
            )
            ->during('__invoke', [new TestClass()])
        ;
    }

    function it_can_serialize_to_json(ObjectJsonSerializer $serializer)
    {
        $object = new TestClass();

        $serializer->__invoke($object)->willReturn(['json' => 'object'])->shouldBeCalledOnce();

        $this->beConstructedWith([__NAMESPACE__], $serializer);
        $this($object)->shouldBe(['json' => 'object']);
    }
}

/**
 * @internal
 */
final class TestClass
{
}
