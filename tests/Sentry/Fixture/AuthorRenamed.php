<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Fixture;

use Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity\Author;

final readonly class AuthorRenamed
{
    public function __construct(
        public Author $author,
        public string $newName,
    ) {
    }
}
