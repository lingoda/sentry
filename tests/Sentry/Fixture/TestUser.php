<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Fixture;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class TestUser implements UserInterface
{
    /**
     * @param non-empty-string $email
     */
    public function __construct(
        private int $id,
        private string $email,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
