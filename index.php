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

define( 'SCROLL_WP_URL', plugin_dir_url( __FILE__ ) );
define( 'SCROLL_WP_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCROLL_WP_BASENAME', plugin_basename( __FILE__ ) );
define( 'SCROLL_WP_FILE', __FILE__ );
define( 'SCROLL_WP_SK_URL', 'https://www.scrollkit.com/' );
//define( 'SCROLL_WP_SK_URL', 'localhost:3000/' );
define( 'SCROLL_WP_API', SCROLL_WP_SK_URL . 'api/' );

class Scroll {

	function __construct() {

		add_action( 'add_meta_boxes', array( $this, 'action_add_metaboxes' ) );

		add_action( 'template_redirect', array( $this, 'evaluate_query_parameters' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );

		add_action( 'admin_init', array( $this, 'scroll_wp_init' ) );
		add_action( 'admin_menu', array( $this, 'scroll_wp_add_options_page') );
		add_filter( 'plugin_action_links', array( $this, 'scroll_wp_action_links' ), 10, 2 );

		register_uninstall_hook( __FILE__, 'scroll_wp_delete_plugin_options' );
		register_activation_hook( __FILE__, 'scroll_wp_add_defaults' );
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
		global $post;
		switch ( $method ) {
			case 'update':
				$this->update_sk_post($post->ID);
				break;
			case 'activate':
				$this->activate_post($post->ID);
				break;
			case 'deactivate':
				$this->deactivate_post($post->ID);
				break;
			case 'delete':
				$this->delete_post($post->ID);
				break;
		}
		$this->temporary_redirect( get_edit_post_link( $post->ID, '' ) );
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

		$post = get_post($post_id);

		$scroll_id = get_post_meta( $post_id, '_scroll_id', true );
		$content_url = $this->build_content_url($scroll_id);

		if ( empty ( $post ) || empty ( $content_url ) ) {
			// TODO make this less shitty
			die('there is a problem');
		}

		$data = json_decode ( $this->fetch_data_from_url( $content_url ) ) ;

		update_post_meta( $post->ID, '_scroll_content', $data->content );
		update_post_meta( $post->ID, '_scroll_css', $data->css );
		update_post_meta( $post->ID, '_scroll_fonts', $data->fonts );
		update_post_meta( $post->ID, '_scroll_js',  $data->js );

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

	/*
	 * Returns the data of a get request on a URL
	 */
	function fetch_data_from_url ($url) {
		$curl_session = curl_init();
		curl_setopt($curl_session, CURLOPT_URL, $url);
		curl_setopt($curl_session, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);

		$data = curl_exec ( $curl_session );
		curl_close ( $curl_session );

		return $data;
	}

	/*
	 * Posts your array to a url as JSON and returns a php array
	 * TODO throw exception if the server response isn't a 2XX
	 */
	function post_array_as_json_to_url ($url, $data) {

		$content = json_encode($data);

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER,
		array("Content-type: application/json"));
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

		$json_response = curl_exec($curl);

		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// TODO handle errors
		if ( $status != 200 ) {
			die("eeek! response: $status\nurl: $url\ndata: $data");

		}

		curl_close($curl);

		$response = json_decode($json_response, true);
		return $response;
	}

	/**
	 * Add the Scroll metabox to the post view so users can send content to scroll
	 */
	function action_add_metaboxes() {
		add_meta_box( 'scroll', __( 'Scroll', 'scroll' ), array( $this, 'metabox' ),
				'post', 'side' );
	}

	function build_scrollkit_edit_url($id) {
		return SCROLL_WP_SK_URL . "s/$id/edit";
	}

	function activate_post($post_ID) {
		$state = get_post_meta( $post_ID, '_scroll_state', true );
		$state = empty($state) ? 'none' : $state;

		// if a post is activated, return
		switch($state) {
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

		// set some defaults
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
		$data['content'] = $post->post_content;
		$data['cms_id'] = $post_id;
		// XXX probably not smart to include paramaterized callback url
		$data['cms_url'] = get_bloginfo('url') . '?scrollkit=update';
		$data['api_key'] = $api_key;

		$response = $this->post_array_as_json_to_url(SCROLL_WP_API . 'new', $data);

		update_post_meta($post->ID, '_scroll_id', $response['sk_id']);

		// set some defaults
		update_post_meta($post->ID, '_scroll_state', 'active');
		update_post_meta($post->ID, '_scroll_content', '');
		update_post_meta($post->ID, '_scroll_css', array());
		update_post_meta($post->ID, '_scroll_fonts', '');
		update_post_meta($post->ID, '_scroll_js', array());

		$encoded_edit_link = urlencode($this->build_edit_url($response['sk_id']));

		$edit = get_edit_post_link($post->ID , '');

		$this->temporary_redirect($edit . "&scrollkitpopup=$encoded_edit_link");
	}

	function temporary_redirect($url) {
		header("Location: $url", true, 302);
		exit;
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
			$settings_link = '<a href="'.get_admin_url().'options-general.php?'
					. 'page=scroll-wp/index.php">'
					. __('Settings')
					. '</a>';
			// make the 'Settings' link appear first
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	// Delete options table entries ONLY when plugin deactivated AND deleted
	function scroll_wp_delete_plugin_options() {
		delete_option('scroll_wp_options');
	}

	// sets some default values on first activation
	function scroll_wp_add_defaults () {
		$tmp = get_option( 'scroll_wp_options' );
		if(!is_array($tmp)) {

			$blog_title = get_bloginfo('name');

			// TODO consider putting this in an external file
			// and reading it in
			$template_header_default = <<<EOT
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

			$template_footer_default = <<<EOT
			{{scripts}}
		</div>
	</body>
</html>
EOT;

			$arr = array(
				"scrollkit_api_key" => "",
				"template_header" => $template_header_default,
				"template_footer" => $template_footer_default,
			);
			update_option('scroll_wp_options', $arr);
		}
	}

	function scroll_wp_render_form() {
		include(dirname( __FILE__ ) . '/settings-view.php');
	}
}

new Scroll();
