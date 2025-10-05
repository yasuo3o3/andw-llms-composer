<?php
/**
 * Plugin bootstrap.
 *
 * @package andw-llms-composer
 */

/**
 * Plugin Name: andW LLMS Composer
 * Description: Markdown-driven content synchronization with automated llms.txt and sitemap support.
 * Version: 0.0.1
 * Author: yasuo3o3
 * Author URI: https://yasuo-o.xyz/
 * Contributors: yasuo3o3
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: andw-llms-composer
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ANDW_LLMS_COMPOSER_VERSION', '0.0.1' );
define( 'ANDW_LLMS_COMPOSER_PLUGIN_FILE', __FILE__ );
define( 'ANDW_LLMS_COMPOSER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ANDW_LLMS_COMPOSER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ANDW_LLMS_COMPOSER_MD_BUNDLE_DIR', ANDW_LLMS_COMPOSER_PLUGIN_DIR . 'content-md/' );
define( 'ANDW_LLMS_COMPOSER_MD_STORAGE_DIR', trailingslashit( WP_CONTENT_DIR ) . 'andw-llms-composer/content-md/' );

require_once ANDW_LLMS_COMPOSER_PLUGIN_DIR . 'includes/helpers.php';

if ( ! function_exists( 'andw_llms_composer_autoload' ) ) {
	/**
	 * Autoload plugin classes following class-name mapping.
	 *
	 * @param string $class Class name.
	 * @return void
	 */
	function andw_llms_composer_autoload( $class ) {
		if ( 0 !== strpos( $class, 'Andw_LLMS_Composer_' ) ) {
			return;
		}

		$relative = strtolower( str_replace( '_', '-', str_replace( 'Andw_LLMS_Composer_', '', $class ) ) );
		$filename = 'class-' . $relative . '.php';
		$filepath = ANDW_LLMS_COMPOSER_PLUGIN_DIR . 'includes/' . $filename;

		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}
}

spl_autoload_register( 'andw_llms_composer_autoload' );

if ( ! function_exists( 'andw_llms_composer_bootstrap_directories' ) ) {
	/**
	 * Ensure storage directories exist and populate bundled samples.
	 *
	 * @return void
	 */
	function andw_llms_composer_bootstrap_directories() {
		$target = ANDW_LLMS_COMPOSER_MD_STORAGE_DIR;

		if ( ! wp_mkdir_p( $target ) ) {
			return;
		}

		$index_path = trailingslashit( dirname( $target ) ) . 'index.php';
		if ( ! file_exists( $index_path ) ) {
			file_put_contents( $index_path, "<?php\n// Silence is golden.\n" );
		}

		if ( ! is_dir( ANDW_LLMS_COMPOSER_MD_BUNDLE_DIR ) ) {
			return;
		}

		$iterator = new DirectoryIterator( ANDW_LLMS_COMPOSER_MD_BUNDLE_DIR );
		foreach ( $iterator as $fileinfo ) {
			if ( $fileinfo->isDot() || ! $fileinfo->isFile() ) {
				continue;
			}

			$destination = $target . $fileinfo->getFilename();
			if ( file_exists( $destination ) ) {
				continue;
			}

			copy( $fileinfo->getPathname(), $destination );
		}
	}
}

if ( ! function_exists( 'andw_llms_composer_activate' ) ) {
	/**
	 * Plugin activation hook.
	 *
	 * @return void
	 */
	function andw_llms_composer_activate() {
		andw_llms_composer_bootstrap_directories();
	}
}

if ( ! function_exists( 'andw_llms_composer_deactivate' ) ) {
	/**
	 * Plugin deactivation hook.
	 *
	 * @return void
	 */
	function andw_llms_composer_deactivate() {
		// Reserved for future cleanup logic.
	}
}

register_activation_hook( ANDW_LLMS_COMPOSER_PLUGIN_FILE, 'andw_llms_composer_activate' );
register_deactivation_hook( ANDW_LLMS_COMPOSER_PLUGIN_FILE, 'andw_llms_composer_deactivate' );

if ( ! function_exists( 'andw_llms_composer_bootstrap' ) ) {
	/**
	 * Initialize plugin runtime.
	 *
	 * @return void
	 */
	function andw_llms_composer_bootstrap() {
		load_plugin_textdomain( 'andw-llms-composer', false, dirname( plugin_basename( ANDW_LLMS_COMPOSER_PLUGIN_FILE ) ) . '/languages/' );

		$plugin = Andw_LLMS_Composer_Plugin::instance();
		$plugin->init();
	}
}

add_action( 'plugins_loaded', 'andw_llms_composer_bootstrap' );
