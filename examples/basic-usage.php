<?php

declare(strict_types=1);

/**
 * Example: Loading a .env file with phpdotenv.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Immutable loading (recommended for production).
$dotenv = Dotenv::create_immutable(__DIR__ . '/../');
$dotenv->load();

// Mutable loading for local overrides.
$dotenv = Dotenv::create_mutable(__DIR__ . '/../');
$dotenv->load();

// Parse without mutating the environment.
$values = Dotenv::parse((string) @file_get_contents(__DIR__ . '/../.env'));

// Validation after load.
$dotenv = Dotenv::create_immutable(__DIR__ . '/../');
$dotenv->load();
$dotenv->required(['APP_ENV', 'DB_HOST']);
$dotenv->required('APP_ENV')->allowed_values(['production', 'staging', 'development']);
$dotenv->required('DB_PASSWORD')->not_empty();

// Multi-file fallback with short circuit.
$dotenv = Dotenv::create_immutable(__DIR__ . '/../', ['.env.local', '.env'], true);
$dotenv->safe_load();
