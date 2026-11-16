<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry;

use Lingoda\SentryBundle\Sentry\Handler;
use Lingoda\SentryBundle\Sentry\RequestContext;
use Lingoda\SentryBundle\Sentry\RequestContextProcessor;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\BufferHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Sentry\Event;
use Sentry\Monolog\Handler as SentryHandler;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Applications buffer records (fingers_crossed, deduplication) and send them after the request has ended.
 * Each test runs in its own process: sentry-php keeps one global hub and scope per process.
 */
#[RunTestsInSeparateProcesses]
final class RequestContextTest extends KernelTestCase
{
    public function testWithoutTheProcessorABufferedErrorLosesTheRequestAndTheUser(): void
    {
        $event = $this->logDuringARequestAndSendAfterIt(withProcessor: false);

        self::assertSame([], $event->getRequest());
        self::assertNull($event->getUser());
    }

    public function testABufferedErrorKeepsTheRequestAndTheUser(): void
    {
        $event = $this->logDuringARequestAndSendAfterIt(withProcessor: true);

        self::assertSame(['url' => 'https://app.example.com/login', 'method' => 'POST'], $event->getRequest());
        $user = $event->getUser();
        self::assertNotNull($user);
        self::assertSame('student@example.com', $user->getId());
        self::assertSame('203.0.113.7', $user->getIpAddress());
    }

    public function testOnlyWarningsAndAboveCarryTheContext(): void
    {
        self::bootKernel(['environment' => 'pii']);
        $requestStack = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push(Request::create('https://app.example.com/login', 'POST'));
        $processor = $this->processor();

        $info = $processor(new LogRecord(new \DateTimeImmutable(), 'request', Level::Info, 'Login page'));
        $warning = $processor(new LogRecord(new \DateTimeImmutable(), 'request', Level::Warning, 'Login slow'));

        self::assertArrayNotHasKey(RequestContextProcessor::EXTRA_KEY, $info->extra);
        self::assertInstanceOf(RequestContext::class, $warning->extra[RequestContextProcessor::EXTRA_KEY] ?? null);
    }

    public function testAnErrorOutsideARequestGetsNoRequestAndNoUser(): void
    {
        self::bootKernel(['environment' => 'pii']);
        $recorder = $this->recorder();

        $buffer = $this->buffer();
        $logger = new Logger('console', [$buffer], [$this->processor()]);
        $logger->error('Command failed');
        $buffer->close();

        $events = $recorder->getEvents();
        self::assertCount(1, $events);
        self::assertSame([], $events[0]->getRequest());
        self::assertNull($events[0]->getUser());
    }

    public function testOtherLogHandlersDoNotPrintTheContext(): void
    {
        $context = new RequestContext('https://app.example.com/login', 'POST', 'student@example.com', '203.0.113.7');

        $line = (new JsonFormatter())->format(new LogRecord(
            new \DateTimeImmutable(),
            'request',
            Level::Error,
            'Something failed',
            extra: [RequestContextProcessor::EXTRA_KEY => $context],
        ));

        self::assertStringNotContainsString('student@example.com', $line);
        self::assertStringNotContainsString('203.0.113.7', $line);
    }

    private function logDuringARequestAndSendAfterIt(bool $withProcessor): Event
    {
        self::bootKernel(['environment' => 'pii']);
        $recorder = $this->recorder();
        $hub = $this->hub();
        $requestStack = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);

        $buffer = $this->buffer();
        $logger = new Logger('request', [$buffer], $withProcessor ? [$this->processor()] : []);

        // During the request: the request is on the stack and sentry-symfony has put the user on the scope.
        $requestStack->push(Request::create('https://app.example.com/login?token=secret', 'POST'));
        $hub->configureScope(static fn (Scope $scope) => $scope->setUser(
            UserDataBag::createFromUserIdentifier('student@example.com')->setIpAddress('203.0.113.7')
        ));
        $logger->error('Something failed for user 42');

        // After the request: the stack is empty and the scope is reset, then the buffer is sent.
        $requestStack->pop();
        $hub->configureScope(static fn (Scope $scope) => $scope->clear());
        $buffer->close();

        $events = $recorder->getEvents();
        self::assertCount(1, $events);

        return $events[0];
    }

    private function buffer(): BufferHandler
    {
        return new BufferHandler(new Handler(new SentryHandler($this->hub())));
    }

    private function processor(): RequestContextProcessor
    {
        $processor = self::getContainer()->get(RequestContextProcessor::class);
        self::assertInstanceOf(RequestContextProcessor::class, $processor);

        return $processor;
    }

    private function hub(): HubInterface
    {
        $hub = self::getContainer()->get(HubInterface::class);
        self::assertInstanceOf(HubInterface::class, $hub);

        return $hub;
    }

    private function recorder(): RecordingBeforeSend
    {
        $recorder = self::getContainer()->get(RecordingBeforeSend::class);
        self::assertInstanceOf(RecordingBeforeSend::class, $recorder);
        $recorder->reset();

        return $recorder;
    }
}
