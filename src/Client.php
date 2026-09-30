<?php

declare(strict_types=1);

namespace GenderApi;

use GenderApi\Exception\ApiException;
use GenderApi\Exception\GenderApiException;
use GenderApi\Exception\RedirectException;
use GenderApi\Exception\UnexpectedAccessModeException;
use GenderApi\Exception\ValidationException;
use GenderApi\Http\CurlTransport;
use GenderApi\Http\HttpRequest;
use GenderApi\Http\HttpResponse;
use GenderApi\Http\StreamTransport;
use GenderApi\Http\Transport;
use GenderApi\Internal\Json;
use GenderApi\Internal\ResponseShape;
use GenderApi\Internal\Validator;
use GenderApi\Response\BatchResult;
use GenderApi\Response\GenderResult;
use GenderApi\Response\PhoneResult;
use GenderApi\Response\UsageResult;

/**
 * Official GenderAPI.io V2 client for PHP. Server-side use only: never expose your API key to browsers.
 *
 * Every prediction or phone call is exactly one HTTP attempt. The client never retries (a lost
 * response can still have been billed) and never follows redirects. Constructing it makes no request.
 */
final class Client
{
    public const VERSION = '2.0.0';
    public const DEFAULT_BASE_URL = 'https://api.genderapi.io/api/v2';
    public const DEFAULT_TIMEOUT = 10.0;
    public const MAX_BATCH_ITEMS = Validator::MAX_BATCH_ITEMS;
    public const API_KEY_ENV = 'GENDERAPI_API_KEY';

    private readonly ?string $apiKey;
    private readonly string $baseUrl;
    private readonly float $timeout;
    private readonly Transport $transport;
    private readonly bool $requireApiKeyAccess;

