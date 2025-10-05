<?php
/**
 * Markdown store.
 *
 * @package andw-llms-composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage markdown documents with front matter metadata.
 */
class Andw_LLMS_Composer_Md_Store {
	/**
	 * Cached documents.
	 *
	 * @var array
	 */
	protected $index = array();

	/**
	 * Initialize the store.
	 *
	 * @return void
	 */
	public function init() {
		$this->ensure_storage();
		$this->refresh_index();
	}

	/**
	 * Ensure storage directories exist.
	 *
	 * @return void
	 */
	public function ensure_storage() {
		andw_llms_composer_bootstrap_directories();
	}

	/**
	 * Rebuild in-memory index.
	 *
	 * @return void
	 */
	public function refresh_index() {
		$this->index = array();
		$base = andw_llms_composer_get_storage_path();

		if ( ! is_dir( $base ) ) {
			return;
		}

		$iterator = new DirectoryIterator( $base );
		foreach ( $iterator as $fileinfo ) {
			if ( $fileinfo->isDot() || ! $fileinfo->isFile() ) {
				continue;
			}

			if ( ! andw_llms_composer_is_markdown_file( $fileinfo->getFilename() ) ) {
				continue;
			}

			$document = $this->read_file( $fileinfo->getPathname() );

			if ( empty( $document['meta']['id'] ) ) {
				continue;
			}

			$this->index[ $document['meta']['id'] ] = $document;
		}
	}

	/**
	 * Return all documents.
	 *
	 * @return array
	 */
	public function all() {
		return $this->index;
	}

	/**
	 * Get single document.
	 *
	 * @param string $id Document ID.
	 * @return array|false
	 */
	public function get( $id ) {
		return isset( $this->index[ $id ] ) ? $this->index[ $id ] : false;
	}

	/**
	 * Persist document.
	 *
	 * @param array  $meta Metadata.
	 * @param string $body Markdown body.
	 * @return true|WP_Error
	 */
	public function save( $meta, $body ) {
		$meta   = $this->sanitize_meta( $meta );
		$body   = $this->sanitize_body( $body );
		$check  = $this->validate_meta( $meta );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$meta['source_hash'] = andw_llms_composer_hash( $body );
		$meta['updated_at']  = isset( $meta['updated_at'] ) && $meta['updated_at'] ? $meta['updated_at'] : current_time( 'mysql', true );

		$file    = $this->document_path( $meta['id'] );
		$content = $this->build_front_matter( $meta ) . "\n\n" . $body;

		$this->ensure_storage();

		$result = file_put_contents( $file, $content );

		if ( false === $result ) {
			return andw_llms_composer_wp_error( 'write_failed', __( 'Failed to write markdown file.', 'andw-llms-composer' ) );
		}

		$this->index[ $meta['id'] ] = array(
			'meta' => $meta,
			'body' => $body,
			'raw'  => $content,
		);

		return true;
	}

	/**
	 * Delete document by ID.
	 *
	 * @param string $id Document ID.
	 * @return bool
	 */
	public function delete( $id ) {
		$file = $this->document_path( $id );

		if ( file_exists( $file ) ) {
			unlink( $file );
		}

		unset( $this->index[ $id ] );

		return true;
	}

	/**
	 * Resolve file path from document id.
	 *
	 * @param string $id Document ID.
	 * @return string
	 */
	protected function document_path( $id ) {
		$filename = sanitize_title( $id );
		if ( '' === $filename ) {
			$filename = sanitize_file_name( $id );
		}

		$filename = preg_replace( '/[^a-z0-9\-]+/i', '-', $filename );
		$filename = trim( $filename, '-' );

		if ( '' === $filename ) {
			$filename = strtolower( bin2hex( random_bytes( 6 ) ) );
		}

		return andw_llms_composer_get_storage_path( $filename . '.md' );
	}

