<?php
/**
 * Overview tab.
 *
 * @var array $settings
 */
?>
<form method="post">
	<?php wp_nonce_field( 'andw_llms_save_overview' ); ?>
	<input type="hidden" name="andw_llms_action" value="save_overview" />
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="overview_title"><?php esc_html_e( 'Site title', 'andw-llms-composer' ); ?></label></th>
			<td><input name="overview_title" id="overview_title" type="text" class="regular-text" value="<?php echo esc_attr( $settings['site_overview']['title'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="overview_summary"><?php esc_html_e( 'Summary', 'andw-llms-composer' ); ?></label></th>
			<td><textarea name="overview_summary" id="overview_summary" class="large-text" rows="5"><?php echo esc_textarea( $settings['site_overview']['summary'] ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="overview_notes"><?php esc_html_e( 'Notes', 'andw-llms-composer' ); ?></label></th>
			<td><textarea name="overview_notes" id="overview_notes" class="large-text" rows="5"><?php echo esc_textarea( $settings['site_overview']['notes'] ); ?></textarea></td>
		</tr>
	</table>
	<?php submit_button( __( 'Save overview', 'andw-llms-composer' ) ); ?>
</form>
