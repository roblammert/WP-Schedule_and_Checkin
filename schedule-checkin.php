<?php
/**
 * Plugin Name: Schedule and Check-In
 * Description: Volunteer scheduling, signup, and kiosk check-in/check-out for events.
 * Version: 1.0.0
 * Author: Robert J. Lammert
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: schedule-checkin
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SC_PLUGIN_VERSION', '1.0.0');
define('SC_PLUGIN_FILE', __FILE__);
define('SC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SC_PLUGIN_URL', plugin_dir_url(__FILE__));

$autoload = SC_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

require_once SC_PLUGIN_DIR . 'includes/class-sc-install.php';
require_once SC_PLUGIN_DIR . 'includes/class-sc-plugin.php';

register_activation_hook(__FILE__, ['SC_Install', 'activate']);
register_deactivation_hook(__FILE__, ['SC_Install', 'deactivate']);

function sc_plugin_bootstrap() {
    SC_Plugin::instance();
}
add_action('plugins_loaded', 'sc_plugin_bootstrap');
