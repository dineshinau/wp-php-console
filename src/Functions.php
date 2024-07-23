<?php
/**
 * WP PHP Console
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) or exit;


/**
 * Gets the WP PHP Console instance.
 *
 * @since 1.5.4
 *
 * @return WP_PHP_Console\Plugin
 */
function wp_php_console() {
	return new \WP_PHP_Console\Plugin();
}

/**
 * WordPress PC debugging function.
 *
 * @param mixed $data Data that needs to add to debug.
 * @param null  $tags
 * @param int   $ignoreTraceCalls
 */
function wpd( $data, $tags = null, $ignoreTraceCalls = 0 ) {
	if ( ! class_exists( 'PC' ) ) {
		PhpConsole\Helper::register();
	}
	PC::debug( $data, $tags, $ignoreTraceCalls );
}
