<?php
/**
 * Sitemap integration and scoring.
 *
 * @package andw-llms-composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build sitemap insights and optional XML export.
 */
class Andw_LLMS_Composer_Sitemap {
	/**
	 * Markdown store.
	 *
	 * @var Andw_LLMS_Composer_Md_Store
	 */
	protected $store;

	/**
	 * Cached entries.
	 *
	 * @var array
	 */
	protected $entries = array();

	/**
	 * Constructor.
	 *
	 * @param Andw_LLMS_Composer_Md_Store $store Markdown store.
	 */
	public function __construct( Andw_LLMS_Composer_Md_Store $store ) {
		$this->store = $store;
	}

	/**
	 * Initialize hooks and load entries.
	 *
	 * @return void
	 */
	public function init() {
		$this->entries = get_option( 'andw_llms_composer_sitemap_entries', array() );
		add_action( 'andw_llms_composer_rebuild_sitemap', array( $this, 'rebuild' ) );
	}

	/**
	 * Schedule rebuild using cron.
	 *
	 * @return void
	 */
	public function schedule_rebuild() {
		if ( wp_next_scheduled( 'andw_llms_composer_rebuild_sitemap' ) ) {
			return;
		}

		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'andw_llms_composer_rebuild_sitemap' );
	}

	/**
	 * Rebuild entries immediately.
	 *
	 * @return void
	 */
	public function rebuild() {
		$entries = $this->collect_entries();

		$this->entries = $entries;
		update_option( 'andw_llms_composer_sitemap_entries', $entries );

		if ( andw_llms_composer_bool( andw_llms_composer_get_setting( 'sitemap_enabled', true ) ) ) {
			$this->write_sitemap_file( $entries );
		}
	}

	/**
	 * Return prioritized entries.
	 *
	 * @return array
	 */
	public function top_entries() {
		$entries = $this->entries;

		usort( $entries, function ( $a, $b ) {
			return ( $a['priority'] > $b['priority'] ) ? -1 : 1;
		} );

		return array_slice( $entries, 0, 20 );
	}

	/**
	 * Collect entries from existing sitemap or internal fallback.
	 *
	 * @return array
	 */
	protected function collect_entries() {
		$external = $this->load_existing_sitemap();
		if ( ! empty( $external ) ) {
			return $external;
		}

		return $this->build_internal_entries();
	}

	/**
	 * Attempt to load existing sitemap.xml.
	 *
	 * @return array
	 */
	protected function load_existing_sitemap() {
		$urls      = array();
		$endpoints = array( '/sitemap.xml', '/wp-sitemap.xml' );

		foreach ( $endpoints as $endpoint ) {
			$response = wp_safe_remote_get( home_url( $endpoint ), array( 'timeout' => 5 ) );
			if ( is_wp_error( $response ) ) {
				continue;
			}

			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$body = wp_remote_retrieve_body( $response );
			if ( empty( $body ) ) {
				continue;
			}

			libxml_use_internal_errors( true );
			$xml = simplexml_load_string( $body );
			libxml_clear_errors();

			if ( ! $xml ) {
				continue;
			}

			if ( isset( $xml->url ) ) {
				foreach ( $xml->url as $item ) {
					$loc = (string) $item->loc;
					if ( $loc ) {
						$urls[] = $loc;
					}
				}
			} elseif ( isset( $xml->sitemap ) ) {
				foreach ( $xml->sitemap as $child ) {
					$loc = (string) $child->loc;
					if ( $loc ) {
						$urls = array_merge( $urls, $this->load_sitemap_child( $loc ) );
					}
				}
			}

			if ( ! empty( $urls ) ) {
				break;
			}
		}

		if ( empty( $urls ) ) {
			return array();
		}

		return $this->map_urls_to_entries( array_slice( array_unique( $urls ), 0, 200 ) );
	}

	/**
	 * Fetch nested sitemap part.
	 *
	 * @param string $url URL.
	 * @return array
	 */
	protected function load_sitemap_child( $url ) {
		$response = wp_safe_remote_get( $url, array( 'timeout' => 5 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body );
		libxml_clear_errors();

		$urls = array();

		if ( $xml && isset( $xml->url ) ) {
			foreach ( $xml->url as $item ) {
				$loc = (string) $item->loc;
				if ( $loc ) {
					$urls[] = $loc;
				}
			}
		}

		return $urls;
	}

	/**
	 * Map URLs to entry metadata.
	 *
	 * @param array $urls URLs.
	 * @return array
	 */
	protected function map_urls_to_entries( $urls ) {
		$entries = array();
		$coeff   = andw_llms_composer_get_setting( 'priority_coefficients', array() );
		$coeff   = wp_parse_args( $coeff, array( 'links' => 1, 'frecency' => 1, 'pattern' => 1 ) );

		foreach ( $urls as $url ) {
			$post_id = url_to_postid( $url );
			$title   = $url;
			$summary = '';
			$links   = 0;
			$frecency = 0.5;

			if ( $post_id ) {
				$post     = get_post( $post_id );
				$title    = get_the_title( $post_id );
				$summary  = wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 40 );
				$links    = $this->count_internal_links( $post->post_content );
				$modified = mysql2date( 'U', $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified, false );
				$frecency = max( 0.1, 1 - ( time() - $modified ) / ( 60 * 60 * 24 * 90 ) );
			}

			$pattern = $this->pattern_score( $url );
			$priority = ( $coeff['links'] * $links ) + ( $coeff['frecency'] * $frecency ) + ( $coeff['pattern'] * $pattern );

			$entries[] = array(
				'title'    => $title,
				'url'      => esc_url_raw( $url ),
				'summary'  => $summary,
				'priority' => round( $priority, 4 ),
				'locale'   => get_locale(),
			);
		}

		return $entries;
	}

	/**
	 * Build entries by querying posts directly.
	 *
	 * @return array
	 */
	protected function build_internal_entries() {
		$query = new WP_Query( array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		) );

		$entries = array();
		$coeff   = andw_llms_composer_get_setting( 'priority_coefficients', array() );
		$coeff   = wp_parse_args( $coeff, array( 'links' => 1, 'frecency' => 1, 'pattern' => 1 ) );

		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );

			$links    = $this->count_internal_links( $post->post_content );
			$modified = mysql2date( 'U', $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified, false );
			$frecency = max( 0.1, 1 - ( time() - $modified ) / ( 60 * 60 * 24 * 90 ) );
			$pattern  = $this->pattern_score( get_permalink( $post ) );

			$priority = ( $coeff['links'] * $links ) + ( $coeff['frecency'] * $frecency ) + ( $coeff['pattern'] * $pattern );

			$entries[] = array(
				'title'    => get_the_title( $post ),
				'url'      => get_permalink( $post ),
				'summary'  => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 40 ),
				'priority' => round( $priority, 4 ),
				'locale'   => get_locale(),
			);
		}

		return $entries;
	}

	/**
	 * Count internal links within HTML content.
	 *
	 * @param string $html HTML string.
	 * @return int
	 */
	protected function count_internal_links( $html ) {
		$matches = array();
		$count   = preg_match_all( '#<a[^>]+href="([^"]+)"#i', $html, $matches );

		if ( empty( $matches[1] ) ) {
			return 0;
		}

		$home  = home_url();
		$total = 0;

		foreach ( $matches[1] as $href ) {
			if ( 0 === strpos( $href, $home ) || 0 === strpos( $href, '/' ) ) {
				$total++;
			}
		}

		return $total;
	}

	/**
	 * Basic pattern heuristic score.
	 *
	 * @param string $url URL.
	 * @return float
	 */
	protected function pattern_score( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( null === $path || '' === $path || '/' === $path ) {
			return 2.0;
		}

		$score = 1.0;

		if ( preg_match( '#/(about|contact|services|product)#i', $path ) ) {
			$score += 0.5;
		}

		if ( substr_count( $path, '/' ) <= 2 ) {
			$score += 0.25;
		}

		return $score;
	}

	/**
	 * Persist sitemap.xml file under wp-content.
	 *
	 * @param array $entries Entries.
	 * @return void
	 */
	protected function write_sitemap_file( $entries ) {
		$base = trailingslashit( WP_CONTENT_DIR ) . 'andw-llms-composer';
		wp_mkdir_p( $base );

		$file = $base . '/sitemap.xml';

		$xml   = array();
		$xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

		foreach ( $entries as $entry ) {
			$xml[] = '  <url>';
			$xml[] = '    <loc>' . esc_url( $entry['url'] ) . '</loc>';
			$xml[] = '    <priority>' . number_format_i18n( min( 1, max( 0.1, $entry['priority'] / 10 ) ), 2 ) . '</priority>';
			$xml[] = '  </url>';
		}

		$xml[] = '</urlset>';

		file_put_contents( $file, implode( "\n", $xml ) );
	}
}
