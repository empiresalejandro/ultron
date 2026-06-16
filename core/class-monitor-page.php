<?php
/**
 * Página "Monitor" de Ultron.
 *
 * Submenú compartido por los módulos de monitoreo. El orden de pestañas
 * es fijo e independiente del orden de registro/activación.
 *
 * @package Ultron
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ultron_Monitor_Page {

	/**
	 * Slugs de los módulos de monitor reconocidos.
	 *
	 * @var array
	 */
	private array $monitor_modules = [
		'wp-monitor',
		'db-monitor',
		'st-monitor',
		'plugins-monitor',
	];

	/**
	 * Orden fijo de las pestañas.
	 *
	 * @var array
	 */
	private array $tab_order = [
		'wordpress',
		'plugins',
		'storage',
		'database',
	];

	/**
	 * Instancia del gestor de módulos.
	 *
	 * @var Ultron_Modules
	 */
	private Ultron_Modules $modules;

	/**
	 * Constructor.
	 *
	 * @param Ultron_Modules $modules Instancia del gestor de módulos.
	 */
	public function __construct( Ultron_Modules $modules ) {
		$this->modules = $modules;
		add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
	}

	/**
	 * Comprueba si algún módulo de monitor está activo.
	 *
	 * @return bool
	 */
	private function has_active_monitor(): bool {
		foreach ( $this->monitor_modules as $slug ) {
			if ( $this->modules->is_module_active( $slug ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Registra el submenú "Monitor" si hay al menos un módulo activo.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		if ( ! $this->has_active_monitor() ) {
			return;
		}

		add_submenu_page(
			'ultron',
			__( 'Monitor', 'ultron' ),
			__( 'Monitor', 'ultron' ),
			'manage_options',
			'ultron-monitor',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Ordena las pestañas según el orden fijo definido.
	 *
	 * @param array $tabs Pestañas registradas.
	 * @return array
	 */
	private function sort_tabs( array $tabs ): array {
		$ordered = [];

		foreach ( $this->tab_order as $key ) {
			if ( isset( $tabs[ $key ] ) ) {
				$ordered[ $key ] = $tabs[ $key ];
				unset( $tabs[ $key ] );
			}
		}

		return $ordered + $tabs;
	}

	/**
	 * Renderiza la página "Monitor" con las pestañas registradas.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$tabs = apply_filters( 'ultron_monitor_tabs', [] );
		$tabs = $this->sort_tabs( $tabs );

		if ( empty( $tabs ) ) {
			?>
			<div class="wrap">
				<h1><?php _e( 'Monitor', 'ultron' ); ?></h1>
				<p><?php _e( 'No hay módulos de monitor activos.', 'ultron' ); ?></p>
			</div>
			<?php
			return;
		}

		$tab_keys = array_keys( $tabs );
		$current  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : $tab_keys[0];

		if ( ! isset( $tabs[ $current ] ) ) {
			$current = $tab_keys[0];
		}
		?>
		<div class="wrap">
			<h1><?php _e( 'Monitor', 'ultron' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $tab ) : ?>
					<a href="?page=ultron-monitor&tab=<?php echo esc_attr( $key ); ?>"
					   class="nav-tab <?php echo $current === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="tab-content" style="margin-top: 20px;">
				<?php
				if ( is_callable( $tabs[ $current ]['callback'] ) ) {
					call_user_func( $tabs[ $current ]['callback'] );
				}
				?>
			</div>
		</div>
		<?php
	}

}
