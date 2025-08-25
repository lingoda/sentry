<?php

declare(strict_types=1);

namespace spec\Lingoda\SentryBundle\Sentry;

use Lingoda\SentryBundle\Sentry\LogReleaseProcessor;
use Monolog\LogRecord;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class  LogReleaseProcessorSpec extends ObjectBehavior
{
    public function let(RequestStack $requestStack)
    {
        $this->beConstructedWith($requestStack);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(LogReleaseProcessor::class);
    }

    public function it_should_add_release(
        RequestStack $requestStack,
        Request $request,
        LogRecord $record,
        HeaderBag $headerBag
    ) {
        $requestStack->getCurrentRequest()->willReturn($request);
        $request->headers = $headerBag;
        $headerBag->get('x-release-id')->willReturn('release-id')->shouldBeCalledOnce();

        $this->__invoke($record)->shouldExtraEquals('release-id');
    }

    public function getMatchers(): array
    {
        return [
            'extraEquals' => static fn (LogRecord $subject, $value) => $subject->extra['release'] === $value,
        ];
    }
}
