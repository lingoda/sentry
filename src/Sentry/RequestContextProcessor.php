<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Sentry;

use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Runs on the logger, at log time, so it sees the request and the Sentry user while they still exist.
 * The user is on the scope only when the data collection options allow it (send_default_pii).
 */
final readonly class RequestContextProcessor implements ProcessorInterface
{
    public const string EXTRA_KEY = 'sentry_request_context';

    public function __construct(
        private HubInterface $hub,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (!Level::Warning->includes($record->level)) {
            return $record;
        }

        $request = $this->requestStack->getMainRequest();
        $user = null;
        $this->hub->configureScope(static function (Scope $scope) use (&$user): void {
            $user = $scope->getUser();
        });

        if (null === $request && !$user instanceof UserDataBag) {
            return $record;
        }

        $userId = $user?->getId();
        $record->extra[self::EXTRA_KEY] = new RequestContext(
            // Without the query string: it can hold tokens.
            url: null === $request ? null : $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo(),
            method: $request?->getMethod(),
            userId: null === $userId ? null : (string) $userId,
            ipAddress: $user?->getIpAddress(),
        );

        return $record;
    }
}
