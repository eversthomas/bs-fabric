<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Unit-Tests laufen ohne echtes WordPress. Template-Dateien (templates/default/*.php)
// prüfen `defined('ABSPATH') || exit;` als Direktzugriffsschutz — ohne diese Konstante
// bricht ein `exit()` mitten im Test-Lauf lautlos den kompletten PHPUnit-Prozess ab
// (falscher "grüner" Status, Rest der Suite wird nie ausgeführt).
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// REST-Unit-Tests brauchen WP_REST_Request; Integration-Tests mit WP-Core laden die echte Klasse.
if (! class_exists('WP_REST_Request', false)) {
    require_once __DIR__ . '/Stubs/WP_REST_Request.php';
}
