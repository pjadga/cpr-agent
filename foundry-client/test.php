<?php

// This is a simple test script to verify that the package structure is correct
// and that the autoloading works as expected.

// Require the Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Use the FoundryClient namespace
use FoundryClient\FoundryClient;
use FoundryClient\JsonPrettifier;

// Create instances of the classes
$client = new FoundryClient('https://example.com', 'test-api-key');
$prettifier = new JsonPrettifier();

// Test that the classes are working
echo "FoundryClient initialized successfully.\n";
echo "JsonPrettifier initialized successfully.\n";

// Test a method from each class
$apiStatus = $client->getApiStatus();
echo "getApiStatus method called successfully.\n";

$testData = ['id' => '123456', 'name' => 'Test Entity'];
$prettifiedData = $prettifier->prettifyResponse($testData);
echo "prettifyResponse method called successfully.\n";

echo "All tests passed successfully!\n";
