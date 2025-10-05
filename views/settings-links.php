<?php
/**
 * Links tab view.
 *
 * @var array $links
 */
?>
<form method="post">
	<?php wp_nonce_field( 'andw_llms_save_links' ); ?>
	<input type="hidden" name="andw_llms_action" value="save_links" />
	<table class="widefat andw-llms-links">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Title', 'andw-llms-composer' ); ?></th>
				<th><?php esc_html_e( 'URL', 'andw-llms-composer' ); ?></th>
				<th><?php esc_html_e( 'Summary', 'andw-llms-composer' ); ?></th>
				<th><?php esc_html_e( 'Priority', 'andw-llms-composer' ); ?></th>
				<th><?php esc_html_e( 'Locale', 'andw-llms-composer' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php
		$rows = array_merge( $links, array_fill( 0, 3, array( 'title' => '', 'url' => '', 'summary' => '', 'priority' => 1, 'locale' => get_locale() ) ) );
		foreach ( $rows as $index => $row ) :
		?>
		<tr>
			<td><input type="text" name="links[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $row['title'] ); ?>" class="regular-text" /></td>
			<td><input type="url" name="links[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $row['url'] ); ?>" class="regular-text" /></td>
			<td><input type="text" name="links[<?php echo esc_attr( $index ); ?>][summary]" value="<?php echo esc_attr( $row['summary'] ); ?>" class="regular-text" /></td>
			<td><input type="number" step="0.1" name="links[<?php echo esc_attr( $index ); ?>][priority]" value="<?php echo esc_attr( $row['priority'] ); ?>" class="small-text" /></td>
			<td><input type="text" name="links[<?php echo esc_attr( $index ); ?>][locale]" value="<?php echo esc_attr( $row['locale'] ); ?>" class="small-text" /></td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php submit_button( __( 'Save links', 'andw-llms-composer' ) ); ?>
</form>
