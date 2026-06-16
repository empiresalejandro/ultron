<?php
/**
 * Clase principal de Ultron.
 *
 * Inicializa el plugin, registra el menú, carga los módulos,
 * encola los assets y gestiona las pestañas del hub.
 *
 * @package Ultron
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ultron {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var Ultron|null
	 */
	private static ?Ultron $instance = null;

	/**
	 * Instancia del gestor de módulos.
	 *
	 * @var Ultron_Modules
	 */
	private Ultron_Modules $modules;

	/**
	 * Instancia del gestor de opciones.
	 *
	 * @var Ultron_Options
	 */
	private Ultron_Options $options;

	/**
	 * Instancia de la página Monitor.
	 *
	 * @var Ultron_Monitor_Page
	 */
	private Ultron_Monitor_Page $monitor_page;

	/**
	 * Obtiene la instancia única de la clase.
	 *
	 * @return Ultron
	 */
	public static function get_instance(): Ultron {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado. Carga dependencias y registra hooks.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->modules      = new Ultron_Modules();
		$this->options      = new Ultron_Options();
		$this->monitor_page = new Ultron_Monitor_Page( $this->modules );
		$this->modules->load_active_modules();

		add_action( 'admin_menu',             [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Carga los archivos del core necesarios.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		require_once ULTRON_PATH . 'core/class-modules.php';
		require_once ULTRON_PATH . 'core/class-options.php';
		require_once ULTRON_PATH . 'core/class-monitor-page.php';
	}

	/**
	 * Encola los assets de Ultron solo en páginas propias del plugin.
	 *
	 * @param string $hook_suffix Sufijo de la página actual.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$ultron_pages = [
			'toplevel_page_ultron',
			'ultron_page_ultron-monitor',
		];

		if ( ! in_array( $hook_suffix, $ultron_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'ultron-admin',
			ULTRON_URL . 'core/assets/ultron-admin.css',
			[],
			ULTRON_VERSION
		);
	}

	/**
	 * Registra el menú principal de Ultron en el admin de WordPress.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Ultron', 'ultron' ),
			__( 'Ultron', 'ultron' ),
			'manage_options',
			'ultron',
			[ $this, 'render_hub' ],
			'dashicons-superhero',
			2.1
		);
	}

	/**
	 * Renderiza la página principal del hub con pestañas.
	 *
	 * @return void
	 */
	public function render_hub(): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
		?>
		<div class="wrap">
			<h1>Ultron</h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=ultron&tab=dashboard"
				   class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Dashboard', 'ultron' ); ?>
				</a>
				<a href="?page=ultron&tab=modules"
				   class="nav-tab <?php echo $tab === 'modules' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Módulos', 'ultron' ); ?>
				</a>
				<a href="?page=ultron&tab=options"
				   class="nav-tab <?php echo $tab === 'options' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Opciones', 'ultron' ); ?>
				</a>
			</nav>

			<div class="tab-content" style="margin-top: 20px;">
				<?php
				switch ( $tab ) {
					case 'modules':
						$this->render_tab_modules();
						break;
					case 'options':
						$this->render_tab_options();
						break;
					default:
						$this->render_tab_dashboard();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderiza la pestaña Dashboard.
	 *
	 * @return void
	 */
	private function render_tab_dashboard(): void {
		$local  = $this->modules->get_local_modules();
		$active = array_filter( $local, fn( $m ) => $m['active'] );

		if ( empty( $active ) ) {
			echo '<p>' . __( 'No hay módulos activos. Activa módulos desde la pestaña Módulos.', 'ultron' ) . '</p>';
			return;
		}
		?>
		<div class="ultron-widgets">
			<?php do_action( 'ultron_dashboard_widgets' ); ?>
		</div>
		<?php
	}

	/**
	 * Renderiza la pestaña Módulos.
	 *
	 * @return void
	 */
	private function render_tab_modules(): void {
		$local   = $this->modules->get_local_modules();
		$remote  = $this->modules->get_remote_modules();
		$updates = $this->modules->get_modules_with_updates();
		?>

		<p class="ultron-section-title"><?php _e( 'Módulos instalados', 'ultron' ); ?></p>

		<?php if ( empty( $local ) ) : ?>
			<p><?php _e( 'No hay módulos instalados.', 'ultron' ); ?></p>
		<?php else : ?>
			<table class="ultron-table">
				<thead>
					<tr>
						<th><?php _e( 'Módulo', 'ultron' ); ?></th>
						<th><?php _e( 'Versión', 'ultron' ); ?></th>
						<th><?php _e( 'Estado', 'ultron' ); ?></th>
						<th><?php _e( 'Acción', 'ultron' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $local as $slug => $module ) : ?>
						<tr>
							<td>
								<?php echo esc_html( $module['name'] ); ?>
								<?php if ( isset( $updates[ $slug ] ) ) : ?>
									<span class="ultron-badge warning" style="margin-left:6px;">
										↑ <?php echo esc_html( $updates[ $slug ]['remote_version'] ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $module['version'] ); ?></td>
							<td>
								<?php if ( $module['active'] ) : ?>
									<span class="ultron-status-active"><?php _e( 'Activo', 'ultron' ); ?></span>
								<?php else : ?>
									<span class="ultron-status-inactive"><?php _e( 'Inactivo', 'ultron' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'ultron_module_action' ); ?>
									<input type="hidden" name="module_slug" value="<?php echo esc_attr( $slug ); ?>">
									<?php if ( $module['active'] ) : ?>
										<input type="hidden" name="action" value="ultron_deactivate_module">
										<button type="submit" class="button">
											<?php _e( 'Desactivar', 'ultron' ); ?>
										</button>
									<?php else : ?>
										<input type="hidden" name="action" value="ultron_activate_module">
										<button type="submit" class="button button-primary">
											<?php _e( 'Activar', 'ultron' ); ?>
										</button>
									<?php endif; ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p class="ultron-section-title" style="margin-top:30px;"><?php _e( 'Módulos disponibles en GitHub', 'ultron' ); ?></p>

		<?php if ( empty( $remote ) ) : ?>
			<p><?php _e( 'No se pudo conectar con GitHub o no hay módulos disponibles.', 'ultron' ); ?></p>
		<?php else : ?>
			<table class="ultron-table">
				<thead>
					<tr>
						<th><?php _e( 'Módulo', 'ultron' ); ?></th>
						<th><?php _e( 'Versión', 'ultron' ); ?></th>
						<th><?php _e( 'Categoría', 'ultron' ); ?></th>
						<th><?php _e( 'Tipo', 'ultron' ); ?></th>
						<th><?php _e( 'Incluido', 'ultron' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $remote as $slug => $module ) : ?>
						<tr>
							<td><?php echo esc_html( $module['name'] ?? $slug ); ?></td>
							<td><?php echo esc_html( $module['version'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $module['category'] ?? '—' ); ?></td>
							<td><?php echo ! empty( $module['private'] ) ? __( 'Privado', 'ultron' ) : __( 'Público', 'ultron' ); ?></td>
							<td><?php echo ! empty( $module['bundled'] ) ? __( 'Sí', 'ultron' ) : __( 'No', 'ultron' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php
	}

	/**
	 * Renderiza la pestaña Opciones.
	 *
	 * @return void
	 */
	private function render_tab_options(): void {
		$master_url         = $this->options->get_master_url();
		$github_token       = $this->options->get_github_token();
		$wp_history_limit   = $this->options->get_wp_monitor_history_limit();
		$st_history_limit   = $this->options->get_st_monitor_history_limit();
		$error_log_limit_mb = $this->options->get_error_log_limit_mb();
		$update_checker     = $this->options->get_update_checker_enabled();
		$delete_on_uninstall = $this->options->get_delete_on_uninstall();
		$nonce              = wp_create_nonce( 'ultron_test_nonce' );

		$saved = isset( $_GET['saved'] ) ? sanitize_key( $_GET['saved'] ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_key( $_GET['error'] ) : '';

		$saved_messages = [
			'master'     => __( 'URL de Ultron Master guardada correctamente.', 'ultron' ),
			'github'     => __( 'Token de GitHub guardado correctamente.', 'ultron' ),
			'wp_history' => __( 'Límite histórico de WordPress Monitor guardado.', 'ultron' ),
			'st_history' => __( 'Límite histórico de Storage Monitor guardado.', 'ultron' ),
			'error_log'  => __( 'Umbral de error.log guardado correctamente.', 'ultron' ),
			'update_checker' => __( 'Configuración de actualizaciones guardada.', 'ultron' ),
			'uninstall'  => __( 'Preferencia de desinstalación guardada.', 'ultron' ),
		];
		?>

		<?php if ( $saved && isset( $saved_messages[ $saved ] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $saved_messages[ $saved ] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $error === 'invalid_limit' ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php _e( 'El límite debe ser un número mayor que cero.', 'ultron' ); ?></p>
			</div>
		<?php endif; ?>

		<!-- Ultron Master -->
		<div class="ultron-options-section">
			<h2><?php _e( 'Ultron Master', 'ultron' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ultron_save_options' ); ?>
				<input type="hidden" name="action" value="ultron_save_options">
				<input type="hidden" name="save_field" value="master_url">
				<table class="form-table">
					<tr>
						<th><label for="master_url"><?php _e( 'URL Ultron Master', 'ultron' ); ?></label></th>
						<td>
							<input type="url" id="master_url" name="master_url"
							       value="<?php echo esc_attr( $master_url ); ?>"
							       class="regular-text" placeholder="https://tudominio.com">
							<button type="button" class="button" id="test-master">
								<?php _e( 'Probar conexión', 'ultron' ); ?>
							</button>
							<span id="master-result" style="margin-left:8px;font-size:13px;"></span>
							<p class="description"><?php _e( 'URL del sitio donde está instalado Ultron Master.', 'ultron' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary" id="master-save-btn" disabled>
						<?php _e( 'Guardar URL', 'ultron' ); ?>
					</button>
					<span style="margin-left:10px;font-size:12px;color:var(--ultron-muted);">
						<?php _e( 'Prueba la conexión antes de guardar.', 'ultron' ); ?>
					</span>
				</p>
			</form>
		</div>

		<!-- GitHub -->
		<div class="ultron-options-section">
			<h2><?php _e( 'GitHub', 'ultron' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ultron_save_options' ); ?>
				<input type="hidden" name="action" value="ultron_save_options">
				<input type="hidden" name="save_field" value="github_token">
				<table class="form-table">
					<tr>
						<th><label for="github_token"><?php _e( 'Token de GitHub', 'ultron' ); ?></label></th>
						<td>
							<input type="password" id="github_token" name="github_token"
							       value="<?php echo ! empty( $github_token ) ? esc_attr( $github_token ) : ''; ?>"
							       class="regular-text"
							       placeholder="<?php echo ! empty( $github_token ) ? '••••••••••••••••' : __( 'Introduce el token', 'ultron' ); ?>">
							<button type="button" class="button" id="test-github">
								<?php _e( 'Probar conexión', 'ultron' ); ?>
							</button>
							<span id="github-result" style="margin-left:8px;font-size:13px;"></span>
							<p class="description"><?php _e( 'Token de acceso personal para repositorios privados.', 'ultron' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary" id="github-save-btn" disabled>
						<?php _e( 'Guardar token', 'ultron' ); ?>
					</button>
					<span style="margin-left:10px;font-size:12px;color:var(--ultron-muted);">
						<?php _e( 'Prueba la conexión antes de guardar.', 'ultron' ); ?>
					</span>
				</p>
			</form>
		</div>

		<!-- Actualizaciones -->
		<div class="ultron-options-section">
			<h2><?php _e( 'Actualizaciones', 'ultron' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ultron_save_options' ); ?>
				<input type="hidden" name="action" value="ultron_save_options">
				<input type="hidden" name="save_field" value="update_checker">
				<table class="form-table">
					<tr>
						<th><label for="update_checker"><?php _e( 'Plugin Update Checker', 'ultron' ); ?></label></th>
						<td>
							<input type="checkbox" id="update_checker" name="update_checker" value="1"
							       <?php checked( $update_checker, true ); ?>>
							<label for="update_checker"><?php _e( 'Activar detección automática de actualizaciones (cada 24h)', 'ultron' ); ?></label>
							<p class="description"><?php _e( 'WordPress avisará cuando haya una nueva versión disponible en GitHub.', 'ultron' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php _e( 'Guardar', 'ultron' ); ?></button>
				</p>
			</form>
		</div>

		<!-- Histórico de datos -->
		<div class="ultron-options-section">
			<h2><?php _e( 'Histórico de datos', 'ultron' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ultron_save_options' ); ?>
				<input type="hidden" name="action" value="ultron_save_options">
				<input type="hidden" name="save_field" value="wp_monitor_history_limit">
				<table class="form-table">
					<tr>
						<th><label for="wp_monitor_history_limit"><?php _e( 'Límite WordPress Monitor', 'ultron' ); ?></label></th>
						<td>
							<input type="number" id="wp_monitor_history_limit" name="wp_monitor_history_limit"
							       value="<?php echo esc_attr( $wp_history_limit ); ?>"
							       class="small-text" min="1" step="1">
							<?php _e( 'registros', 'ultron' ); ?>
							<p class="description"><?php _e( 'Número máximo de snapshots guardados para WordPress Monitor.', 'ultron' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php _e( 'Guardar', 'ultron' ); ?></button>
				</p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ultron_save_options' ); ?>
				<input type="hidden" name="action" value="ultron_save_options">
				<input type="hidden" name="save_field" value="st_monitor_history_limit">
				<table class="form-table">
					<tr>
						<th><label for="st_monitor_history_limit"><?php _e( 'Límite Storage Monitor', 'ultron' ); ?></label></th>
						<td>
							<input type="number" id="st_monitor_history_limit" name="st_monitor_history_limit"
							       value="<?php echo esc_attr( $st_history_limit ); ?>"
							       class="small-text" min="1" step="1">
							<?php _e( 'registros', 'ultron' ); ?>
							<p class="description"><?php _e( 'Número máximo de snapshots guardados para Storage Monitor.', 'ultron' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php _e( 'Guardar', 'ultron' ); ?></button>
				</p>
			</form>
		</div>

		<!-- Alertas -->
		<div class="ultron-options-section">
			<h2><?php _e( 'Alertas', 'ultron' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ultron_save_options' ); ?>
				<input type="hidden" name="action" value="ultron_save_options">
				<input type="hidden" name="save_field" value="error_log_limit_mb">
				<table class="form-table">
					<tr>
						<th><label for="error_log_limit_mb"><?php _e( 'Umbral error.log', 'ultron' ); ?></label></th>
						<td>
							<input type="number" id="error_log_limit_mb" name="error_log_limit_mb"
							       value="<?php echo esc_attr( $error_log_limit_mb ); ?>"
							       class="small-text" min="1" step="1">
							<?php _e( 'MB', 'ultron' ); ?>
							<p class="description"><?php _e( 'El Dashboard alertará cuando error.log supere este tamaño.', 'ultron' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php _e( 'Guardar', 'ultron' ); ?></button>
				</p>
			</form>
		</div>

		<!-- Desinstalación -->
		<div class="ultron-options-section" style="border-color: #f8d7da;">
			<h2 style="color:var(--ultron-danger);"><?php _e( 'Desinstalación', 'ultron' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ultron_save_options' ); ?>
				<input type="hidden" name="action" value="ultron_save_options">
				<input type="hidden" name="save_field" value="delete_on_uninstall">
				<table class="form-table">
					<tr>
						<th><label for="delete_on_uninstall"><?php _e( 'Eliminar todos los datos', 'ultron' ); ?></label></th>
						<td>
							<input type="checkbox" id="delete_on_uninstall" name="delete_on_uninstall" value="1"
							       <?php checked( $delete_on_uninstall, true ); ?>>
							<label for="delete_on_uninstall">
								<?php _e( 'Al desinstalar Ultron, eliminar tablas de base de datos, opciones y archivos de módulos.', 'ultron' ); ?>
							</label>
							<p class="description" style="color:var(--ultron-danger);">
								<?php _e( '⚠️ Esta acción es irreversible. Todos los datos históricos se perderán permanentemente.', 'ultron' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php _e( 'Guardar', 'ultron' ); ?></button>
				</p>
			</form>
		</div>

		<!-- Información -->
		<div class="ultron-options-section">
			<h2><?php _e( 'Información', 'ultron' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php _e( 'Versión de Ultron', 'ultron' ); ?></th>
					<td><span style="font-size:13px;"><?php echo esc_html( ULTRON_VERSION ); ?></span></td>
				</tr>
			</table>
		</div>

		<script>
		( function() {
			const ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
			const nonce   = '<?php echo esc_js( $nonce ); ?>';

			function showResult( id, success, message ) {
				const el = document.getElementById( id );
				el.textContent = message;
				el.style.color = success ? '#00a32a' : '#d63638';
			}

			document.getElementById( 'test-master' ).addEventListener( 'click', function() {
				const url = document.getElementById( 'master_url' ).value.trim();
				if ( ! url ) {
					showResult( 'master-result', false, '<?php echo esc_js( __( 'Introduce una URL.', 'ultron' ) ); ?>' );
					return;
				}
				this.disabled = true;
				showResult( 'master-result', true, '<?php echo esc_js( __( 'Probando...', 'ultron' ) ); ?>' );
				const data = new FormData();
				data.append( 'action', 'ultron_test_master' );
				data.append( '_ajax_nonce', nonce );
				data.append( 'master_url', url );
				fetch( ajaxUrl, { method: 'POST', body: data } )
					.then( r => r.json() )
					.then( res => {
						showResult( 'master-result', res.success, res.data );
						document.getElementById( 'master-save-btn' ).disabled = ! res.success;
					} )
					.catch( () => {
						showResult( 'master-result', false, '<?php echo esc_js( __( 'Error de conexión.', 'ultron' ) ); ?>' );
					} )
					.finally( () => { this.disabled = false; } );
			} );

			document.getElementById( 'test-github' ).addEventListener( 'click', function() {
				const token = document.getElementById( 'github_token' ).value.trim();
				if ( ! token ) {
					showResult( 'github-result', false, '<?php echo esc_js( __( 'Introduce un token.', 'ultron' ) ); ?>' );
					return;
				}
				this.disabled = true;
				showResult( 'github-result', true, '<?php echo esc_js( __( 'Probando...', 'ultron' ) ); ?>' );
				const data = new FormData();
				data.append( 'action', 'ultron_test_github' );
				data.append( '_ajax_nonce', nonce );
				data.append( 'github_token', token );
				fetch( ajaxUrl, { method: 'POST', body: data } )
					.then( r => r.json() )
					.then( res => {
						showResult( 'github-result', res.success, res.data );
						document.getElementById( 'github-save-btn' ).disabled = ! res.success;
					} )
					.catch( () => {
						showResult( 'github-result', false, '<?php echo esc_js( __( 'Error de conexión.', 'ultron' ) ); ?>' );
					} )
					.finally( () => { this.disabled = false; } );
			} );
		} )();
		</script>

		<?php
	}

}
