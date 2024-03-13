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

        $this->decoratedHandler->handle($highestRecord);
    }

    public function close(): void
    {
        $this->decoratedHandler->close();
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
