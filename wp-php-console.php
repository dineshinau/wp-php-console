<?php
/**
 * Plugin Name:  WP PHP Console (Forked for wpd)
 * Plugin URI:   https://github.com/unfulvio/wp-php-console/
 * Description:  An implementation of PHP Console for WordPress. Easily debug and trace PHP errors and warnings from your Chrome dev tools console using a Google Chrome extension.
 * Version:      2.2.0
 * Author:       Dinesh Yadav
 * Author URI:   https://github.com/dineshinau
 *
 * Text Domain:  wp-php-console
 * Domain Path:  /languages
 *
 * Requires at least: 6.8
 * Tested up to: 7.0
 * Requires PHP: 7.4
 *
 * WP PHP Console
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * A copy of the license is also available through the world-wide-web
 * at this URL: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WP_PHP_Console
 */

defined( 'ABSPATH' ) or exit;

/**
 * WP PHP Console loader.
 *
 * @since 1.5.4
 */
class WP_PHP_Console_Loader {
	/** Minimum PHP version required by this plugin */
	const MINIMUM_PHP_VERSION = '7.4';

	/** Minimum WordPress version required by this plugin */
	const MINIMUM_WP_VERSION = '6.8';

	/** The plugin name, for displaying notices */
	const PLUGIN_NAME = 'WP PHP Console';

	/**
	 * Single instance of this class.
	 *
	 * @var \WP_PHP_Console_Loader
	 */
	protected static $instance;

	/**
	 * The admin notices to add.
	 *
	 * @var array
	 */
	protected $notices = array();

	/**
	 * Loads WP PHP Console after performing compatibility checks.
	 *
	 * @since 1.5.4
	 */
	protected function __construct() {

		register_activation_hook( __FILE__, array( $this, 'activation_check' ) );

		add_action( 'admin_init', array( $this, 'check_environment' ) );
		add_action( 'admin_init', array( $this, 'add_plugin_notices' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );

		// Only initialize plugin if environment is compatible.
		// Defer to 'plugins_loaded' to avoid output during activation.
		if ( $this->is_environment_compatible() && $this->is_wp_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
		}
	}

	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 1.5.4
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', get_class( $this ) ), '2.0.0' );
	}

	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 1.5.4
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ), '2.0.0' );
	}

	/**
	 * Initializes the plugin.
	 *
	 * @internal
	 *
	 * @since 1.5.4
	 */
	public function init_plugin() {
		// Don't initialize during activation to prevent output.
		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'activate' === $_REQUEST['action'] ) {
			return;
		}

		// Autoload plugin and vendor files.
		$autoloader = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
		if ( ! file_exists( $autoloader ) ) {
			return;
		}
		$loader = require_once $autoloader;

		// Register plugin namespace with autoloader.
		if ( $loader && method_exists( $loader, 'addPsr4' ) ) {
			$loader->addPsr4( 'WP_PHP_Console\\', __DIR__ . '/src' );
		}

		$functions_file = plugin_dir_path( __FILE__ ) . 'src/Functions.php';
		if ( file_exists( $functions_file ) ) {
			require_once $functions_file;
		}

		// Verify the PHP Console library exists before initializing.
		if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/php-console/php-console/src/PhpConsole/Connector.php' ) ) {
			wp_php_console();
		}
	}

	/**
	 * Checks the server environment and other factors and deactivates plugins as necessary.
	 *
	 * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
	 *
	 * @internal
	 *
	 * @since 1.5.4
	 */
	public function activation_check() {
		if ( ! $this->is_environment_compatible() ) {
			$this->deactivate_plugin();
			wp_die( self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message() );
		}
	}

	/**
	 * Checks the environment on loading WordPress, just in case the environment changes after activation.
	 *
	 * @internal
	 *
	 * @since 1.5.4
	 */
	public function check_environment() {
		if ( ! $this->is_environment_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			$this->deactivate_plugin();
			$this->add_admin_notice( 'bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message() );
		}
	}

	/**
	 * Adds notices for out-of-date WordPress and/or WooCommerce versions.
	 *
	 * @internal
	 *
	 * @since 1.5.4
	 */
	public function add_plugin_notices() {
		if ( ! $this->is_wp_compatible() ) {
			$this->add_admin_notice(
				'update_wordpress',
				'error',
				sprintf(
					'%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
					'<strong>' . self::PLUGIN_NAME . '</strong>',
					self::MINIMUM_WP_VERSION,
					'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
					'</a>'
				)
			);
		}
	}

	/**
	 * Determines if the plugin is WordPress compatible.
	 *
	 * @since 1.5.4
	 *
	 * @return bool
	 */
	private function is_wp_compatible() {
		global $wp_version;
		return version_compare( $wp_version, self::MINIMUM_WP_VERSION, '>=' );
	}

	/**
	 * Deactivates the plugin.
	 *
	 * @since 1.5.4
	 */
	private function deactivate_plugin() {
		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}

	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 1.5.4
	 *
	 * @param string $slug notice ID.
	 * @param string $class CSS class.
	 * @param string $message message content.
	 */
	private function add_admin_notice( $slug, $class, $message ) {
		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message,
		);
	}

	/**
	 * Displays admin notices.
	 *
	 * @internal
	 *
	 * @since 1.5.4
	 */
	public function admin_notices() {
		foreach ( (array) $this->notices as $notice ) :
			?>
			<div class="<?php echo esc_attr( $notice['class'] ); ?>">
				<p><?php echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) ); ?></p>
			</div>
			<?php
		endforeach;
	}

	/**
	 * Determines if the server environment is compatible with this plugin.
	 *
	 * @since 1.5.4
	 *
	 * @return bool
	 */
	private function is_environment_compatible() {
		return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
	}

	/**
	 * Returns the message for display when the environment is incompatible with this plugin.
	 *
	 * @since 1.5.4
	 *
	 * @return string
	 */
	private function get_environment_message() {
		return sprintf( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION );
	}

	/**
	 * Returns the main \WP_PHP_Console_Loader instance.
	 *
	 * Ensures only one instance can be loaded at any given time.
	 *
	 * @since 1.5.4
	 *
	 * @return \WP_PHP_Console_Loader
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

// initialize the plugin loader.
WP_PHP_Console_Loader::instance();
