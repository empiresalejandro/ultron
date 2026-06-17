<?php
/**
 * Clase principal de Ultron.
 *
 * Inicializa el plugin, registra el menú, carga los módulos,
 * encola los assets y gestiona las pestañas del hub (Dashboard,
 * Módulos, Información). La página Opciones vive en su propia
 * clase (Ultron_Options_Page) como submenú independiente.
 *
 * @package Ultron
 * @since   1.1.0
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
	 * Instancia de la página Opciones.
	 *
	 * @var Ultron_Options_Page
	 */
	private Ultron_Options_Page $options_page;

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
		$this->options_page = new Ultron_Options_Page( $this->options );
		$this->modules->load_active_modules();

		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
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
		require_once ULTRON_PATH . 'core/class-options-page.php';
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
			'ultron_page_ultron-options',
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
	 * Renderiza la página principal del hub con pestañas
	 * Dashboard, Módulos e Información.
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
				<a href="?page=ultron&tab=info"
				   class="nav-tab <?php echo $tab === 'info' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Información', 'ultron' ); ?>
				</a>
			</nav>

			<div class="tab-content" style="margin-top: 20px;">
				<?php
				switch ( $tab ) {
					case 'modules':
						$this->render_tab_modules();
						break;
					case 'info':
						$this->render_tab_info();
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

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo sprintf( __( 'Módulo "%s" actualizado correctamente.', 'ultron' ), esc_html( $_GET['updated'] ) ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['error'] ) && $_GET['error'] === 'update_failed' ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo sprintf( __( 'No se pudo actualizar el módulo "%s". Revisa la conexión, el token de GitHub o que exista el tag de versión correspondiente.', 'ultron' ), esc_html( $_GET['module'] ?? '' ) ); ?></p>
			</div>
		<?php endif; ?>

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
							<td style="display:flex; gap:6px;">
								<?php if ( isset( $updates[ $slug ] ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<?php wp_nonce_field( 'ultron_module_action' ); ?>
										<input type="hidden" name="module_slug" value="<?php echo esc_attr( $slug ); ?>">
										<input type="hidden" name="action" value="ultron_update_module">
										<button type="submit" class="button">
											<?php _e( 'Actualizar', 'ultron' ); ?>
										</button>
									</form>
								<?php endif; ?>
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
	 * Parsea el archivo changelog.txt a un array de entradas.
	 * Formato esperado por entrada: "= versión — fecha =" seguido de líneas "* texto".
	 *
	 * @return array
	 */
	private function parse_changelog(): array {
		$path = ULTRON_PATH . 'changelog.txt';

		if ( ! file_exists( $path ) ) {
			return [];
		}

		$content = file_get_contents( $path );
		$entries  = [];

		// Divide por bloques que empiezan con "= ... ="
		$blocks = preg_split( '/^=\s*(.+?)\s*=\s*$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

		// $blocks alterna: [preámbulo, título1, cuerpo1, título2, cuerpo2, ...]
		for ( $i = 1; $i < count( $blocks ); $i += 2 ) {
			$title = trim( $blocks[ $i ] );
			$body  = trim( $blocks[ $i + 1 ] ?? '' );

			$items = [];
			foreach ( preg_split( '/\r?\n/', $body ) as $line ) {
				$line = trim( $line );
				if ( str_starts_with( $line, '*' ) ) {
					$items[] = trim( substr( $line, 1 ) );
				}
			}

			$entries[] = [
				'title' => $title,
				'items' => $items,
			];
		}

		return $entries;
	}

	/**
	 * Renderiza la pestaña Información: banner, modo de uso y novedades.
	 *
	 * @return void
	 */
	private function render_tab_info(): void {
		$changelog = $this->parse_changelog();
		?>

		<!-- Banner de versión -->
		<div style="
			height: 50vh;
			min-height: 320px;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			text-align: center;
			background: linear-gradient(135deg, #1d2327 0%, #2271b1 100%);
			border-radius: var(--ultron-radius);
			color: #fff;
			margin-bottom: 30px;
		">
			<span class="dashicons dashicons-superhero" style="font-size: 64px; width: 64px; height: 64px; opacity: .9;"></span>
			<h1 style="color:#fff; font-size: 36px; margin: 16px 0 4px;">Ultron</h1>
			<p style="font-size: 14px; opacity: .85; margin: 0;">
				<?php echo sprintf( __( 'Versión %s', 'ultron' ), esc_html( ULTRON_VERSION ) ); ?>
			</p>
		</div>

		<!-- Modo de uso -->
		<p class="ultron-section-title"><?php _e( 'Modo de uso', 'ultron' ); ?></p>
		<div class="ultron-options-section">
			<p><?php _e( 'Ultron es un hub modular: cada funcionalidad vive en un módulo independiente que puedes activar o desactivar sin afectar al resto.', 'ultron' ); ?></p>
			<ol style="margin-left: 18px; line-height: 1.8;">
				<li><?php _e( 'Ve a la pestaña Módulos y activa los que necesites.', 'ultron' ); ?></li>
				<li><?php _e( 'Los módulos de monitoreo (WordPress, Plugins, Almacenamiento, Base de datos) aparecen agrupados en el submenú Monitor.', 'ultron' ); ?></li>
				<li><?php _e( 'El Dashboard muestra un resumen (widget) de cada módulo activo que lo soporte.', 'ultron' ); ?></li>
				<li><?php _e( 'La configuración general del plugin vive en el submenú Opciones.', 'ultron' ); ?></li>
			</ol>
		</div>

		<!-- Novedades / Changelog -->
		<p class="ultron-section-title"><?php _e( 'Novedades', 'ultron' ); ?></p>

		<?php if ( empty( $changelog ) ) : ?>
			<p><?php _e( 'No se encontró el historial de cambios.', 'ultron' ); ?></p>
		<?php else : ?>
			<?php foreach ( $changelog as $entry ) : ?>
				<details class="ultron-details">
					<summary><?php echo esc_html( $entry['title'] ); ?></summary>
					<div style="padding: 14px;">
						<?php if ( empty( $entry['items'] ) ) : ?>
							<p style="margin:0; color: var(--ultron-muted);"><?php _e( 'Sin detalles.', 'ultron' ); ?></p>
						<?php else : ?>
							<ul style="margin: 0 0 0 18px; line-height: 1.7;">
								<?php foreach ( $entry['items'] as $item ) : ?>
									<li><?php echo esc_html( $item ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</details>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php
	}

}
