<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Fixture;

use Lingoda\SentryBundle\Sentry\UserIdResolver;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The README example: an internal id instead of the email.
 */
final readonly class InternalUserIdResolver implements UserIdResolver
{
    public function resolve(UserInterface $user): ?string
    {
        return $user instanceof TestUser ? (string) $user->id() : null;
    }
}
