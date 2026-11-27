<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Fixture;

final readonly class ReviewPosted
{
    public function __construct(
        public Review $review,
    ) {
    }
}
