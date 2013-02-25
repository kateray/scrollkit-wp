<?php

	$options = get_option( 'scroll_wp_options' );

	$settings_updated = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'];
	$api_key_error = isset( $_GET['api-key-error'] ) && $_GET['api-key-error'];
?>
<div class="wrap">

	<div class="icon32" id="icon-options-general"><br></div>
	<h2>Scroll Kit</h2>
	<!--<p>You could have some words here if you are a fancy plugin</p>-->

<?php if ( $api_key_error && !$settings_updated): ?>
	<div class="error">
		<p>
			There was an error with your API key. <a href="<?php echo SCROLL_WP_SK_URL ?>api/wp" target="_blank">Get yours here</a>
		</p>
	</div>
<?php endif; ?>

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
						<li><?php echo sanitize_text_field($error) ?></li>
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
					<input type="text" size="57" name="scroll_wp_options[scrollkit_api_key]" value="<?php echo sanitize_text_field($options['scrollkit_api_key']); ?>" />
					<br>
				  <a href="https://www.scrollkit.com/api/wp" target="_blank">Get an api key</a>
				</td>
			</tr>
			<tr>
				<td>
					HTML Header
					<br>
					<button id="scroll-header-default" type="button">Set to Default</button>
				</td>
				<td>
					<textarea rows="10" cols="100"
						id="header-input"
						name="scroll_wp_options[template_header]"><?php
						echo htmlentities($options['template_header'], ENT_QUOTES, "UTF-8");
					?></textarea>
				</td>
			</tr>
			<tr>
				<td>
					HTML Footer
					<br>
					<button id="scroll-footer-default" type="button">Set to Default</button>
				</td>
				<td>
					<textarea rows="10" cols="100"
						id="footer-input"
						name="scroll_wp_options[template_footer]"><?php
						echo htmlentities($options['template_footer'], ENT_QUOTES, "UTF-8")
					?></textarea>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
</div>
<script>
	(function(){
		document.getElementById("scroll-header-default").onclick = function() {
			var headerInput = document.getElementById("header-input");
			headerInput.innerHTML = <?php echo json_encode($this->template_header_default) ?>;
		}
		document.getElementById("scroll-footer-default").onclick = function() {
			var footerInput = document.getElementById("footer-input");
			footerInput.innerHTML = <?php echo json_encode($this->template_footer_default) ?>;
		}
	})();
</script>
