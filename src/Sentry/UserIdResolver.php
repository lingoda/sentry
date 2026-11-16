<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Sentry;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Chooses the user id sent to Sentry, e.g. an internal id instead of the login identifier.
 * Configure it with `lingoda_sentry.user_id_resolver`.
 */
interface UserIdResolver
{
    /**
     * Null keeps the login identifier.
     */
    public function resolve(UserInterface $user): ?string;
}
