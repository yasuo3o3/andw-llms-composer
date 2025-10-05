<?php
/**
 * WP-CLI integration.
 *
 * @package andw-llms-composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * CLI command handler.
	 */
	class Andw_LLMS_Composer_Cli {
		/**
		 * Plugin instance.
		 *
		 * @var Andw_LLMS_Composer_Plugin
		 */
		protected $plugin;

		/**
		 * Constructor.
		 *
		 * @param Andw_LLMS_Composer_Plugin $plugin Plugin instance.
		 */
		public function __construct( Andw_LLMS_Composer_Plugin $plugin ) {
			$this->plugin = $plugin;
			WP_CLI::add_command( 'andwllms regen', array( $this, 'handle_regen' ) );
		}

		/**
		 * Handle regeneration command.
		 *
		 * @param array $args Positional arguments.
		 * @param array $assoc Associative arguments.
		 * @return void
		 */
		public function handle_regen( $args, $assoc ) {
			$target = isset( $assoc['target'] ) ? $assoc['target'] : 'all';

			switch ( $target ) {
				case 'llms':
					$this->plugin->llms_builder()->invalidate_cache();
					WP_CLI::success( 'llms.txt cache cleared.' );
					break;
				case 'sitemap':
					$this->plugin->sitemap()->rebuild();
					WP_CLI::success( 'Sitemap rebuilt.' );
					break;
				case 'all':
				default:
					$this->plugin->sitemap()->rebuild();
					$this->plugin->llms_builder()->invalidate_cache();
					WP_CLI::success( 'llms.txt cache cleared and sitemap rebuilt.' );
					break;
			}
		}
	}
}
