<?php

	$options = get_option( 'scroll_wp_options' );

	$settings_updated = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'];
	$api_key_error = isset( $_GET['api-key-error'] ) && $_GET['api-key-error'];
?>
<div class="wrap">

	<div class="icon32" id="icon-options-general"><br></div>
	<h2>Scroll Kit</h2>

	<?php if ( $api_key_error && !$settings_updated): ?>
		<div class="error">
			<p>
				There was an error with your API key. <a href="<?php echo SCROLL_WP_SK_URL ?>api/wp" target="_blank">Get yours here</a>
			</p>
		</div>
	<?php endif ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'scroll_wp_plugin_options' ) ?>

		<?php //TODO make this more pretty, and with support links ?>


		<table class="form-table">

			<?php // print the errors stored in the option table ?>
			<?php if ( array_key_exists( 'errors', $options ) ): ?>
			<tr>
				<th scope="row">Error Log</th>
				<th>
					Scroll Kit has encoutered the following errors:
					<ul>
					<?php foreach ( $options['errors'] as $error ): ?>
						<li><?php echo esc_html($error) ?></li>
					<?php endforeach ?>
					</ul>
					<p class="submit">
						<input type="submit" class="button-primary" value="Clear Error Log" />
					</p>
				</th>
			</tr>
			<?php endif ?>

			<tr>
				<th scope="row">Scroll Kit API Key</th>
				<td>
					<input type="text" size="57" name="scroll_wp_options[scrollkit_api_key]" value="<?php echo esc_attr($options['scrollkit_api_key']); ?>" autocomplete="off" />
					<br>
				  <a href="https://www.scrollkit.com/api/wp" target="_blank">Get an api key</a>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
</div>
