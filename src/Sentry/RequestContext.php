<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Sentry;

/**
 * The request and the user at the time a record was logged. Monolog buffers can send the record to Sentry
 * after the request has ended, when neither is available any more.
 *
 * It serializes to nothing, so log formatters that print `extra` do not write the user or the IP into logs.
 */
final readonly class RequestContext implements \JsonSerializable
{
    public function __construct(
        public ?string $url,
        public ?string $method,
        public ?string $userId,
        public ?string $ipAddress,
    ) {
    }

    /**
     * @return array{}
     */
    public function jsonSerialize(): array
    {
        return [];
    }
}
