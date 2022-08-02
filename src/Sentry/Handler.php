<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Sentry;

use DateTimeImmutable;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
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

    private const BREADCRUMB_LEVELS = [
        Logger::DEBUG => Breadcrumb::LEVEL_DEBUG,
        Logger::INFO => Breadcrumb::LEVEL_INFO,
        Logger::NOTICE => Breadcrumb::LEVEL_INFO,
        Logger::WARNING => Breadcrumb::LEVEL_WARNING,
        Logger::ERROR => Breadcrumb::LEVEL_ERROR,
        Logger::CRITICAL => Breadcrumb::LEVEL_FATAL,
        Logger::ALERT => Breadcrumb::LEVEL_FATAL,
        Logger::EMERGENCY => Breadcrumb::LEVEL_FATAL,
    ];

    private SentryHandler $decoratedHandler;

    public function __construct(SentryHandler $decoratedHandler)
    {
        $this->decoratedHandler = $decoratedHandler;
    }

    /**
     * @param array{level: 100|200|250|300|400|500|550|600} $record
     */
    public function isHandling(array $record): bool
    {
        return $this->decoratedHandler->isHandling($record);
    }

    /**
     * @param array{
     *     message: string,
     *     context?: array<string, mixed>,
     *     level: 100|200|250|300|400|500|550|600,
     *     level_name: 'ALERT'|'CRITICAL'|'DEBUG'|'EMERGENCY'|'ERROR'|'INFO'|'NOTICE'|'WARNING',
     *     channel: string,
     *     datetime: DateTimeImmutable,
     *     extra: array<string, mixed>
     * } $record
     */
    public function handle(array $record): bool
    {
        if (!isset($record['context'])) {
            $record['context'] = [];
        }

        return $this->decoratedHandler->handle($record);
    }

    /**
     * Send everything as breadcrumb and the last of the highest as event
     *
     * @param array{
     *     message: string,
     *     context?: array<string, mixed>,
     *     level: 100|200|250|300|400|500|550|600,
     *     level_name: 'ALERT'|'CRITICAL'|'DEBUG'|'EMERGENCY'|'ERROR'|'INFO'|'NOTICE'|'WARNING',
     *     channel: string,
     *     datetime: DateTimeImmutable,
     *     extra: array<string, mixed>
     * }[] $records
     */
    public function handleBatch(array $records): void
    {
        if (!$records) {
            return;
        }

        $highestRecord = reset($records);
        foreach ($records as $record) {
            $messages = mb_str_split($record['message'], self::MAX_SPLIT_CHARS);

            foreach ($messages as $message) {
                addBreadcrumb(
                    new Breadcrumb(
                            $this->getSentryLevel($record['level']),
                            Breadcrumb::TYPE_DEFAULT,
                            $record['channel'],
                            $message,
                            $record['context'] ?? []
                        )
                );
            }

            if ($highestRecord['level'] <= $record['level']) {
                $highestRecord = $record;
            }
        }

        if (!isset($highestRecord['context'])) {
            $highestRecord['context'] = [];
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
    private function getSentryLevel(int $monologLevel): string
    {
        return self::BREADCRUMB_LEVELS[$monologLevel] ?? Breadcrumb::LEVEL_INFO;
    }
}
