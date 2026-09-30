# Changelog

All notable changes to `genderapi/genderapi` are documented here. Versions follow semantic versioning and
are published to Packagist from git tags.

## [2.0.0] - 2026-09-30

### Breaking

- The client now targets the **GenderAPI.io V2 API** (`https://api.genderapi.io/api/v2`). The V1 routes, request
  fields and flat response arrays are no longer used. 1.x stays available on the `v1` branch (maintenance only).
- The entry class `GenderApi\GenderApi` is replaced by `GenderApi\Client`. The root namespace `GenderApi\` is
  unchanged, and the sub-namespaces `GenderApi\Response`, `GenderApi\Exception` and `GenderApi\Http` are new.
- The V1 methods `getGenderByName`, `getGenderByEmail`, `getGenderByUsername` and the `*Bulk` methods are removed.
  Use `name()`, `email()`, `username()`, `gender()` and `genderBatch()`.
- Responses are typed objects (`GenderResult`, `BatchResult`, `UsageResult`, `PhoneResult`). The raw JSON is
  still available through `->raw` / `->toArray()`.
- `probability` (percentage) is replaced by `confidence` (0-1) plus `confidence_kind`. `used_credits` is replaced
  by `meta.usage.charged_credits` and `billing_status`.
- Errors are typed exceptions exposing the Problem Details `code`, `action`, `request_id`, `Retry-After` and billing
  status. The generic `\Exception` is gone.
- PHP 8.1 or newer is required (was 7.2).

### Added

- `gender()`, `name()`, `email()`, `username()` with `country`, `ai_mode` (`off`/`fallback`/`always`),
  `forceToGenderize` and `id`.
- `genderBatch()` for 1-50 mixed items (10 on the IP trial). Partial success is returned, not thrown.
- `usage()` (free), `validatePhone()`, `capabilities()` and `errorCatalog()`.
- Works without an API key through the server-side IP trial (10 credits / 24 h). The key can come from
  `GENDERAPI_API_KEY`.
- Client-side validation that mirrors the request schema and throws before any network request.
- cURL transport with a PHP-streams fallback. A pluggable `GenderApi\Http\Transport` interface.
- PHPUnit test suite that runs against the OpenAPI examples, mocked HTTP and a local stub server. GitHub Actions CI
  and a tag-based release workflow.

### Safety

- No automatic retries of any request, including 429 and 5xx responses.
- Redirects are never followed. The API key is only sent in the `Authorization` header, never in a URL, and it
  never appears in debug output or exception messages.
- The timeout is 10 seconds by default (configurable). HTTPS is required, except for localhost test servers.

## [1.0.3] and earlier

V1 API client. See the `v1` branch and the [V1 documentation](https://www.genderapi.io/api-documentation/v1).

[2.0.0]: https://github.com/GenderAPI/genderapi-php/releases/tag/v2.0.0
[1.0.3]: https://github.com/GenderAPI/genderapi-php/releases/tag/v1.0.3
