<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\EventListener;

use Lingoda\SentryBundle\Sentry\UserIdResolver;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Sets the Sentry user id from the configured resolver. It runs before sentry-symfony's LoginListener,
 * which only fills an id that is still empty.
 */
final readonly class UserContextListener
{
    public function __construct(
        private HubInterface $hub,
        private UserIdResolver $resolver,
        private ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function handleKernelRequestEvent(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->setUserId($this->tokenStorage?->getToken()?->getUser());
        }
    }

    public function handleLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        $this->setUserId($event->getUser());
    }

    private function setUserId(?UserInterface $user): void
    {
        if (null === $user || !DataCollectionPolicy::fromHub($this->hub)->shouldCollectUserInfo()) {
            return;
        }

        $id = $this->resolver->resolve($user);
        if (null === $id) {
            return;
        }

        $this->hub->configureScope(static function (Scope $scope) use ($id): void {
            $scope->setUser(($scope->getUser() ?? new UserDataBag())->setId($id));
        });
    }
}
