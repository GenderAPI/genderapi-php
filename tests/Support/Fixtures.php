<?php

declare(strict_types=1);

namespace GenderApi\Tests\Support;

/**
 * Example payloads copied from openapi-v2.json so the SDK is tested against the published contract.
 */
final class Fixtures
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $examples = null;

    /** @return array<string, mixed> */
    public static function get(string $key): array
    {
        if (self::$examples === null) {
            $json = file_get_contents(__DIR__ . '/../fixtures/openapi-v2-examples.json');
            self::$examples = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR)['examples'];
        }
        if (!isset(self::$examples[$key])) {
            throw new \OutOfBoundsException('Unknown fixture ' . $key);
        }

        return self::$examples[$key];
    }

    /**
     * A published success example as a request with a recognised API key sees it
     * (the published examples show meta.access.mode "ip_trial").
     *
     * @return array<string, mixed>
     */
    public static function keyed(string $key): array
    {
        $body = self::get($key);
        $body['meta']['access'] = ['mode' => 'api_key', 'reason' => null];

        return $body;
    }

    /**
     * A Problem body shaped like the API's (for statuses without a published example).
     *
     * @param array<string, mixed> $usage
     * @return array<string, mixed>
     */
    public static function problem(int $status, string $code, string $action, string $detail, array $usage): array
    {
        return [
            'type' => 'urn:genderapi:problem:' . $code,
            'title' => str_replace('_', ' ', $code),
            'status' => $status,
            'detail' => $detail,
            'instance' => 'urn:uuid:22222222-2222-4222-8222-222222222222',
            'code' => $code,
            'request_id' => '22222222-2222-4222-8222-222222222222',
            'documentation' => 'https://api.genderapi.io/api/v2/errors',
            'action' => $action,
            'meta' => [
                'request_id' => '22222222-2222-4222-8222-222222222222',
                'duration_ms' => 3,
                'access' => ['mode' => 'api_key', 'reason' => null],
                'usage' => $usage + ['resets_at' => null, 'limit' => null, 'period_seconds' => null],
            ],
        ];
    }
}
