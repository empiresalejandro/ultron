<?php
/**
 * Desinstalación de Ultron.
 *
 * Se ejecuta cuando WordPress desinstala el plugin.
 * Si la opción "Eliminar todos los datos" está activada, elimina:
 * - Tablas de base de datos creadas por Ultron
 * - Todas las opciones con prefijo ultron_
 *
 * @package Ultron
 * @since   1.0.0
 */

// Solo ejecutar desde WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = get_option( 'ultron_delete_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

global $wpdb;

// Eliminar tablas.
$tables = [
	$wpdb->prefix . 'ultron_wp_monitor',
	$wpdb->prefix . 'ultron_st_monitor',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// Eliminar todas las opciones con prefijo ultron_.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ultron_%'" );
