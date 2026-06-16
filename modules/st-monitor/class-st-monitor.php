<?php
/**
 * Clase principal del módulo Storage Monitor.
 *
 * Recopila información de almacenamiento (inodos y tamaño en disco) de la
 * instalación de WordPress y de la carpeta uploads, desglosada por año y mes.
 * Vigila archivos error.log con umbral configurable.
 *
 * @package Ultron
 * @subpackage ST_Monitor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ultron_ST_Monitor {

	private string $table         = 'ultron_st_monitor';
	private string $option_limit  = 'ultron_st_monitor_history_limit';
	private int    $default_limit = 100;

	public function __construct() {
		add_action( 'admin_post_ultron_st_monitor_refresh', [ $this, 'handle_refresh' ] );
		add_action( 'admin_post_ultron_st_monitor_export',  [ $this, 'handle_export' ] );
	}

	private function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . $this->table;
	}

	public function maybe_create_table(): void {
		global $wpdb;
		$table_name      = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			data LONGTEXT NOT NULL,
			PRIMARY KEY (id)
		) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function handle_refresh(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sin permisos.', 'ultron' ) );
		}
		check_admin_referer( 'ultron_st_monitor_refresh' );
		$this->save_snapshot( $this->collect_data() );
		wp_redirect( admin_url( 'admin.php?page=ultron-monitor&tab=storage&refreshed=1' ) );
		exit;
	}

	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sin permisos.', 'ultron' ) );
		}
		check_admin_referer( 'ultron_st_monitor_export' );
		$snapshot = $this->get_latest_snapshot();
		if ( ! $snapshot ) {
			wp_die( __( 'No hay datos para exportar.', 'ultron' ) );
		}
		$filename = 'ultron-st-monitor-' . gmdate( 'Y-m-d-His' ) . '.json';
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $snapshot, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Recorre un directorio recursivamente acumulando inodos y tamaño.
	 *
	 * @param string $path Ruta absoluta.
	 * @return array{inodes: int, size: int}
	 */
	private function scan_directory( string $path ): array {
		$inodes = 0;
		$size   = 0;

		if ( ! is_dir( $path ) ) {
			return [ 'inodes' => 0, 'size' => 0 ];
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$path,
					FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
				),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $item ) {
				$inodes++;
				if ( $item->isFile() ) {
					$size += $item->getSize();
				}
			}
		} catch ( Exception $e ) {
			// Ignorar errores de permisos.
		}

		$inodes++; // El propio directorio raíz.

		return [ 'inodes' => $inodes, 'size' => $size ];
	}

	/**
	 * Verifica los archivos error.log y su tamaño.
	 *
	 * @return array
	 */
	private function check_error_logs(): array {
		$limit_mb    = (int) get_option( 'ultron_error_log_limit_mb', 100 );
		$limit_bytes = $limit_mb * 1024 * 1024;

		$log_paths = [
			'error.log'          => ABSPATH . 'error.log',
			'wp-admin/error.log' => ABSPATH . 'wp-admin/error.log',
		];

		$results = [];

		foreach ( $log_paths as $label => $path ) {
			$exists = file_exists( $path );
			$size   = $exists ? filesize( $path ) : 0;
			$alert  = $exists && $size > $limit_bytes;

			$results[ $label ] = [
				'exists'   => $exists,
				'size'     => $size,
				'alert'    => $alert,
				'limit_mb' => $limit_mb,
			];
		}

		return $results;
	}

	/**
	 * Recopila los datos de almacenamiento.
	 *
	 * @return array
	 */
	private function collect_data(): array {
		$wp_path      = ABSPATH;
		$uploads_dir  = wp_upload_dir();
		$uploads_path = $uploads_dir['basedir'];

		$wp_totals      = $this->scan_directory( $wp_path );
		$uploads_totals = $this->scan_directory( $uploads_path );
		$error_logs     = $this->check_error_logs();

		$years = [];

		if ( is_dir( $uploads_path ) ) {
			foreach ( scandir( $uploads_path ) as $year_entry ) {
				if ( $year_entry === '.' || $year_entry === '..' ) continue;

				$year_path = $uploads_path . '/' . $year_entry;

				if ( ! is_dir( $year_path ) || ! preg_match( '/^\d{4}$/', $year_entry ) ) continue;

				$months      = [];
				$year_inodes = 1;
				$year_size   = 0;

				foreach ( scandir( $year_path ) as $month_entry ) {
					if ( $month_entry === '.' || $month_entry === '..' ) continue;

					$month_path = $year_path . '/' . $month_entry;

					if ( ! is_dir( $month_path ) || ! preg_match( '/^\d{2}$/', $month_entry ) ) continue;

					$month_totals = $this->scan_directory( $month_path );

					$months[ $month_entry ] = [
						'inodes' => $month_totals['inodes'],
						'size'   => $month_totals['size'],
					];

					$year_inodes += $month_totals['inodes'];
					$year_size   += $month_totals['size'];
				}

				ksort( $months );

				$years[ $year_entry ] = [
					'inodes' => $year_inodes,
					'size'   => $year_size,
					'months' => $months,
				];
			}

			ksort( $years );
		}

		return [
			'wordpress'      => [
				'inodes' => $wp_totals['inodes'],
				'size'   => $wp_totals['size'],
			],
			'uploads'        => [
				'inodes' => $uploads_totals['inodes'],
				'size'   => $uploads_totals['size'],
			],
			'uploads_years'  => $years,
			'error_logs'     => $error_logs,
		];
	}

	private function save_snapshot( array $data ): void {
		global $wpdb;
		$wpdb->insert(
			$this->get_table_name(),
			[
				'created_at' => current_time( 'mysql' ),
				'data'       => wp_json_encode( $data ),
			],
			[ '%s', '%s' ]
		);
		$this->enforce_history_limit();
	}

	private function enforce_history_limit(): void {
		global $wpdb;
		$table_name = $this->get_table_name();
		$limit      = (int) get_option( $this->option_limit, $this->default_limit );
		if ( $limit <= 0 ) return;
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		if ( $total > $limit ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} ORDER BY id ASC LIMIT %d", $total - $limit ) );
		}
	}

	private function get_latest_snapshot(): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			"SELECT data, created_at FROM {$this->get_table_name()} ORDER BY id DESC LIMIT 1",
			ARRAY_A
		);
		if ( ! $row ) return null;
		$data               = json_decode( $row['data'], true );
		$data['created_at'] = $row['created_at'];
		return $data;
	}

	private function format_size( int $bytes ): string {
		if ( $bytes >= 1024 * 1024 * 1024 ) return round( $bytes / ( 1024 * 1024 * 1024 ), 2 ) . ' GB';
		if ( $bytes >= 1024 * 1024 )        return round( $bytes / ( 1024 * 1024 ), 2 ) . ' MB';
		if ( $bytes >= 1024 )               return round( $bytes / 1024, 2 ) . ' KB';
		return $bytes . ' B';
	}

	private function get_month_name( string $month_number ): string {
		$months = [
			'01' => __( 'Enero', 'ultron' ),   '02' => __( 'Febrero', 'ultron' ),
			'03' => __( 'Marzo', 'ultron' ),    '04' => __( 'Abril', 'ultron' ),
			'05' => __( 'Mayo', 'ultron' ),     '06' => __( 'Junio', 'ultron' ),
			'07' => __( 'Julio', 'ultron' ),    '08' => __( 'Agosto', 'ultron' ),
			'09' => __( 'Septiembre', 'ultron' ), '10' => __( 'Octubre', 'ultron' ),
			'11' => __( 'Noviembre', 'ultron' ), '12' => __( 'Diciembre', 'ultron' ),
		];
		return $months[ $month_number ] ?? $month_number;
	}

	/**
	 * Renderiza la pestaña Almacenamiento.
	 *
	 * @return void
	 */
	public function render_tab(): void {
		$this->maybe_create_table();
		$snapshot = $this->get_latest_snapshot();
		?>

		<?php if ( isset( $_GET['refreshed'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Datos actualizados correctamente.', 'ultron' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="ultron-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ultron_st_monitor_refresh' ); ?>
				<input type="hidden" name="action" value="ultron_st_monitor_refresh">
				<button type="submit" class="button button-primary"><?php _e( 'Actualizar datos', 'ultron' ); ?></button>
			</form>
			<?php if ( $snapshot ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ultron_st_monitor_export' ); ?>
					<input type="hidden" name="action" value="ultron_st_monitor_export">
					<button type="submit" class="button"><?php _e( 'Exportar JSON', 'ultron' ); ?></button>
				</form>
			<?php endif; ?>
		</div>

		<div class="ultron-actions-meta">
			<?php _e( 'Esta operación puede tardar varios segundos en sitios grandes.', 'ultron' ); ?>
			<?php if ( $snapshot ) : ?>
				&nbsp;&mdash;&nbsp;
				<?php echo sprintf( __( 'Última actualización: %s', 'ultron' ), esc_html( $snapshot['created_at'] ) ); ?>
			<?php endif; ?>
		</div>

		<?php if ( ! $snapshot ) : ?>
			<p><?php _e( 'No hay datos aún. Pulsa "Actualizar datos" para generar el primer snapshot.', 'ultron' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<!-- Error logs -->
		<?php if ( ! empty( $snapshot['error_logs'] ) ) : ?>
			<?php foreach ( $snapshot['error_logs'] as $log_name => $log ) : ?>
				<?php if ( $log['exists'] && $log['alert'] ) : ?>
					<div class="notice notice-warning" style="max-width:700px;">
						<p>
							<strong><?php _e( 'Alerta:', 'ultron' ); ?></strong>
							<?php echo sprintf(
								__( 'El archivo %s supera el umbral configurado (%s MB). Tamaño actual: %s.', 'ultron' ),
								'<code>' . esc_html( $log_name ) . '</code>',
								esc_html( $log['limit_mb'] ),
								esc_html( $this->format_size( $log['size'] ) )
							); ?>
						</p>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>

		<!-- WordPress -->
		<p class="ultron-section-title"><?php _e( 'Instalación de WordPress', 'ultron' ); ?></p>
		<table class="ultron-table">
			<tbody>
				<tr><td><?php _e( 'Inodos', 'ultron' ); ?></td><td><?php echo esc_html( number_format_i18n( $snapshot['wordpress']['inodes'] ) ); ?></td></tr>
				<tr><td><?php _e( 'Espacio usado', 'ultron' ); ?></td><td><?php echo esc_html( $this->format_size( $snapshot['wordpress']['size'] ) ); ?></td></tr>
			</tbody>
		</table>

		<!-- Uploads completa -->
		<p class="ultron-section-title"><?php _e( 'Carpeta uploads (completa)', 'ultron' ); ?></p>
		<table class="ultron-table">
			<tbody>
				<tr><td><?php _e( 'Inodos', 'ultron' ); ?></td><td><?php echo esc_html( number_format_i18n( $snapshot['uploads']['inodes'] ) ); ?></td></tr>
				<tr><td><?php _e( 'Tamaño', 'ultron' ); ?></td><td><?php echo esc_html( $this->format_size( $snapshot['uploads']['size'] ) ); ?></td></tr>
			</tbody>
		</table>

		<!-- Uploads por año -->
		<p class="ultron-section-title"><?php _e( 'Uploads por año', 'ultron' ); ?></p>

		<?php if ( empty( $snapshot['uploads_years'] ) ) : ?>
			<p><?php _e( 'No se encontraron carpetas de año dentro de uploads.', 'ultron' ); ?></p>
		<?php else : ?>
			<?php foreach ( $snapshot['uploads_years'] as $year => $year_data ) : ?>
				<details class="ultron-details">
					<summary>
						<?php echo esc_html( $year ); ?>
						— <?php echo esc_html( $this->format_size( $year_data['size'] ) ); ?>
						(<?php echo esc_html( number_format_i18n( $year_data['inodes'] ) ); ?> <?php _e( 'inodos', 'ultron' ); ?>)
					</summary>

					<?php if ( empty( $year_data['months'] ) ) : ?>
						<p style="padding:10px 14px;"><?php _e( 'No hay carpetas de mes.', 'ultron' ); ?></p>
					<?php else : ?>
						<table class="ultron-table">
							<thead>
								<tr>
									<th><?php _e( 'Mes', 'ultron' ); ?></th>
									<th><?php _e( 'Inodos', 'ultron' ); ?></th>
									<th><?php _e( 'Tamaño', 'ultron' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $year_data['months'] as $month_number => $month_data ) : ?>
									<tr>
										<td><?php echo esc_html( $this->get_month_name( $month_number ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $month_data['inodes'] ) ); ?></td>
										<td><?php echo esc_html( $this->format_size( $month_data['size'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</details>
			<?php endforeach; ?>
		<?php endif; ?>

		<!-- Error logs tabla -->
		<p class="ultron-section-title"><?php _e( 'Archivos error.log', 'ultron' ); ?></p>
		<table class="ultron-table">
			<thead>
				<tr>
					<th><?php _e( 'Archivo', 'ultron' ); ?></th>
					<th><?php _e( 'Existe', 'ultron' ); ?></th>
					<th><?php _e( 'Tamaño', 'ultron' ); ?></th>
					<th><?php _e( 'Estado', 'ultron' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $snapshot['error_logs'] as $log_name => $log ) : ?>
					<tr>
						<td><code><?php echo esc_html( $log_name ); ?></code></td>
						<td>
							<?php echo $log['exists']
								? '<span class="ultron-badge ok">' . __( 'Sí', 'ultron' ) . '</span>'
								: '<span class="ultron-badge muted">' . __( 'No', 'ultron' ) . '</span>'; ?>
						</td>
						<td><?php echo $log['exists'] ? esc_html( $this->format_size( $log['size'] ) ) : '—'; ?></td>
						<td>
							<?php if ( ! $log['exists'] ) : ?>
								<span class="ultron-badge muted"><?php _e( 'N/A', 'ultron' ); ?></span>
							<?php elseif ( $log['alert'] ) : ?>
								<span class="ultron-badge warning"><?php _e( 'Supera el umbral', 'ultron' ); ?></span>
							<?php else : ?>
								<span class="ultron-badge ok"><?php _e( 'OK', 'ultron' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
	}

	/**
	 * Widget del Dashboard.
	 *
	 * @return void
	 */
	public function render_widget(): void {
		$snapshot = $this->get_latest_snapshot();

		// Verificar error.log en tiempo real (sin depender del snapshot).
		$error_logs   = $this->check_error_logs();
		$log_alerts   = array_filter( $error_logs, fn( $l ) => $l['alert'] );
		$widget_class = ! empty( $log_alerts ) ? 'ultron-widget is-warning' : 'ultron-widget';
		?>
		<div class="<?php echo esc_attr( $widget_class ); ?>">
			<h3><?php _e( 'Storage Monitor', 'ultron' ); ?></h3>
			<?php if ( ! $snapshot ) : ?>
				<p><?php _e( 'Sin datos. Ve a Monitor → Almacenamiento para generar el primer snapshot.', 'ultron' ); ?></p>
			<?php else : ?>
				<p>
					<strong><?php _e( 'WordPress:', 'ultron' ); ?></strong>
					<?php echo esc_html( $this->format_size( $snapshot['wordpress']['size'] ) ); ?>
					(<?php echo esc_html( number_format_i18n( $snapshot['wordpress']['inodes'] ) ); ?> <?php _e( 'inodos', 'ultron' ); ?>)
				</p>
				<p>
					<strong><?php _e( 'Uploads:', 'ultron' ); ?></strong>
					<?php echo esc_html( $this->format_size( $snapshot['uploads']['size'] ) ); ?>
				</p>
			<?php endif; ?>

			<?php foreach ( $log_alerts as $log_name => $log ) : ?>
				<div class="ultron-widget-alert">
					⚠️ <strong><?php echo esc_html( $log_name ); ?></strong>
					<?php echo sprintf( __( 'supera %s MB', 'ultron' ), esc_html( $log['limit_mb'] ) ); ?>
					(<?php echo esc_html( $this->format_size( $log['size'] ) ); ?>)
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

}
