<?php

namespace GenderApi;

/**
 * GenderAPI.io PHP SDK
 *
 * This SDK allows determining gender from:
 *   - personal names
 *   - email addresses
 *   - social media usernames
 *
 * It supports additional options like country filtering,
 * direct AI queries, and forced genderization for nicknames.
 */
class GenderApi
{
    private $apiKey;
    private $baseUrl;

    /**
     * GenderApi constructor.
     *
     * @param string $apiKey Your Bearer token.
     * @param string $baseUrl API base URL. Default: https://api.genderapi.io
     */
    public function __construct($apiKey, $baseUrl = 'https://api.genderapi.io')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Determine gender from a personal name.
     *
     * @param string $name The name to analyze. (Required)
     * @param string|null $country Optional two-letter country code (e.g. "US").
     * @param bool $askToAI Whether to directly query AI for prediction (default false).
     * @param bool $forceToGenderize Whether to force genderizing nicknames (default false).
     * @return array JSON response as associative array.
     * @throws \Exception
     */
    public function getGenderByName($name, $country = null, $askToAI = false, $forceToGenderize = false)
    {
        return $this->postRequest('/api', [
            'name' => $name,
            'country' => $country,
            'askToAI' => $askToAI,
            'forceToGenderize' => $forceToGenderize
        ]);
    }

    /**
     * Determine gender from an email address.
     *
     * @param string $email The email address to analyze. (Required)
     * @param string|null $country Optional two-letter country code (e.g. "US").
     * @param bool $askToAI Whether to directly query AI for prediction (default false).
     * @return array JSON response as associative array.
     * @throws \Exception
     */
    public function getGenderByEmail($email, $country = null, $askToAI = false)
    {
        return $this->postRequest('/api/email', [
            'email' => $email,
            'country' => $country,
            'askToAI' => $askToAI
        ]);
    }

    /**
     * Determine gender from a social media username.
     *
     * @param string $username The username to analyze. (Required)
     * @param string|null $country Optional two-letter country code (e.g. "US").
     * @param bool $askToAI Whether to directly query AI for prediction (default false).
     * @param bool $forceToGenderize Whether to force genderizing nicknames (default false).
     * @return array JSON response as associative array.
     * @throws \Exception
     */
    public function getGenderByUsername($username, $country = null, $askToAI = false, $forceToGenderize = false)
    {
        return $this->postRequest('/api/username', [
            'username' => $username,
            'country' => $country,
            'askToAI' => $askToAI,
            'forceToGenderize' => $forceToGenderize
        ]);
    }

    /**
     * Determine gender for multiple names (bulk).
     *
     * @param array $data Array of name objects. Each:
     *                    [ 'name' => string, 'country' => ?string, 'id' => ?string|int ]
     * @return array JSON response as associative array.
     * @throws \Exception
     */
    public function getGenderByNameBulk(array $data)
    {
        if (count($data) > 100) {
            throw new \Exception("getGenderByNameBulk cannot exceed 100 records per request.");
        }
        return $this->postRequest('/api/name/multi/country', [
            'data' => $data
        ]);
    }

    /**
     * Determine gender for multiple emails (bulk).
     *
     * @param array $data Array of email objects. Each:
     *                    [ 'email' => string, 'country' => ?string, 'id' => ?string|int ]
     * @return array JSON response as associative array.
     * @throws \Exception
     */
    public function getGenderByEmailBulk(array $data)
    {
        if (count($data) > 50) {
            throw new \Exception("getGenderByEmailBulk cannot exceed 50 records per request.");
        }
        return $this->postRequest('/api/email/multi/country', [
            'data' => $data
        ]);
    }

    /**
     * Determine gender for multiple usernames (bulk).
     *
     * @param array $data Array of username objects. Each:
     *                    [ 'username' => string, 'country' => ?string, 'id' => ?string|int ]
     * @return array JSON response as associative array.
     * @throws \Exception
     */
    public function getGenderByUsernameBulk(array $data)
    {
        if (count($data) > 50) {
            throw new \Exception("getGenderByUsernameBulk cannot exceed 50 records per request.");
        }
        return $this->postRequest('/api/username/multi/country', [
            'data' => $data
        ]);
    }

    /**
     * Internal helper method to send POST requests via cURL.
     *
     * @param string $endpoint API endpoint (e.g. /api)
     * @param array $payload Payload data.
     * @return array JSON-decoded response.
     * @throws \Exception
     */
    private function postRequest($endpoint, array $payload)
    {
        $url = $this->baseUrl . $endpoint;

        // Remove null values
        $payload = array_filter($payload, function ($value) {
            return $value !== null;
        });

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            throw new \Exception("cURL error: $curlError");
        }

        if ($httpCode == 500 || $httpCode == 502 || $httpCode == 503 || $httpCode == 504 || $httpCode == 408) {
            throw new \Exception("GenderAPI server error (500).");
        }

        $json = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response.");
        }

        return $json;
    }
}
