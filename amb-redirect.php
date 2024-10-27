<?php
/*
Plugin Name: Ambition Resource Latest Redirect
Plugin URI: https://ambitionresource.com/
Description: Easily redirect to your latest post.  Excellent for instagram or other platforms where you want to have 1 link that redirects to new content.
Author: Jonathon Klem
Version: 0.0.2
Author URI: https://ambitionresource.com/
Text Domain: amb_redirect

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class AMB_Redirect {
	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Singleton
	 */
	private static $instance = null;

	/**
	 * Instantiate singleton
	 */
	public static function get_instance() {
		if ( ! is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Dummy magic methods
	 */
	public function __clone() { _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; uh?' ), '0.1' ); }
	public function __wakeup() { _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; uh?' ), '0.1' ); }
	public function __call( $name = '', $args = array() ) { unset( $name, $args ); return null; }

	/**
	 * Class properties
	 */
	private $plugin_option_name = 'amb-redirect';
	private $slug               = '';
	private $default_slug       = '';

	/**
	 * Register plugin's setup action
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'parse_request', array( $this, 'action_parse_request' ) );

		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
	}

	/**
	 * Translate plugin slug
	 */
	public function action_init() {
		$this->default_slug = __( 'latest-post', 'amb_redirect' );

		$_slug = get_option( $this->plugin_option_name, $this->default_slug );

		if ( is_string( $_slug ) && ! empty( $_slug ) ) {
			$this->slug = $_slug;
		}

		if ( empty( $this->slug ) ) {
			$this->slug = $this->default_slug;
		}
	}

		/**
	 * Redirect to the latest post any requests made to plugin's slug
	 */
	public function action_parse_request( $r ) {
		// Nothing to do if permalinks aren't enabled
		if ( ! $r->did_permalink ) {
			return;
		}

		// By default, there's also nothing to do
		$should_intercept = false;

		// Check if request is for our slug
		// The first condition catches permastructs that are more than just post slug, whereas the second catches for slug-only permalinks
		if ( isset( $r->query_vars['pagename'] ) && $this->slug === $r->query_vars['pagename'] ) {
			$should_intercept = true;
		} elseif ( isset( $r->query_vars['name'] ) && $this->slug === $r->query_vars['name'] ) {
			$should_intercept = true;
		} elseif ( isset( $r->query_vars['category_name'] ) && $this->slug === $r->query_vars['category_name'] ) {
			$should_intercept = true;
		}

		// Handle redirection
		if ( $should_intercept ) {
			$latest = get_posts( array(
				'posts_per_page'   => 1,
				'post_type'        => 'post',
				'orderby'          => 'date',
				'order'            => 'desc',
				'suppress_filters' => false,
				'no_found_rows'    => true,
			) );

			if ( is_array( $latest ) && ! empty( $latest ) ) {
				$latest = array_shift( $latest );

				$dest = get_permalink( $latest->ID );

				if ( ! $dest ) {
					$dest = user_trailingslashit( home_url() );
				}

				wp_redirect( $dest, 302 ); // Not validating in case other plugins redirect elsewhere
				exit;
			}
		}
	}

	/**
	 * ADMIN OPTIONS
	 */

	/**
	 * Save plugin settings and register settings field
	 *
	 * Permalinks screen is a snowflake, hence the custom saving handler
	 */
	public function action_admin_init() {
		// Make sure user has necessary permissions first
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save custom option, permalinks screen is a snowflake and doesn't fully use the Settings API
		global $pagenow;

		if ( 'options-permalink.php' === $pagenow && isset( $_POST[ $this->plugin_option_name ] ) ) {
			check_admin_referer( 'update-permalink' );

			$_slug = sanitize_text_field( $_POST[ $this->plugin_option_name ] );

			if ( empty( $_slug ) ) {
				$_slug = $this->default_slug;
			}

			update_option( $this->plugin_option_name, $_slug );
		}

		// Add custom input field to permalinks screen
		add_settings_field( $this->plugin_option_name, __( '&quot;Latest post&quot; slug', 'amb_redirect' ), array( $this, 'settings_field' ), 'permalink', 'optional' );
	}

	/**
	 * Render settings field
	 */
	public function settings_field() {
		?>
		<input type="text" name="<?php echo esc_attr( $this->plugin_option_name ); ?>" value="<?php echo esc_attr( $this->slug ); ?>" class="regular-text" />

		<p class="description"><?php printf( __( 'Set the slug that will redirect to the latest published post. The default value is %s.', 'amb_redirect' ), '<code style="font-style: normal;">' . $this->default_slug . '</code>' ); ?></p>
		<?php
	}
}

/**
 * Singleton 
 */
AMB_Redirect::get_instance();
