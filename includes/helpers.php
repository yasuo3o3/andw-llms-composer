<?php
/**
 * Helper functions.
 *
 * @package andw-llms-composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'andw_llms_composer_settings_key' ) ) {
	/**
	 * Option key helper.
	 *
	 * @return string
	 */
	function andw_llms_composer_settings_key() {
		return 'andw_llms_composer_settings';
	}
}

if ( ! function_exists( 'andw_llms_composer_default_settings' ) ) {
	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	function andw_llms_composer_default_settings() {
		return array(
			'cache_ttl'             => 15,
			'cache_enabled'         => true,
			'auto_meta_enabled'     => false,
			'auto_script_enabled'   => false,
			'sitemap_enabled'       => true,
			'priority_coefficients' => array(
				'links'    => 1.0,
				'frecency' => 1.0,
				'pattern'  => 1.0,
			),
			'default_lock'          => 'html',
			'site_overview'         => array(
				'title'   => get_bloginfo( 'name' ),
				'summary' => '',
				'notes'   => '',
			),
			'primary_links'         => array(),
		);
	}
}

if ( ! function_exists( 'andw_llms_composer_get_settings' ) ) {
	/**
	 * Retrieve stored settings with defaults.
	 *
	 * @return array
	 */
	function andw_llms_composer_get_settings() {
		$defaults = andw_llms_composer_default_settings();
		$stored   = get_option( andw_llms_composer_settings_key(), array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $defaults );
	}
}

if ( ! function_exists( 'andw_llms_composer_update_settings' ) ) {
	/**
	 * Persist settings payload.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	function andw_llms_composer_update_settings( $settings ) {
		$defaults = andw_llms_composer_default_settings();
		$settings = wp_parse_args( $settings, $defaults );

		update_option( andw_llms_composer_settings_key(), $settings );
	}
}

if ( ! function_exists( 'andw_llms_composer_get_setting' ) ) {
	/**
	 * Fetch single setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function andw_llms_composer_get_setting( $key, $default = null ) {
		$settings = andw_llms_composer_get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}

if ( ! function_exists( 'andw_llms_composer_set_setting' ) ) {
	/**
	 * Update single setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	function andw_llms_composer_set_setting( $key, $value ) {
		$settings         = andw_llms_composer_get_settings();
		$settings[ $key ] = $value;
		andw_llms_composer_update_settings( $settings );
	}
}

if ( ! function_exists( 'andw_llms_composer_bool' ) ) {
	/**
	 * Convert mixed value to boolean.
	 *
	 * @param mixed $value Input value.
	 * @return bool
	 */
	function andw_llms_composer_bool( $value ) {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}
}

if ( ! function_exists( 'andw_llms_composer_hash' ) ) {
	/**
	 * Generate deterministic hash for content.
	 *
	 * @param string $content Content string.
	 * @return string
	 */
	function andw_llms_composer_hash( $content ) {
		return hash( 'sha256', (string) $content );
	}
}

if ( ! function_exists( 'andw_llms_composer_transient_key' ) ) {
	/**
	 * Generate transient key.
	 *
	 * @param string $suffix Suffix.
	 * @return string
	 */
	function andw_llms_composer_transient_key( $suffix ) {
		return 'andw_llms_' . sanitize_key( $suffix );
	}
}

if ( ! function_exists( 'andw_llms_composer_request_is_llms' ) ) {
	/**
	 * Determine if current request points to llms.txt.
	 *
	 * @return bool
	 */
	function andw_llms_composer_request_is_llms() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		$path = untrailingslashit( strtolower( $path ) );

		return ( '/llms.txt' === $path );
	}
}

if ( ! function_exists( 'andw_llms_composer_get_storage_path' ) ) {
	/**
	 * Resolve markdown storage path.
	 *
	 * @param string $file Optional file name.
	 * @return string
	 */
	function andw_llms_composer_get_storage_path( $file = '' ) {
		$base = ANDW_LLMS_COMPOSER_MD_STORAGE_DIR;

		if ( '' === $file ) {
			return $base;
		}

		return trailingslashit( $base ) . ltrim( $file, '/' );
	}
}

if ( ! function_exists( 'andw_llms_composer_is_markdown_file' ) ) {
	/**
	 * Check markdown extension.
	 *
	 * @param string $filename Filename.
	 * @return bool
	 */
	function andw_llms_composer_is_markdown_file( $filename ) {
		return (bool) preg_match( '/\.md$/i', $filename );
	}
}

if ( ! function_exists( 'andw_llms_composer_wp_error' ) ) {
	/**
	 * Helper to create WP_Error with namespace.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param mixed  $data Optional data.
	 * @return WP_Error
	 */
	function andw_llms_composer_wp_error( $code, $message, $data = null ) {
		return new WP_Error( 'andw_llms_' . $code, $message, $data );
	}
}

if ( ! function_exists( 'andw_llms_composer_clean_array' ) ) {
	/**
	 * Recursively sanitize array (text fields).
	 *
	 * @param array $input Input array.
	 * @return array
	 */
	function andw_llms_composer_clean_array( $input ) {
		$clean = array();

		foreach ( (array) $input as $key => $value ) {
			if ( is_array( $value ) ) {
				$clean[ sanitize_key( $key ) ] = andw_llms_composer_clean_array( $value );
			} else {
				$clean[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}
}

if ( ! function_exists( 'andw_llms_composer_require_manage_cap' ) ) {
	/**
	 * Abort when capability missing.
	 *
	 * @return void
	 */
	function andw_llms_composer_require_manage_cap() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this resource.', 'andw-llms-composer' ) );
		}
	}
}

if ( ! function_exists( 'andw_llms_composer_dependencies' ) ) {
	/**
	 * Convenience accessor for plugin singleton.
	 *
	 * @return Andw_LLMS_Composer_Plugin
	 */
	function andw_llms_composer_dependencies() {
		return Andw_LLMS_Composer_Plugin::instance();
	}
}
