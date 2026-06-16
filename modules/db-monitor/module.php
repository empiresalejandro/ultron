<?php
/**
 * Module Name: Database Monitor
 * Description: Monitor de base de datos con truncate seguro.
 * Version: 1.0.0
 *
 * @package Ultron
 * @subpackage DB_Monitor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-db-monitor.php';

$ultron_db_monitor = new Ultron_DB_Monitor();

add_filter( 'ultron_monitor_tabs', function( $tabs ) use ( $ultron_db_monitor ) {
	$tabs['database'] = [
		'label'    => __( 'Base de datos', 'ultron' ),
		'callback' => [ $ultron_db_monitor, 'render_tab' ],
	];
	return $tabs;
} );

add_action( 'ultron_dashboard_widgets', [ $ultron_db_monitor, 'render_widget' ] );
