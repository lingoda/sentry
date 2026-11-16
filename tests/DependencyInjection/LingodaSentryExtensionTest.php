<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\DependencyInjection;

use Lingoda\SentryBundle\DependencyInjection\LingodaSentryExtension;
use Lingoda\SentryBundle\EventListener\UserContextListener;
use Sentry\DataCollection\HttpMessageType;
use Sentry\DataCollection\KeyValueCollectionBehavior;
use Sentry\Options;
use Sentry\State\HubInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class LingodaSentryExtensionTest extends KernelTestCase
{
    public function testTheDefaultsSendNoUserCookiesOrBodies(): void
    {
        self::bootKernel();

        $options = $this->clientOptions();
        $dataCollection = $options->getDataCollection();
        self::assertNotNull($dataCollection);

        self::assertFalse($dataCollection->shouldCollectUserInfo());
        self::assertSame(KeyValueCollectionBehavior::MODE_OFF, $dataCollection->getCookies()->getMode());
        self::assertFalse($dataCollection->shouldCollectHttpBody(HttpMessageType::incomingRequest()));
        self::assertFalse($dataCollection->shouldCollectHttpBody(HttpMessageType::outgoingResponse()));
        self::assertFalse($dataCollection->shouldCollectDatabaseQueryData());
        self::assertFalse($dataCollection->shouldCollectQueues());
        self::assertSame(['inputs' => false, 'outputs' => false], $dataCollection->getGenAi());
        self::assertSame(KeyValueCollectionBehavior::MODE_DENY_LIST, $dataCollection->getStackFrameVariables()->getMode());
        self::assertSame('none', $options->getMaxRequestBodySize());
        self::assertContains('forwarded', $dataCollection->getHttpHeaders()['request']->getTerms());
        self::assertFalse(self::getContainer()->has(UserContextListener::class));
    }

    public function testPiiSendsTheUserButStillNoCookies(): void
    {
        self::bootKernel(['environment' => 'pii']);

        $dataCollection = $this->clientOptions()->getDataCollection();
        self::assertNotNull($dataCollection);

        self::assertTrue($dataCollection->shouldCollectUserInfo());
        self::assertSame(KeyValueCollectionBehavior::MODE_OFF, $dataCollection->getCookies()->getMode());
        self::assertSame([], $dataCollection->getHttpHeaders()['request']->getTerms());
    }

    public function testARequestBodySizeCollectsIncomingRequestBodiesOnly(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('lingoda_sentry', [
            'dsn' => 'https://user@test.ingest.sentry.io/1',
            'environment' => 'test',
            'max_request_body_size' => 'medium',
        ]);

        (new LingodaSentryExtension())->prepend($container);

        $options = $container->getExtensionConfig('sentry')[0]['options'];
        self::assertSame('medium', $options['max_request_body_size']);
        self::assertSame(['incomingRequest'], $options['data_collection']['http_bodies']);
        self::assertArrayNotHasKey('send_default_pii', $options);
    }

    public function testPiiWithoutTheIpFiltersTheClientIpHeaders(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('lingoda_sentry', [
            'dsn' => 'https://user@test.ingest.sentry.io/1',
            'environment' => 'test',
            'send_default_pii' => true,
            'send_ip' => false,
        ]);

        (new LingodaSentryExtension())->prepend($container);

        $terms = $container->getExtensionConfig('sentry')[0]['options']['data_collection']['http_headers']['request']['terms'];
        self::assertContains('forwarded', $terms);
        self::assertContains('x-real-ip', $terms);
    }

    private function clientOptions(): Options
    {
        $hub = self::getContainer()->get(HubInterface::class);
        self::assertInstanceOf(HubInterface::class, $hub);
        $options = $hub->getClient()?->getOptions();
        self::assertNotNull($options);

        return $options;
    }
}
