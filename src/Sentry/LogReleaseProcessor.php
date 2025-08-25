<?php

declare(strict_types=1);

namespace Lingoda\SentryBundle\Sentry;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class LogReleaseProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest === null) {
            return $record;
        }

        $release = $currentRequest->headers->get('x-release-id');

        if ($release !== null) {
            $record->extra['release'] = $release;
        }

        return $record;
    }
}
