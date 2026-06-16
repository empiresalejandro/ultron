<?php
/**
 * Clase principal del módulo Plugins Monitor.
 *
 * Detecta plugins recomendados, muestra conflictos entre categorías,
 * permite indicar estado de licencia de plugins premium y ofrece
 * acciones rápidas de instalar/activar/desactivar.
 *
 * @package Ultron
 * @subpackage Plugins_Monitor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ultron_Plugins_Monitor {

	/**
	 * Lista de plugins por categoría.
	 *
	 * @var array
	 */
	private array $plugins_list = [
		'Constructores' => [
			'Elementor'                     => 'elementor/elementor.php',
			'Ultimate Addons for Elementor' => 'ultimate-elementor/ultimate-elementor.php',
			'Happy Elementor Addons'        => 'happy-elementor-addons/happy-elementor-addons.php',
		],
		'Formularios' => [
			'Contact Form 7'     => 'contact-form-7/wp-contact-form-7.php',
			'Contact Form CFDB7' => 'contact-form-cfdb7/contact-form-cfdb7.php',
			'WP Forms'           => 'wpforms-lite/wpforms.php',
			'Ninja Forms'        => 'ninja-forms/ninja-forms.php',
		],
		'SEO' => [
			'Yoast SEO' => 'wordpress-seo/wp-seo.php',
			'Rank Math' => 'seo-by-rank-math/rank-math.php',
			'AIOSEO'    => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
		],
		'Seguridad' => [
			'Wordfence'                     => 'wordfence/wordfence.php',
			'Akismet'                       => 'akismet/akismet.php',
			'Limit Login Attempts Reloaded' => 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php',
			'Advanced Google reCAPTCHA'     => 'advanced-google-recaptcha/advanced-google-recaptcha.php',
		],
		'Performance' => [
			'LiteSpeed Cache' => 'litespeed-cache/litespeed-cache.php',
		],
		'Utilidades' => [
			'SiteKit'      => 'google-site-kit/google-site-kit.php',
			'Jetpack'      => 'jetpack/jetpack.php',
			'SVG Support'  => 'svg-support/svg-support.php',
			'404 to 301'   => '404-to-301/404-to-301.php',
			'WP Mail SMTP' => 'wp-mail-smtp/wp_mail_smtp.php',
			'GSpeech'      => 'gspeech/gspeech.php',
		],
	];

	/**
	 * Plugins no disponibles en WordPress.org (sin botón instalar).
	 *
	 * @var array
	 */
	private array $not_in_wp_org = [
		'happy-elementor-addons/happy-elementor-addons.php',
		'js_composer/js_composer.php',
		'revslider/revslider.php',
	];

	/**
	 * Plugins futuros módulos.
	 *
	 * @var array
	 */
	private array $future_modules = [
		'Popup Box'   => [ 'path' => 'ays-popup-box/ays-pb.php',    'module' => 'Popup Utilities' ],
		'WP Rollback' => [ 'path' => 'wp-rollback/wp-rollback.php', 'module' => 'Rollback Utilities' ],
		'Ally'        => [ 'path' => 'ally/ally.php',               'module' => 'Accessibility Utilities' ],
	];

	/**
	 * Plugins premium con licencia.
	 *
	 * @var array
	 */
	private array $license_plugins = [
		'Elementor'             => [ 'path' => 'elementor/elementor.php',     'option' => 'ultron_license_elementor' ],
		'WPBakery Page Builder' => [ 'path' => 'js_composer/js_composer.php', 'option' => 'ultron_license_wpbakery' ],
		'Slider Revolution'     => [ 'path' => 'revslider/revslider.php',     'option' => 'ultron_license_revslider' ],
	];

	/**
	 * Reglas de conflicto entre plugins.
	 *
	 * @var array
	 */
	private array $conflict_rules = [
		'all-in-one-seo-pack/all_in_one_seo_pack.php'                    => [ 'wordpress-seo/wp-seo.php' ],
		'wordpress-seo/wp-seo.php'                                        => [ 'all-in-one-seo-pack/all_in_one_seo_pack.php' ],
		'wpforms-lite/wpforms.php'                                        => [ 'ninja-forms/ninja-forms.php', 'contact-form-7/wp-contact-form-7.php' ],
		'ninja-forms/ninja-forms.php'                                     => [ 'wpforms-lite/wpforms.php', 'contact-form-7/wp-contact-form-7.php' ],
		'akismet/akismet.php'                                             => [ 'wordfence/wordfence.php', 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' ],
		'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' => [ 'akismet/akismet.php', 'wordfence/wordfence.php' ],
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_post_ultron_plugins_monitor_save_license', [ $this, 'handle_save_license' ] );
		add_action( 'admin_post_ultron_plugins_monitor_activate',     [ $this, 'handle_activate' ] );
		add_action( 'admin_post_ultron_plugins_monitor_deactivate',   [ $this, 'handle_deactivate' ] );
	}

	/**
	 * Estado de un plugin.
	 *
	 * @param string $plugin_path Path del plugin.
	 * @return array{installed: bool, active: bool}
	 */
	private function get_plugin_status( string $plugin_path ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all     = get_plugins();
		$installed = isset( $all[ $plugin_path ] );
		$active    = $installed && is_plugin_active( $plugin_path );
		return [ 'installed' => $installed, 'active' => $active ];
	}

	/**
	 * Conflictos activos de un plugin.
	 *
	 * @param string $plugin_path Path del plugin.
	 * @return array
	 */
	private function get_conflicts( string $plugin_path ): array {
		if ( ! isset( $this->conflict_rules[ $plugin_path ] ) ) return [];
		$found = [];
		foreach ( $this->conflict_rules[ $plugin_path ] as $conflict_path ) {
			if ( $this->get_plugin_status( $conflict_path )['active'] ) {
				$found[] = $conflict_path;
			}
		}
		return $found;
	}

	/**
	 * Nombre legible de un plugin por su path.
	 *
	 * @param string $plugin_path Path del plugin.
	 * @return string
	 */
	private function get_plugin_name_by_path( string $plugin_path ): string {
		foreach ( $this->plugins_list as $plugins ) {
			foreach ( $plugins as $name => $path ) {
				if ( $path === $plugin_path ) return $name;
			}
		}
		return $plugin_path;
	}

	/**
	 * Extrae el slug del directorio del path.
	 *
	 * @param string $plugin_path Path del plugin.
	 * @return string
	 */
	private function get_plugin_slug( string $plugin_path ): string {
		return explode( '/', $plugin_path )[0];
	}

	/**
	 * Verifica si un path pertenece a la lista conocida.
	 *
	 * @param string $plugin_path Path del plugin.
	 * @return bool
	 */
	private function is_known_plugin( string $plugin_path ): bool {
		foreach ( $this->plugins_list as $plugins ) {
			if ( in_array( $plugin_path, $plugins, true ) ) return true;
		}
		foreach ( $this->license_plugins as $info ) {
			if ( $info['path'] === $plugin_path ) return true;
		}
		foreach ( $this->future_modules as $info ) {
			if ( $info['path'] === $plugin_path ) return true;
		}
		return false;
	}

	/** @return void */
	public function handle_save_license(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sin permisos.', 'ultron' ) );
		}
		check_admin_referer( 'ultron_plugins_monitor_license' );
		$option        = isset( $_POST['license_option'] ) ? sanitize_key( $_POST['license_option'] ) : '';
		$value         = isset( $_POST['has_license'] ) && $_POST['has_license'] === '1';
		$valid_options = wp_list_pluck( $this->license_plugins, 'option' );
		if ( in_array( $option, $valid_options, true ) ) {
			update_option( $option, $value );
		}
		wp_redirect( admin_url( 'admin.php?page=ultron-monitor&tab=plugins&saved=1' ) );
		exit;
	}

	/** @return void */
	public function handle_activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( __( 'Sin permisos.', 'ultron' ) );
		}
		check_admin_referer( 'ultron_plugins_monitor_action' );
		$plugin_path = isset( $_POST['plugin_path'] ) ? sanitize_text_field( $_POST['plugin_path'] ) : '';
		if ( $this->is_known_plugin( $plugin_path ) ) {
			activate_plugin( $plugin_path );
		}
		wp_redirect( admin_url( 'admin.php?page=ultron-monitor&tab=plugins' ) );
		exit;
	}

	/** @return void */
	public function handle_deactivate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( __( 'Sin permisos.', 'ultron' ) );
		}
		check_admin_referer( 'ultron_plugins_monitor_action' );
		$plugin_path = isset( $_POST['plugin_path'] ) ? sanitize_text_field( $_POST['plugin_path'] ) : '';
		if ( $this->is_known_plugin( $plugin_path ) ) {
			deactivate_plugins( $plugin_path );
		}
		wp_redirect( admin_url( 'admin.php?page=ultron-monitor&tab=plugins' ) );
		exit;
	}

	/**
	 * Renderiza botón de acción.
	 *
	 * @param string $plugin_path Path del plugin.
	 * @param array  $status      Estado del plugin.
	 * @return void
	 */
	private function render_action_button( string $plugin_path, array $status ): void {
		if ( ! $status['installed'] ) {
			if ( in_array( $plugin_path, $this->not_in_wp_org, true ) ) {
				echo '—';
				return;
			}
			$slug = $this->get_plugin_slug( $plugin_path );
			$url  = admin_url( 'plugin-install.php?s=' . rawurlencode( $slug ) . '&tab=search&type=term' );
			echo '<a href="' . esc_url( $url ) . '" class="button">' . __( 'Instalar', 'ultron' ) . '</a>';
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ultron_plugins_monitor_action' ); ?>
			<input type="hidden" name="plugin_path" value="<?php echo esc_attr( $plugin_path ); ?>">
			<?php if ( $status['active'] ) : ?>
				<input type="hidden" name="action" value="ultron_plugins_monitor_deactivate">
				<button type="submit" class="button"><?php _e( 'Desactivar', 'ultron' ); ?></button>
			<?php else : ?>
				<input type="hidden" name="action" value="ultron_plugins_monitor_activate">
				<button type="submit" class="button button-primary"><?php _e( 'Activar', 'ultron' ); ?></button>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Renderiza la pestaña Plugins.
	 *
	 * @return void
	 */
	public function render_tab(): void {
		?>

		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Estado de licencia actualizado.', 'ultron' ); ?></p>
			</div>
		<?php endif; ?>

		<p style="color:var(--ultron-muted);font-size:13px;margin-bottom:15px;">
			<?php _e( 'Lista de plugins recomendados y su estado en este sitio.', 'ultron' ); ?>
		</p>

		<?php foreach ( $this->plugins_list as $category => $plugins ) : ?>
			<p class="ultron-section-title"><?php echo esc_html( $category ); ?></p>
			<table class="ultron-table">
				<thead>
					<tr>
						<th><?php _e( 'Plugin', 'ultron' ); ?></th>
						<th><?php _e( 'Instalado', 'ultron' ); ?></th>
						<th><?php _e( 'Activo', 'ultron' ); ?></th>
						<th><?php _e( 'Aviso', 'ultron' ); ?></th>
						<th><?php _e( 'Acción', 'ultron' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plugins as $name => $path ) : ?>
						<?php
						$status    = $this->get_plugin_status( $path );
						$conflicts = $status['active'] ? $this->get_conflicts( $path ) : [];
						?>
						<tr>
							<td><?php echo esc_html( $name ); ?></td>
							<td>
								<?php echo $status['installed']
									? '<span class="ultron-badge ok">' . __( 'Sí', 'ultron' ) . '</span>'
									: '<span class="ultron-badge muted">' . __( 'No', 'ultron' ) . '</span>'; ?>
							</td>
							<td>
								<?php echo $status['active']
									? '<span class="ultron-badge ok">' . __( 'Sí', 'ultron' ) . '</span>'
									: '<span class="ultron-badge muted">' . __( 'No', 'ultron' ) . '</span>'; ?>
							</td>
							<td>
								<?php if ( ! empty( $conflicts ) ) : ?>
									<span class="ultron-badge warning">
										<?php _e( 'Conflicto:', 'ultron' ); ?>
										<?php echo esc_html( implode( ', ', array_map( [ $this, 'get_plugin_name_by_path' ], $conflicts ) ) ); ?>
									</span>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php $this->render_action_button( $path, $status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>

		<p class="ultron-section-title"><?php _e( 'Licencias de plugins premium', 'ultron' ); ?></p>
		<table class="ultron-table">
			<thead>
				<tr>
					<th><?php _e( 'Plugin', 'ultron' ); ?></th>
					<th><?php _e( 'Instalado', 'ultron' ); ?></th>
					<th><?php _e( 'Activo', 'ultron' ); ?></th>
					<th><?php _e( '¿Tiene licencia?', 'ultron' ); ?></th>
					<th><?php _e( 'Acción', 'ultron' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $this->license_plugins as $name => $info ) : ?>
					<?php
					$status      = $this->get_plugin_status( $info['path'] );
					$has_license = (bool) get_option( $info['option'], false );
					?>
					<tr>
						<td><?php echo esc_html( $name ); ?></td>
						<td>
							<?php echo $status['installed']
								? '<span class="ultron-badge ok">' . __( 'Sí', 'ultron' ) . '</span>'
								: '<span class="ultron-badge muted">' . __( 'No', 'ultron' ) . '</span>'; ?>
						</td>
						<td>
							<?php echo $status['active']
								? '<span class="ultron-badge ok">' . __( 'Sí', 'ultron' ) . '</span>'
								: '<span class="ultron-badge muted">' . __( 'No', 'ultron' ) . '</span>'; ?>
						</td>
						<td>
							<?php if ( ! $status['installed'] ) : ?>
								—
							<?php else : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								      style="display:flex;align-items:center;gap:8px;">
									<?php wp_nonce_field( 'ultron_plugins_monitor_license' ); ?>
									<input type="hidden" name="action" value="ultron_plugins_monitor_save_license">
									<input type="hidden" name="license_option" value="<?php echo esc_attr( $info['option'] ); ?>">
									<select name="has_license" onchange="this.form.submit()">
										<option value="1" <?php selected( $has_license, true ); ?>><?php _e( 'Sí', 'ultron' ); ?></option>
										<option value="0" <?php selected( $has_license, false ); ?>><?php _e( 'No', 'ultron' ); ?></option>
									</select>
									<noscript><button type="submit" class="button"><?php _e( 'Guardar', 'ultron' ); ?></button></noscript>
									<?php if ( ! $has_license ) : ?>
										<span class="ultron-badge danger"><?php _e( 'Sin licencia', 'ultron' ); ?></span>
									<?php endif; ?>
								</form>
							<?php endif; ?>
						</td>
						<td><?php $this->render_action_button( $info['path'], $status ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="ultron-section-title"><?php _e( 'Próximos módulos detectados', 'ultron' ); ?></p>
		<table class="ultron-table">
			<thead>
				<tr>
					<th><?php _e( 'Plugin', 'ultron' ); ?></th>
					<th><?php _e( 'Instalado', 'ultron' ); ?></th>
					<th><?php _e( 'Activo', 'ultron' ); ?></th>
					<th><?php _e( 'Módulo futuro', 'ultron' ); ?></th>
					<th><?php _e( 'Acción', 'ultron' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $this->future_modules as $name => $info ) : ?>
					<?php $status = $this->get_plugin_status( $info['path'] ); ?>
					<tr>
						<td><?php echo esc_html( $name ); ?></td>
						<td>
							<?php echo $status['installed']
								? '<span class="ultron-badge ok">' . __( 'Sí', 'ultron' ) . '</span>'
								: '<span class="ultron-badge muted">' . __( 'No', 'ultron' ) . '</span>'; ?>
						</td>
						<td>
							<?php echo $status['active']
								? '<span class="ultron-badge ok">' . __( 'Sí', 'ultron' ) . '</span>'
								: '<span class="ultron-badge muted">' . __( 'No', 'ultron' ) . '</span>'; ?>
						</td>
						<td><?php echo esc_html( $info['module'] ); ?></td>
						<td><?php $this->render_action_button( $info['path'], $status ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
	}

}
