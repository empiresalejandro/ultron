<?php
/**
 * Plugin Name: Ultron
 * Plugin URI:  https://github.com/tuusuario/ultron-client
 * Description: Hub de gestión y monitoreo para WordPress.
 * Version:     1.0.0
 * Author:      Tu Nombre
 * Author URI:  https://tudominio.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ultron
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package Ultron
 */

// Seguridad: evitar acceso directo al archivo.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constantes del plugin.
define( 'ULTRON_VERSION', '1.0.0' );
define( 'ULTRON_PATH',    plugin_dir_path( __FILE__ ) );
define( 'ULTRON_URL',     plugin_dir_url( __FILE__ ) );
define( 'ULTRON_MODULES', ULTRON_PATH . 'modules/' );

// Carga del core.
require_once ULTRON_PATH . 'core/class-ultron.php';

// Inicialización.
function ultron_init() {
    Ultron::get_instance();
}
add_action( 'plugins_loaded', 'ultron_init' );