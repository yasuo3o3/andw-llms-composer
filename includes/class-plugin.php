<?php
/**
 * Core plugin orchestrator.
 *
 * @package andw-llms-composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin runtime container.
 */
class Andw_LLMS_Composer_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Andw_LLMS_Composer_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Markdown storage service.
	 *
	 * @var Andw_LLMS_Composer_Md_Store
	 */
	protected $md_store;

	/**
	 * HTML sync handler.
	 *
	 * @var Andw_LLMS_Composer_Html_Sync
	 */
	protected $html_sync;

	/**
	 * llms builder.
	 *
	 * @var Andw_LLMS_Composer_Llms_Builder
	 */
	protected $llms_builder;

	/**
	 * Sitemap handler.
	 *
	 * @var Andw_LLMS_Composer_Sitemap
	 */
	protected $sitemap;

	/**
	 * Admin UI handler.
	 *
	 * @var Andw_LLMS_Composer_Admin
	 */
	protected $admin;

	/**
	 * REST controller.
	 *
	 * @var Andw_LLMS_Composer_Rest
	 */
	protected $rest;

	/**
	 * Optional CLI controller.
	 *
	 * @var Andw_LLMS_Composer_Cli|null
	 */
	protected $cli;

	/**
	 * Inline localization helper.
	 *
	 * @var Andw_LLMS_Composer_I18n
	 */
	protected $i18n;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return Andw_LLMS_Composer_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hidden constructor.
	 */
	protected function __construct() {}

	/**
	 * Bootstrap services and hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->md_store     = new Andw_LLMS_Composer_Md_Store();
		$this->sitemap      = new Andw_LLMS_Composer_Sitemap( $this->md_store );
		$this->html_sync    = new Andw_LLMS_Composer_Html_Sync( $this->md_store );
		$this->llms_builder = new Andw_LLMS_Composer_Llms_Builder( $this->md_store, $this->sitemap, $this->html_sync );
		$this->admin        = new Andw_LLMS_Composer_Admin( $this );
		$this->rest         = new Andw_LLMS_Composer_Rest( $this );
		$this->i18n         = new Andw_LLMS_Composer_I18n();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->cli = new Andw_LLMS_Composer_Cli( $this );
		}

		$this->md_store->init();
		$this->i18n->init();
		$this->register_hooks();
		$this->admin->init();
		$this->rest->init();
		$this->sitemap->init();
		$this->html_sync->init();
		$this->llms_builder->init();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		add_action( 'init', array( $this, 'register_rewrites' ) );
		add_action( 'parse_request', array( $this, 'maybe_intercept_llms' ), 1 );
		add_action( 'template_redirect', array( $this, 'setup_head_injection' ) );
		add_action( 'wp_head', array( $this, 'render_head_link' ), 9 );
		add_action( 'wp_head', array( $this, 'render_head_script' ), 99 );
		add_action( 'save_post', array( $this, 'handle_post_save' ), 20, 3 );
		add_action( 'transition_post_status', array( $this, 'handle_status_transition' ), 20, 3 );
		add_action( 'admin_init', array( $this, 'maybe_handle_manual_jobs' ) );
	}

	/**
	 * Register rewrite rules for llms.txt.
	 *
	 * @return void
	 */
	public function register_rewrites() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?andw_llms_composer=1', 'top' );
		add_rewrite_tag( '%andw_llms_composer%', '([0-1])' );
	}

	/**
	 * Serve llms.txt when requested.
	 *
	 * @param WP $wp Global wp object.
	 * @return void
	 */
	public function maybe_intercept_llms( $wp ) {
		if ( ! andw_llms_composer_request_is_llms() && empty( $wp->query_vars['andw_llms_composer'] ) ) {
			return;
		}

		$result = $this->llms_builder->output();

		if ( is_wp_error( $result ) ) {
			status_header( 503 );
			header( 'Content-Type: text/plain; charset=utf-8', true );
			echo esc_html( $result->get_error_message() );
		} else {
			status_header( 200 );
		}

		exit;
	}

	/**
	 * Placeholder to keep template_redirect hook.
	 *
	 * @return void
	 */
	public function setup_head_injection() {
		if ( is_admin() ) {
			return;
		}
	}

	/**
	 * Render alternate markdown link tag.
	 *
	 * @return void
	 */
	public function render_head_link() {
		$settings = andw_llms_composer_get_settings();

		if ( empty( $settings['auto_meta_enabled'] ) ) {
			return;
		}

		echo "\n<link rel=\"alternate\" type=\"text/markdown\" href=\"" . esc_url( home_url( '/llms.txt' ) ) . "\" />\n";
	}

	/**
	 * Render embedded llms summary script.
	 *
	 * @return void
	 */
	public function render_head_script() {
		$settings = andw_llms_composer_get_settings();

		if ( empty( $settings['auto_script_enabled'] ) ) {
			return;
		}

		$summary = $this->llms_builder->get_page_summary_for_script();

		if ( empty( $summary ) ) {
			return;
		}

		echo "\n<script type=\"text/llms.txt\">" . wp_json_encode( $summary ) . '</script>' . "\n";
	}

	/**
	 * Handle post save events to manage cache and sync.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Whether update.
	 * @return void
	 */
	public function handle_post_save( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		$this->sitemap->schedule_rebuild();
		$this->llms_builder->invalidate_cache();

		if ( $update ) {
			$this->html_sync->capture_post_html( $post_id );
		}
	}

	/**
	 * Capture status transitions that affect visibility.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function handle_status_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		$this->sitemap->schedule_rebuild();
		$this->llms_builder->invalidate_cache();
	}

	/**
	 * Process manual jobs triggered via query args.
	 *
	 * @return void
	 */
	public function maybe_handle_manual_jobs() {
		if ( empty( $_GET['andw_llms_job'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		andw_llms_composer_require_manage_cap();

		$job = sanitize_text_field( wp_unslash( $_GET['andw_llms_job'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		switch ( $job ) {
			case 'flush-cache':
				check_admin_referer( 'andw_llms_run_job' );
				$this->llms_builder->invalidate_cache();
				add_settings_error( 'andw-llms-composer', 'cache-flushed', esc_html__( 'Cache cleared.', 'andw-llms-composer' ), 'updated' );
				break;
			case 'regen-sitemap':
				check_admin_referer( 'andw_llms_run_job' );
				$this->sitemap->rebuild();
				add_settings_error( 'andw-llms-composer', 'sitemap-regenerated', esc_html__( 'Sitemap regenerated.', 'andw-llms-composer' ), 'updated' );
				break;
		}
	}

	/**
	 * Markdown store accessor.
	 *
	 * @return Andw_LLMS_Composer_Md_Store
	 */
	public function md_store() {
		return $this->md_store;
	}

	/**
	 * HTML sync accessor.
	 *
	 * @return Andw_LLMS_Composer_Html_Sync
	 */
	public function html_sync() {
		return $this->html_sync;
	}

	/**
	 * llms builder accessor.
	 *
	 * @return Andw_LLMS_Composer_Llms_Builder
	 */
	public function llms_builder() {
		return $this->llms_builder;
	}

	/**
	 * Sitemap accessor.
	 *
	 * @return Andw_LLMS_Composer_Sitemap
	 */
	public function sitemap() {
		return $this->sitemap;
	}
}
