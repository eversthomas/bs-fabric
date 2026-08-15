<?php

declare(strict_types=1);

/**
 * Plugin Name:       bs-fabric
 * Plugin URI:        https://github.com/bs-fabric/bs-fabric
 * Description:       ProcessWire-inspiriertes Template/Field/Entry-System für WordPress.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Author:            bs-fabric
 * Text Domain:       bs-fabric
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit;
}

define('BS_FABRIC_VERSION', '1.0.0');
define('BS_FABRIC_FILE', __FILE__);
define('BS_FABRIC_PATH', plugin_dir_path(__FILE__));
define('BS_FABRIC_URL', plugin_dir_url(__FILE__));

/**
 * Eigener PSR-4-Autoloader für den BsFabric\-Namespace (src/).
 *
 * Die Plugin-Laufzeit hat keine Composer-Produktionsabhängigkeiten (composer.json
 * "require" enthält nur die PHP-Version) — Composer/vendor/ wird ausschließlich
 * für die Testsuite gebraucht (siehe tests/bootstrap.php). Damit lässt sich das
 * Plugin ohne vorherigen `composer install`-Schritt in jede WordPress-Installation
 * kopieren und aktivieren.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'BsFabric\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = BS_FABRIC_PATH . 'src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_readable($file)) {
        require $file;
    }
});

$plugin = BsFabric\Core\Plugin::instance();
$plugin->init();

register_activation_hook(
    BS_FABRIC_FILE,
    static function (): void {
        BsFabric\Core\Plugin::instance()->activate();
    }
);

register_deactivation_hook(
    BS_FABRIC_FILE,
    static function (): void {
        BsFabric\Core\Plugin::instance()->deactivate();
    }
);
