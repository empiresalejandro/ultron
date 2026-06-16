<?php
/**
 * Página "Opciones" de Ultron.
 *
 * Submenú independiente (al mismo nivel que Monitor) con la
 * configuración general del plugin: GitHub, actualizaciones,
 * histórico de datos, alertas y desinstalación.
 *
 * @package Ultron
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ultron_Options_Page {

	/**
	 * Instancia del gestor de opciones.
	 *
	 * @var Ultron_Options
	 */
	private Ultron_Options $options;

	/**
	 * Constructor.
	 *
	 * @param Ultron_Options $options Instancia del gestor de opciones.
	 */
	public function __construct( Ultron_Options $options ) {
		$this->options = $options;
		add_action( 'admin_menu', [ $this, 'register_menu' ], 30 );
	}

	/**
	 * Registra el submenú "Opciones".
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'ultron',
			__( 'Opciones', 'ultron' ),
			__( 'Opciones', 'ultron' ),
			'manage_options',
			'ultron-options',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Renderiza la página completa de Opciones.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$github_token        = $this->options->get_github_token();
		$wp_history_limit    = $this->options->get_wp_monitor_history_limit();
		$st_history_limit    = $this->options->get_st_monitor_history_limit();
		$error_log_limit_mb  = $this->options->get_error_log_limit_mb();
		$update_checker      = $this->options->get_update_checker_enabled();
		$delete_on_uninstall = $this->options->get_delete_on_uninstall();
		$nonce               = wp_create_nonce( 'ultron_test_nonce' );

		$saved = isset( $_GET['saved'] ) ? sanitize_key( $_GET['saved'] ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_key( $_GET['error'] ) : '';

		$saved_messages = [
			'github'         => __( 'Token de GitHub guardado correctamente.', 'ultron' ),
			'wp_history'     => __( 'Límite histórico de WordPress Monitor guardado.', 'ultron' ),
			'st_history'     => __( 'Límite histórico de Storage Monitor guardado.', 'ultron' ),
			'error_log'      => __( 'Umbral de error.log guardado correctamente.', 'ultron' ),
			'update_checker' => __( 'Configuración de actualizaciones guardada.', 'ultron' ),
			'uninstall'      => __( 'Preferencia de desinstalación guardada.', 'ultron' ),
		];
		?>
		<div class="wrap">
			<h1><?php _e( 'Opciones', 'ultron' ); ?></h1>

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

			<!-- Versión -->
			<div class="ultron-options-section">
				<h2><?php _e( 'Versión', 'ultron' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php _e( 'Versión de Ultron', 'ultron' ); ?></th>
						<td><span style="font-size:13px;"><?php echo esc_html( ULTRON_VERSION ); ?></span></td>
					</tr>
				</table>
			</div>

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
