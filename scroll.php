<?php
/*
Plugin Name: Scroll
Plugin URI: http://scrollmkr.com
Description: Removes WP template from a page or post.
Version: .1
Author: Scroll
Author URI: http://scrollmkr.com
License: GPL2
*/

function scroll_button() {
	global $wp_query;
	$post_id = $wp_query->post->ID;
	$post = get_post($post_id);
	$postID = $post->ID;
	$post_title = $post->post_title;
	$post_title = str_replace("'", "\'", $post_title);
	$post_content = $post->post_content;
	$post_content = preg_replace('/\s\s+/', '', $post_content);
	$post_content = preg_replace('/\n/', '<br>', $post_content);
	$post_content = str_replace('&nbsp;', '<br>', $post_content);
	$post_content = str_replace("'", "\'", $post_content);
	echo
	"<script type = 'text/javascript'>
	jQuery(document).ready(function(jQuery) {
		jQuery('input.sendajax').click(function(){
			var sendData = {
				action: 'my_action',
				external_id: $postID,
				title: '$post_title',
				content: '$post_content'
			}
			jQuery.ajax({
				type: 'POST',
				url: 'http://lvh.me:3000/s/wp',
				xhrFields: {
					withCredentials: true
				},
				headers: {'X-Requested-With': 'XMLHttpRequest'},
				data: sendData,
				error: function(jqXHR){
					console.log(jqXHR.responseText);
				},
				success: function(data){
					window.open(data['link']);
				}
			});
		});
	})
	</script>
	<input class='sendajax' type='button' value='Scrollify' />";
}

add_action( 'dbx_post_sidebar', scroll_button);


function my_action_callback() {

	global $wpdb; // this is how you get access to the database

	$api_key = $_POST['api_key'];
	$user = wp_get_current_user();
	$user_id = $user->ID;
	add_user_meta($user_id, 'scroll_api_key', $api_key);
	
	$response = array( 'success' => true, 'link' => 'this is a response var' ); // Stuff to send back to AJAX
  	echo json_encode( $response );

  	die(); // Needs this
}

/**
 * Base code from Benjamin J. Balter's no format plugin. (http://ben.balter.com)
 */

/**
 * Hook to check for meta and call template filter
 */
function scrollify() {

	//if not a page or single post, kick
	if (!is_single() && !is_page() )
		return;
	
	//get current post ID
	global $wp_query;
	$post_id = $wp_query->post->ID;
	
	//Look for a "scroll" page meta
	$no_formatting = get_post_meta($post_id, 'scroll', true);
	
	//if the meta is set, call our template filter
	if ($no_formatting) {
		remove_filter( 'the_content', 'wpautop' );
		add_filter('single_template', 'scroll_redirect', 100);
	}
}

add_action('template_redirect', 'scrollify');

/**
 * Callback to replace the current template with our blank template
 */
function scroll_redirect() {
	return dirname(__FILE__) . '/template.php';
}


?>