<?php
	// hi
	//


	$options = get_option( 'scroll_wp_options' );

	$header_default = $this->template_header_default;
	$header_saved = htmlentities($options['template_header'], ENT_QUOTES, "UTF-8");

	$footer_default = htmlentities($this->template_footer_default, ENT_QUOTES, "UTF-8");
	$footer_saved = htmlentities($options['template_footer'], ENT_QUOTES, "UTF-8");

?>
<div class="wrap">

	<div class="icon32" id="icon-options-general"><br></div>
	<h2>Scroll Kit WP</h2>
	<!--<p>You could have some words here if you are a fancy plugin</p>-->

	<form method="post" action="options.php">
		<?php settings_fields( 'scroll_wp_plugin_options' ) ?>

		<table class="form-table">
			<tr>
				<th scope="row">Scroll Kit API Key</th>
				<td>
					<input type="text" size="57" name="scroll_wp_options[scrollkit_api_key]" value="<?php echo $options['scrollkit_api_key']; ?>" />
					<br>
				  (TODO add link to get api key)
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
						echo $header_saved;
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
			headerInput.innerHTML = <?php echo json_encode($header_default) ?>;
		}
		document.getElementById("scroll-footer-default").onclick = function() {
			var footerInput = document.getElementById("footer-input");
			footerInput.innerHTML = <?php echo json_encode($footer_default) ?>;
		}
	})();
</script>
