<?php

declare(strict_types=1);

namespace GenderApi\Tests;

use GenderApi\Client;
use GenderApi\Exception\ApiException;
use GenderApi\Exception\AuthenticationException;
use GenderApi\Exception\ForbiddenException;
use GenderApi\Exception\GenderApiException;
use GenderApi\Exception\InsufficientCreditsException;
use GenderApi\Exception\InvalidRequestException;
use GenderApi\Exception\RateLimitException;
use GenderApi\Exception\RedirectException;
use GenderApi\Exception\ServerException;
use GenderApi\Exception\TimeoutException;
use GenderApi\Exception\TransportException;
use GenderApi\Exception\UnexpectedAccessModeException;
use GenderApi\Exception\UnexpectedResponseException;
use GenderApi\Exception\ValidationException;
use GenderApi\Http\HttpResponse;
use GenderApi\Internal\ResponseShape;
use GenderApi\Item;
use GenderApi\Response\BatchItemResult;
use GenderApi\Response\BatchResult;
use GenderApi\Response\GenderResult;
use GenderApi\Response\PhoneResult;
use GenderApi\Response\UsageResult;
use GenderApi\Tests\Support\Fixtures;
use GenderApi\Tests\Support\MockTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private const KEY = '0123456789abcdef01234567';

    protected function setUp(): void
    {
        putenv(Client::API_KEY_ENV);
        unset($_ENV[Client::API_KEY_ENV], $_SERVER[Client::API_KEY_ENV]);
    }

    private function client(MockTransport $transport, ?string $key = self::KEY): Client
    {
        return new Client($key, transport: $transport);
    }

    // ---- construction ----------------------------------------------------------------------

    public function testConstructingMakesNoRequest(): void
    {
        $transport = new MockTransport();
        new Client(self::KEY, transport: $transport);
        new Client(null, transport: $transport);
        new Client();
        self::assertSame([], $transport->requests);
    }

    public function testDefaults(): void
    {
        $client = new Client(self::KEY, transport: new MockTransport());
        self::assertSame('https://api.genderapi.io/api/v2', $client->baseUrl());
        self::assertSame(10.0, $client->timeout());
        self::assertTrue($client->hasApiKey());
        self::assertSame('2.0.0', Client::VERSION);
    }

    public function testApiKeyFromEnvironment(): void
    {
        putenv(Client::API_KEY_ENV . '=' . self::KEY);
        $transport = new MockTransport(MockTransport::json(200, Fixtures::keyed('POST /api/v2/gender 200 dataset')));
        $client = new Client(transport: $transport);
        self::assertTrue($client->hasApiKey());
        $client->name('Onur');
        self::assertSame('Bearer ' . self::KEY, $transport->lastRequest()->headers['Authorization']);
    }

    public function testWithoutKeyNoAuthorizationHeaderIsSent(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::get('POST /api/v2/gender 200 dataset')));
        $client = new Client(null, transport: $transport);
        self::assertFalse($client->hasApiKey());
        $result = $client->name('Onur', 'TR');
        self::assertArrayNotHasKey('Authorization', $transport->lastRequest()->headers);
        self::assertSame('ip_trial', $result->meta->access?->mode);
        self::assertSame('api_key_missing', $result->meta->access?->reason);
    }

    public function testEmptyStringKeyMeansNoKeyEvenWithEnvironment(): void
    {
        putenv(Client::API_KEY_ENV . '=' . self::KEY);
        self::assertFalse((new Client('', transport: new MockTransport()))->hasApiKey());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidBaseUrls(): iterable
    {
        yield 'plain http remote' => ['http://api.genderapi.io/api/v2'];
        yield 'ftp' => ['ftp://127.0.0.1/api/v2'];
        yield 'relative' => ['/api/v2'];
        yield 'credentials' => ['https://user:pass@api.genderapi.io/api/v2'];
        yield 'query' => ['https://api.genderapi.io/api/v2?key=x'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidBaseUrls')]
    public function testRejectsUnsafeBaseUrl(string $url): void
    {
        $this->expectException(ValidationException::class);
        new Client(self::KEY, $url, transport: new MockTransport());
    }

    public function testAllowsLoopbackHttpForTests(): void
    {
        foreach (['http://127.0.0.1:8080/api/v2/', 'http://localhost/api/v2', 'http://[::1]:9000/api/v2'] as $url) {
            $client = new Client(self::KEY, $url, transport: new MockTransport());
            self::assertSame(rtrim($url, '/'), $client->baseUrl());
        }
    }

    public function testRejectsInvalidTimeout(): void
    {
        $this->expectException(ValidationException::class);
        new Client(self::KEY, timeout: 0, transport: new MockTransport());
    }

    public function testRejectsKeyWithControlCharactersWithoutEchoingIt(): void
    {
        try {
            new Client("abc\r\nX-Evil: 1", transport: new MockTransport());
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertStringNotContainsString('X-Evil', $e->getMessage());
        }
    }

    public function testKeyIsNotExposedByDebugOutputOrSerialization(): void
    {
        $client = new Client(self::KEY, transport: new MockTransport());
        self::assertStringNotContainsString(self::KEY, print_r($client, true));
        ob_start();
        var_dump($client);
        self::assertStringNotContainsString(self::KEY, (string) ob_get_clean());
        $this->expectException(\LogicException::class);
        serialize($client);
    }

    // ---- request format --------------------------------------------------------------------

    public function testRequestHeadersAndUrl(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::keyed('POST /api/v2/gender 200 dataset')));
        $this->client($transport)->name('Onur', 'TR');
        $request = $transport->lastRequest();

        self::assertSame('POST', $request->method);
        self::assertSame('https://api.genderapi.io/api/v2/gender', $request->url);
        self::assertStringNotContainsString(self::KEY, $request->url);
        self::assertStringNotContainsString(self::KEY, (string) $request->body);
        self::assertSame('Bearer ' . self::KEY, $request->headers['Authorization']);
        self::assertSame('application/json', $request->headers['Content-Type']);
        self::assertStringContainsString('application/json', $request->headers['Accept']);
        self::assertStringStartsWith('genderapi-php/2.0.0', $request->headers['User-Agent']);
        self::assertSame(10.0, $request->timeout);
        self::assertStringNotContainsString(self::KEY, print_r($request, true));
    }

    public function testMinimalSingleBodyUsesServerDefaults(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::keyed('POST /api/v2/gender 200 dataset')));
        $this->client($transport)->name('Onur');
        self::assertSame('{"type":"name","value":"Onur"}', $transport->lastRequest()->body);
    }

    public function testFullSingleBodyUsesExactWireNames(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::keyed('POST /api/v2/gender 200 dataset')));
        $this->client($transport)->gender('email', 'ayşe@example.com', 'TR', 'always', false, 'row-1');
        self::assertSame(
            '{"id":"row-1","type":"email","value":"ayşe@example.com","country":"TR","options":{"ai_mode":"always"}}',
            $transport->lastRequest()->body,
        );
    }

    public function testForceToGenderizeBody(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::keyed('POST /api/v2/gender 200 alias')));
        $this->client($transport)->username('prenses', 'TR', forceToGenderize: true);
        self::assertSame(
            ['type' => 'username', 'value' => 'prenses', 'country' => 'TR', 'forceToGenderize' => true],
            $transport->lastJsonBody(),
        );
    }

    public function testBatchBody(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::keyed('POST /api/v2/gender/batch 200 batch')));
        $this->client($transport)->genderBatch([
            Item::name('Onur', 'TR', id: 'known'),
            ['id' => 'missing', 'type' => 'name', 'value' => 'zzzxxyy', 'country' => null, 'options' => ['ai_mode' => 'off']],
            ['id' => 'failed', 'type' => 'username', 'value' => 'prenses', 'forceToGenderize' => true],
        ]);
        $request = $transport->lastRequest();
        self::assertSame('https://api.genderapi.io/api/v2/gender/batch', $request->url);
        self::assertSame([
            'items' => [
                ['id' => 'known', 'type' => 'name', 'value' => 'Onur', 'country' => 'TR'],
                ['id' => 'missing', 'type' => 'name', 'value' => 'zzzxxyy', 'options' => ['ai_mode' => 'off']],
                ['id' => 'failed', 'type' => 'username', 'value' => 'prenses', 'forceToGenderize' => true],
            ],
        ], $transport->lastJsonBody());
    }

    // ---- successful responses --------------------------------------------------------------

    public function testSingleDatasetSuccess(): void
    {
        $fixture = Fixtures::keyed('POST /api/v2/gender 200 dataset');
        $result = $this->client(new MockTransport(MockTransport::json(200, $fixture)))->name('Onur', 'TR');

        $d = $result->data;
        self::assertSame('male', $d->gender);
        self::assertSame('identified', $d->resultStatus);
        self::assertTrue($d->isIdentified());
        self::assertNull($d->reason);
        self::assertSame('onur', $d->name);
        self::assertSame('TR', $d->country);
        self::assertSame(0.9, $d->confidence); // 0-1 scale, never a percentage
        self::assertSame('observed_frequency', $d->confidenceKind);
        self::assertSame(100, $d->sampleCount);
        self::assertSame('dataset', $d->source);
        self::assertSame('dataset', $d->countrySource);
        self::assertSame(['type' => 'name', 'value' => 'Onur', 'country' => 'TR'], $d->input);
        self::assertSame('onur', $d->match?->name);
        self::assertSame('normalized', $d->match?->method);
        self::assertSame('country', $d->match?->scope);
        self::assertSame('TR', $d->match?->country);

        $m = $result->meta;
        self::assertSame('11111111-1111-4111-8111-111111111111', $m->requestId);
        self::assertSame(12, $m->durationMs);
        self::assertSame('confirmed', $m->usage?->billingStatus);
        self::assertTrue($m->usage?->isConfirmed());
        self::assertSame(1, $m->usage?->chargedCredits);
        self::assertSame(9, $m->usage?->remainingCredits);
        self::assertSame('2026-09-26T12:00:00.000Z', $m->usage?->resetsAt);
        self::assertSame(10, $m->usage?->limit);
        self::assertSame(86400, $m->usage?->periodSeconds);
        self::assertTrue($m->access?->isApiKey());
        self::assertNull($m->access?->reason);
        self::assertSame($fixture, $result->toArray());
    }

    public function testSingleAiSuccessWithNegativeBalance(): void
    {
        $result = $this->client(new MockTransport(MockTransport::json(200, Fixtures::keyed('POST /api/v2/gender 200 alias'))))
            ->username('prenses', 'TR', forceToGenderize: true);

        self::assertSame('female', $result->data->gender);
        self::assertNull($result->data->name);
        self::assertSame('ai', $result->data->source);
        self::assertSame(0.7, $result->data->confidence);
        self::assertSame('model_reported', $result->data->confidenceKind);
        self::assertNull($result->data->sampleCount);
        self::assertSame('ai_association', $result->data->countrySource);
        self::assertSame('model_inference', $result->data->match?->method);
        self::assertTrue($result->data->input['forceToGenderize']);
        self::assertSame(2, $result->meta->usage?->chargedCredits);
        self::assertSame(-1, $result->meta->usage?->remainingCredits);
    }

    public function testSingleUnknownIsASuccessfulBillableResult(): void
    {
        $result = $this->client(new MockTransport(MockTransport::json(200, Fixtures::keyed('POST /api/v2/gender 200 unknown'))))
            ->name('zzzxxyy');

        self::assertNull($result->data->gender);
        self::assertTrue($result->data->isUnknown());
        self::assertSame('unknown', $result->data->resultStatus);
        self::assertSame('not_found', $result->data->reason);
        self::assertNull($result->data->confidence);
        self::assertNull($result->data->confidenceKind);
        self::assertSame('none', $result->data->source);
        self::assertNull($result->data->match?->method);
        self::assertSame(1, $result->meta->usage?->chargedCredits);
    }

    public function testUnknownFieldsAreToleratedAndPreserved(): void
    {
        $body = Fixtures::get('POST /api/v2/gender 200 dataset');
        $body['future_top_level'] = ['x' => 1];
        $body['data']['future_field'] = 'value';
        $body['data']['match']['future'] = true;
        $body['meta']['usage']['future_counter'] = 3;
        $body['meta']['future_meta'] = 'm';
        $body['meta']['access']['mode'] = 'some_future_mode';

        // Opt out of the access-mode check so the unknown mode is surfaced as data.
        $client = new Client(self::KEY, transport: new MockTransport(MockTransport::json(200, $body)), requireApiKeyAccess: false);
        $result = $client->name('Onur');

        self::assertSame('male', $result->data->gender);
        self::assertSame('value', $result->data->raw['future_field']);
        self::assertTrue($result->data->match?->raw['future']);
        self::assertSame(3, $result->meta->usage?->raw['future_counter']);
        self::assertSame('m', $result->meta->raw['future_meta']);
        self::assertSame('some_future_mode', $result->meta->access?->mode);
        self::assertSame(['x' => 1], $result->raw['future_top_level']);
    }

    public function testBatchPartialSuccessIsNotAnException(): void
    {
        $fixture = Fixtures::keyed('POST /api/v2/gender/batch 200 batch');
        $result = $this->client(new MockTransport(MockTransport::json(200, $fixture)))->genderBatch([
            Item::name('Onur', 'TR', id: 'known'),
            Item::name('zzzxxyy', id: 'missing'),
            Item::name('Alex', aiMode: 'always', id: 'failed'),
        ]);

        self::assertCount(3, $result);
        self::assertTrue($result->hasFailures());
        self::assertCount(2, $result->succeeded());
        [$failed] = $result->failed();
        self::assertSame(2, $failed->index);
        self::assertSame('failed', $failed->id);
        self::assertSame(0, $failed->chargedCredits);
        self::assertNull($failed->data);
        self::assertSame('ai_upstream_error', $failed->error?->code);
        self::assertSame(502, $failed->error?->status);
        self::assertSame('inspect_billing_before_retry', $failed->error?->action);

        $known = $result->items[0];
        self::assertSame('known', $known->id);
        self::assertSame(1, $known->chargedCredits);
        self::assertSame('male', $known->data?->gender);
        self::assertNull($known->error);
        self::assertTrue($result->items[1]->data?->isUnknown());

        $s = $result->summary();
        self::assertSame([3, 2, 1, 1, 1], [$s?->total, $s?->succeeded, $s?->identified, $s?->unknown, $s?->failed]);
        self::assertSame(2, $result->meta->usage?->chargedCredits);
        self::assertSame(['known', 'missing', 'failed'], array_map(static fn (BatchItemResult $i) => $i->id, iterator_to_array($result)));
        self::assertSame($fixture, $result->toArray());
    }

    public function testUsage(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::keyed('GET /api/v2/usage 200 usage')));
        $usage = $this->client($transport)->usage();

        $request = $transport->lastRequest();
        self::assertSame('GET', $request->method);
        self::assertSame('https://api.genderapi.io/api/v2/usage', $request->url);
        self::assertNull($request->body);
        self::assertArrayNotHasKey('Content-Type', $request->headers);
        self::assertSame('Bearer ' . self::KEY, $request->headers['Authorization']);

        self::assertSame(9, $usage->remainingCredits);
        self::assertSame('2026-09-26T12:00:00.000Z', $usage->expiresAt);
        self::assertSame('2026-09-26T12:00:00.000Z', $usage->resetsAt);
        self::assertSame(10, $usage->limit);
        self::assertSame(86400, $usage->periodSeconds);
        self::assertTrue($usage->meta->usage?->isNotCharged());
        self::assertSame(0, $usage->meta->usage?->chargedCredits);
    }

    public function testValidatePhone(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::keyed('POST /api/v2/phone/validate 200 phone')));
        $phone = $this->client($transport)->validatePhone('0555 000 00 00', 'TR');

        self::assertSame('https://api.genderapi.io/api/v2/phone/validate', $transport->lastRequest()->url);
        self::assertSame(['number' => '0555 000 00 00', 'country' => 'TR'], $transport->lastJsonBody());
        self::assertFalse($phone->valid);
        self::assertFalse($phone->possible);
        self::assertNull($phone->e164);
        self::assertNull($phone->countryCallingCode);
        self::assertSame(1, $phone->meta->usage?->chargedCredits); // billed even when invalid
    }

    public function testCapabilitiesAndErrorCatalogSendNoKey(): void
    {
        $catalog = json_decode((string) file_get_contents(__DIR__ . '/fixtures/error-catalog.json'), true, 512, JSON_THROW_ON_ERROR);
        $transport = new MockTransport(
            MockTransport::json(200, ['version' => '2.0.0', 'capabilities' => ['max_batch_items' => 50]]),
            MockTransport::json(200, $catalog),
        );
        $client = $this->client($transport);

        self::assertSame(50, $client->capabilities()['capabilities']['max_batch_items']);
        self::assertSame('https://api.genderapi.io/api/v2', $transport->requests[0]->url);
        self::assertSame('GET', $transport->requests[0]->method);
        self::assertArrayNotHasKey('Authorization', $transport->requests[0]->headers);

        self::assertSame('wait_then_retry', $client->errorCatalog()['rate_limit_exceeded']['action']);
        self::assertSame('https://api.genderapi.io/api/v2/errors', $transport->requests[1]->url);
        self::assertArrayNotHasKey('Authorization', $transport->requests[1]->headers);
    }

    // ---- access-mode check -----------------------------------------------------------------

    public function testKeyedIpTrialResponseThrowsWithFullResult(): void
    {
        $fixture = Fixtures::get('POST /api/v2/gender 200 dataset'); // meta.access.mode "ip_trial"
        $fixture['meta']['access']['reason'] = 'api_key_invalid';
        $transport = new MockTransport(MockTransport::json(200, $fixture));
        try {
            $this->client($transport)->name('Onur', 'TR');
            self::fail('Expected UnexpectedAccessModeException');
        } catch (UnexpectedAccessModeException $e) {
            self::assertInstanceOf(GenderApiException::class, $e);
            self::assertSame('unexpected_access_mode', $e->errorCode);
            self::assertSame('unexpected_access_mode', $e->getErrorCode());
            self::assertSame('ip_trial', $e->accessMode);
            self::assertSame('ip_trial', $e->getAccessMode());
            self::assertSame('api_key_invalid', $e->getAccessReason());
            self::assertSame(200, $e->status);
            self::assertSame(200, $e->getStatus());
            self::assertSame('11111111-1111-4111-8111-111111111111', $e->getRequestId());
            self::assertSame($fixture, $e->getBody());
            self::assertNotSame('', $e->rawBody);
            $result = $e->getResult();
            self::assertInstanceOf(GenderResult::class, $result);
            self::assertSame($e->result, $result);
            self::assertSame('male', $result->data->gender);
            self::assertSame(1, $result->meta->usage?->chargedCredits);
            self::assertSame($fixture, $result->toArray());
            self::assertSame(
                'Expected API-key access but the response reports access mode "ip_trial". '
                . 'Check your API key; this request may have consumed IP-trial credits.',
                $e->getMessage(),
            );
            self::assertStringNotContainsString(self::KEY, $e->getMessage());
            self::assertStringNotContainsString(self::KEY, print_r($e->getResult(), true));
        }
        self::assertCount(1, $transport->requests, 'must not retry');
    }

    public function testIpTrialResponseWithoutKeyReturnsNormally(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::get('POST /api/v2/gender 200 dataset')));
        $result = $this->client($transport, null)->name('Onur');
        self::assertTrue($result->meta->access?->isIpTrial());
        self::assertCount(1, $transport->requests);
    }

    public function testAccessModeCheckCanBeDisabled(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::get('POST /api/v2/gender 200 dataset')));
        $client = new Client(self::KEY, transport: $transport, requireApiKeyAccess: false);
        self::assertFalse($client->requiresApiKeyAccess());
        self::assertTrue($this->client(new MockTransport())->requiresApiKeyAccess());
        $result = $client->name('Onur');
        self::assertTrue($result->meta->access?->isIpTrial());
        self::assertCount(1, $transport->requests);
    }

    public function testMissingAccessModeIsAccepted(): void
    {
        $body = Fixtures::get('POST /api/v2/gender 200 dataset');
        unset($body['meta']['access']);
        $result = $this->client(new MockTransport(MockTransport::json(200, $body)))->name('Onur');
        self::assertNull($result->meta->access);
    }

    public function testBatchWithTopLevelIpTrialThrows(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::get('POST /api/v2/gender/batch 200 batch')));
        try {
            $this->client($transport)->genderBatch([Item::name('Onur'), Item::name('zzzxxyy'), Item::name('Alex')]);
            self::fail('Expected UnexpectedAccessModeException');
        } catch (UnexpectedAccessModeException $e) {
            self::assertSame('ip_trial', $e->getAccessMode());
            self::assertInstanceOf(BatchResult::class, $e->getResult());
            self::assertCount(3, $e->getResult());
        }
        self::assertCount(1, $transport->requests);
    }

    public function testUsageAndPhoneWithIpTrialThrow(): void
    {
        $transport = new MockTransport(
            MockTransport::json(200, Fixtures::get('GET /api/v2/usage 200 usage')),
            MockTransport::json(200, Fixtures::get('POST /api/v2/phone/validate 200 phone')),
        );
        $client = $this->client($transport);
        try {
            $client->usage();
            self::fail('Expected UnexpectedAccessModeException');
        } catch (UnexpectedAccessModeException $e) {
            self::assertInstanceOf(UsageResult::class, $e->getResult());
        }
        try {
            $client->validatePhone('0555 000 00 00', 'TR');
            self::fail('Expected UnexpectedAccessModeException');
        } catch (UnexpectedAccessModeException $e) {
            self::assertInstanceOf(PhoneResult::class, $e->getResult());
        }
        self::assertCount(2, $transport->requests);
    }

    public function testPublicEndpointsAreNotAccessChecked(): void
    {
        $ipTrial = ['version' => '2.0.0', 'meta' => ['access' => ['mode' => 'ip_trial', 'reason' => 'api_key_missing']]];
        $transport = new MockTransport(MockTransport::json(200, $ipTrial), MockTransport::json(200, $ipTrial));
        $client = $this->client($transport);
        self::assertSame('2.0.0', $client->capabilities()['version']);
        self::assertSame('2.0.0', $client->errorCatalog()['version']);
    }

    // ---- errors ----------------------------------------------------------------------------

    public function testAllFailedBatchThrowsAndKeepsData(): void
    {
        $transport = new MockTransport(MockTransport::json(502, Fixtures::get('POST /api/v2/gender/batch 502 batchFailed')));
        try {
            $this->client($transport)->genderBatch([Item::name('Alex', aiMode: 'always')]);
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            self::assertSame(502, $e->status);
            self::assertSame('ai_upstream_error', $e->errorCode);
            self::assertSame('inspect_billing_before_retry', $e->action);
            self::assertSame('confirmed', $e->billingStatus);
            self::assertSame(0, $e->meta?->usage?->chargedCredits);
            self::assertSame(1, $e->meta?->summary?->failed);
            self::assertCount(1, $e->batchItems);
            self::assertCount(1, $e->failedBatchItems());
            self::assertSame('ai_upstream_error', $e->batchItems[0]->error?->code);
            self::assertSame(0, $e->batchItems[0]->chargedCredits);
            self::assertArrayHasKey('results', (array) $e->body);
        }
        self::assertCount(1, $transport->requests);
    }

    public function testAllFailedBatchFallsBackToLegacyResultsAlias(): void
    {
        $body = Fixtures::get('POST /api/v2/gender/batch 502 batchFailed');
        unset($body['data']);
        try {
            $this->client(new MockTransport(MockTransport::json(502, $body)))->genderBatch([Item::name('Alex')]);
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            self::assertCount(1, $e->batchItems);
        }
    }

    public function testUnauthorized(): void
    {
        $body = Fixtures::problem(401, 'invalid_api_key', 'check_credentials', 'The API key is not valid.', [
            'charged_credits' => 0, 'remaining_credits' => null, 'billing_status' => 'not_charged',
        ]);
        $body['meta']['access'] = ['mode' => 'unauthenticated', 'reason' => null];
        $e = $this->expectApiError(401, $body, AuthenticationException::class);
        self::assertSame('invalid_api_key', $e->errorCode);
        self::assertSame('check_credentials', $e->action);
        self::assertSame('invalid api key', $e->title);
        self::assertSame('The API key is not valid.', $e->detail);
        self::assertSame('urn:genderapi:problem:invalid_api_key', $e->type);
        self::assertSame('https://api.genderapi.io/api/v2/errors', $e->documentation);
        self::assertSame('22222222-2222-4222-8222-222222222222', $e->requestId);
        self::assertSame('unauthenticated', $e->meta?->access?->mode);
    }

    public function testInsufficientCredits(): void
    {
        $e = $this->expectApiError(403, Fixtures::get('POST /api/v2/gender 403 insufficient'), InsufficientCreditsException::class);
        self::assertInstanceOf(ForbiddenException::class, $e);
        self::assertSame('insufficient_credits', $e->errorCode);
        self::assertSame('add_credits_or_wait_for_reset', $e->action);
        self::assertSame('not_charged', $e->billingStatus);
        self::assertSame(0, $e->meta?->usage?->remainingCredits);
        self::assertSame('2026-09-26T12:00:00.000Z', $e->meta?->usage?->resetsAt);
        self::assertSame(403, $e->getCode());
        self::assertStringContainsString('insufficient_credits', $e->getMessage());
    }

    public function testOtherForbidden(): void
    {
        $body = Fixtures::problem(403, 'key_disabled', 'check_account', 'The API key is disabled.', [
            'charged_credits' => 0, 'remaining_credits' => null, 'billing_status' => 'not_charged',
        ]);
        $e = $this->expectApiError(403, $body, ForbiddenException::class);
        self::assertNotInstanceOf(InsufficientCreditsException::class, $e);
        self::assertSame('key_disabled', $e->errorCode);
    }

    public function testValidationProblemExposesPointers(): void
    {
        $e = $this->expectApiError(422, Fixtures::get('POST /api/v2/gender 422 validation'), InvalidRequestException::class);
        self::assertSame('validation_error', $e->errorCode);
        self::assertSame([['pointer' => '/value', 'message' => 'Invalid email address.']], $e->errors);
        self::assertSame('not_charged', $e->billingStatus);
    }

    public function testRateLimitSurfacesRetryAfterAndDoesNotRetry(): void
    {
        $body = Fixtures::problem(429, 'rate_limit_exceeded', 'wait_then_retry', 'Too many requests.', [
            'charged_credits' => 0, 'remaining_credits' => 40, 'billing_status' => 'not_charged',
        ]);
        $transport = new MockTransport(MockTransport::json(429, $body, ['Retry-After' => '30', 'X-RateLimit-Remaining' => '0']));
        try {
            $this->client($transport)->name('Onur');
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(429, $e->status);
            self::assertSame(30, $e->retryAfter);
            self::assertSame('rate_limit_exceeded', $e->errorCode);
            self::assertSame('0', $e->headers['x-ratelimit-remaining']);
        }
        self::assertCount(1, $transport->requests);
    }

    public function testProviderFailure502(): void
    {
        $body = Fixtures::problem(502, 'invalid_ai_response', 'inspect_billing_before_retry', 'The AI response was invalid.', [
            'charged_credits' => 0, 'remaining_credits' => 12, 'billing_status' => 'confirmed',
        ]);
        $e = $this->expectApiError(502, $body, ServerException::class);
        self::assertSame('invalid_ai_response', $e->errorCode);
        self::assertSame('confirmed', $e->billingStatus);
        self::assertFalse($e->isBillingUnconfirmed());
        self::assertSame([], $e->batchItems);
    }

    public function testUnconfirmedBilling503IsNotRetried(): void
    {
        $transport = new MockTransport(MockTransport::json(503, Fixtures::get('POST /api/v2/gender 503 unconfirmed')));
        try {
            $this->client($transport)->name('Onur');
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            self::assertSame(503, $e->status);
            self::assertSame('billing_reconciliation_required', $e->errorCode);
            self::assertSame('contact_support', $e->action);
            self::assertTrue($e->isBillingUnconfirmed());
            self::assertNull($e->meta?->usage?->chargedCredits);
            self::assertNull($e->meta?->usage?->remainingCredits);
            self::assertStringContainsString('contact support', $e->getMessage());
        }
        self::assertCount(1, $transport->requests);
    }

    public function testNonJsonErrorBodyUsesHeaderRequestId(): void
    {
        $transport = new MockTransport(new HttpResponse(502, ['Content-Type' => 'text/html', 'X-Request-ID' => 'proxy-id'], '<html>Bad Gateway</html>'));
        try {
            $this->client($transport)->name('Onur');
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            self::assertNull($e->errorCode);
            self::assertNull($e->body);
            self::assertSame('<html>Bad Gateway</html>', $e->rawBody);
            self::assertSame('proxy-id', $e->requestId);
        }
    }

    public function testRedirectIsRejected(): void
    {
        $transport = new MockTransport(new HttpResponse(301, ['Location' => 'https://elsewhere.example/api/v2/gender'], ''));
        try {
            $this->client($transport)->name('Onur');
            self::fail('Expected RedirectException');
        } catch (RedirectException $e) {
            self::assertSame(301, $e->status);
            self::assertSame('https://elsewhere.example/api/v2/gender', $e->location);
        }
        self::assertCount(1, $transport->requests);
    }

    public function testTransportFailuresAreNotRetried(): void
    {
        foreach ([new TransportException('reset'), new TimeoutException('slow')] as $failure) {
            $transport = new MockTransport($failure);
            try {
                $this->client($transport)->genderBatch([Item::name('Onur')]);
                self::fail('Expected TransportException');
            } catch (TransportException $e) {
                self::assertSame($failure, $e);
                self::assertInstanceOf(GenderApiException::class, $e);
            }
            self::assertCount(1, $transport->requests);
        }
    }

    /** @return iterable<string, array{HttpResponse}> */
    public static function unusableSuccessResponses(): iterable
    {
        yield 'html' => [new HttpResponse(200, ['Content-Type' => 'text/html'], '<html></html>')];
        yield 'invalid json' => [new HttpResponse(200, [], '{"data":')];
        yield 'json array' => [new HttpResponse(200, [], '[1,2]')];
        yield 'no data' => [MockTransport::json(200, ['meta' => ['request_id' => 'r']])];
        yield 'empty' => [new HttpResponse(200, [], '')];
    }

    #[DataProvider('unusableSuccessResponses')]
    public function testUnusableSuccessResponse(HttpResponse $response): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->client(new MockTransport($response))->name('Onur');
    }

    public function testMalformedBatchRowIsReported(): void
    {
        $body = Fixtures::get('POST /api/v2/gender/batch 200 batch');
        $body['data'][1]['error'] = ['code' => 'x']; // both data and error
        $this->expectException(UnexpectedResponseException::class);
        $this->client(new MockTransport(MockTransport::json(200, $body)))->genderBatch([Item::name('a'), Item::name('b'), Item::name('c')]);
    }

    public function testRetryAfterParsing(): void
    {
        self::assertSame(12, ResponseShape::retryAfter(' 12 '));
        self::assertNull(ResponseShape::retryAfter(null));
        self::assertNull(ResponseShape::retryAfter('soon'));
        $now = 1_790_000_000;
        $date = gmdate('D, d M Y H:i:s', $now + 90) . ' GMT';
        self::assertSame(90, ResponseShape::retryAfter($date, $now));
    }

    /**
     * @param array<string, mixed>      $body
     * @param class-string<ApiException> $class
     */
    private function expectApiError(int $status, array $body, string $class): ApiException
    {
        $transport = new MockTransport(MockTransport::json($status, $body));
        try {
            $this->client($transport)->name('Onur');
        } catch (ApiException $e) {
            self::assertInstanceOf($class, $e);
            self::assertInstanceOf(GenderApiException::class, $e);
            self::assertSame($status, $e->status);
            self::assertSame($body, $e->body);
            self::assertCount(1, $transport->requests, 'must not retry');

            return $e;
        }
        self::fail('Expected ' . $class);
    }
}
