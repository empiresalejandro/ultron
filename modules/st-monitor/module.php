<?php
/**
 * Module Name: Storage Monitor
 * Description: Monitor de almacenamiento, inodos y tamaño de carpetas.
 * Version: 1.0.0
 *
 * @package Ultron
 * @subpackage ST_Monitor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-st-monitor.php';

$ultron_st_monitor = new Ultron_ST_Monitor();

add_filter( 'ultron_monitor_tabs', function( $tabs ) use ( $ultron_st_monitor ) {
	$tabs['storage'] = [
		'label'    => __( 'Almacenamiento', 'ultron' ),
		'callback' => [ $ultron_st_monitor, 'render_tab' ],
	];
	return $tabs;
} );

add_action( 'ultron_dashboard_widgets', [ $ultron_st_monitor, 'render_widget' ] );

$ultron_st_monitor->maybe_create_table();
