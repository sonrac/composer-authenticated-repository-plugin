<?php

/**
 * Custom Composer JSON Validator
 * 
 * This script validates composer.json and accepts the composer-authenticated repository type.
 * Usage: php validate-composer.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Composer\Json\JsonFile;
use Composer\Json\JsonValidationException;

function validateComposerJson(string $file = 'composer.json'): bool
{
    if (!file_exists($file)) {
        echo "❌ $file not found\n";
        return false;
    }

    try {
        $jsonFile = new JsonFile($file);
        $data = $jsonFile->read();

        // Validate our custom repository type
        validateAuthenticatedRepositories($data);

        // Use Composer's original validation for everything else
        $jsonFile->validateSchema();

        echo "✅ $file is valid\n";
        return true;

    } catch (JsonValidationException $e) {
        echo "❌ Validation failed: " . $e->getMessage() . "\n";
        return false;
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        return false;
    }
}

function validateAuthenticatedRepositories(array $data): void
{
    if (!isset($data['repositories']) || !is_array($data['repositories'])) {
        return;
    }

    foreach ($data['repositories'] as $index => $repository) {
        if (isset($repository['type']) && $repository['type'] === 'composer-authenticated') {
            validateAuthenticatedRepository($repository, $index);
        }
    }
}

function validateAuthenticatedRepository(array $repository, int $index): void
{
    $errors = [];

    // Check required fields
    if (!isset($repository['url'])) {
        $errors[] = 'composer-authenticated repository requires a "url" field';
    }

    // Check URL format
    if (isset($repository['url']) && !filter_var($repository['url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'composer-authenticated repository "url" must be a valid URL';
    }

    // Check for invalid fields
    $allowedFields = ['type', 'url'];
    foreach (array_keys($repository) as $field) {
        if (!in_array($field, $allowedFields)) {
            $errors[] = "composer-authenticated repository does not support field '$field'";
        }
    }

    if (!empty($errors)) {
        throw new JsonValidationException(
            "Validation failed for composer-authenticated repository at index $index: " . implode(', ', $errors)
        );
    }
}

// Run validation if script is called directly
if (php_sapi_name() === 'cli') {
    $file = $argv[1] ?? 'composer.json';
    $result = validateComposerJson($file);
    exit($result ? 0 : 1);
} 