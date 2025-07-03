<?php

require_once __DIR__ . '/vendor/autoload.php';

use GenderApi\GenderApi;

$apiKey = 'YOUR_API_KEY';

$client = new GenderApi($apiKey);

// Test name
try {
    $result = $client->getGenderByName("Michael", "US");
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}

// Test email
try {
    $result = $client->getGenderByEmail("michael.smith@example.com", "US", true);
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}

// Test username
try {
    $result = $client->getGenderByUsername("spider_man", "US", false, true);
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
