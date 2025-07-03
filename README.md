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

### 🔹 Get Gender by Name

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use GenderApi\GenderApi;

$api = new GenderApi("YOUR_API_KEY");

// Basic usage
$result = $api->getGenderByName("Michael");
print_r($result);

// With askToAI set to true
$result = $api->getGenderByName("李雷", null, true);
print_r($result);
```

---

### 🔹 Get Gender by Email

```php
<?php

$api = new GenderApi("YOUR_API_KEY");

$result = $api->getGenderByEmail("michael.smith@example.com");
print_r($result);

// With askToAI set to true
$result = $api->getGenderByEmail("michael.smith@example.com", null, true);
print_r($result);
```

---

### 🔹 Get Gender by Username

```php
<?php

$api = new GenderApi("YOUR_API_KEY");

$result = $api->getGenderByUsername("michael_dev");
print_r($result);

// With askToAI set to true
$result = $api->getGenderByUsername("michael_dev", null, true);
print_r($result);
```

---

## 📥 API Parameters

---

### Name Lookup

| Parameter          | Type     | Required | Description |
|--------------------|----------|----------|-------------|
| name               | String   | Yes      | Name to query. |
| country            | String   | No       | Two-letter country code (e.g. "US"). Helps narrow down gender detection results by region. |
| askToAI            | Boolean  | No       | Default is `false`. If `true`, sends the query directly to AI for maximum accuracy, consuming 3 credits per request. If `false`, GenderAPI first tries its internal database and uses AI only if necessary, without spending 3 credits. Recommended for non-latin characters or unusual strings. |
| forceToGenderize   | Boolean  | No       | Default is `false`. When `true`, analyzes even nicknames, emojis, or unconventional strings like "spider man" instead of returning `null` for non-standard names. |

---

### Email Lookup

| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| email     | String | Yes      | Email address to query. |
| country   | String | No       | Two-letter country code (e.g. "US"). Helps narrow down gender detection results by region. |
| askToAI   | Boolean| No       | Default is `false`. If `true`, sends the query directly to AI for maximum accuracy, consuming 3 credits per request. If `false`, GenderAPI first tries its internal database and uses AI only if necessary, without spending 3 credits. Recommended for non-latin characters or unusual strings. |

---

### Username Lookup

| Parameter          | Type     | Required | Description |
|--------------------|----------|----------|-------------|
| username           | String   | Yes      | Username to query. |
| country            | String   | No       | Two-letter country code (e.g. "US"). Helps narrow down gender detection results by region. |
| askToAI            | Boolean  | No       | Default is `false`. If `true`, sends the query directly to AI for maximum accuracy, consuming 3 credits per request. If `false`, GenderAPI first tries its internal database and uses AI only if necessary, without spending 3 credits. Recommended for non-latin characters or unusual strings. |
| forceToGenderize   | Boolean  | No       | Default is `false`. When `true`, analyzes even nicknames, emojis, or unconventional strings like "spider man" instead of returning `null` for non-standard names. |

---

## ✅ API Response

Example JSON response for all endpoints:

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

### Response Fields

| Field             | Type               | Description                                         |
|-------------------|--------------------|-----------------------------------------------------|
| status            | Boolean            | `true` or `false`. Check errors if false.          |
| used_credits      | Integer            | Credits used for this request.                     |
| remaining_credits | Integer            | Remaining credits on your package.                 |
| expires           | Integer (timestamp)| Package expiration date (in seconds).             |
| q                 | String             | Your input query (name, email, or username).       |
| name              | String             | Found name.                                        |
| gender            | Enum[String]       | `"male"`, `"female"`, or `"null"`.                |
| country           | Enum[String]       | Most likely country (e.g. `"US"`, `"DE"`, etc.).  |
| total_names       | Integer            | Number of samples behind the prediction.          |
| probability       | Integer            | Likelihood percentage (50-100).                   |
| duration          | String             | Processing time (e.g. `"4ms"`).                   |

---

## ⚠️ Error Codes

When `status` is `false`, check the following error codes:

| errno | errmsg                      | Description                                                       |
|-------|-----------------------------|-------------------------------------------------------------------|
| 50    | access denied               | Unauthorized IP Address or Referrer. Check your access privileges. |
| 90    | invalid country code        | Check supported country codes. [ISO 3166-1 alpha-2](https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2) |
| 91    | name not set \|\| email not set | Missing `name` or `email` parameter on your request.         |
| 92    | too many names \|\| too many emails | Limit is 100 for names, 50 for emails in one request.     |
| 93    | limit reached               | The API key credit has been finished.                            |
| 94    | invalid or missing key      | The API key cannot be found.                                      |
| 99    | API key has expired         | Please renew your API key.                                       |

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
