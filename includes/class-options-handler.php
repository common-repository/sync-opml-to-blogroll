<?php
/**
 * Handles WP Admin settings pages and the like.
 *
 * @package Sync_OPML_Blogroll
 */

namespace Sync_OPML_Blogroll;

/**
 * Options handler class.
 */
class Options_Handler {
	/**
	 * Plugin options.
	 *
	 * @var array $options
	 */
	private $options = array(
		'url'                => '',
		'username'           => '',
		'password'           => '',
		'denylist'           => '',
		'categories_enabled' => false,
		'default_category'   => null,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->options = get_option(
			'sync_opml_blogroll_settings',
			$this->options
		);

		add_action( 'admin_menu', array( $this, 'create_menu' ) );
	}

	/**
	 * Registers the plugin settings page.
	 */
	public function create_menu() {
		add_options_page(
			__( 'Sync OPML to Blogroll', 'sync-opml-blogroll' ),
			__( 'Sync OPML to Blogroll', 'sync-opml-blogroll' ),
			'manage_links',
			'sync-opml-blogroll',
			array( $this, 'settings_page' )
		);
		add_action( 'admin_init', array( $this, 'add_settings' ) );
	}

	/**
	 * Registers the actual options.
	 */
	public function add_settings() {
		register_setting(
			'sync-opml-blogroll-settings-group',
			'sync_opml_blogroll_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	/**
	 * Handles submitted options.
	 *
	 * @param array $settings Settings as submitted through WP Admin.
	 *
	 * @return array Options to be stored.
	 */
	public function sanitize_settings( $settings ) {
		if ( isset( $settings['url'] ) ) {
			if ( '' === $settings['url'] ) {
				$this->options['url'] = '';
			} elseif ( wp_http_validate_url( $settings['url'] ) ) {
				$this->options['url'] = esc_url_raw( $settings['url'] );
			}
		}

		if ( isset( $settings['username'] ) ) {
			$this->options['username'] = $settings['username'];
		}

		if ( isset( $settings['password'] ) && ! defined( 'SYNC_OPML_BLOGROLL_PASS' ) ) {
			$this->options['password'] = $settings['password'];
		} else {
			// Clear password, as it is defined elsewhere.
			$this->options['password'] = '';
		}

		if ( isset( $settings['denylist'] ) ) {
			// Normalize line endings.
			$denylist = preg_replace( '~\R~u', "\r\n", $settings['denylist'] );

			$this->options['denylist'] = trim( $denylist );
		}

		if ( isset( $settings['categories_enabled'] ) && '1' === $settings['categories_enabled'] ) {
			// Categories enabled.
			$this->options['categories_enabled'] = true;
		} else {
			$this->options['categories_enabled'] = false;
		}

		if ( isset( $settings['default_category'] ) ) {
			$term = term_exists( intval( $settings['default_category'] ), 'link_category' );

			if ( isset( $term['term_id'] ) ) {
				$this->options['default_category'] = $term['term_id'];
			} else {
				// `default_category` is an empty string or otherwise invalid.
				$this->options['default_category'] = null;
			}
		}

		// Updated settings.
		return $this->options;
	}

	/**
	 * Echoes the plugin options form. Handles the OAuth flow, too, for now.
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sync OPML to Blogroll', 'sync-opml-blogroll' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				// Print nonces and such.
				settings_fields( 'sync-opml-blogroll-settings-group' );
				?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="sync_opml_blogroll_settings[url]"><?php esc_html_e( 'OPML URL', 'sync-opml-blogroll' ); ?></label></th>
						<td>
							<input type="text" id="sync_opml_blogroll_settings[url]" name="sync_opml_blogroll_settings[url]" style="min-width: 33%;" value="<?php echo esc_attr( $this->options['url'] ); ?>" />
							<p class="description"><?php esc_html_e( 'The URL to your feed reader&rsquo;s OPML endpoint.', 'sync-opml-blogroll' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="sync_opml_blogroll_settings[username]"><?php esc_html_e( 'Username', 'sync-opml-blogroll' ); ?></label></th>
						<td>
							<input type="text" id="sync_opml_blogroll_settings[username]" name="sync_opml_blogroll_settings[username]" style="min-width: 33%;" value="<?php echo esc_attr( $this->options['username'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Your feed reader&rsquo;s username, should it require Basic Authentication. Leave blank if not applicable.', 'sync-opml-blogroll' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="sync_opml_blogroll_settings[password]"><?php esc_html_e( 'Password', 'sync-opml-blogroll' ); ?></label></th>
						<td>
							<input type="password" id="sync_opml_blogroll_settings[password]" name="sync_opml_blogroll_settings[password]" style="min-width: 33%;" value="<?php echo esc_attr( ( ! defined( 'SYNC_OPML_BLOGROLL_PASS' ) ? $this->options['password'] : '' ) ); ?>" <?php echo ( defined( 'SYNC_OPML_BLOGROLL_PASS' ) ? 'disabled="disabled"' : '' ); ?> />
							<p class="description"><?php esc_html_e( 'Your feed reader&rsquo;s password, should it require Basic Authentication. Leave blank if not applicable.', 'sync-opml-blogroll' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="sync_opml_blogroll_settings[denylist]"><?php esc_html_e( 'Denylist', 'sync-opml-blogroll' ); ?></label></th>
						<td>
							<?php
							$denylist = '';

							if ( ! empty( $this->options['denylist'] ) ) {
								$denylist = $this->options['denylist'];
							} elseif ( ! empty( $this->options['blacklist'] ) ) {
								// Legacy setting.
								$denylist = $this->options['blacklist'];
							}
							?>
							<textarea id="sync_opml_blogroll_settings[denylist]" name="sync_opml_blogroll_settings[denylist]" style="min-width: 33%;" rows="6"><?php echo ( ! empty( $this->options['denylist'] ) ? esc_html( $this->options['denylist'] ) : '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Feed URLs that contain any of these strings&mdash;one per line, please&mdash;will be ignored.', 'sync-opml-blogroll' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="sync_opml_blogroll_settings[default_category]"><?php esc_html_e( 'Default Category', 'sync-opml-blogroll' ); ?></th>
						<td>
							<?php
							$terms = get_terms(
								array(
									'taxonomy'   => 'link_category',
									'hide_empty' => false,
								)
							);
							?>
							<select name="sync_opml_blogroll_settings[default_category]" id="sync_opml_blogroll_settings[default_category]">
								<option value=""></option>
								<?php foreach ( $terms as $term ) : ?>
									<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $term->term_id, $this->options['default_category'] ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Select a default category. (OPML-defined categories, if enabled below, will override this value!)', 'sync-opml-blogroll' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable Categories', 'sync-opml-blogroll' ); ?></th>
						<td>
							<label><input type="checkbox" name="sync_opml_blogroll_settings[categories_enabled]" value="1" <?php checked( $this->options['categories_enabled'] ); ?> /> <?php esc_html_e( 'Enabled', 'sync-opml-blogroll' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Import categories, too?', 'sync-opml-blogroll' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit"><?php submit_button( __( 'Save Changes', 'sync-opml-blogroll' ), 'primary', 'submit', false ); ?></p>
			</form>
		</div>
		<?php
	}
}
