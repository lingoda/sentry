<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\DependencyInjection;

use Lingoda\SentryBundle\DependencyInjection\LingodaSentryExtension;
use Sentry\State\HubInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class LingodaSentryExtensionTest extends KernelTestCase
{
    public function testTheClientSendsNoCookiesUserOrRequestBodyByDefault(): void
    {
        self::bootKernel();

        $hub = self::getContainer()->get(HubInterface::class);
        self::assertInstanceOf(HubInterface::class, $hub);
        $options = $hub->getClient()?->getOptions();
        self::assertNotNull($options);

        self::assertFalse($options->shouldSendDefaultPii());
        self::assertSame('none', $options->getMaxRequestBodySize());
    }

    public function testAnApplicationCanOptIn(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('lingoda_sentry', [
            'dsn' => 'https://user@test.ingest.sentry.io/1',
            'environment' => 'test',
            'send_default_pii' => true,
            'max_request_body_size' => 'medium',
        ]);

        (new LingodaSentryExtension())->prepend($container);

        $options = $container->getExtensionConfig('sentry')[0]['options'];
        self::assertTrue($options['send_default_pii']);
        self::assertSame('medium', $options['max_request_body_size']);
    }
}
