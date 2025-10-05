<?php
/**
 * Output tab view.
 *
 * @var array $settings
 * @var array $validation_log
 */

$plugin = andw_llms_composer_dependencies();
$llms   = $plugin->llms_builder()->get_body();
?>
<form method="post" class="andw-llms-output">
	<?php wp_nonce_field( 'andw_llms_update_settings' ); ?>
	<input type="hidden" name="andw_llms_action" value="update_settings" />
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Cache TTL (minutes)', 'andw-llms-composer' ); ?></th>
			<td><input type="number" min="5" name="cache_ttl" value="<?php echo esc_attr( $settings['cache_ttl'] ); ?>" class="small-text" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable cache', 'andw-llms-composer' ); ?></th>
			<td><input type="checkbox" name="cache_enabled" value="1" <?php checked( $settings['cache_enabled'], true ); ?> /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Inject alternate link', 'andw-llms-composer' ); ?></th>
			<td><input type="checkbox" name="auto_meta_enabled" value="1" <?php checked( $settings['auto_meta_enabled'], true ); ?> /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Embed llms script', 'andw-llms-composer' ); ?></th>
			<td><input type="checkbox" name="auto_script_enabled" value="1" <?php checked( $settings['auto_script_enabled'], true ); ?> /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Generate sitemap.xml', 'andw-llms-composer' ); ?></th>
			<td><input type="checkbox" name="sitemap_enabled" value="1" <?php checked( $settings['sitemap_enabled'], true ); ?> /></td>
		</tr>
	</table>
	<?php submit_button( __( 'Save output settings', 'andw-llms-composer' ) ); ?>
</form>

<div class="andw-llms-output-actions">
	<?php $flush_url = wp_nonce_url( add_query_arg( 'andw_llms_job', 'flush-cache', admin_url( 'admin.php?page=andw-llms-composer&tab=output' ) ), 'andw_llms_run_job' ); ?>
	<?php $regen_url = wp_nonce_url( add_query_arg( 'andw_llms_job', 'regen-sitemap', admin_url( 'admin.php?page=andw-llms-composer&tab=output' ) ), 'andw_llms_run_job' ); ?>
	<a class="button" href="<?php echo esc_url( $flush_url ); ?>"><?php esc_html_e( 'Clear cache', 'andw-llms-composer' ); ?></a>
	<a class="button" href="<?php echo esc_url( $regen_url ); ?>"><?php esc_html_e( 'Rebuild sitemap', 'andw-llms-composer' ); ?></a>
</div>

<h2><?php esc_html_e( 'llms.txt Preview', 'andw-llms-composer' ); ?></h2>
<div class="andw-llms-preview">
<?php if ( is_wp_error( $llms ) ) : ?>
	<p class="notice notice-error"><strong><?php echo esc_html( $llms->get_error_message() ); ?></strong></p>
<?php else : ?>
	<pre><?php echo esc_html( $llms ); ?></pre>
<?php endif; ?>
</div>

<?php if ( ! empty( $validation_log ) ) : ?>
<h2><?php esc_html_e( 'Validation log', 'andw-llms-composer' ); ?></h2>
<ul class="andw-llms-validation">
	<?php foreach ( $validation_log as $entry ) : ?>
	<li><?php echo esc_html( $entry['message'] . ( ! empty( $entry['url'] ) ? ' [' . $entry['url'] . ']' : '' ) ); ?></li>
	<?php endforeach; ?>
</ul>
<?php endif; ?>
