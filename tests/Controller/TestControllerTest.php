<?php

declare(strict_types=1);

namespace Lingoda\SentryBundle\Tests\Controller;

use Lingoda\SentryBundle\Sentry\Handler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TestControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
    }

    public function testError(): void
    {
        $client = static::createClient();
        $testHandler = self::getContainer()->get(Handler::class);

        $client->request('GET', '/', server: ['HTTP_x-release-id' => '1234']);

        $records = $testHandler->getRecords();
        self::assertCount(2, $records);
        $record = $records[1];
        self::assertSame('1234', $record['extra']['release']);
    }
}
