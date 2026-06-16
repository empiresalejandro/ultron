<?php
/**
 * Plugin Name: Ultron
 * Plugin URI:  https://github.com/empiresalejandro/ultron
 * Description: Gestión y monitoreo para WordPress.
 * Version:     1.1.0
 * Author:      alejandro.network
 * Author URI:  https://alejandro.network/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ultron
 * Domain Path: /languages
 * Requires at least: 7.0
 * Requires PHP:      8.0
 *
 * @package Ultron
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ULTRON_VERSION', '1.1.0' );
define( 'ULTRON_PATH',    plugin_dir_path( __FILE__ ) );
define( 'ULTRON_URL',     plugin_dir_url( __FILE__ ) );
define( 'ULTRON_MODULES', ULTRON_PATH . 'modules/' );

require_once ULTRON_PATH . 'core/class-ultron.php';

/**
 * Inicializa el plugin.
 *
 * @return void
 */
function ultron_init(): void {
	Ultron::get_instance();
}
add_action( 'plugins_loaded', 'ultron_init' );
