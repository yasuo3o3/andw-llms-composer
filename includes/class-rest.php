<?php
/**
 * REST API endpoints.
 *
 * @package andw-llms-composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provide REST routes for preview and regeneration.
 */
class Andw_LLMS_Composer_Rest {
	/**
	 * Plugin instance.
	 *
	 * @var Andw_LLMS_Composer_Plugin
	 */
	protected $plugin;

	/**
	 * Constructor.
	 *
	 * @param Andw_LLMS_Composer_Plugin $plugin Plugin.
	 */
	public function __construct( Andw_LLMS_Composer_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'andw-llms-composer/v1',
			'/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_preview' ),
				'permission_callback' => array( $this, 'ensure_manage' ),
			)
		);

		register_rest_route(
			'andw-llms-composer/v1',
			'/regenerate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_regenerate' ),
				'permission_callback' => array( $this, 'ensure_manage' ),
				'args'                => array(
					'target' => array(
						'type'     => 'string',
						'enum'     => array( 'llms', 'sitemap', 'all' ),
						'default'  => 'all',
					),
				),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function ensure_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle preview endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_preview( WP_REST_Request $request ) {
		$body = $this->plugin->llms_builder()->get_body();
		if ( is_wp_error( $body ) ) {
			return new WP_REST_Response( array( 'error' => $body->get_error_message() ), 500 );
		}

		return new WP_REST_Response( array( 'body' => $body ), 200 );
	}

	/**
	 * Handle regeneration endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_regenerate( WP_REST_Request $request ) {
		$target = $request->get_param( 'target' );

		switch ( $target ) {
			case 'llms':
				$this->plugin->llms_builder()->invalidate_cache();
				break;
			case 'sitemap':
				$this->plugin->sitemap()->rebuild();
				break;
			case 'all':
			default:
				$this->plugin->sitemap()->rebuild();
				$this->plugin->llms_builder()->invalidate_cache();
				break;
		}

		return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}
}
