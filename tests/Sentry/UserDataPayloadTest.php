<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry;

use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * With PII on, the event keeps the user but carries no cookie, no request body and no Authorization value.
 */
final class UserDataPayloadTest extends KernelTestCase
{
    public function testTheUserIsSentWithoutCookiesOrBody(): void
    {
        self::bootKernel(['environment' => 'pii']);
        $container = self::getContainer();

        $recorder = $container->get(RecordingBeforeSend::class);
        self::assertInstanceOf(RecordingBeforeSend::class, $recorder);
        $recorder->reset();

        $form = ['password' => 'hunter2', 'code' => '123456'];
        $request = Request::create('https://app.example.com/login', 'POST', $form,
            ['PHPSESSID' => 'session-secret'], [],
            ['HTTP_AUTHORIZATION' => 'Bearer abcdefghijklmnop1234', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $request->headers->set('Content-Length', (string) \strlen(http_build_query($form)));
        $requestStack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);

        $hub = $container->get(HubInterface::class);
        self::assertInstanceOf(HubInterface::class, $hub);
        $hub->configureScope(static fn (Scope $scope) => $scope->setUser(UserDataBag::createFromUserIdentifier('student@example.com')));
        $hub->captureException(new \RuntimeException('payload check'));

        $events = $recorder->getEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        $request = $event->getRequest();

        self::assertSame('student@example.com', $event->getUser()?->getId());
        self::assertArrayNotHasKey('cookies', $request);
        self::assertArrayNotHasKey('data', $request);
        self::assertStringNotContainsString('abcdefghijklmnop1234', json_encode($request, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('session-secret', json_encode($request, \JSON_THROW_ON_ERROR));
    }
}
