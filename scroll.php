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

    // add_action( 'wp_ajax_scroll_send', array( $this, 'handle_ajax_scroll_send' ) );

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
				  '<?php
				    global $wp_query;
				    $post_id = intval($_GET["post"]);
				    $post = get_post($post_id);
				    $post_title = $post->post_title;
				    $post_title = str_replace("'", "\'", $post_title);
          	$post_content = $post->post_content;
          	$post_content = preg_replace('/\s\s+/', '', $post_content);
          	$post_content = preg_replace('/\n/', '<br>', $post_content);
          	$post_content = str_replace('&nbsp;', '<br>', $post_content);
          	$post_content = str_replace("'", "\'", $post_content);
				   ?>'
    			var sendData = {
    				external_id: '<?php echo esc_js( $post_id ); ?>',
    				title: '<?php echo esc_js( $post_title ); ?>',
    				content: '<?php echo esc_js( $post_content ); ?>'
    			}
    			jQuery.ajax({
    				type: 'POST',
    				url: 'http://scrollmkr.com/s/wp',
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
			});
		</script>
		<input id='scroll-send' type='button' value='Send to Scroll' />
		<?php
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