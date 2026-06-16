<?php
/**
 * Clase principal del módulo Database Monitor.
 *
 * Lista las tablas de la base de datos con su tamaño, número de filas
 * y estado de salud. Permite truncar tablas no-core de WordPress
 * mediante una página de confirmación con doble verificación.
 * El widget del Dashboard solo aparece si hay tablas en warning o danger.
 *
 * @package Ultron
 * @subpackage DB_Monitor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ultron_DB_Monitor {

	/**
	 * Umbral en bytes para el estado "warning" (50 MB).
	 *
	 * @var int
	 */
	private const THRESHOLD_WARNING = 50 * 1024 * 1024;

	/**
	 * Umbral en bytes para el estado "danger" (1 GB).
	 *
	 * @var int
	 */
	private const THRESHOLD_DANGER = 1024 * 1024 * 1024;

	/**
	 * Sufijos de tablas core de WordPress que no se pueden truncar.
	 *
	 * @var array
	 */
	private array $core_table_suffixes = [
		'users',
		'usermeta',
		'options',
		'posts',
		'postmeta',
		'terms',
		'term_taxonomy',
		'term_relationships',
		'termmeta',
		'comments',
		'commentmeta',
		'links',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_post_ultron_db_monitor_truncate', [ $this, 'handle_truncate' ] );
	}

	/**
	 * Comprueba si una tabla es core de WordPress.
	 *
	 * @param string $table_name Nombre completo de la tabla.
	 * @return bool
	 */
	private function is_core_table( string $table_name ): bool {
		global $wpdb;
		$without_prefix = preg_replace( '/^' . preg_quote( $wpdb->prefix, '/' ) . '/', '', $table_name );
		return in_array( $without_prefix, $this->core_table_suffixes, true );
	}

	/**
	 * Determina el estado de salud según el tamaño en bytes.
	 *
	 * @param int $size_bytes Tamaño en bytes.
	 * @return array{icon: string, label: string, status: string, badge: string}
	 */
	private function get_table_status( int $size_bytes ): array {
		if ( $size_bytes > self::THRESHOLD_DANGER ) {
			return [
				'icon'   => '🔴',
				'label'  => __( 'Danger', 'ultron' ),
				'status' => 'danger',
				'badge'  => 'danger',
			];
		}

		if ( $size_bytes > self::THRESHOLD_WARNING ) {
			return [
				'icon'   => '🟡',
				'label'  => __( 'Warning', 'ultron' ),
				'status' => 'warning',
				'badge'  => 'warning',
			];
		}

		return [
			'icon'   => '🟢',
			'label'  => __( 'OK', 'ultron' ),
			'status' => 'ok',
			'badge'  => 'ok',
		];
	}

	/**
	 * Obtiene la lista de tablas con tamaño, filas y estado.
	 *
	 * @return array
	 */
	private function get_tables(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					TABLE_NAME AS name,
					TABLE_ROWS AS rows_count,
					(DATA_LENGTH + INDEX_LENGTH) AS size_bytes
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = %s
				ORDER BY TABLE_NAME ASC",
				DB_NAME
			),
			ARRAY_A
		);

		$tables = [];

		foreach ( $results as $row ) {
			$size_bytes = (int) $row['size_bytes'];
			$tables[]   = [
				'name'     => $row['name'],
				'size_mb'  => round( $size_bytes / ( 1024 * 1024 ), 2 ),
				'size_bytes' => $size_bytes,
				'rows'     => (int) $row['rows_count'],
				'status'   => $this->get_table_status( $size_bytes ),
				'is_core'  => $this->is_core_table( $row['name'] ),
			];
		}

		return $tables;
	}

	/**
	 * Maneja la acción de truncate de una tabla.
	 *
	 * @return void
	 */
	public function handle_truncate(): void {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos para realizar esta acción.', 'ultron' ) );
		}

		check_admin_referer( 'ultron_db_monitor_truncate' );

		$table    = isset( $_POST['table_name'] )  ? sanitize_text_field( $_POST['table_name'] )  : '';
		$confirm  = isset( $_POST['confirm_name'] ) ? sanitize_text_field( $_POST['confirm_name'] ) : '';
		$redirect = admin_url( 'admin.php?page=ultron-monitor&tab=database' );

		$valid_tables = wp_list_pluck( $this->get_tables(), 'name' );

		if ( ! in_array( $table, $valid_tables, true ) ) {
			wp_redirect( add_query_arg( 'error', 'invalid_table', $redirect ) );
			exit;
		}

		if ( $this->is_core_table( $table ) ) {
			wp_redirect( add_query_arg( 'error', 'core_table', $redirect ) );
			exit;
		}

		if ( $confirm !== $table ) {
			$error_redirect = admin_url( 'admin.php?page=ultron-monitor&tab=database&action=truncate&table=' . rawurlencode( $table ) );
			wp_redirect( add_query_arg( 'error', 'name_mismatch', $error_redirect ) );
			exit;
		}

		$wpdb->query( "TRUNCATE TABLE `{$table}`" );

		wp_redirect( add_query_arg( 'truncated', rawurlencode( $table ), $redirect ) );
		exit;
	}

	/**
	 * Renderiza la página de confirmación de truncate.
	 *
	 * @param string $table Nombre de la tabla.
	 * @return void
	 */
	private function render_truncate_confirmation( string $table ): void {
		$valid_tables = wp_list_pluck( $this->get_tables(), 'name' );
		$back_url     = admin_url( 'admin.php?page=ultron-monitor&tab=database' );

		if ( ! in_array( $table, $valid_tables, true ) ) {
			echo '<div class="notice notice-error"><p>' . __( 'La tabla indicada no existe.', 'ultron' ) . '</p></div>';
			echo '<p><a href="' . esc_url( $back_url ) . '" class="button">' . __( 'Volver', 'ultron' ) . '</a></p>';
			return;
		}

		if ( $this->is_core_table( $table ) ) {
			echo '<div class="notice notice-error"><p>' . __( 'No se puede truncar una tabla core de WordPress.', 'ultron' ) . '</p></div>';
			echo '<p><a href="' . esc_url( $back_url ) . '" class="button">' . __( 'Volver', 'ultron' ) . '</a></p>';
			return;
		}
		?>
		<h2><?php echo sprintf( __( 'Confirmar truncate: %s', 'ultron' ), '<code>' . esc_html( $table ) . '</code>' ); ?></h2>

		<?php if ( isset( $_GET['error'] ) && $_GET['error'] === 'name_mismatch' ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php _e( 'El nombre escrito no coincide con la tabla. Operación cancelada.', 'ultron' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="ultron-confirm-box">
			<p><?php echo sprintf( __( 'Para confirmar, escribe el nombre exacto de la tabla (%s) y pulsa "Truncate".', 'ultron' ), '<code>' . esc_html( $table ) . '</code>' ); ?></p>

			<div class="ultron-confirm-warning">
				<strong><?php _e( 'Esta acción es irreversible.', 'ultron' ); ?></strong>
				<?php _e( 'Todos los datos de esta tabla se eliminarán permanentemente.', 'ultron' ); ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ultron_db_monitor_truncate' ); ?>
				<input type="hidden" name="action" value="ultron_db_monitor_truncate">
				<input type="hidden" name="table_name" value="<?php echo esc_attr( $table ); ?>">

				<label for="confirm_name"><?php _e( 'Nombre de la tabla:', 'ultron' ); ?></label>
				<input type="text" id="confirm_name" name="confirm_name"
				       class="regular-text" placeholder="<?php echo esc_attr( $table ); ?>">

				<div class="ultron-confirm-actions">
					<button type="submit" class="button button-primary"
					        style="background:var(--ultron-danger);border-color:var(--ultron-danger);">
						<?php _e( 'Truncate', 'ultron' ); ?>
					</button>
					<a href="<?php echo esc_url( $back_url ); ?>" class="button">
						<?php _e( 'Cancelar', 'ultron' ); ?>
					</a>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Renderiza la pestaña "Base de datos" dentro de Monitor.
	 *
	 * @return void
	 */
	public function render_tab(): void {

		// Página de confirmación de truncate.
		if ( isset( $_GET['action'], $_GET['table'] ) && $_GET['action'] === 'truncate' ) {
			$this->render_truncate_confirmation( sanitize_text_field( wp_unslash( $_GET['table'] ) ) );
			return;
		}

		$tables = $this->get_tables();
		?>

		<?php if ( isset( $_GET['truncated'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo sprintf( __( 'Tabla "%s" truncada correctamente.', 'ultron' ), esc_html( $_GET['truncated'] ) ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['error'] ) ) : ?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					switch ( $_GET['error'] ) {
						case 'core_table':
							_e( 'No se puede truncar una tabla core de WordPress.', 'ultron' );
							break;
						case 'invalid_table':
							_e( 'La tabla indicada no existe.', 'ultron' );
							break;
						default:
							_e( 'Ocurrió un error.', 'ultron' );
							break;
					}
					?>
				</p>
			</div>
		<?php endif; ?>

		<table class="ultron-table" style="max-width:100%;">
			<thead>
				<tr>
					<th><?php _e( 'Tabla', 'ultron' ); ?></th>
					<th><?php _e( 'Tamaño (MB)', 'ultron' ); ?></th>
					<th><?php _e( 'Filas', 'ultron' ); ?></th>
					<th><?php _e( 'Estado', 'ultron' ); ?></th>
					<th><?php _e( 'Acciones', 'ultron' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tables as $table ) : ?>
					<tr>
						<td><?php echo esc_html( $table['name'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $table['size_mb'], 2 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $table['rows'] ) ); ?></td>
						<td>
							<span class="ultron-badge <?php echo esc_attr( $table['status']['badge'] ); ?>">
								<?php echo esc_html( $table['status']['label'] ); ?>
							</span>
						</td>
						<td>
							<?php if ( $table['is_core'] ) : ?>
								<span style="color:var(--ultron-muted);font-size:12px;">
									<?php _e( 'Tabla core', 'ultron' ); ?>
								</span>
							<?php else : ?>
								<a class="ultron-action-link"
								   href="<?php echo esc_url( add_query_arg( [ 'action' => 'truncate', 'table' => rawurlencode( $table['name'] ) ] ) ); ?>">
									<?php _e( 'Truncate', 'ultron' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
	}

	/**
	 * Widget del Dashboard — solo muestra si hay tablas en warning o danger.
	 *
	 * @return void
	 */
	public function render_widget(): void {
		$tables   = $this->get_tables();
		$alerts   = array_filter( $tables, fn( $t ) => in_array( $t['status']['status'], [ 'warning', 'danger' ], true ) );

		if ( empty( $alerts ) ) {
			return;
		}

		$has_danger  = ! empty( array_filter( $alerts, fn( $t ) => $t['status']['status'] === 'danger' ) );
		$widget_class = 'ultron-widget ' . ( $has_danger ? 'is-danger' : 'is-warning' );
		?>
		<div class="<?php echo esc_attr( $widget_class ); ?>">
			<h3><?php _e( 'Database Monitor', 'ultron' ); ?></h3>
			<p><?php echo sprintf( __( '%d tabla(s) requieren atención:', 'ultron' ), count( $alerts ) ); ?></p>
			<?php foreach ( $alerts as $table ) : ?>
				<p>
					<span class="ultron-badge <?php echo esc_attr( $table['status']['badge'] ); ?>">
						<?php echo esc_html( $table['status']['label'] ); ?>
					</span>
					<strong><?php echo esc_html( $table['name'] ); ?></strong>
					— <?php echo esc_html( number_format_i18n( $table['size_mb'], 2 ) ); ?> MB
				</p>
			<?php endforeach; ?>
		</div>
		<?php
	}

}
