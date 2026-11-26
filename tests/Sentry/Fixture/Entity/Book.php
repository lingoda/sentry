<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Book
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private int $id,
        #[ORM\Column]
        private string $title,
        #[ORM\ManyToOne(inversedBy: 'books')]
        private Author $author,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): Author
    {
        return $this->author;
    }
}
