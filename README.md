# genderapi-php

Official PHP SDK for [GenderAPI.io](https://www.genderapi.io) — determine gender from **names**, **emails**, and **usernames** using AI.

---

Get Free API Key: [https://app.genderapi.io](https://app.genderapi.io)

---

## 🚀 Installation

Install the required package via Composer:

```bash
composer require genderapi/genderapi
```

---

## 📝 Usage

---

### 🔹 Get Gender by Name (Single)

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use GenderApi\GenderApi;

$api = new GenderApi("YOUR_API_KEY");

// Basic usage
$result = $api->getGenderByName("Michael");
print_r($result);

// With country and askToAI
$result = $api->getGenderByName("李雷", "CN", true);
print_r($result);
```

---

### 🔹 Get Gender by Email (Single)

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use GenderApi\GenderApi;

$api = new GenderApi("YOUR_API_KEY");

$result = $api->getGenderByEmail("michael.smith@example.com");
print_r($result);

// With country and askToAI
$result = $api->getGenderByEmail("michael.smith@example.com", "US", true);
print_r($result);
```

---

### 🔹 Get Gender by Username (Single)

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use GenderApi\GenderApi;

$api = new GenderApi("YOUR_API_KEY");

$result = $api->getGenderByUsername("michael_dev");
print_r($result);

// With country, askToAI and forceToGenderize
$result = $api->getGenderByUsername("spider_man", "US", true, true);
print_r($result);
```

---

### 🔹 Get Gender by Multiple Names (Bulk)

Analyze up to **100 names** in a single request.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use GenderApi\GenderApi;

$api = new GenderApi("YOUR_API_KEY");

$result = $api->getGenderByNameBulk([
    ['name' => 'Andrea', 'country' => 'DE', 'id' => '123'],
    ['name' => 'andrea', 'country' => 'IT', 'id' => '456'],
    ['name' => 'james', 'country' => 'US', 'id' => '789'],
]);

print_r($result);
```

---

### 🔹 Get Gender by Multiple Emails (Bulk)

Analyze up to **50 emails** in a single request.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use GenderApi\GenderApi;

$api = new GenderApi("YOUR_API_KEY");

$result = $api->getGenderByEmailBulk([
    ['email' => 'john@example.com', 'country' => 'US', 'id' => 'abc123'],
    ['email' => 'maria@domain.de', 'country' => 'DE', 'id' => 'def456'],
]);

print_r($result);
```

---

### 🔹 Get Gender by Multiple Usernames (Bulk)

Analyze up to **50 usernames** in a single request.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use GenderApi\GenderApi;

$api = new GenderApi("YOUR_API_KEY");

$result = $api->getGenderByUsernameBulk([
    ['username' => 'cooluser', 'country' => 'US', 'id' => 'u001'],
    ['username' => 'maria2025', 'country' => 'DE', 'id' => 'u002'],
]);

print_r($result);
```

---

## 📥 API Parameters

---

### Name Lookup (Single)

| Parameter          | Type     | Required | Description |
|--------------------|----------|----------|-------------|
| name               | String   | Yes      | Name to query. |
| country            | String   | No       | Two-letter country code (e.g. "US"). Helps narrow down gender detection results by region. |
| askToAI            | Boolean  | No       | Default is `false`. If `true`, sends the query directly to AI for maximum accuracy, consuming 3 credits per request. |
| forceToGenderize   | Boolean  | No       | Default is `false`. When `true`, analyzes even nicknames, emojis, or unconventional strings. |

---

### Name Lookup (Bulk)

| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| data      | Array  | Yes      | Array of name objects (max 100 per request). |
| name      | String | Yes      | Name to analyze (inside each object). |
| country   | String | No       | Two-letter country code. |
| id        | String/Integer | No | Optional. Pass your own ID to match responses to your records. |

---

### Email Lookup (Single)

| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| email     | String | Yes      | Email address to query. |
| country   | String | No       | Two-letter country code. |
| askToAI   | Boolean| No       | Default `false`. If `true`, forces AI lookup. |

---

### Email Lookup (Bulk)

| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| data      | Array  | Yes      | Array of email objects (max 50 per request). |
| email     | String | Yes      | Email to analyze (inside each object). |
| country   | String | No       | Two-letter country code. |
| id        | String/Integer | No | Optional. Pass your own ID to match responses to your records. |

---

### Username Lookup (Single)

| Parameter          | Type     | Required | Description |
|--------------------|----------|----------|-------------|
| username           | String   | Yes      | Username to analyze. |
| country            | String   | No       | Two-letter country code. |
| askToAI            | Boolean  | No       | Default `false`. Forces AI lookup if `true`. |
| forceToGenderize   | Boolean  | No       | Default `false`. Allows analyzing unconventional strings. |

---

### Username Lookup (Bulk)

| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| data      | Array  | Yes      | Array of username objects (max 50 per request). |
| username  | String | Yes      | Username to analyze (inside each object). |
| country   | String | No       | Two-letter country code. |
| id        | String/Integer | No | Optional. Pass your own ID to match responses to your records. |

---

## ✅ API Response

---

### Single Response

Example JSON response for single name, email, or username lookups:

```json
{
  "status": true,
  "used_credits": 1,
  "remaining_credits": 4999,
  "expires": 1743659200,
  "q": "michael.smith@example.com",
  "name": "Michael",
  "gender": "male",
  "country": "US",
  "total_names": 325,
  "probability": 98,
  "duration": "4ms"
}
```

---

### Bulk (Multiple) Response

Example JSON response for bulk name lookup (same structure for email and username bulk lookups):

```json
{
  "status": true,
  "used_credits": 3,
  "remaining_credits": 7265,
  "expires": 1717069765,
  "names": [
    {
      "name": "andrea",
      "q": "Andrea",
      "gender": "female",
      "country": "DE",
      "total_names": 644,
      "probability": 88,
      "id": "123"
    },
    {
      "name": "andrea",
      "q": "andrea",
      "gender": "male",
      "country": "IT",
      "total_names": 13537,
      "probability": 98,
      "id": "456"
    },
    {
      "name": "james",
      "q": "james",
      "gender": "male",
      "country": "US",
      "total_names": 45274,
      "probability": 100,
      "id": "789"
    }
  ],
  "duration": "5ms"
}
```

---

### Response Fields

| Field               | Type               | Description                                         |
|---------------------|--------------------|-----------------------------------------------------|
| status              | Boolean            | Indicates whether the request was successful.       |
| used_credits        | Integer            | Number of credits consumed for this request.       |
| remaining_credits   | Integer            | Remaining credits on your package.                  |
| expires             | Integer (timestamp)| Expiration date of your package (UNIX timestamp).   |
| q                   | String             | Your input query (single only).                     |
| name                | String             | Normalized version of the name/email/username.     |
| gender              | Enum[String]       | `"male"`, `"female"`, or `"null"`.                |
| country             | String             | Country used in prediction.                         |
| total_names         | Integer            | Number of samples used for prediction.             |
| probability         | Integer            | Confidence percentage for the prediction.         |
| names               | Array of Objects   | Results list (only in bulk response).             |
| id                  | String / Integer   | Your own ID returned back (bulk only).            |
| duration            | String             | Processing time for the request.                   |

---

## ⚠️ Limits

- **Single requests** → 1 item per call.
- **Bulk Name Lookup** → max **100 names** per request.
- **Bulk Email Lookup** → max **50 emails** per request.
- **Bulk Username Lookup** → max **50 usernames** per request.

---

## ⚠️ Error Codes

When `status` is `false`, check the following error codes:

| errno | errmsg                        | Description                                                   |
|-------|-------------------------------|---------------------------------------------------------------|
| 50    | access denied                 | Unauthorized IP or referrer.                                  |
| 90    | invalid country code          | Country code is invalid.                                      |
| 91    | name/email/username not set   | Missing required parameter.                                   |
| 92    | too many items in bulk        | Limit exceeded (100 for names, 50 for emails/usernames).     |
| 93    | limit reached                 | Credits depleted.                                             |
| 94    | invalid or missing key        | API key is invalid or missing.                               |
| 99    | API key has expired           | Renew your API key.                                           |

Example error response:

```json
{
  "status": false,
  "errno": 94,
  "errmsg": "invalid or missing key"
}
```

---

## 🔗 Live Test Pages

You can try live gender detection directly on GenderAPI.io:

- **Determine gender from a name:**  
  [www.genderapi.io](https://www.genderapi.io)

- **Determine gender from an email address:**  
  [https://www.genderapi.io/determine-gender-from-email](https://www.genderapi.io/determine-gender-from-email)

- **Determine gender from a username:**  
  [https://www.genderapi.io/determine-gender-from-username](https://www.genderapi.io/determine-gender-from-username)

---

## 📚 Detailed API Documentation

For the complete API reference, visit:

[https://www.genderapi.io/api-documentation](https://www.genderapi.io/api-documentation)

---

## ⚖️ License

MIT License
