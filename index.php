<?php
/*
Plugin Name: Scroll
Plugin URI: http://scrollkit.com
Description: Adds a button to send a page's content to the scroll kit design interface, which generates custom html and css that override the page's default template.
Version: 0.1
Author: scroll kit
Author URI: http://scrollkit.com
License: GPL2
*/

if ( !defined('SCROLLKIT_URL') )
	define( 'SCROLLKIT_URL', plugin_dir_url( __FILE__ ) );
if ( !defined('SCROLLKIT_PATH') )
	define( 'SCROLLKIT_PATH', plugin_dir_path( __FILE__ ) );
if ( !defined('SCROLLKIT_BASENAME') )
	define( 'SCROLLKIT_BASENAME', plugin_basename( __FILE__ ) );

define( 'SCROLLKIT_FILE', __FILE__ );

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
		wp_enqueue_script(
			'scrollkit-wp',
			SCROLLKIT_URL . 'scrollkit-wp.js',
			array('jquery')
		);
		?>
			<button id="scrollkit-wp-convert" type="button">
				Convert to Scroll
			</button>
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
