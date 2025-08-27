<?php

declare(strict_types=1);

namespace Lingoda\SentryBundle\Tests\Sentry;

use Lingoda\SentryBundle\Sentry\Handler;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;

class TestHandler implements HandlerInterface
{
    /**
     * @var LogRecord[]
     */
    private array $records = [];

    public function __construct(
        private readonly Handler $decoratedHandler
    ) {
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->decoratedHandler->isHandling($record);
    }

    public function handle(LogRecord $record): bool
    {
        $this->records[] = $record;

        return $this->decoratedHandler->handle($record);
    }

    public function handleBatch(array $records): void
    {
        $this->records = array_merge($this->records, $records);

        $this->decoratedHandler->handleBatch($records);
    }

    public function close(): void
    {
        $this->decoratedHandler->close();
    }

    public function getRecords(): array
    {
        return $this->records;
    }
}
