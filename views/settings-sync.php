<?php
/**
 * Sync tab view.
 *
 * @var array $posts
 * @var array $documents
 */

$plugin = andw_llms_composer_dependencies();
?>
<form method="post" class="andw-llms-sync">
	<?php wp_nonce_field( 'andw_llms_run_sync' ); ?>
	<input type="hidden" name="andw_llms_action" value="run_sync" />
	<h2><?php esc_html_e( 'Run sync job', 'andw-llms-composer' ); ?></h2>
	<p>
		<label for="target_post"><?php esc_html_e( 'Target post (optional)', 'andw-llms-composer' ); ?></label>
		<select name="target_post" id="target_post">
			<option value="0"><?php esc_html_e( 'All', 'andw-llms-composer' ); ?></option>
			<?php foreach ( $posts as $post_id ) : ?>
			<?php $post = get_post( $post_id ); ?>
			<option value="<?php echo esc_attr( $post_id ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?> (<?php echo esc_html( $post->post_type ); ?>)</option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php submit_button( __( 'Execute sync', 'andw-llms-composer' ), 'primary', 'submit', false ); ?>
</form>

<h2><?php esc_html_e( 'Documents', 'andw-llms-composer' ); ?></h2>
<table class="widefat">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Post', 'andw-llms-composer' ); ?></th>
			<th><?php esc_html_e( 'Direction', 'andw-llms-composer' ); ?></th>
			<th><?php esc_html_e( 'Updated', 'andw-llms-composer' ); ?></th>
			<th><?php esc_html_e( 'Summary', 'andw-llms-composer' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'andw-llms-composer' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $posts as $post_id ) : ?>
		<?php
		$post    = get_post( $post_id );
		$doc_id  = $post->post_type . '-' . $post_id;
		$doc     = isset( $documents[ $doc_id ] ) ? $documents[ $doc_id ] : null;
		$lock    = $plugin->html_sync()->get_lock( $post_id );
		?>
		<tr>
			<td>
				<strong><?php echo esc_html( get_the_title( $post ) ); ?></strong><br />
				<code><?php echo esc_html( $doc_id ); ?></code>
			</td>
			<td>
				<form method="post">
					<?php wp_nonce_field( 'andw_llms_set_lock' ); ?>
					<input type="hidden" name="andw_llms_action" value="set_lock" />
					<input type="hidden" name="lock_post" value="<?php echo esc_attr( $post_id ); ?>" />
					<select name="lock_direction">
						<option value="html" <?php selected( 'html', $lock ); ?>><?php esc_html_e( 'HTML priority', 'andw-llms-composer' ); ?></option>
						<option value="markdown" <?php selected( 'markdown', $lock ); ?>><?php esc_html_e( 'Markdown priority', 'andw-llms-composer' ); ?></option>
						<option value="auto" <?php selected( 'auto', $lock ); ?>><?php esc_html_e( 'Auto', 'andw-llms-composer' ); ?></option>
					</select>
					<?php submit_button( __( 'Update', 'andw-llms-composer' ), 'secondary', 'submit', false ); ?>
				</form>
			</td>
			<td>
				<?php echo esc_html( get_post_modified_time( 'Y-m-d H:i', false, $post ) ); ?><br />
				<?php if ( $doc ) : ?>
					<?php esc_html_e( 'Markdown', 'andw-llms-composer' ); ?>: <?php echo esc_html( $doc['meta']['updated_at'] ); ?>
				<?php else : ?>
					<?php esc_html_e( 'No markdown', 'andw-llms-composer' ); ?>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $doc ) : ?>
					<?php echo esc_html( $doc['meta']['summary'] ); ?>
				<?php endif; ?>
			</td>
			<td><a class="button" href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'andw-llms-composer' ); ?></a></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
