<?php

declare(strict_types=1);

namespace Lingoda\SentryBundle\Tests\Sentry;

use Lingoda\SentryBundle\Sentry\Handler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Sentry\State\HubInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\Assert;

/**
 * End-to-end coverage for the fingerprint pinned by {@see Handler} on
 * exception-less Monolog records, which is what stops Sentry from collapsing
 * unrelated message errors into a single issue (LW-34036).
 *
 * Events are captured through a real Sentry hub via the {@see RecordingBeforeSend}
 * spy wired as `lingoda_sentry.before_send` in the test config, so the assertions
 * exercise the actual scope/fingerprint propagation rather than a stub.
 */
final class HandlerFingerprintTest extends KernelTestCase
{
    private HandlerInterface $handler;

    private RecordingBeforeSend $beforeSend;

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();
        self::bootKernel();

        $container = self::getContainer();

        // Forces the hub service (HubAdapter::getInstance + bindClient) to bind
        // the configured client to the global hub that withScope() resolves.
        $container->get(HubInterface::class);

        $handler = $container->get(Handler::class);
        Assert::isInstanceOf($handler, TestHandler::class);
        $this->handler = $handler;

        $beforeSend = $container->get(RecordingBeforeSend::class);
        Assert::isInstanceOf($beforeSend, RecordingBeforeSend::class);
        $this->beforeSend = $beforeSend;
    }

    public function testDistinctMessageShapesGetDistinctNonEmptyFingerprints(): void
    {
        $this->handler->handleBatch([$this->errorRecord('User 42 not found')]);
        $this->handler->handleBatch([$this->errorRecord('Order 7 could not be shipped')]);

        $events = $this->beforeSend->getEvents();
        self::assertCount(2, $events);

        $first = $events[0]->getFingerprint();
        $second = $events[1]->getFingerprint();

        self::assertNotEmpty($first, 'A message-only event must get an explicit fingerprint');
        self::assertNotEmpty($second);
        self::assertNotSame(
            $first,
            $second,
            'Unrelated messages must produce different fingerprints so Sentry keeps them as separate issues',
        );
    }

    /**
     * @dataProvider volatileTokenPairs
     */
    public function testSameShapeWithVolatileTokensSharesFingerprint(string $a, string $b): void
    {
        $this->handler->handleBatch([$this->errorRecord($a)]);
        $this->handler->handleBatch([$this->errorRecord($b)]);

        $events = $this->beforeSend->getEvents();
        self::assertCount(2, $events);
        self::assertSame(
            $events[0]->getFingerprint(),
            $events[1]->getFingerprint(),
            sprintf('"%s" and "%s" only differ by a volatile token and must group together', $a, $b),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function volatileTokenPairs(): iterable
    {
        yield 'integer ids' => ['User 42 not found', 'User 99 not found'];
        yield 'uuids' => [
            'Session 550e8400-e29b-41d4-a716-446655440000 expired',
            'Session 9b2fd3e1-7c4a-4b2e-8f1a-0c6d5e4a3b2c expired',
        ];
        yield 'hex tokens' => ['Token a1b2c3d4e5f6a7b8 is invalid', 'Token 0f1e2d3c4b5a6978 is invalid'];
        // Real BACKEND-EH6 cluster A: same log line, different entity ids.
        yield 'entity ids in real message' => [
            '[TeacherCalendarEventRemove] Failed to remove calendar event for classId=6468757, userId=1453528: Expected a value other than null.',
            '[TeacherCalendarEventRemove] Failed to remove calendar event for classId=7096510, userId=990161: Expected a value other than null.',
        ];
        // Real BACKEND-EH6 cluster B: same call, different Google spreadsheet ids.
        yield 'opaque spreadsheet ids' => [
            'Google API call error: Unable to retrieve sheet 10hhRad452qfZCrIfy1d7ZSqbcYYQXhNN1Pg8n8u5SfE',
            'Google API call error: Unable to retrieve sheet 1cpNaijhlM8UTiRFMjV6wdJIw3jKRsrTqmfNs8YATXUq',
        ];
    }

    public function testLongCamelCaseTokensAreNotTreatedAsVolatile(): void
    {
        // Two distinct >=21-char, digit-free markers: the id rule must leave them
        // alone (they contain no digit) so unrelated subsystems stay separate.
        $this->handler->handleBatch([$this->errorRecord('[TeacherCalendarEventRemove] sync failed')]);
        $this->handler->handleBatch([$this->errorRecord('[MicrosoftCalendarProvider] sync failed')]);

        $events = $this->beforeSend->getEvents();
        self::assertCount(2, $events);
        self::assertNotSame(
            $events[0]->getFingerprint(),
            $events[1]->getFingerprint(),
            'Long digit-free CamelCase markers must not be normalized away, or distinct errors would merge',
        );
    }

    public function testEventWithThrowableKeepsDefaultGroupingAndDoesNotLeakFingerprint(): void
    {
        // A message-only event first, so its fingerprint is on the scope stack...
        $this->handler->handleBatch([$this->errorRecord('User 42 not found')]);
        // ...then an event carrying a real exception, which must be left untouched.
        $this->handler->handleBatch([
            $this->errorRecord('Something blew up', ['exception' => new \RuntimeException('boom')]),
        ]);

        $events = $this->beforeSend->getEvents();
        self::assertCount(2, $events);

        self::assertNotEmpty($events[0]->getFingerprint());
        self::assertSame(
            [],
            $events[1]->getFingerprint(),
            'Events with a Throwable must keep Sentry default grouping and not inherit the previous fingerprint',
        );
        self::assertNotEmpty($events[1]->getExceptions(), 'The exception must reach Sentry as a real exception');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function errorRecord(string $message, array $context = []): LogRecord
    {
        return new LogRecord(
            new \DateTimeImmutable('2026-06-30T00:00:00+00:00'),
            'app',
            Level::Error,
            $message,
            $context,
        );
    }
}
