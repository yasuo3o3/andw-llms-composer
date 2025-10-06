<?php
/**
 * Admin interface handler.
 *
 * @package andw-llms-composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render and process admin settings pages.
 */
class Andw_LLMS_Composer_Admin {
	/**
	 * Plugin instance.
	 *
	 * @var Andw_LLMS_Composer_Plugin
	 */
	protected $plugin;

	/**
	 * Current tab slug.
	 *
	 * @var string
	 */
	protected $tab = 'overview';

	/**
	 * Constructor.
	 *
	 * @param Andw_LLMS_Composer_Plugin $plugin Plugin instance.
	 */
	public function __construct( Andw_LLMS_Composer_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_post_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register menu page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'LLMS Composer', 'andw-llms-composer' ),
			__( 'LLMS Composer', 'andw-llms-composer' ),
			'manage_options',
			'andw-llms-composer',
			array( $this, 'render_page' ),
			'dashicons-media-text',
			65
		);
	}

	/**
	 * Handle admin POST submissions.
	 *
	 * @return void
	 */
	public function handle_post_actions() {
		if ( empty( $_POST['andw_llms_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		andw_llms_composer_require_manage_cap();

		$action = sanitize_key( wp_unslash( $_POST['andw_llms_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		switch ( $action ) {
			case 'save_overview':
				check_admin_referer( 'andw_llms_save_overview' );
				$this->save_overview();
				break;
			case 'save_links':
				check_admin_referer( 'andw_llms_save_links' );
				$this->save_links();
				break;
			case 'run_sync':
				check_admin_referer( 'andw_llms_run_sync' );
				$this->run_manual_sync();
				break;
			case 'set_lock':
				check_admin_referer( 'andw_llms_set_lock' );
				$this->update_lock();
				break;
			case 'update_settings':
				check_admin_referer( 'andw_llms_update_settings' );
				$this->save_output_settings();
				break;
		}
	}

	/**
	 * Persist overview values.
	 *
	 * @return void
	 */
	protected function save_overview() {
		$title   = isset( $_POST['overview_title'] ) ? sanitize_text_field( wp_unslash( $_POST['overview_title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$summary = isset( $_POST['overview_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['overview_summary'] ) ) : '';
		$notes   = isset( $_POST['overview_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['overview_notes'] ) ) : '';

		$settings                     = andw_llms_composer_get_settings();
		$settings['site_overview']    = array(
			'title'   => $title,
			'summary' => $summary,
			'notes'   => $notes,
		);

		andw_llms_composer_update_settings( $settings );
		add_settings_error( 'andw-llms-composer', 'overview-saved', esc_html__( 'Overview saved.', 'andw-llms-composer' ), 'updated' );
	}

	/**
	 * Save manual link entries.
	 *
	 * @return void
	 */
	protected function save_links() {
		$entries = isset( $_POST['links'] ) ? (array) wp_unslash( $_POST['links'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$clean   = array();

		foreach ( $entries as $entry ) {
			$title    = isset( $entry['title'] ) ? sanitize_text_field( $entry['title'] ) : '';
			$url      = isset( $entry['url'] ) ? esc_url_raw( $entry['url'] ) : '';
			$summary  = isset( $entry['summary'] ) ? sanitize_text_field( $entry['summary'] ) : '';
			$priority = isset( $entry['priority'] ) ? (float) $entry['priority'] : 1.0;
			$locale   = isset( $entry['locale'] ) ? sanitize_text_field( $entry['locale'] ) : get_locale();

			if ( empty( $title ) || empty( $url ) ) {
				continue;
			}

			$clean[] = compact( 'title', 'url', 'summary', 'priority', 'locale' );
		}

		$settings                    = andw_llms_composer_get_settings();
		$settings['primary_links']   = $clean;

		andw_llms_composer_update_settings( $settings );
		add_settings_error( 'andw-llms-composer', 'links-saved', esc_html__( 'Links updated.', 'andw-llms-composer' ), 'updated' );
	}

	/**
	 * Run manual sync job for selected post/all.
	 *
	 * @return void
	 */
	protected function run_manual_sync() {
		$post_id = isset( $_POST['target_post'] ) ? intval( $_POST['target_post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $post_id ) {
			$this->plugin->html_sync()->capture_post_html( $post_id );
		}

		$this->plugin->sitemap()->rebuild();
		$this->plugin->llms_builder()->invalidate_cache();

		add_settings_error( 'andw-llms-composer', 'sync-run', esc_html__( 'Sync job executed.', 'andw-llms-composer' ), 'updated' );
	}

	/**
	 * Update sync direction lock.
	 *
	 * @return void
	 */
	protected function update_lock() {
		$post_id   = isset( $_POST['lock_post'] ) ? intval( $_POST['lock_post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$direction = isset( $_POST['lock_direction'] ) ? sanitize_key( wp_unslash( $_POST['lock_direction'] ) ) : 'html';

		if ( $post_id ) {
			$this->plugin->html_sync()->set_lock( $post_id, $direction );
			add_settings_error( 'andw-llms-composer', 'lock-updated', esc_html__( 'Sync direction updated.', 'andw-llms-composer' ), 'updated' );
		}
	}

	/**
	 * Save output/caching settings.
	 *
	 * @return void
	 */
	protected function save_output_settings() {
		$ttl         = isset( $_POST['cache_ttl'] ) ? absint( $_POST['cache_ttl'] ) : 15; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$cache       = isset( $_POST['cache_enabled'] );
		$meta        = isset( $_POST['auto_meta_enabled'] );
		$script      = isset( $_POST['auto_script_enabled'] );
		$sitemap     = isset( $_POST['sitemap_enabled'] );

		$settings = andw_llms_composer_get_settings();

		$settings['cache_ttl']           = max( 5, $ttl );
		$settings['cache_enabled']       = (bool) $cache;
		$settings['auto_meta_enabled']   = (bool) $meta;
		$settings['auto_script_enabled'] = (bool) $script;
		$settings['sitemap_enabled']     = (bool) $sitemap;

		andw_llms_composer_update_settings( $settings );
		add_settings_error( 'andw-llms-composer', 'output-saved', esc_html__( 'Output settings saved.', 'andw-llms-composer' ), 'updated' );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_andw-llms-composer' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'andw-llms-admin', ANDW_LLMS_COMPOSER_PLUGIN_URL . 'assets/admin.css', array(), ANDW_LLMS_COMPOSER_VERSION );
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		andw_llms_composer_require_manage_cap();

		$this->tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$tabs = array(
			'overview' => __( 'Overview', 'andw-llms-composer' ),
			'links'    => __( 'Links', 'andw-llms-composer' ),
			'sync'     => __( 'Sync', 'andw-llms-composer' ),
			'output'   => __( 'Output', 'andw-llms-composer' ),
			'docs'     => __( 'Docs', 'andw-llms-composer' ),
		);

		if ( ! isset( $tabs[ $this->tab ] ) ) {
			$this->tab = 'overview';
		}

		settings_errors( 'andw-llms-composer' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'LLMS Composer', 'andw-llms-composer' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$active = $slug === $this->tab ? ' nav-tab-active' : '';
			echo '<a class="nav-tab' . esc_attr( $active ) . '" href="' . esc_url( admin_url( 'admin.php?page=andw-llms-composer&tab=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

			switch ( $this->tab ) {
			case 'links':
				$this->render_view( 'settings-links', array(
					'links' => andw_llms_composer_get_settings()['primary_links'],
				) );
				break;
			case 'sync':
				$this->render_view( 'settings-sync', array(
					'posts'     => $this->get_recent_posts(),
					'documents' => $this->plugin->md_store()->all(),
				) );
				break;
			case 'output':
				$this->render_view( 'settings-output', array(
					'settings'       => andw_llms_composer_get_settings(),
					'validation_log' => get_transient( andw_llms_composer_transient_key( 'llms_validation' ) ),
				) );
				break;
			case 'docs':
				$this->render_view( 'settings-docs', array() );
				break;
			case 'overview':
			default:
				$this->render_view( 'settings-overview', array(
					'settings' => andw_llms_composer_get_settings(),
				) );
				break;
		}

		echo '</div>';
	}

	/**
	 * Load PHP view.
	 *
	 * @param string $view View slug.
	 * @param array  $data Data passed to view.
	 * @return void
	 */
	protected function render_view( $view, $data ) {
		extract( $data, EXTR_SKIP );
		$path = ANDW_LLMS_COMPOSER_PLUGIN_DIR . 'views/' . $view . '.php';
		if ( file_exists( $path ) ) {
			require $path;
		}
	}

	/**
	 * Retrieve recently modified posts.
	 *
	 * @return array
	 */
	protected function get_recent_posts() {
		$query = new WP_Query( array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		) );

		return $query->posts;
	}
}
