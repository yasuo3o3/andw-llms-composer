<?php
/**
 * llms.txt builder.
 *
 * @package andw-llms-composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compose llms.txt output with caching and validation.
 */
class Andw_LLMS_Composer_Llms_Builder {
	/**
	 * Markdown store.
	 *
	 * @var Andw_LLMS_Composer_Md_Store
	 */
	protected $store;

	/**
	 * Sitemap handler.
	 *
	 * @var Andw_LLMS_Composer_Sitemap
	 */
	protected $sitemap;

	/**
	 * HTML sync.
	 *
	 * @var Andw_LLMS_Composer_Html_Sync
	 */
	protected $html_sync;

	/**
	 * Validation log cache.
	 *
	 * @var array
	 */
	protected $validation_log = array();

	/**
	 * Constructor.
	 *
	 * @param Andw_LLMS_Composer_Md_Store  $store Markdown store.
	 * @param Andw_LLMS_Composer_Sitemap   $sitemap Sitemap handler.
	 * @param Andw_LLMS_Composer_Html_Sync $html_sync HTML sync handler.
	 */
	public function __construct( Andw_LLMS_Composer_Md_Store $store, Andw_LLMS_Composer_Sitemap $sitemap, Andw_LLMS_Composer_Html_Sync $html_sync ) {
		$this->store     = $store;
		$this->sitemap   = $sitemap;
		$this->html_sync = $html_sync;
	}

	/**
	 * Service init placeholder.
	 *
	 * @return void
	 */
	public function init() {}

	/**
	 * Output llms.txt content directly.
	 *
	 * @return true|WP_Error
	 */
	public function output() {
		header( 'Content-Type: text/plain; charset=utf-8', true );

		$body = $this->get_cached_body();

		if ( is_wp_error( $body ) ) {
			echo esc_html( $body->get_error_message() );
			return $body;
		}

		echo $body;

		return true;
	}

	/**
	 * Retrieve body content for preview.
	 *
	 * @return string|WP_Error
	 */
	public function get_body() {
		return $this->get_cached_body();
	}

	/**
	 * Provide summary payload for script tag.
	 *
	 * @return array
	 */
	public function get_page_summary_for_script() {
		$primary = $this->get_primary_links();
		$current = array();

		if ( is_singular() ) {
			$current_url = get_permalink();
			foreach ( $primary as $link ) {
				if ( $link['url'] === $current_url ) {
					$current = $link;
					break;
				}
			}

			if ( empty( $current ) ) {
				$current = array(
					'title'   => get_the_title(),
					'url'     => $current_url,
					'summary' => wp_trim_words( wp_strip_all_tags( get_the_excerpt() ? get_the_excerpt() : get_post()->post_content ), 40 ),
				);
			}
		}

		return $current;
	}

	/**
	 * Clear cache.
	 *
	 * @return void
	 */
	public function invalidate_cache() {
		delete_transient( andw_llms_composer_transient_key( 'llms_body' ) );
		delete_transient( andw_llms_composer_transient_key( 'llms_validation' ) );
	}

