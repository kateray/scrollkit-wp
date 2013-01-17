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

if ( !defined('SCROLL_WP_URL') )
	define( 'SCROLL_WP_URL', plugin_dir_url( __FILE__ ) );
if ( !defined('SCROLL_WP_PATH') )
	define( 'SCROLL_WP_PATH', plugin_dir_path( __FILE__ ) );
if ( !defined('SCROLL_WP_BASENAME') )
	define( 'SCROLL_WP_BASENAME', plugin_basename( __FILE__ ) );

define( 'SCROLL_WP_FILE', __FILE__ );

class Scroll {

	function __construct() {

		add_action( 'add_meta_boxes', array( $this, 'action_add_metaboxes' ) );

    // add_action( 'wp_ajax_scroll_send', array( $this, 'handle_ajax_scroll_send' ) );

		// Scrollify if we want to
		add_action( 'template_redirect', array( $this, 'scrollify' ) );

		add_filter('query_vars', array( $this, 'query_vars' ) );

		// redirect every single page hit through our plugin...
		add_action('template_redirect', array( $this, 'template_redirect' ) );

	}

	function query_vars($wp_vars) {
		$wp_vars[] = 'scrollkit';
		return $wp_vars;
	}

	function template_redirect() {
		$method = get_query_var('scrollkit');
		if ( empty($method) ) {
			return;
		}

		if ( $method == 'update' ) {
			$post_id = get_query_var('p');
			$post = get_post($post_id);
			$content_url = get_post_meta($post_id, 'scroll_content_url', true);

			if ( empty ( $post ) || empty ( $content_url ) ) {
				return;
			}

			header ( 'Content-type: text/plain' );
			$data = json_decode ( $this->fetch_url( $content_url ) ) ;

			update_post_meta($post->ID, 'scroll', $data->content);
			echo 'ADDED DATA';
			//echo $data;
			//add_post_meta($post_id, $meta_key, $meta_value, $unique);

			exit;
		}
	}

	// le sigh...
	function fetch_url ($url) {
		$curl_session = curl_init();
		curl_setopt($curl_session, CURLOPT_URL, $url);
		curl_setopt($curl_session, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);

		$data = curl_exec ( $curl_session );
		curl_close ( $curl_session );

		return $data;
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
		global $post;
		wp_enqueue_script(
			'scrollkit-wp',
			SCROLL_WP_URL . 'scrollkit-wp.js',
			array('jquery')
		);
		?>
			<button id="scrollkit-wp-convert" type="button">
				Convert to Scroll
			</button>
			<a href="/?scrollkit=update&p=<?php echo $post->ID ?>">
				Manually pull changes
			</a>
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


