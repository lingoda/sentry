<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, Book> */
    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'author', cascade: ['persist'])]
    private Collection $books;

    public function __construct(
        #[ORM\Column]
        private string $name,
    ) {
        $this->books = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, Book> */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function writeBook(string $title): void
    {
        $this->books->add(new Book($title, $this));
    }
}