	/**
	 * Get cached body or rebuild.
	 *
	 * @return string|WP_Error
	 */
	protected function get_cached_body() {
		$settings = andw_llms_composer_get_settings();
		$key      = andw_llms_composer_transient_key( 'llms_body' );

		if ( ! empty( $settings['cache_enabled'] ) ) {
			$cached = get_transient( $key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$body = $this->build_body();

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if ( ! empty( $settings['cache_enabled'] ) ) {
			set_transient( $key, $body, MINUTE_IN_SECONDS * max( 1, (int) $settings['cache_ttl'] ) );
			set_transient( andw_llms_composer_transient_key( 'llms_validation' ), $this->validation_log, MINUTE_IN_SECONDS * max( 1, (int) $settings['cache_ttl'] ) );
		}

		return $body;
	}

	/**
	 * Compose llms.txt body from settings and sitemap data.
	 *
	 * @return string|WP_Error
	 */
	protected function build_body() {
		$this->validation_log = array();

		$settings = andw_llms_composer_get_settings();
		$lines    = array();

		$lines[] = '# ' . $settings['site_overview']['title'];

		if ( ! empty( $settings['site_overview']['summary'] ) ) {
			$lines[] = '';
			$lines[] = $settings['site_overview']['summary'];
		}

		if ( ! empty( $settings['site_overview']['notes'] ) ) {
			$lines[] = '';
			$lines[] = $settings['site_overview']['notes'];
		}

		$lines[] = '';
		$lines[] = '## ' . __( 'Primary Links', 'andw-llms-composer' );

		$links = $this->get_primary_links();
		$links = $this->validate_links( $links );

		foreach ( $links as $link ) {
			$entry = '[' . $link['title'] . '](' . $link['url'] . ')';
			if ( ! empty( $link['summary'] ) ) {
				$entry .= ': ' . $link['summary'];
			}
			$lines[] = $entry;
		}

		$body = implode( "\n", $lines );

		return $this->apply_guards( $body );
	}

	/**
	 * Collect prioritized link set from sitemap and manual config.
	 *
	 * @return array
	 */
	protected function get_primary_links() {
		$settings   = andw_llms_composer_get_settings();
		$configured = array();

		foreach ( (array) $settings['primary_links'] as $entry ) {
			$title    = isset( $entry['title'] ) ? sanitize_text_field( $entry['title'] ) : '';
			$url      = isset( $entry['url'] ) ? esc_url_raw( $entry['url'] ) : '';
			$summary  = isset( $entry['summary'] ) ? sanitize_text_field( $entry['summary'] ) : '';
			$priority = isset( $entry['priority'] ) ? (float) $entry['priority'] : 1.0;
			$locale   = isset( $entry['locale'] ) ? sanitize_text_field( $entry['locale'] ) : get_locale();

			if ( empty( $title ) || empty( $url ) ) {
				continue;
			}

			$configured[] = array(
				'title'    => $title,
				'url'      => $url,
				'summary'  => $summary,
				'priority' => $priority,
				'locale'   => $locale,
			);
		}

		$top = $this->sitemap->top_entries();
		$combined = array_merge( $top, $configured );

		$unique = array();
		$seen   = array();

		foreach ( $combined as $link ) {
			$key = md5( strtolower( $link['url'] ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]    = $link;
		}

		usort( $unique, function ( $a, $b ) {
			$priority_a = isset( $a['priority'] ) ? $a['priority'] : 0;
			$priority_b = isset( $b['priority'] ) ? $b['priority'] : 0;

			if ( $priority_a === $priority_b ) {
				return strcmp( $a['title'], $b['title'] );
			}

			return ( $priority_a > $priority_b ) ? -1 : 1;
		} );

		return $unique;
	}

	/**
	 * Validate link URLs and normalize redirects.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	protected function validate_links( $links ) {
		$validated = array();

		foreach ( $links as $link ) {
			$response = $this->validate_url( $link['url'] );

			if ( is_wp_error( $response ) ) {
				$this->validation_log[] = array(
					'url'     => $link['url'],
					'message' => $response->get_error_message(),
				);
				continue;
			}

			if ( isset( $response['normalized_url'] ) ) {
				$link['url'] = $response['normalized_url'];
			}

			$validated[] = $link;
		}

		return $validated;
	}

	/**
	 * Validate individual URL.
	 *
	 * @param string $url URL.
	 * @return array|WP_Error
	 */
	protected function validate_url( $url ) {
		$args = array(
			'timeout'    => 5,
			'redirects'  => 3,
			'user-agent' => 'andw-llms-composer/' . ANDW_LLMS_COMPOSER_VERSION,
		);

		$response = wp_safe_remote_head( $url, $args );

		if ( is_wp_error( $response ) || empty( $response['response'] ) ) {
			$response = wp_safe_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return andw_llms_composer_wp_error( 'http_error', sprintf( __( 'Failed to fetch %s', 'andw-llms-composer' ), esc_url( $url ) ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			return array();
		}

		if ( 301 === $code || 302 === $code ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( $location ) {
				return array( 'normalized_url' => esc_url_raw( $location ) );
			}
		}

		return andw_llms_composer_wp_error( 'http_status', sprintf( __( 'Unexpected response %1$s for %2$s', 'andw-llms-composer' ), $code, esc_url( $url ) ) );
	}

	/**
	 * Apply maximum size guards.
	 *
	 * @param string $body Body string.
	 * @return string
	 */
	protected function apply_guards( $body ) {
		$max_lines = 400;
		$max_chars = 20000;

		$lines = substr_count( $body, "\n" ) + 1;

		if ( $lines > $max_lines ) {
			$this->validation_log[] = array(
				'url'     => '',
				'message' => sprintf( __( 'Trimmed to %d lines.', 'andw-llms-composer' ), $max_lines ),
			);
			$body = implode( "\n", array_slice( explode( "\n", $body ), 0, $max_lines ) );
		}

		if ( strlen( $body ) > $max_chars ) {
			$this->validation_log[] = array(
				'url'     => '',
				'message' => sprintf( __( 'Trimmed to %d characters.', 'andw-llms-composer' ), $max_chars ),
			);
			$body = substr( $body, 0, $max_chars );
		}

		return $body;
	}
}
