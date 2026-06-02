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

$autoload = BS_FABRIC_PATH . 'vendor/autoload.php';

if (! is_readable($autoload)) {
    add_action('admin_notices', static function (): void {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'bs-fabric: Composer-Abhängigkeiten fehlen. Bitte `composer install` im Plugin-Verzeichnis ausführen.',
                'bs-fabric'
            )
        );
    });

    return;
}

require_once $autoload;

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
