<?php

declare(strict_types=1);

namespace Lingoda\SentryBundle\Tests\Sentry;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Test `before_send` spy. Sentry applies the active scope (and therefore the
 * fingerprint set by {@see \Lingoda\SentryBundle\Sentry\Handler}) to the event
 * before invoking `before_send`, so capturing the event here lets tests assert
 * on the final fingerprint. The event is dropped (returns null) so nothing is
 * sent over the network during the test run.
 */
final class RecordingBeforeSend
{
    /**
     * @var list<Event>
     */
    private array $events = [];

    /**
     * Records the event and drops it (returns null) so the test never performs
     * a real network send. Sentry treats a null return as "discard this event".
     */
    public function __invoke(Event $event, ?EventHint $hint = null): null
    {
        $this->events[] = $event;

        return null;
    }

    /**
     * @return list<Event>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
