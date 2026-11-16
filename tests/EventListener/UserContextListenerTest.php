<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\EventListener;

use Lingoda\SentryBundle\EventListener\UserContextListener;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\InternalUserIdResolver;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\TestUser;
use Lingoda\SentryBundle\Tests\Sentry\RecordingBeforeSend;
use Sentry\State\HubInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class UserContextListenerTest extends KernelTestCase
{
    public function testTheResolvedIdReplacesTheLoginIdentifier(): void
    {
        self::bootKernel(['environment' => 'pii']);
        $container = self::getContainer();

        $hub = $container->get(HubInterface::class);
        self::assertInstanceOf(HubInterface::class, $hub);
        $resolver = $container->get(InternalUserIdResolver::class);
        self::assertInstanceOf(InternalUserIdResolver::class, $resolver);
        $recorder = $container->get(RecordingBeforeSend::class);
        self::assertInstanceOf(RecordingBeforeSend::class, $recorder);
        $recorder->reset();

        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new TestUser(42, 'student@example.com'), 'main', ['ROLE_USER']));

        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        (new UserContextListener($hub, $resolver, $tokens))->handleKernelRequestEvent(
            new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST)
        );

        $hub->captureMessage('user context');

        $events = $recorder->getEvents();
        self::assertCount(1, $events);
        self::assertSame('42', $events[0]->getUser()?->getId());
        self::assertTrue($container->has(UserContextListener::class));
    }
}
