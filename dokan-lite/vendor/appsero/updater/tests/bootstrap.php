<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// WordPress constants that src/Updater.php relies on.
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
