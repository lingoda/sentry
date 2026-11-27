<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Fixture;

use Doctrine\Common\Collections\Collection;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity\Book;

final readonly class BooksListed
{
    /**
     * @param Collection<int, Book> $books
     */
    public function __construct(
        public Collection $books,
    ) {
    }
}
