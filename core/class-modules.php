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
		add_action( 'admin_post_ultron_update_module',     [ $this, 'handle_update' ] );
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
	 * Maneja la actualización de un módulo desde GitHub.
	 *
	 * @return void
	 */
	public function handle_update(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos para realizar esta acción.', 'ultron' ) );
		}

		check_admin_referer( 'ultron_module_action' );

		$slug     = isset( $_POST['module_slug'] ) ? sanitize_key( $_POST['module_slug'] ) : '';
		$redirect = admin_url( 'admin.php?page=ultron&tab=modules' );

		if ( empty( $slug ) ) {
			wp_redirect( add_query_arg( 'error', 'invalid_module', $redirect ) );
			exit;
		}

		$result = $this->download_and_install_module( $slug );

		if ( is_wp_error( $result ) ) {
			wp_redirect( add_query_arg( [ 'error' => 'update_failed', 'module' => $slug ], $redirect ) );
			exit;
		}

		wp_redirect( add_query_arg( [ 'updated' => $slug ], $redirect ) );
		exit;
	}

	/**
	 * Descarga e instala/actualiza un módulo desde su repositorio de GitHub,
	 * usando el tag correspondiente a la versión publicada en modules.json.
	 *
	 * @param string $slug Slug del módulo (clave en modules.json).
	 * @return true|WP_Error
	 */
	public function download_and_install_module( string $slug ): bool|WP_Error {
		$remote = $this->get_remote_modules();

		if ( ! isset( $remote[ $slug ] ) ) {
			return new WP_Error( 'ultron_module_not_found', __( 'El módulo no existe en el índice remoto.', 'ultron' ) );
		}

		$module_info = $remote[ $slug ];

		if ( ! empty( $module_info['bundled'] ) ) {
			return new WP_Error( 'ultron_module_bundled', __( 'Este módulo viene incluido con Ultron y no se actualiza desde GitHub.', 'ultron' ) );
		}

		if ( empty( $module_info['repo'] ) ) {
			return new WP_Error( 'ultron_module_no_repo', __( 'El módulo no tiene un repositorio configurado.', 'ultron' ) );
		}

		$version = $module_info['version'] ?? '';

		if ( empty( $version ) ) {
			return new WP_Error( 'ultron_module_no_version', __( 'El módulo no tiene una versión definida.', 'ultron' ) );
		}

		// Construir la URL de descarga vía la API de GitHub (zipball por tag).
		$download_url = sprintf(
			'https://api.github.com/repos/%s/zipball/v%s',
			$module_info['repo'],
			$version
		);

		$args = [
			'timeout'  => 30,
			'headers'  => [ 'Accept' => 'application/vnd.github+json' ],
			'stream'   => true,
			'filename' => wp_tempnam( $slug . '.zip' ),
		];

		if ( ! empty( $module_info['private'] ) ) {
			$token = get_option( 'ultron_github_token', '' );

			if ( empty( $token ) ) {
				return new WP_Error( 'ultron_missing_token', __( 'Este módulo es privado y requiere un token de GitHub configurado en Opciones.', 'ultron' ) );
			}

			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( $download_url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			@unlink( $args['filename'] );
			return new WP_Error( 'ultron_download_failed', sprintf( __( 'GitHub respondió con código %d al descargar el módulo.', 'ultron' ), $code ) );
		}

		$zip_path = $args['filename'];

		// Extraer el zip a una carpeta temporal.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmp_extract_dir = trailingslashit( get_temp_dir() ) . 'ultron-' . $slug . '-' . wp_generate_password( 6, false );

		$unzip_result = unzip_file( $zip_path, $tmp_extract_dir );

		@unlink( $zip_path );

		if ( is_wp_error( $unzip_result ) ) {
			return $unzip_result;
		}

		// GitHub empaqueta el contenido dentro de una única carpeta raíz
		// con nombre tipo "{repo}-{sha}". La localizamos.
		$extracted_items = glob( $tmp_extract_dir . '/*', GLOB_ONLYDIR );

		if ( empty( $extracted_items ) || ! isset( $extracted_items[0] ) ) {
			$this->delete_directory( $tmp_extract_dir );
			return new WP_Error( 'ultron_zip_structure', __( 'No se pudo localizar el contenido del módulo dentro del zip.', 'ultron' ) );
		}

		$source_dir = $extracted_items[0];
		$target_dir = ULTRON_MODULES . $slug;

		// Reemplazar el módulo existente, si lo hay.
		if ( is_dir( $target_dir ) ) {
			$this->delete_directory( $target_dir );
		}

		$moved = @rename( $source_dir, $target_dir );

		// Limpiar la carpeta temporal (rename ya movió el contenido si tuvo éxito).
		$this->delete_directory( $tmp_extract_dir );

		if ( ! $moved ) {
			return new WP_Error( 'ultron_move_failed', __( 'No se pudo mover el módulo descargado a su carpeta final.', 'ultron' ) );
		}

		return true;
	}

	/**
	 * Elimina un directorio de forma recursiva.
	 *
	 * @param string $dir Ruta del directorio.
	 * @return void
	 */
	private function delete_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() );
			} else {
				@unlink( $item->getPathname() );
			}
		}

		@rmdir( $dir );
	}

}
