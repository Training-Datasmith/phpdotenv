<?php

declare(strict_types=1);

/**
 * Example: Loading a .env file with phpdotenv.
 *
 * Install:
 *   composer require vlucas/phpdotenv
 *
 * This example shows the three most common loading modes.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// --- Immutable loading (recommended for production) ---
// Variables already set in the environment are NOT overwritten.

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// After load(), variables from .env are in $_ENV and $_SERVER.
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';


// --- Mutable loading (useful for local development overrides) ---
// Overwrites any existing environment variables.

$dotenv = Dotenv::createMutable(__DIR__ . '/../');
$dotenv->load();


// --- Array-backed parsing (no side effects — good for testing) ---
// Returns an associative array; does NOT modify $_ENV or $_SERVER.

$values = Dotenv::parse(file_get_contents(__DIR__ . '/../.env'));
// $values is array<string, string|null>


// --- Validation: require specific variables ---
// Throws Dotenv\Exception\ValidationException when a required variable is missing.

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$dotenv->required(['APP_ENV', 'DB_HOST', 'DB_NAME']);

// Validate allowed values:
$dotenv->required('APP_ENV')->allowedValues(['production', 'staging', 'development']);

// Validate that a variable is not empty:
$dotenv->required('DB_PASSWORD')->notEmpty();


// --- Multi-file loading with fallback ---
// Loads the first file that exists (.env.local, then .env).

$dotenv = Dotenv::createImmutable(
    __DIR__ . '/../',
    ['.env.local', '.env'],
    shortCircuit: true   // stop after first readable file
);
$dotenv->safeLoad(); // suppresses InvalidPathException if no file found
