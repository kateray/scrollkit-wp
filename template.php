<?php

	/**
	 * Ghetto template rendering
	 */
	function render_template($data, $template){
		$rendered = $template;
		foreach ($data as $key => $val){
			$pattern = "/{{" . $key . "}}/";
			$rendered = preg_replace($pattern, $val, $rendered);
		}
		return $rendered;
	}

	$stylesheet_html = '';
	$stylesheets = get_post_meta($post->ID, '_scroll_css', true);
	foreach($stylesheets as $stylesheet){
		$stylesheet_html .= "<link href=\"$stylesheet\" media=\"screen\" rel=\"stylesheet\" type=\"text/css\" />\n";
	}

	$script_html = '';
	$scripts = get_post_meta($post->ID, '_scroll_js', true);
	foreach($scripts as $script) {
		$script_html .= "<script src=\"$script\" type=\"text/javascript\"></script>\n";
	}

	$options = get_option('scroll_wp_options');

	global $post;
	$data = array(
		'stylesheets' => $stylesheet_html,
		'scripts' => $script_html,
		// why wordpress doesn't escape the title is beyond me
		'title' => wp_filter_nohtml_kses(get_the_title($post->ID)),
	);

	$header = render_template($data, $options['template_header']);
	$content = get_post_meta($post->ID, '_scroll_content', true);
	$footer = render_template($data, $options['template_footer']);

	echo $header . $content . $footer;
