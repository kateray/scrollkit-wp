<?php
	/**
	 * Template for displaying posts with scroll content
	 */

	$stylesheet_html = '';
	$stylesheets = get_post_meta($post->ID, '_scroll_css', true);
	foreach($stylesheets as $stylesheet){
		$stylesheet_html .= "<link href=\"$stylesheet\" media=\"screen\" rel=\"stylesheet\" type=\"text/css\" />\n";
	}

	$stylesheet_html .= get_post_meta($post->ID, '_scroll_fonts', true);

	$script_html = '';
	$scripts = get_post_meta($post->ID, '_scroll_js', true);
	foreach($scripts as $script) {
		$script_html .= "<script src=\"$script\" type=\"text/javascript\"></script>\n";
	}

	$template_data = array(
		'stylesheets' => $stylesheet_html,
		'scripts' => $script_html,
		// why wordpress doesn't escape the title is beyond me
		'title' => wp_filter_nohtml_kses(get_the_title(get_the_ID())),
	);


	$options = get_option('scroll_wp_options', ScrollKit::option_defaults() );

?>
<?php ScrollKit::render_template($template_data, $options['template_header']); ?>

<?php if (WP_DEBUG): ?>
	<!--
	scroll id: <?php get_post_meta(get_the_ID(), '_scroll_id', true); ?>
	-->
<?php endif ?>

<?php echo get_post_meta(get_the_ID(), '_scroll_content', true); ?>

<?php ScrollKit::render_template($template_data, $options['template_footer']); ?>
