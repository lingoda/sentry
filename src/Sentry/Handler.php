<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Sentry;

use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use function Sentry\addBreadcrumb;
use Sentry\Breadcrumb;
use Sentry\Monolog\Handler as SentryHandler;
use Sentry\State\Scope;
use function Sentry\withScope;

/**
 * Decorates the official sentry handler to send all logs as breadcrumbs
 * and only the latest of the highest as event
 */
class Handler implements HandlerInterface
{
    private const MAX_SPLIT_CHARS = 1000;

    private SentryHandler $decoratedHandler;

    public function __construct(SentryHandler $decoratedHandler)
    {
        $this->decoratedHandler = $decoratedHandler;
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->decoratedHandler->isHandling($record);
    }

    public function handle(LogRecord $record): bool
    {
        return $this->decoratedHandler->handle($record);
    }

    /**
     * Send everything as breadcrumb and the last of the highest as event
     */
    public function handleBatch(array $records): void
    {
        if (!$records) {
            return;
        }

        $highestRecord = reset($records);
        foreach ($records as $record) {
            $messages = mb_str_split($record->message, self::MAX_SPLIT_CHARS);

            foreach ($messages as $message) {
                addBreadcrumb(
                    new Breadcrumb(
                        $this->getSentryLevel($record->level),
                        Breadcrumb::TYPE_DEFAULT,
                        $record->channel,
                        $message,
                        $record->context
                    )
                );
            }

            if ($highestRecord->level->includes($record->level)) {
                $highestRecord = $record;
            }
        }

        $this->sendAsEvent($highestRecord);
    }

    public function close(): void
    {
        $this->decoratedHandler->close();
    }

    /**
     * Records carrying a real Throwable produce an event with a meaningful
     * stack trace and group correctly out of the box, so they are forwarded
     * untouched.
     *
     * Message-only records are the problem: without an exception, the
     *`attach_stacktrace` option makes the SDK backfill the stack trace of the
     * Monolog flush path. That stack is identical for every such event,
     * and Sentry groups by it, so unrelated errors collapse into a single issue.
     * Pin an explicit fingerprint built from the log identity instead so each
     * distinct error gets its own issue.
     */
    private function sendAsEvent(LogRecord $record): void
    {
        if ($this->hasException($record)) {
            $this->decoratedHandler->handle($record);

            return;
        }

        withScope(function (Scope $scope) use ($record): void {
            $scope->setFingerprint([
                'monolog',
                $record->channel,
                $record->level->getName(),
                $this->groupingKey($record->message),
            ]);

            $this->decoratedHandler->handle($record);
        });
    }

    private function hasException(LogRecord $record): bool
    {
        return ($record->context['exception'] ?? null) instanceof \Throwable;
    }

    /**
     * Reduces a log message to a stable grouping key by replacing volatile
     * tokens (uuids, opaque ids, hex, integers) with placeholders, so the same
     * logical error groups together while genuinely different messages stay
     * apart.
     */
    private function groupingKey(string $message): string
    {
        $patterns = [
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i' => '{uuid}',
            '/(?=[A-Za-z0-9_\-]{21,})[A-Za-z0-9_\-]*[0-9][A-Za-z0-9_\-]*/'        => '{id}',
            '/\b[0-9a-f]{16,}\b/i'                                                => '{hex}',
            '/\b\d+\b/'                                                           => '{n}',
        ];

        return trim((string) preg_replace(array_keys($patterns), array_values($patterns), $message));
    }

    /**
     * Translates the Monolog level into the Sentry breadcrumbs level.
     */
    private function getSentryLevel(Level $monologLevel): string
    {
        return match ($monologLevel->toPsrLogLevel()) {
            LogLevel::DEBUG => Breadcrumb::LEVEL_DEBUG,
            LogLevel::WARNING => Breadcrumb::LEVEL_WARNING,
            LogLevel::ERROR => Breadcrumb::LEVEL_ERROR,
            LogLevel::CRITICAL, LogLevel::EMERGENCY, LogLevel::ALERT => Breadcrumb::LEVEL_FATAL,
            // includes also LogLevel::INFO, LogLevel::NOTICE
            default => Breadcrumb::LEVEL_INFO,
        };
    }
}
