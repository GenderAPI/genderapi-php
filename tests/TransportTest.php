<?php

declare(strict_types=1);

namespace GenderApi\Tests;

use GenderApi\Client;
use GenderApi\Exception\RateLimitException;
use GenderApi\Exception\RedirectException;
use GenderApi\Exception\ServerException;
use GenderApi\Exception\TimeoutException;
use GenderApi\Exception\TransportException;
use GenderApi\Http\CurlTransport;
use GenderApi\Http\StreamTransport;
use GenderApi\Http\Transport;
use GenderApi\Tests\Support\StubServer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests of both real transports against a local stub server on 127.0.0.1.
 * No request leaves the machine and no credits are used.
 */
final class TransportTest extends TestCase
{
    private const KEY = 'fedcba9876543210fedcba98';

    private static ?StubServer $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$server = StubServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
    }

    /** @return iterable<string, array{class-string<Transport>}> */
    public static function transports(): iterable
    {
        if (\extension_loaded('curl')) {
            yield 'curl' => [CurlTransport::class];
        }
        yield 'stream' => [StreamTransport::class];
    }

    /** @param class-string<Transport> $transport */
    private function client(string $scenario, string $transport, float $timeout = 5.0): Client
    {
        return new Client(self::KEY, self::server()->url($scenario), $timeout, new $transport());
    }

    private static function server(): StubServer
    {
        return self::$server ?? throw new \LogicException('Stub server not started');
    }

    /** @param class-string<Transport> $transport */
    #[DataProvider('transports')]
    public function testPostSendsExpectedHeadersAndBody(string $transport): void
    {
        $result = $this->client('echo', $transport)->name('Ayşe', 'TR', 'off', false, 'r-1');
        $echo = $result->raw['echo'];

        self::assertSame('POST', $echo['method']);
        self::assertSame('/echo/api/v2/gender', $echo['uri']);
        self::assertStringNotContainsString(self::KEY, $echo['uri']);
        self::assertSame('Bearer ' . self::KEY, $echo['headers']['authorization']);
        self::assertSame('application/json', $echo['headers']['content-type']);
        self::assertStringContainsString('application/json', $echo['headers']['accept']);
        self::assertStringStartsWith('genderapi-php/2.0.0', $echo['headers']['user-agent']);
        self::assertSame(
            ['id' => 'r-1', 'type' => 'name', 'value' => 'Ayşe', 'country' => 'TR', 'options' => ['ai_mode' => 'off']],
            json_decode($echo['body'], true),
        );
        self::assertSame('male', $result->data->gender);
        self::assertSame('stub-request-id', $result->headers['x-request-id']);
    }

    /** @param class-string<Transport> $transport */
    #[DataProvider('transports')]
    public function testGetUsageSendsNoBody(string $transport): void
    {
        $usage = $this->client('echo', $transport)->usage();
        self::assertSame('GET', $usage->raw['echo']['method']);
        self::assertSame('/echo/api/v2/usage', $usage->raw['echo']['uri']);
        self::assertSame('', $usage->raw['echo']['body']);
        self::assertSame('Bearer ' . self::KEY, $usage->raw['echo']['headers']['authorization']);
    }

    /** @param class-string<Transport> $transport */
    #[DataProvider('transports')]
    public function testPublicEndpointsSendNoKey(string $transport): void
    {
        $body = $this->client('echo', $transport)->capabilities();
        self::assertSame('/echo/api/v2', $body['echo']['uri']);
        self::assertArrayNotHasKey('authorization', $body['echo']['headers']);
    }

    /** @param class-string<Transport> $transport */
    #[DataProvider('transports')]
    public function testRedirectIsNotFollowed(string $transport): void
    {
        $before = self::server()->hits('target');
        try {
            $this->client('redirect', $transport)->name('Onur');
            self::fail('Expected RedirectException');
        } catch (RedirectException $e) {
            self::assertSame(302, $e->status);
            self::assertStringEndsWith('/target/api/v2/gender', (string) $e->location);
            self::assertSame('stub-request-id', $e->requestId);
        }
        self::assertSame($before, self::server()->hits('target'), 'redirect target must not be requested');
    }

    /** @param class-string<Transport> $transport */
    #[DataProvider('transports')]
    public function testTimeout(string $transport): void
    {
        $before = self::server()->hits('slow');
        $start = microtime(true);
        try {
            $this->client('slow', $transport, 0.5)->name('Onur');
            self::fail('Expected TimeoutException');
        } catch (TimeoutException $e) {
            self::assertStringContainsString('do not retry', $e->getMessage());
        }
        self::assertLessThan(1.8, microtime(true) - $start);
        self::assertSame($before + 1, self::server()->hits('slow'), 'exactly one attempt');
    }

    /** @param class-string<Transport> $transport */
    #[DataProvider('transports')]
    public function testRateLimitIsSurfacedNotRetried(string $transport): void
    {
        $before = self::server()->hits('ratelimit');
        try {
            $this->client('ratelimit', $transport)->genderBatch([['type' => 'name', 'value' => 'Onur']]);
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(7, $e->retryAfter);
            self::assertSame('rate_limit_exceeded', $e->errorCode);
            self::assertSame('33333333-3333-4333-8333-333333333333', $e->requestId);
        }
        self::assertSame($before + 1, self::server()->hits('ratelimit'));
    }

    /** @param class-string<Transport> $transport */
    #[DataProvider('transports')]
    public function testNonJsonProxyError(string $transport): void
    {
        try {
            $this->client('html502', $transport)->name('Onur');
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            self::assertSame(502, $e->status);
            self::assertNull($e->errorCode);
            self::assertStringContainsString('Bad Gateway', $e->rawBody);
        }
    }

    /** @param class-string<Transport> $transport */
    #[DataProvider('transports')]
    public function testConnectionRefused(string $transport): void
    {
        $client = new Client(self::KEY, 'http://127.0.0.1:' . StubServer::freePort() . '/api/v2', 2.0, new $transport());
        $this->expectException(TransportException::class);
        $client->name('Onur');
    }
}
