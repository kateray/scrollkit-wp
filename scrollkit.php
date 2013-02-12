<?php
/*
Plugin Name: Scroll Kit
Plugin URI: http://scrollkit.com
Description: Adds a button to send a page's content to the scroll kit design interface, which generates custom html and css that override the page's default template.
Version: 0.1
Author: scroll kit
Author URI: http://scrollkit.com
License: GPL2
*/

define( 'SCROLL_WP_URL', plugin_dir_url( __FILE__ ) );
define( 'SCROLL_WP_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCROLL_WP_BASENAME', plugin_basename( __FILE__ ) );
define( 'SCROLL_WP_FILE', __FILE__ );

if (WP_DEBUG === true) {
	define( 'SCROLL_WP_SK_URL', 'http://localhost:3000/' );
} else {
	define( 'SCROLL_WP_SK_URL', 'https://www.scrollkit.com/' );
}

define( 'SCROLL_WP_API', SCROLL_WP_SK_URL . 'api/' );


class Scroll {

	function __construct() {

		add_action( 'add_meta_boxes', array( $this, 'action_add_metaboxes' ) );

		add_action( 'template_redirect', array( $this, 'evaluate_query_parameters' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );

		add_action( 'admin_init', array( $this, 'scroll_wp_init' ) );
		add_action( 'admin_menu', array( $this, 'scroll_wp_add_options_page') );
		add_filter( 'plugin_action_links', array( $this, 'scroll_wp_action_links' ),
				10, 2 );

		// TODO test this
		register_uninstall_hook( __FILE__, array( 'Scroll', 'scroll_wp_delete_plugin_options' ) );
		register_activation_hook( __FILE__, array( $this, 'scroll_wp_add_defaults' ));

	  $blog_title = get_bloginfo('name');

		$this->template_header_default = <<<EOT
<!DOCTYPE html>
<html>
	<head>
		<title>{{title}} | $blog_title</title>
		<meta name="viewport" content="width=980">
		{{stylesheets}}
	</head>
	<body class="published">
		<div id="skrollr-body">
EOT;

		$this->template_footer_default = <<<EOT
		</div>
		{{scripts}}
	</body>
</html>
EOT;

	}

	/**
	 * Adds a menu page that's accessible from the settings category in wp-admin
	 */
	function scroll_wp_add_options_page() {
		add_options_page( 'Scroll Kit WP', 'Scroll Kit WP', 'manage_options',
				__FILE__, array( $this, 'scroll_wp_render_form' ) );
	}

	/**
	 * Functionality the user to send content to scroll
	 */
	function metabox() {
		include(dirname( __FILE__ ) . '/metabox-view.php');
	}

	function query_vars($wp_vars) {
		$wp_vars[] = 'scrollkit';
		return $wp_vars;
	}

	function evaluate_query_parameters() {
		$method = get_query_var('scrollkit');
		if ( empty($method) ) {
			$this->scrollify();
			return;
		}

		// there are prettier ways to do this
		$post_id = get_query_var('p');
		switch ( $method ) {
			case 'update':
				$this->update_sk_post( $post_id );
				break;
			case 'activate':
				$this->activate_post($post_id);
				break;
			case 'deactivate':
				$this->deactivate_post($post_id);
				break;
			case 'delete':
				$this->delete_post($post_id);
				break;
		}

		wp_safe_redirect( get_edit_post_link( $post_id, '' ) );

	}

	function update_sk_post() {
		$post_id = get_query_var('p');
		$api_key = isset($_GET['key']) ? $_GET['key'] : null;
		// 401 if the api key doesn't match

		$options = get_option( 'scroll_wp_options' );

		if ( empty( $options['scrollkit_api_key'] )
				|| $api_key !== $options['scrollkit_api_key'] ) {
			header('HTTP/1.0 401 Unauthorized');
			echo 'invalid api key';
			exit;
		}

		$post = get_post( $post_id );

		$scroll_id = get_post_meta( $post_id, '_scroll_id', true );
		$content_url = $this->build_content_url($scroll_id);

		if ( empty( $post ) || empty( $content_url ) ) {
			// TODO make this less shitty
			die('there is a problem');
		}

		$results = wp_remote_get( $content_url );
		// TODO handle non 2XX response

		$data = json_decode( $results['body'] );

		//$data = json_decode ( $this->fetch_data_from_url( $content_url ) ) ;

		update_post_meta( $post_id, '_scroll_content', $data->content );
		update_post_meta( $post_id, '_scroll_css', $data->css );
		update_post_meta( $post_id, '_scroll_fonts', $data->fonts );
		update_post_meta( $post_id, '_scroll_js',  $data->js );

		$edit = get_edit_post_link( $post->ID , '' );
		header( 'Content-Type:' );
		exit;
	}

	function build_edit_url($scrollkit_id) {
		return SCROLL_WP_SK_URL . "s/$scrollkit_id/edit";
	}

	function build_content_url($scrollkit_id) {
		return SCROLL_WP_SK_URL . "s/$scrollkit_id/content";
	}


	/**
	 * Add the Scroll metabox to the post view so users can send content to scroll
	 */
	function action_add_metaboxes() {
		add_meta_box( 'scroll', __( 'Scroll Kit', 'scroll' ),
				array( $this, 'metabox' ), 'post', 'side' );
	}

	function build_scrollkit_edit_url($id) {
		return SCROLL_WP_SK_URL . "s/$id/edit";
	}

	function get_settings_url() {
		return get_admin_url() . 'options-general.php?page=scroll-wp/scrollkit.php';
	}

	function activate_post($post_ID) {
		$state = get_post_meta( $post_ID, '_scroll_state', true );
		$state = empty( $state ) ? 'none' : $state;

		switch ($state) {
			case 'active':
				return;
			case 'inactive':
				update_post_meta($post_ID, '_scroll_state', 'active');
				return;
			case 'none':
				$this->convert_post();
				return;
		}
	}

	function deactivate_post($post_ID) {
		$state = get_post_meta( $post_ID, '_scroll_state', true );
		if (empty($state) || $state == 'inactive') {
			return;
		}
		update_post_meta($post_ID, '_scroll_state', 'inactive');
	}

	/**
	 * Removes all scrollkit data associated with a post
	 */
	function delete_post($post_ID) {
		delete_post_meta($post_ID, '_scroll_id');
		delete_post_meta($post_ID, '_scroll_state');
		delete_post_meta($post_ID, '_scroll_content');
		delete_post_meta($post_ID, '_scroll_css');
		delete_post_meta($post_ID, '_scroll_fonts');
		delete_post_meta($post_ID, '_scroll_js');
	}

	/**
	 * Converts a wordpress post into a wordpress scroll post
	 */
	function convert_post() {
		$post_id = get_query_var('p');
		$post = get_post($post_id);

		$options = get_option( 'scroll_wp_options' );
		$api_key = $options['scrollkit_api_key'];

		$data = array();
		$data['title'] = get_the_title($post_id);
		$content = $post->post_content;
		$content = str_replace(PHP_EOL, '<br />&nbsp;', $content);
		$data['content'] = $content;
		$data['cms_id'] = $post_id;
		// XXX probably not smart to include paramaterized callback url
		$data['cms_url'] = get_bloginfo('url') . '?scrollkit=update';
		$data['api_key'] = $api_key;

		$response = wp_remote_post( SCROLL_WP_API . 'new',  array(
				'method' => 'POST',
				'timeout' => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				'headers' => array(),
				'body' => $data,
				'cookies' => array()
			)
		);

		// Handle wp errors
		if ( is_wp_error( $response ) ) {
			wp_die($response->get_error_message());
		}

		// TODO handle non 2XX
		$http_response_code = $response['response']['code'];
		if ( $http_response_code === 422) {
			// redirect and tell the user to fix their api key
			$destination = add_query_arg('api-key-error', 'true', $this->get_settings_url());
			wp_safe_redirect($destination);
			exit;
		} else if ( $http_response_code !== 200) {
			wp_die("Scroll Kit had an unexpected error, please contact hey@scrollkit.com if this continues to happen", "Error with Scroll Kit WP");
		}

		$response_body = json_decode( $response['body'], true );


		update_post_meta($post->ID, '_scroll_id', $response_body['sk_id']);

		// set some defaults
		update_post_meta($post->ID, '_scroll_state', 'active');
		update_post_meta($post->ID, '_scroll_content', '');
		update_post_meta($post->ID, '_scroll_css', array());
		update_post_meta($post->ID, '_scroll_fonts', '');
		update_post_meta($post->ID, '_scroll_js', array());

		$encoded_edit_link = urlencode($this->build_edit_url($response_body['sk_id']));

		$edit = get_edit_post_link($post->ID , '');

		wp_safe_redirect($edit . "&scrollkitpopup=$encoded_edit_link");
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
		if ( get_post_meta( $post_id, '_scroll_state', true ) === 'active' ) {
			remove_filter( 'the_content', 'wpautop' );
			add_action( 'wp_head', array( $this, 'include_head' ) );
			add_filter( 'single_template', array( $this, 'load_template' ), 100 );
		}
	}

	/**
	 * Callback to replace the current template with our blank template
	 */
	function load_template() {
		return dirname( __FILE__ ) . '/template.php';
	}

	// Sanitize and validate input. Accepts an array, return a sanitized array.
	function scroll_wp_validate_options($input) {
		//TODO regex for our api key
		$input['scrollkit_api_key'] = wp_filter_nohtml_kses($input['scrollkit_api_key']);
		return $input;
	}

	// Init plugin options to white list our options
	function scroll_wp_init() {
		register_setting( 'scroll_wp_plugin_options',
				'scroll_wp_options',
				array( $this, 'scroll_wp_validate_options' ) );
	}

	// Display a Settings link on the main Plugins page
	function scroll_wp_action_links( $links, $file ) {

		if ( $file == plugin_basename( __FILE__ ) ) {
			$settings_link = '<a href="'
					. $this->get_settings_url()
				  . '">'
					. __('Settings')
					. '</a>';

			// make the 'Settings' link appear first
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	// Delete options table entries ONLY when plugin deactivated AND deleted
	public static function scroll_wp_delete_plugin_options() {
		delete_option('scroll_wp_options');
		// note that this doesn't remove metadata associated with posts
		// not sure if that's a good idea
	}

	// sets some default values on first activation
	function scroll_wp_add_defaults () {
		$tmp = get_option( 'scroll_wp_options' );
		if(!is_array($tmp)) {

			$arr = array(
				"scrollkit_api_key" => "",
				"template_header" => $this->template_header_default,
				"template_footer" => $this->template_footer_default
			);

			update_option('scroll_wp_options', $arr);
		}
	}

	function scroll_wp_render_form() {
		include(dirname( __FILE__ ) . '/settings-view.php');
	}
}

new Scroll();