    /**
     * @param string|null    $apiKey    your API key; null reads GENDERAPI_API_KEY; '' or no key uses the shared
     *                                  IP trial (10 credits / 24 h, decided by the server)
     * @param string         $baseUrl   HTTPS API base; plain http is accepted only for localhost/127.0.0.1/[::1] test servers
     * @param float          $timeout   total time limit per request in seconds (default 10)
     * @param Transport|null $transport custom HTTP transport; defaults to cURL, or PHP streams without ext-curl
     * @param bool           $requireApiKeyAccess when a key is configured (default true), throw
     *                                  UnexpectedAccessModeException if a successful response reports an access
     *                                  mode other than "api_key" (e.g. an unrecognised key served as IP trial).
     *                                  Has no effect without a key or on capabilities()/errorCatalog()
     *
     * @throws ValidationException for an invalid base URL, timeout or key format
     */
    public function __construct(
        #[\SensitiveParameter] ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = self::DEFAULT_TIMEOUT,
        ?Transport $transport = null,
        bool $requireApiKeyAccess = true,
    ) {
        $this->apiKey = self::resolveApiKey($apiKey);
        $this->baseUrl = self::checkBaseUrl($baseUrl);
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new ValidationException('timeout must be a positive number of seconds.');
        }
        $this->timeout = $timeout;
        $this->transport = $transport ?? (\extension_loaded('curl') ? new CurlTransport() : new StreamTransport());
        $this->requireApiKeyAccess = $requireApiKeyAccess;
    }

    /**
     * Predict gender for one name, email or username (POST /gender).
     *
     * Costs 1 credit with ai_mode fallback (the default) or off; 2 with "always"; with forceToGenderize,
     * 1 for a dataset result or 2 when nickname-aware AI is used. Unknown results are billable.
     *
     * @param string      $type    "name", "email" or "username"
     * @param string|null $country uppercase ISO alpha-2 code; omit when unknown
     * @param string|null $aiMode  "off", "fallback" or "always"; null lets the server default to fallback
     *
     * @throws GenderApiException
     */
    public function gender(
        string $type,
        string $value,
        ?string $country = null,
        ?string $aiMode = null,
        bool $forceToGenderize = false,
        ?string $id = null,
    ): GenderResult {
        $item = Item::of($type, $value, $country, $aiMode, $forceToGenderize, $id);
        [$body, $response] = $this->send('POST', '/gender', $item->toArray());

        return $this->checkAccess(GenderResult::fromResponse($body, $response), $response);
    }

    /** @throws GenderApiException */
    public function name(string $value, ?string $country = null, ?string $aiMode = null, bool $forceToGenderize = false, ?string $id = null): GenderResult
    {
        return $this->gender('name', $value, $country, $aiMode, $forceToGenderize, $id);
    }

    /** @throws GenderApiException */
    public function email(string $value, ?string $country = null, ?string $aiMode = null, bool $forceToGenderize = false, ?string $id = null): GenderResult
    {
        return $this->gender('email', $value, $country, $aiMode, $forceToGenderize, $id);
    }

    /** @throws GenderApiException */
    public function username(string $value, ?string $country = null, ?string $aiMode = null, bool $forceToGenderize = false, ?string $id = null): GenderResult
    {
        return $this->gender('username', $value, $country, $aiMode, $forceToGenderize, $id);
    }

    /**
     * Predict up to 50 items in one request (POST /gender/batch; the IP trial allows 10, enforced by the server).
     *
     * Batch items default to ai_mode off. A partially failed batch returns normally: inspect
     * BatchResult::failed(). When every executed item fails, an ApiException is thrown whose
     * batchItems holds the rows.
     *
     * @param list<Item|array<string, mixed>> $items Item objects or wire-format arrays
     *
     * @throws GenderApiException
     */
    public function genderBatch(array $items): BatchResult
    {
        if (!array_is_list($items)) {
            throw new ValidationException('items must be a list (sequential array).', '/items');
        }
        $wire = [];
        foreach ($items as $index => $item) {
            if ($item instanceof Item) {
                $wire[] = $item->toArray();
            } elseif (is_array($item)) {
                $wire[] = Validator::item($item, '/items/' . $index);
            } else {
                throw new ValidationException('Each batch item must be a GenderApi\Item or an array.', '/items/' . $index);
            }
        }
        Validator::batch($wire);

        [$body, $response] = $this->send('POST', '/gender/batch', ['items' => $wire]);

        return $this->checkAccess(BatchResult::fromResponse($body, $response), $response);
    }

    /**
     * Read the current balance (GET /usage). Free; without a key it reports the IP-trial allowance.
     *
     * @throws GenderApiException
     */
    public function usage(): UsageResult
    {
        [$body, $response] = $this->send('GET', '/usage', null);

        return $this->checkAccess(UsageResult::fromResponse($body, $response), $response);
    }

    /**
     * Validate and format a phone number (POST /phone/validate). A completed validation costs 1 credit,
     * even when the number is invalid. country is required unless the number starts with "+".
     *
     * @throws GenderApiException
     */
    public function validatePhone(string $number, ?string $country = null): PhoneResult
    {
        $payload = Validator::phone($number, $country);
        [$body, $response] = $this->send('POST', '/phone/validate', $payload);

        return $this->checkAccess(PhoneResult::fromResponse($body, $response), $response);
    }

    /**
     * Public service description (GET /). No API key is sent.
     *
     * @return array<string, mixed>
     *
     * @throws GenderApiException
     */
    public function capabilities(): array
    {
        return $this->send('GET', '', null, false)[0];
    }

    /**
     * Public error catalog (GET /errors): codes, HTTP statuses and actions. No API key is sent.
     *
     * @return array<string, mixed>
     *
     * @throws GenderApiException
     */
    public function errorCatalog(): array
    {
        return $this->send('GET', '/errors', null, false)[0];
    }

    /** Whether a key is configured. When false, requests use the shared IP trial. */
    public function hasApiKey(): bool
    {
        return $this->apiKey !== null;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    /** Whether successful keyed responses must report meta.access.mode "api_key" (see the constructor). */
    public function requiresApiKeyAccess(): bool
    {
        return $this->requireApiKeyAccess;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'baseUrl' => $this->baseUrl,
            'timeout' => $this->timeout,
            'hasApiKey' => $this->hasApiKey(),
            'requireApiKeyAccess' => $this->requireApiKeyAccess,
        ];
    }

    public function __serialize(): array
    {
        throw new \LogicException('GenderApi\Client cannot be serialized because it holds an API key.');
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{0: array<string, mixed>, 1: HttpResponse}
     */
    private function send(string $method, string $path, ?array $payload, bool $authenticated = true): array
    {
        $headers = [
            'Accept' => 'application/json, application/problem+json',
            'User-Agent' => sprintf('genderapi-php/%s (PHP %s)', self::VERSION, PHP_VERSION),
        ];
        if ($authenticated && $this->apiKey !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        $body = null;
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            try {
                $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (\JsonException $e) {
                throw new ValidationException('The request could not be encoded as JSON: ' . $e->getMessage());
            }
        }

        // Exactly one attempt: no retry on transport errors, timeouts, 429 or 5xx.
        $response = $this->transport->send(new HttpRequest($method, $this->baseUrl . $path, $headers, $body, $this->timeout));

        $decoded = Json::decodeObject($response->body);

        if ($response->status >= 300 && $response->status < 400) {
            throw new RedirectException(
                $response->status,
                $response->header('location'),
                ResponseShape::requestId($decoded, $response->headers),
                $response->headers,
                $response->body,
            );
        }
        if ($response->status >= 400) {
            throw ApiException::fromResponse($response->status, $decoded, $response->body, $response->headers);
        }
        if ($response->status < 200) {
            throw ResponseShape::unexpected(sprintf('Unexpected HTTP status %d.', $response->status), $decoded, $response);
        }
        if ($decoded === null) {
            throw ResponseShape::unexpected('Expected a JSON object response.', null, $response);
        }

        return [$decoded, $response];
    }

    /**
     * With a key configured and requireApiKeyAccess on, a response whose meta.access.mode is present and not
     * "api_key" is rejected. The request has already been processed (no retry is made); the result is kept on
     * the exception. A missing access object or mode is accepted.
     *
     * @template T of GenderResult|BatchResult|UsageResult|PhoneResult
     * @param T $result
     * @return T
     *
     * @throws UnexpectedAccessModeException
     */
    private function checkAccess(GenderResult|BatchResult|UsageResult|PhoneResult $result, HttpResponse $response): GenderResult|BatchResult|UsageResult|PhoneResult
    {
        if ($this->apiKey === null || !$this->requireApiKeyAccess) {
            return $result;
        }
        $mode = $result->meta->access?->mode;
        if ($mode === null || $mode === 'api_key') {
            return $result;
        }

        throw new UnexpectedAccessModeException($result, $response->status, $response->headers, $response->body);
    }

    private static function resolveApiKey(?string $apiKey): ?string
    {
        if ($apiKey === null) {
            $env = getenv(self::API_KEY_ENV);
            if (!is_string($env) || $env === '') {
                $env = $_ENV[self::API_KEY_ENV] ?? $_SERVER[self::API_KEY_ENV] ?? null;
            }
            $apiKey = is_string($env) ? $env : null;
        }
        if ($apiKey === null) {
            return null;
        }
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return null;
        }
        // Visible ASCII only: prevents header injection. The value itself is never included in messages.
        if (preg_match('/^[\x21-\x7E]+$/D', $apiKey) !== 1) {
            throw new ValidationException('The API key contains invalid characters (whitespace or non-ASCII).');
        }

        return $apiKey;
    }

    private static function checkBaseUrl(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        if ($parts === false) {
            $parts = [];
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (
            $host === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        ) {
            throw new ValidationException('baseUrl must be an absolute URL without credentials, query or fragment.');
        }
        $loopback = in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $loopback)) {
            throw new ValidationException('baseUrl must use https (plain http is allowed only for localhost/127.0.0.1/[::1] test servers).');
        }

        return rtrim($baseUrl, '/');
    }
}
