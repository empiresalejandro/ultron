<?php
/**
 * Module Name: Plugins Monitor
 * Description: Lista y detecta plugins recomendados instalados y activos.
 * Version: 1.0.0
 *
 * @package Ultron
 * @subpackage Plugins_Monitor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-plugins-monitor.php';

$ultron_plugins_monitor = new Ultron_Plugins_Monitor();

add_filter( 'ultron_monitor_tabs', function( $tabs ) use ( $ultron_plugins_monitor ) {
	$tabs['plugins'] = [
		'label'    => __( 'Plugins', 'ultron' ),
		'callback' => [ $ultron_plugins_monitor, 'render_tab' ],
	];
	return $tabs;
} );
