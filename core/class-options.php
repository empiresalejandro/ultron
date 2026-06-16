<?php
/**
 * Gestión de opciones de Ultron.
 *
 * @package Ultron
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ultron_Options {

	/**
	 * Prefijo para las opciones en base de datos.
	 *
	 * @var string
	 */
	private string $prefix = 'ultron_';

	/**
	 * Constructor. Registra acciones de guardado y prueba de conexión.
	 */
	public function __construct() {
		add_action( 'admin_post_ultron_save_options', [ $this, 'handle_save' ] );
		add_action( 'wp_ajax_ultron_test_master',     [ $this, 'handle_test_master' ] );
		add_action( 'wp_ajax_ultron_test_github',     [ $this, 'handle_test_github' ] );
	}

	/**
	 * Obtiene una opción de Ultron desde la base de datos.
	 *
	 * @param string $key     Clave sin prefijo.
	 * @param mixed  $default Valor por defecto.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = '' ): mixed {
		return get_option( $this->prefix . $key, $default );
	}

	/**
	 * Guarda una opción de Ultron en la base de datos.
	 *
	 * @param string $key   Clave sin prefijo.
	 * @param mixed  $value Valor a guardar.
	 * @return bool
	 */
	public function set( string $key, mixed $value ): bool {
		return update_option( $this->prefix . $key, $value );
	}

	/**
	 * Elimina una opción de Ultron de la base de datos.
	 *
	 * @param string $key Clave sin prefijo.
	 * @return bool
	 */
	public function delete( string $key ): bool {
		return delete_option( $this->prefix . $key );
	}

	/** @return string */
	public function get_github_token(): string {
		return $this->get( 'github_token', '' );
	}

	/** @return string */
	public function get_master_url(): string {
		return $this->get( 'master_url', '' );
	}

	/** @return int */
	public function get_wp_monitor_history_limit(): int {
		return (int) $this->get( 'wp_monitor_history_limit', 100 );
	}

	/** @return int */
	public function get_st_monitor_history_limit(): int {
		return (int) $this->get( 'st_monitor_history_limit', 100 );
	}

	/** @return int */
	public function get_error_log_limit_mb(): int {
		return (int) $this->get( 'error_log_limit_mb', 100 );
	}

	/** @return bool */
	public function get_update_checker_enabled(): bool {
		return (bool) $this->get( 'update_checker_enabled', false );
	}

	/** @return bool */
	public function get_delete_on_uninstall(): bool {
		return (bool) $this->get( 'delete_on_uninstall', false );
	}

	/**
	 * Maneja el guardado del formulario de opciones.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos para realizar esta acción.', 'ultron' ) );
		}

		check_admin_referer( 'ultron_save_options' );

		$field    = isset( $_POST['save_field'] ) ? sanitize_key( $_POST['save_field'] ) : '';
		$redirect = admin_url( 'admin.php?page=ultron&tab=options' );

		switch ( $field ) {

			case 'master_url':
				$url = isset( $_POST['master_url'] ) ? esc_url_raw( trim( $_POST['master_url'] ) ) : '';
				$this->set( 'master_url', $url );
				$redirect = add_query_arg( 'saved', 'master', $redirect );
				break;

			case 'github_token':
				$token = isset( $_POST['github_token'] ) ? sanitize_text_field( trim( $_POST['github_token'] ) ) : '';
				if ( ! empty( $token ) ) {
					$this->set( 'github_token', $token );
					$redirect = add_query_arg( 'saved', 'github', $redirect );
				} else {
					$redirect = add_query_arg( 'error', 'empty_token', $redirect );
				}
				break;

			case 'wp_monitor_history_limit':
				$limit = isset( $_POST['wp_monitor_history_limit'] ) ? absint( $_POST['wp_monitor_history_limit'] ) : 0;
				if ( $limit > 0 ) {
					$this->set( 'wp_monitor_history_limit', $limit );
					$redirect = add_query_arg( 'saved', 'wp_history', $redirect );
				} else {
					$redirect = add_query_arg( 'error', 'invalid_limit', $redirect );
				}
				break;

			case 'st_monitor_history_limit':
				$limit = isset( $_POST['st_monitor_history_limit'] ) ? absint( $_POST['st_monitor_history_limit'] ) : 0;
				if ( $limit > 0 ) {
					$this->set( 'st_monitor_history_limit', $limit );
					$redirect = add_query_arg( 'saved', 'st_history', $redirect );
				} else {
					$redirect = add_query_arg( 'error', 'invalid_limit', $redirect );
				}
				break;

			case 'error_log_limit_mb':
				$limit = isset( $_POST['error_log_limit_mb'] ) ? absint( $_POST['error_log_limit_mb'] ) : 0;
				if ( $limit > 0 ) {
					$this->set( 'error_log_limit_mb', $limit );
					$redirect = add_query_arg( 'saved', 'error_log', $redirect );
				} else {
					$redirect = add_query_arg( 'error', 'invalid_limit', $redirect );
				}
				break;

			case 'update_checker':
				$enabled = isset( $_POST['update_checker'] ) && $_POST['update_checker'] === '1';
				$this->set( 'update_checker_enabled', $enabled );
				$redirect = add_query_arg( 'saved', 'update_checker', $redirect );
				break;

			case 'delete_on_uninstall':
				$enabled = isset( $_POST['delete_on_uninstall'] ) && $_POST['delete_on_uninstall'] === '1';
				$this->set( 'delete_on_uninstall', $enabled );
				$redirect = add_query_arg( 'saved', 'uninstall', $redirect );
				break;

			default:
				$redirect = add_query_arg( 'error', 'unknown_field', $redirect );
				break;
		}

		wp_redirect( $redirect );
		exit;
	}

	/**
	 * Prueba la conexión con Ultron Master vía AJAX.
	 *
	 * @return void
	 */
	public function handle_test_master(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Sin permisos.', 'ultron' ) );
		}

		check_ajax_referer( 'ultron_test_nonce' );

		$url = isset( $_POST['master_url'] ) ? esc_url_raw( trim( $_POST['master_url'] ) ) : '';

		if ( empty( $url ) ) {
			wp_send_json_error( __( 'URL vacía.', 'ultron' ) );
		}

		$response = wp_remote_get( trailingslashit( $url ) . 'wp-json/ultron-master/v1/ping', [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 200 ) {
			wp_send_json_success( __( 'Conexión exitosa con Ultron Master.', 'ultron' ) );
		} else {
			wp_send_json_error( sprintf( __( 'Respuesta inesperada: %d', 'ultron' ), $code ) );
		}
	}

	/**
	 * Prueba el token de GitHub vía AJAX.
	 *
	 * @return void
	 */
	public function handle_test_github(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Sin permisos.', 'ultron' ) );
		}

		check_ajax_referer( 'ultron_test_nonce' );

		$token = isset( $_POST['github_token'] ) ? sanitize_text_field( trim( $_POST['github_token'] ) ) : '';

		if ( empty( $token ) ) {
			wp_send_json_error( __( 'Token vacío.', 'ultron' ) );
		}

		$response = wp_remote_get( 'https://api.github.com/user', [
			'timeout' => 10,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/vnd.github+json',
			],
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 200 && isset( $body['login'] ) ) {
			wp_send_json_success( sprintf( __( 'Token válido. Usuario: %s', 'ultron' ), $body['login'] ) );
		} else {
			wp_send_json_error( __( 'Token inválido o sin permisos.', 'ultron' ) );
		}
	}

}
