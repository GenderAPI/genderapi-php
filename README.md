# genderapi-php

Official GenderAPI.io V2 client for PHP (`genderapi/genderapi` 2.x).

It infers a likely gender from a **name**, **email address** or **username** through the
[GenderAPI.io V2 API](https://www.genderapi.io/api-documentation), and validates phone numbers.
Results are inferences, not identity verification, and they can be **unknown**. Always handle `gender === null`.

> **Version 2.0 is a breaking release for the V2 API.** The 1.x client, which uses the V1 API, is in
> maintenance on the [`v1` branch](https://github.com/GenderAPI/genderapi-php/tree/v1). To stay on it, run
> `composer require genderapi/genderapi:^1.0`, and see the [V1 documentation](https://www.genderapi.io/api-documentation/v1).
> To upgrade, see [Migrating from 1.x](#migrating-from-1x).

## Requirements

- PHP 8.1 or newer, with `ext-json`
- `ext-curl` (recommended). Without it, the client uses PHP streams, which need `ext-openssl` for HTTPS.
- No other runtime dependencies

## Installation

```bash
composer require genderapi/genderapi:^2.0
```

## Authentication and the free IP trial

Pass your API key to the constructor, or set `GENDERAPI_API_KEY` in the server environment. The same key and
credit balance work for V1 and V2.

```php
use GenderApi\Client;

$client = new Client('YOUR_API_KEY');   // explicit key
$client = new Client();                 // reads GENDERAPI_API_KEY
```

The client also works **without a key**. The server then applies the shared IP trial: 10 credits per public IP
address per 24 hours, shared with V1. The server decides the trial, and the client adds no trial logic of its own.
A missing, malformed or unrecognised key also falls back to the trial. When you integrate a paid account, check
`$result->meta->access->mode === 'api_key'` (or call `usage()`, which is free).

**Server-side only.** Never embed your API key in browser JavaScript, mobile apps or other code you distribute.
The key is sent only in the `Authorization: Bearer` header, never in a URL. It is hidden from
`var_dump`/`print_r`, it is never included in exception messages, and a `Client` cannot be serialized.

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use GenderApi\Client;
use GenderApi\Item;

$client = new Client(); // GENDERAPI_API_KEY

// Single prediction (POST /gender)
$result = $client->name('Onur', country: 'TR');
$data = $result->data;

if ($data->isIdentified()) {
    echo $data->gender, ' ', $data->confidence, ' (', $data->confidenceKind, ")\n"; // male 0.9 (observed_frequency)
} else {
    echo 'unknown: ', $data->reason, "\n";   // e.g. not_found - still a successful, billed prediction
}
echo $result->meta->usage->chargedCredits, ' credit(s), ',
     $result->meta->usage->remainingCredits, " remaining\n";

// Batch (POST /gender/batch): 1-50 items, mixed types
$batch = $client->genderBatch([
    Item::name('Andrea', 'IT', id: 'row-1'),
    Item::email('alex@example.com', id: 'row-2'),
    Item::username('prenses', 'TR', forceToGenderize: true, id: 'row-3'),
]);
foreach ($batch as $item) {
    if ($item->isSuccess()) {
        echo $item->id, ': ', $item->data->gender ?? 'unknown', "\n";
    } else {
        echo $item->id, ': failed with ', $item->error->code, "\n";
    }
}
echo $batch->summary()->succeeded, ' of ', $batch->summary()->total, " succeeded\n";

// Balance (GET /usage) - free
$usage = $client->usage();
echo $usage->remainingCredits, "\n";
```

## API

| Method | HTTP | Returns |
| --- | --- | --- |
| `gender(string $type, string $value, ?string $country = null, ?string $aiMode = null, bool $forceToGenderize = false, ?string $id = null)` | `POST /gender` | `GenderResult` |
| `name(...)`, `email(...)`, `username(...)`: the same arguments without `$type` | `POST /gender` | `GenderResult` |
| `genderBatch(array $items)`: `Item` objects or wire-format arrays | `POST /gender/batch` | `BatchResult` |
| `usage()` (free) | `GET /usage` | `UsageResult` |
| `validatePhone(string $number, ?string $country = null)` | `POST /phone/validate` | `PhoneResult` |
| `capabilities()`: no key is sent | `GET /` | `array` |
| `errorCatalog()`: no key is sent | `GET /errors` | `array` |

All paths are relative to `https://api.genderapi.io/api/v2`.

### Request options and credit costs

- `$country`: optional uppercase ISO 3166-1 alpha-2 code, such as `"US"`. Omit it when you do not know it.
- `$aiMode`: `"off"`, `"fallback"` or `"always"`. `null` uses the server default: **fallback for single
  requests**, **off for batch items**.
- `$forceToGenderize`: checks the dataset first, then uses nickname-aware AI when the dataset has no answer.
  It works for all three types, and it cannot be combined with `off` or `always`.
- `$id`: your own reference (1-64 characters). It is echoed back and must be unique within a batch.

| Mode | Credits |
| --- | --- |
| `off` (dataset only) | 1 |
| `fallback` (dataset, then AI when needed) | 1 in total |
| `always` (AI only, skips the dataset) | 2 |
| `forceToGenderize` | 1 for a dataset result, 2 when AI is used |
| `validatePhone` | 1, also when the number is not valid |
| `usage`, `capabilities`, `errorCatalog` | free |

A successful **unknown** result is billable at the tariff of the selected mode. When a prediction fails, the
credits are refunded, unless billing cannot be confirmed (see [Billing and retries](#billing-and-retries)).
A positive starting balance is enough to start a request, so a 2-credit request on a 1-credit balance leaves `-1`.

Batch items can be built with `Item::name()`, `Item::email()`, `Item::username()` or `Item::of($type, ...)`.
Plain arrays with the exact V2 wire field names are accepted too:

```php
$client->genderBatch([
    ['id' => 'a', 'type' => 'name', 'value' => 'Andrea', 'country' => 'IT', 'options' => ['ai_mode' => 'off']],
    ['id' => 'b', 'type' => 'username', 'value' => 'prenses', 'forceToGenderize' => true],
]);
```

API-key access allows up to 50 items per batch. The IP trial allows at most 10, and the server enforces that limit.
Split larger jobs into several batches yourself.

### Client-side validation

Invalid input throws `GenderApi\Exception\ValidationException` **before any request is sent**. Its `pointer`
property names the field, for example `/items/2/value`. The client checks the following:

- the type
- the value: 1-254 characters, not only whitespace, no control characters, valid UTF-8
- the country format
- the ai_mode value
- that forceToGenderize is not combined with ai_mode `off` or `always`
- ids: 1-64 characters and unique
- batch size: 1-50 items
- unknown field names
- phone numbers: format, plus a country for national numbers

The API stays authoritative for everything else, such as email syntax and ISO country membership.

### Constructor options

```php
new Client(
    apiKey: null,                                  // null: GENDERAPI_API_KEY; '' forces "no key" (IP trial)
    baseUrl: 'https://api.genderapi.io/api/v2',    // plain http only for localhost / 127.0.0.1 / [::1] test servers
    timeout: 10.0,                                 // seconds, whole request
    transport: null,                               // GenderApi\Http\Transport; default: cURL, or streams
);
```

## Response fields

Every result object keeps the complete decoded JSON in `->raw` (and in `->toArray()`), including fields that
future API versions add. Unknown fields are tolerated. Response headers are available in `->headers`, with
lower-cased names such as `x-request-id` and `x-ratelimit-remaining`.

**`$result->data`** (`GenderApi\Response\Prediction`)

| Property | JSON field | Notes |
| --- | --- | --- |
| `gender` | `gender` | `"male"`, `"female"` or `null` |
| `resultStatus` | `result_status` | `"identified"` or `"unknown"`. This is an inference status, not identity verification. |
| `reason` | `reason` | `null` when identified. Otherwise `not_found`, `no_name_candidate`, `ambiguous` or `insufficient_evidence`. |
| `confidence` | `confidence` | `float` from 0 to 1, or `null` when gender is null. **Not a percentage.** |
| `confidenceKind` | `confidence_kind` | `observed_frequency` (dataset share) or `model_reported` (AI score, not a calibrated probability) |
| `sampleCount` | `sample_count` | dataset sample count; `null` for AI |
| `source` | `source` | `dataset`, `ai` or `none` |
| `name` | `name` | the returned dataset name or the extracted given name; can be `null` |
| `country` | `country` | the country used, or `null` |
| `countrySource` | `country_source` | `dataset`, `ai_association` or `null`. It does not indicate nationality or residence. |
| `match` | `match` | `name`, `method` (`normalized`/`token`/`substring`/`model_inference`), `scope` (`country`/`global`), `country` |
| `input` | `input` | echo of `type`, `value`, `country` (and `forceToGenderize`) |

`isIdentified()` and `isUnknown()` are convenience checks.

**`$result->meta`** (`GenderApi\Response\Meta`): `requestId`, `durationMs`, `access` and `usage`, plus `summary`
for batches.

- `meta->access->mode`: `api_key`, `ip_trial` or `unauthenticated`. `reason` is `api_key_missing`,
  `api_key_invalid`, `api_key_not_found` or `null`.
- `meta->usage->billingStatus`:
  - `not_charged`: `chargedCredits` is `0`.
  - `confirmed`: the net charge is known.
  - `unconfirmed`: `chargedCredits` is `null`; check before resending.
- `meta->usage->chargedCredits` and `meta->usage->remainingCredits`: the remaining balance is a snapshot taken
  at completion. It can be negative, or `null`.
- `meta->usage->resetsAt`, `limit` and `periodSeconds`: set for the IP trial only. `resetsAt` is not a
  subscription expiry.

**Batches** (`BatchResult`, iterable and countable): `items` is a list of `BatchItemResult`. Each item has
`index`, `id`, `chargedCredits` and exactly one of `data` (a `Prediction`, possibly unknown) or `error`
(a `Problem` with `code`, `status`, `action` and so on). `failed()`, `succeeded()`, `hasFailures()` and
`summary()` (`total`, `succeeded`, `identified`, `unknown`, `failed`) help with inspection.

**Usage** (`UsageResult`): `remainingCredits`, `expiresAt`, `resetsAt`, `limit`, `periodSeconds`, `meta`.

**Phone** (`PhoneResult`): `valid`, `possible`, `e164`, `country`, `countryCallingCode`, `meta`. This checks
the number's structure, not whether a subscriber exists.

## Errors

All exceptions implement `GenderApi\Exception\GenderApiException`.

| Exception | When |
| --- | --- |
| `ValidationException` | Invalid input or configuration. **No request was sent.** |
| `ApiException` (base) | HTTP status 400 or higher |
| ↳ `AuthenticationException` | 401 |
| ↳ `ForbiddenException` / `InsufficientCreditsException` | 403 (`access_denied`, `key_disabled`, `key_expired` / `insufficient_credits`) |
| ↳ `InvalidRequestException` | other 4xx responses (400, 404, 405, 413, 415, 422) |
| ↳ `RateLimitException` | 429; see `retryAfter` |
| ↳ `ServerException` | 5xx responses (500, 502, 503, 504) |
| `TransportException` / `TimeoutException` | No usable response: DNS, TLS, connection or timeout. The request may still have been processed and billed. |
| `UnexpectedResponseException` / `RedirectException` | A 2xx response that is not V2 JSON, or a 3xx redirect. Redirects are never followed. |

`ApiException` has these properties:

- `status`
- `errorCode`: the stable machine `code`, such as `insufficient_credits`
- `title`, `detail`, `type`, `instance`
- `action`, such as `wait_then_retry` or `contact_support`
- `documentation`
- `errors`: validation pointers, `[['pointer' => '/value', 'message' => '...'], ...]`
- `requestId`: from the body, `meta.request_id` or the `X-Request-ID` header
- `retryAfter`: seconds, from the `Retry-After` header
- `billingStatus`, `meta`
- `body`: the decoded JSON, or `null` for a non-JSON proxy error
- `rawBody`, `headers`
- `batchItems`: for a batch where every item failed

Match on `errorCode` and `status`, never on the `detail` text. The full catalog is available from
`$client->errorCatalog()` and at <https://api.genderapi.io/api/v2/errors>.

```php
use GenderApi\Exception\ApiException;
use GenderApi\Exception\RateLimitException;
use GenderApi\Exception\TransportException;
use GenderApi\Exception\ValidationException;

try {
    $result = $client->email('alex@example.com');
} catch (ValidationException $e) {
    // fix the input: $e->pointer
} catch (RateLimitException $e) {
    // wait $e->retryAfter seconds before sending a NEW request (it is billed normally)
} catch (ApiException $e) {
    if ($e->isBillingUnconfirmed()) {
        // contact support with $e->requestId; do not resend automatically
    }
    // otherwise follow $e->action ("correct_request", "check_credentials", ...)
} catch (TransportException $e) {
    // outcome unknown: the request may have been billed. Check $client->usage() before resending.
}
```

Do not log `$e->body` or `$e->rawBody` wholesale, because they can contain the inputs you submitted.

## Billing and retries

- **The client never retries automatically.** This covers transport errors, timeouts, 429 and 5xx. Every
  prediction or phone request is a new, billable operation, and a lost response does not mean the request was
  not billed.
- **429:** wait for `retryAfter` before sending another request.
- **`billing_reconciliation_required` or `billingStatus === 'unconfirmed'`:** contact support with the
  `requestId` before you retry.
- **Other prediction failures (502/503/504):** check `billingStatus` and fix the cause before you send a new request.
- **Partial batch success returns normally.** Retry only `$batch->failed()` items, and only after billing is
  confirmed. Resubmitting successful items charges them again.
- **When every item in a batch fails,** the API returns an error status. The client throws an `ApiException`,
  and its `batchItems` holds the per-item rows.

## Safety defaults

- HTTPS base URL. Plain `http://` is accepted only for `localhost`, `127.0.0.1` and `[::1]` test servers.
- Redirects are never followed, so the key is never forwarded to another host.
- The timeout is 10 seconds by default and covers the whole request. You can configure it.
- TLS certificates are verified.
- Constructing the client makes no request.

## Testing your integration

Pass your own `GenderApi\Http\Transport` to the constructor to stub HTTP in your tests. It must send each
request once, must not follow redirects, and must return 4xx/5xx responses rather than throw them.

## Migrating from 1.x

| 1.x (V1 API) | 2.0 (V2 API) |
| --- | --- |
| `use GenderApi\GenderApi;` `new GenderApi($apiKey)` | `use GenderApi\Client;` `new Client($apiKey)`. The root PSR-4 namespace `GenderApi\` is unchanged. The entry class `GenderApi\GenderApi` was removed (it is `GenderApi\Client` now), and the sub-namespaces `GenderApi\Response`, `GenderApi\Exception` and `GenderApi\Http` were added. |
| API key required | Optional: the constructor or `GENDERAPI_API_KEY`. Without a key, the server applies the IP trial. |
| Base URL `https://api.genderapi.io` with the `/api...` routes | `https://api.genderapi.io/api/v2` |
| `getGenderByName($name, $country, $askToAI, $forceToGenderize)` (`POST /api`) | `name($value, $country, $aiMode, $forceToGenderize, $id)` (`POST /api/v2/gender`, `type: name`) |
| `getGenderByEmail($email, $country, $askToAI)` (`POST /api/email`) | `email(...)` (`POST /api/v2/gender`, `type: email`) |
| `getGenderByUsername(...)` (`POST /api/username`) | `username(...)` (`POST /api/v2/gender`, `type: username`) |
| `getGenderByNameBulk` (100), `getGenderByEmailBulk` / `getGenderByUsernameBulk` (50), `/api/*/multi/country` | `genderBatch($items)`: 1-50 mixed items (10 on the IP trial), `POST /api/v2/gender/batch` |
| `askToAI: true` | `options.ai_mode` (`$aiMode`). Single requests already default to `fallback`. Use `always` to skip the dataset (2 credits). |
| `forceToGenderize` (name/username) | `forceToGenderize` for all three types. Dataset first, then nickname-aware AI. Not with `off`/`always`. |
| Flat response array | Typed `data` (inference) and `meta` (access, billing). `->toArray()` returns the raw JSON. |
| `q` | `data.input.value` |
| `probability` (percentage) | `data.confidence` (0-1) plus `data.confidence_kind`. AI scores are not calibrated probabilities. |
| `total_names` | `data.sample_count` (nullable) |
| `gender: "null"` | `gender: null`, `result_status: "unknown"` and a `reason` |
| `used_credits` | `meta.usage.charged_credits` plus `meta.usage.billing_status` |
| `remaining_credits`, `expires` | `meta.usage.remaining_credits` (can be negative or null). `usage()` returns `expires_at`. |
| `status` / `errno` / `errmsg` | HTTP status plus the Problem Details `code` and `action` (`ApiException::$errorCode`, `$action`) |
| bulk `names[]` with `id` | `data[]` items with `index`, `id`, `charged_credits` and `data` **or** `error`, plus `meta.summary` |
| `duration: "4ms"` | `meta.duration_ms` |
| Generic `\Exception`, 30 s timeout | Typed exceptions, a 10 s default timeout, no retries, no redirects |
| PHP >= 7.2 | PHP >= 8.1 |

Each retry is a new operation with normal billing. See the
[V2 migration guide](https://www.genderapi.io/docs/v2/migration).

## Documentation

- [API documentation (V2)](https://www.genderapi.io/api-documentation)
- [Authentication and the free trial](https://www.genderapi.io/docs/v2/authentication)
- [Request parameters](https://www.genderapi.io/docs/v2/request-parameters) and [AI options](https://www.genderapi.io/docs/v2/ai-options)
- [Responses](https://www.genderapi.io/docs/v2/responses) and [Credits and usage](https://www.genderapi.io/docs/v2/credits-and-usage)
- [Batch](https://www.genderapi.io/docs/v2/batch)
- [Errors and retries](https://www.genderapi.io/docs/v2/errors-and-retries)
- [Phone validation](https://www.genderapi.io/docs/v2/phone-validation)
- [Migration from V1](https://www.genderapi.io/docs/v2/migration)
- [V1 documentation](https://www.genderapi.io/api-documentation/v1) (for the 1.x client)

## Development

```bash
composer install
composer test          # PHPUnit: mocked HTTP and a local 127.0.0.1 stub server, no real API calls
composer validate --strict
```

Releases are published by pushing a `vX.Y.Z` tag that matches `Client::VERSION`. Packagist syncs from the tags,
so `composer.json` has no `version` field. The release workflow (`.github/workflows/release.yml`) runs the tests
and creates the GitHub release. If the optional repository secrets `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN`
are set, it also asks Packagist to update immediately.

## License

MIT. See [LICENSE](LICENSE).
