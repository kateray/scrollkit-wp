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

class Scroll {

	function __construct() {
		
		add_action( 'add_meta_boxes', array( $this, 'action_add_metaboxes' ) );

		add_action( 'wp_ajax_scroll_send', array( $this, 'handle_ajax_scroll_send' ) );

		// Scrollify if we want to
		add_action( 'template_redirect', array( $this, 'scrollify' ) );

	}

	/**
	 * Add the Scroll metabox to the post view so users can send content to scroll
	 */
	function action_add_metaboxes() {

		add_meta_box( 'scroll', __( 'Scroll', 'scroll' ), array( $this, 'metabox' ), 'post', 'side' );	

	}

	/**
	 * Functionality the user to send content to scroll
	 */
	function metabox() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function() {
				jQuery('#scroll-send').click(function(){
					var data = {
						action: 'scroll_send',
						post_id: '<?php echo esc_js( get_queried_object_id() ); ?>'
					};
					jQuery.ajax( ajaxurl, data, function( response ) {
						if ( response.status == 'ok') {
							window.open(response.scroll_edit_link);
						}
						return false;
					});
					return false;
				});
			});
		</script>
		<?php
		submit_button( __( 'Send to Scroll', 'scroll' ), 'secondary', 'scroll-send' );
	}

	/**
	 * Handle an AJAX request to send a post to Scroll
	 */
	function handle_ajax_scroll_send() {

		$post_id = intval( $_POST['post_id'] );

		// Get your post data
		$post_data = get_post( $post_id );

		// Send the post data to scroll with Wp_Http class
		$url = 'http://lvh.me:3000/s/wp';
		
		$request  = new Wp_Http();
		
		$response = $request->request( $url, array( 'method' => 'POST', 'body' => $post_data) );

		// Return the URL back to the user
		$return_data = array(
				'scroll_edit_link' => $scroll_edit_link,
				'user_api_key' => $api_key,
			);
		
		
		die();
		
	}

	/**
	 * If the post has an associated scroll, let's load that on the single view
	 */
	function scrollify() {

		// If it's not a single post, don't do anything
		if ( !is_singular() )
			return;
		
		// get current post ID
		$post_id = get_queried_object_id();

		//if the meta is set, call our template filter
		if ( get_post_meta( $post_id, 'scroll', true ) ) {
			remove_filter( 'the_content', 'wpautop' );
			add_filter('single_template', array( $this, 'load_template' ), 100);
		}
		
	}

	/**
	 * Callback to replace the current template with our blank template
	 */
	function load_template() {
		return dirname(__FILE__) . '/template.php';
	}

}

global $scroll;
$scroll = new Scroll();

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
