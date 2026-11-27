<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Fixture;

use Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity\Address;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity\Author;

final readonly class AuthorMoved
{
    public function __construct(
        public Author $author,
        public Address $address,
    ) {
    }
}