	/**
	 * Sanitize metadata payload.
	 *
	 * @param array $meta Metadata.
	 * @return array
	 */
	protected function sanitize_meta( $meta ) {
		$allowed = array(
			'id'             => '',
			'canonical_url'  => '',
			'slug'           => '',
			'locale'         => '',
			'updated_at'     => '',
			'html_synced_at' => '',
			'source_hash'    => '',
			'title'          => '',
			'summary'        => '',
		);

		$meta = wp_parse_args( (array) $meta, $allowed );

		$meta['id']             = sanitize_title( $meta['id'] ? $meta['id'] : $meta['slug'] );
		$meta['canonical_url']  = esc_url_raw( $meta['canonical_url'] );
		$meta['slug']           = sanitize_title( $meta['slug'] );
		$meta['locale']         = sanitize_text_field( $meta['locale'] );
		$meta['updated_at']     = sanitize_text_field( $meta['updated_at'] );
		$meta['html_synced_at'] = sanitize_text_field( $meta['html_synced_at'] );
		$meta['title']          = sanitize_text_field( $meta['title'] );
		$meta['summary']        = sanitize_textarea_field( $meta['summary'] );

		return $meta;
	}

	/**
	 * Normalize line endings.
	 *
	 * @param string $body Markdown body.
	 * @return string
	 */
	protected function sanitize_body( $body ) {
		return preg_replace( "#\r\n?#", "\n", (string) $body );
	}

	/**
	 * Validate metadata requirements.
	 *
	 * @param array $meta Metadata.
	 * @return true|WP_Error
	 */
	protected function validate_meta( $meta ) {
		if ( empty( $meta['id'] ) ) {
			return andw_llms_composer_wp_error( 'invalid_meta', __( 'Document ID is required.', 'andw-llms-composer' ) );
		}

		return true;
	}

	/**
	 * Build front matter block.
	 *
	 * @param array $meta Metadata.
	 * @return string
	 */
	protected function build_front_matter( $meta ) {
		$lines = array( '---' );

		foreach ( $meta as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) || null === $value ) {
				continue;
			}

			$lines[] = $key . ': ' . $this->escape_front_matter_value( (string) $value );
		}

		$lines[] = '---';

		return implode( "\n", $lines );
	}

	/**
	 * Escape front matter values when necessary.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function escape_front_matter_value( $value ) {
		if ( false !== strpos( $value, ':' ) || false !== strpos( $value, '#' ) ) {
			return '"' . str_replace( '"', '\\"', $value ) . '"';
		}

		return $value;
	}

	/**
	 * Read markdown file into document array.
	 *
	 * @param string $path Path to file.
	 * @return array
	 */
	protected function read_file( $path ) {
		$raw = (string) file_get_contents( $path );

		list( $meta, $body ) = $this->split_into_parts( $raw );

		$meta['source_hash'] = isset( $meta['source_hash'] ) ? $meta['source_hash'] : andw_llms_composer_hash( $body );

		return array(
			'meta' => $meta,
			'body' => $body,
			'raw'  => $raw,
			'path' => $path,
		);
	}

	/**
	 * Split raw content into front matter + body.
	 *
	 * @param string $raw Raw file content.
	 * @return array
	 */
	protected function split_into_parts( $raw ) {
		$raw = preg_replace( "#\r\n?#", "\n", (string) $raw );

		if ( 0 !== strpos( $raw, "---\n" ) ) {
			return array( array(), trim( $raw ) );
		}

		$raw      = substr( $raw, 4 );
		$segments = explode( "\n---\n", $raw, 2 );
		$front    = isset( $segments[0] ) ? $segments[0] : '';
		$body     = isset( $segments[1] ) ? $segments[1] : '';

		$meta = $this->parse_front_matter( $front );

		return array( $meta, trim( $body ) );
	}

	/**
	 * Parse simple key:value front matter.
	 *
	 * @param string $front Raw front matter string.
	 * @return array
	 */
	protected function parse_front_matter( $front ) {
		$meta  = array();
		$lines = preg_split( "#\n+#", trim( (string) $front ) );

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) || false === strpos( $line, ':' ) ) {
				continue;
			}

			list( $key, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
			$key = sanitize_key( $key );

			if ( '' === $key ) {
				continue;
			}

			$meta[ $key ] = $this->unescape_front_matter_value( $value );
		}

		return $meta;
	}

	/**
	 * Unescape front matter string value.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function unescape_front_matter_value( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		if ( ( '"' === $value[0] && '"' === substr( $value, -1 ) ) || ( "'" === $value[0] && "'" === substr( $value, -1 ) ) ) {
			$value = substr( $value, 1, -1 );
		}

		return trim( str_replace( '\\"', '"', $value ) );
	}
}
