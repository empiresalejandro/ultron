<?php
/**
 * Gestión de módulos de Ultron.
 *
 * Descubre módulos instalados, lee el índice remoto desde GitHub,
 * compara versiones y gestiona el estado activo/inactivo de cada módulo.
 *
 * @package Ultron
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ultron_Modules {

	/**
	 * URL del archivo modules.json en GitHub.
	 *
	 * @var string
	 */
	private string $modules_json_url = 'https://raw.githubusercontent.com/empiresalejandro/ultron/main/modules.json';

	/**
	 * Opción en BD donde se guardan los estados activo/inactivo.
	 *
	 * @var string
	 */
	private string $option_states = 'ultron_module_states';

	/**
	 * Constructor. Registra el manejo de acciones de activar/desactivar.
	 */
	public function __construct() {
		add_action( 'admin_post_ultron_activate_module',   [ $this, 'handle_activate' ] );
		add_action( 'admin_post_ultron_deactivate_module', [ $this, 'handle_deactivate' ] );
	}

	/**
	 * Maneja la activación de un módulo.
	 *
	 * @return void
	 */
	public function handle_activate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos para realizar esta acción.', 'ultron' ) );
		}

		check_admin_referer( 'ultron_module_action' );

		$slug = isset( $_POST['module_slug'] ) ? sanitize_key( $_POST['module_slug'] ) : '';

		if ( ! empty( $slug ) ) {
			$this->activate_module( $slug );
		}

		wp_redirect( admin_url( 'admin.php?page=ultron&tab=modules' ) );
		exit;
	}

	/**
	 * Maneja la desactivación de un módulo.
	 *
	 * @return void
	 */
	public function handle_deactivate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos para realizar esta acción.', 'ultron' ) );
		}

		check_admin_referer( 'ultron_module_action' );

		$slug = isset( $_POST['module_slug'] ) ? sanitize_key( $_POST['module_slug'] ) : '';

		if ( ! empty( $slug ) ) {
			$this->deactivate_module( $slug );
		}

		wp_redirect( admin_url( 'admin.php?page=ultron&tab=modules' ) );
		exit;
	}

	/**
	 * Obtiene los módulos instalados localmente escaneando la carpeta modules/.
	 *
	 * @return array
	 */
	public function get_local_modules(): array {
		$modules    = [];
		$module_dir = ULTRON_MODULES;

		if ( ! is_dir( $module_dir ) ) {
			return $modules;
		}

		$folders = array_filter( glob( $module_dir . '*' ), 'is_dir' );

		foreach ( $folders as $folder ) {
			$slug        = basename( $folder );
			$module_file = $folder . '/module.php';

			if ( ! file_exists( $module_file ) ) {
				continue;
			}

			$metadata = $this->get_module_metadata( $module_file );

			$modules[ $slug ] = [
				'slug'    => $slug,
				'name'    => $metadata['name']    ?? $slug,
				'version' => $metadata['version'] ?? '0.0.0',
				'active'  => $this->is_module_active( $slug ),
				'path'    => $folder,
			];
		}

		return $modules;
	}

	/**
	 * Lee la metadata de un módulo desde el header de su archivo principal.
	 *
	 * @param string $file Ruta al module.php del módulo.
	 * @return array
	 */
	private function get_module_metadata( string $file ): array {
		$data    = [];
		$content = file_get_contents( $file, false, null, 0, 1024 );

		if ( preg_match( '/Module Name:\s*(.+)/i', $content, $m ) ) {
			$data['name'] = trim( $m[1] );
		}
		if ( preg_match( '/Version:\s*(.+)/i', $content, $m ) ) {
			$data['version'] = trim( $m[1] );
		}

		return $data;
	}

	/**
	 * Obtiene el índice de módulos disponibles desde GitHub.
	 *
	 * @return array
	 */
	public function get_remote_modules(): array {
		$token = get_option( 'ultron_github_token', '' );
		$args  = [ 'timeout' => 10 ];

		if ( ! empty( $token ) ) {
			$args['headers'] = [ 'Authorization' => 'Bearer ' . $token ];
		}

		$response = wp_remote_get( $this->modules_json_url, $args );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return [];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Compara versiones locales con remotas.
	 *
	 * @return array
	 */
	public function get_modules_with_updates(): array {
		$local   = $this->get_local_modules();
		$remote  = $this->get_remote_modules();
		$updates = [];

		foreach ( $local as $slug => $module ) {
			if ( isset( $remote[ $slug ] ) ) {
				$remote_version = $remote[ $slug ]['version'] ?? '0.0.0';
				if ( version_compare( $remote_version, $module['version'], '>' ) ) {
					$updates[ $slug ] = [
						'local_version'  => $module['version'],
						'remote_version' => $remote_version,
					];
				}
			}
		}

		return $updates;
	}

	/**
	 * Comprueba si un módulo está activo.
	 *
	 * @param string $slug Slug del módulo.
	 * @return bool
	 */
	public function is_module_active( string $slug ): bool {
		$states = get_option( $this->option_states, [] );
		return isset( $states[ $slug ] ) && $states[ $slug ] === true;
	}

	/**
	 * Activa un módulo.
	 *
	 * @param string $slug Slug del módulo.
	 * @return void
	 */
	public function activate_module( string $slug ): void {
		$states          = get_option( $this->option_states, [] );
		$states[ $slug ] = true;
		update_option( $this->option_states, $states );
	}

	/**
	 * Desactiva un módulo.
	 *
	 * @param string $slug Slug del módulo.
	 * @return void
	 */
	public function deactivate_module( string $slug ): void {
		$states          = get_option( $this->option_states, [] );
		$states[ $slug ] = false;
		update_option( $this->option_states, $states );
	}

	/**
	 * Carga los módulos activos registrando su archivo principal.
	 *
	 * @return void
	 */
	public function load_active_modules(): void {
		$local = $this->get_local_modules();

		foreach ( $local as $slug => $module ) {
			if ( $module['active'] ) {
				$module_file = $module['path'] . '/module.php';
				if ( file_exists( $module_file ) ) {
					require_once $module_file;
				}
			}
		}
	}

}
