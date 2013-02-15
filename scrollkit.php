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

// put this into your wp-config.php if you are running scrollkit locally:
// define('SK_DEBUG_URL', 'http://localhost:3000/');
if ( defined('SK_DEBUG_URL') ) {
	define( 'SCROLL_WP_SK_URL', SK_DEBUG_URL );
} else {
	define( 'SCROLL_WP_SK_URL', 'http://www.scrollkit.com/' );
}

define( 'SCROLL_WP_API', SCROLL_WP_SK_URL . 'api/' );


class Scroll {

	function __construct() {

		add_action( 'add_meta_boxes', array( $this, 'action_add_metaboxes' ) );

		add_action( 'template_redirect', array( $this, 'template_redirect' ) );

		add_action( 'admin_init', array( $this, 'scroll_wp_init' ) );
		add_action( 'admin_menu', array( $this, 'scroll_wp_add_options_page') );


		add_filter( 'query_vars', array( $this, 'query_vars' ) );

		add_filter( 'admin_footer', array( $this, 'load_scroll_form') );

		add_filter( 'plugin_action_links',
				array( $this, 'scroll_wp_action_links' ), 10, 2 );

		// TODO test this
		register_uninstall_hook( __FILE__,
				array( 'Scroll', 'scroll_wp_delete_plugin_options' ) );

		register_activation_hook( __FILE__,
				array( $this, 'scroll_wp_add_defaults' ));

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
		include(dirname( __FILE__ ) . '/metabox-view.php' );
	}

	/**
	 * Adds scrollkit to the list of query variables that wp pays attention to
	 */
	function query_vars($wp_vars) {
		$wp_vars[] = 'scrollkit';
		return $wp_vars;
	}

	/**
	 * Checks if the post is a scroll every time a post is loaded
	 * and uses the appropriate template if there is a scroll
	 */
	function template_redirect() {

		// deal with special scroll action calls - scrollkit will make these
		// when a user hits 'done' on scroll kit
		$scrollkit_param = get_query_var('scrollkit');
		if ( !empty($scrollkit_param) ) {
			$this->handle_scroll_action();
			wp_safe_redirect( get_edit_post_link( $post_id, '' ) );
			exit;
		}

		// if it's not a single post, don't render it as a scroll
		if ( !is_singular() )
			return;

		// if the meta is set, call our template filter that renders a scroll
		$post_id = get_queried_object_id();
		if ( get_post_meta( $post_id, '_scroll_state', true ) === 'active' ) {
			remove_filter( 'the_content', 'wpautop' );
			add_filter( 'template_include', array( $this, 'load_template' ), 100 );
		}
	}

	/**
	 * Handle actions to scrolls, redirecting the user back to the post
	 * edit view
	 */
	function handle_scroll_action() {
		$method = get_query_var('scrollkit');
		$post_id = get_query_var('p');

		switch ( $method ) {
			case 'update':
				$this->validate_api_key();
				$this->update_sk_post( $post_id );

				// don't like this
				header( 'Content-Type:' );
				exit;
			case 'activate':
				$this->activate_post($post_id);
				break;
			case 'load':
				$this->load_scroll($post_id);
				break;
			case 'deactivate':
				$this->deactivate_post($post_id);
				break;
			case 'delete':
				$this->delete_post($post_id);
				break;
		}
	}

	function validate_api_key() {
		// 401 if the api key doesn't match
		$api_key = isset($_GET['key']) ? $_GET['key'] : null;

		$options = get_option( 'scroll_wp_options' );

		if ( empty( $options['scrollkit_api_key'] )
				|| $api_key !== $options['scrollkit_api_key'] ) {
			header('HTTP/1.0 401 Unauthorized');
			echo 'invalid api key';
			exit;
		}
	}

	/**
	 * Updates wordpress' copy of a scroll post by fetching the data from
	 * scrollkit
	 */
	function update_sk_post($post_id) {

		$post = get_post( $post_id );

		$scroll_id = get_post_meta( $post_id, '_scroll_id', true );
		$content_url = $this->build_content_url($scroll_id);

		if ( empty( $post ) || empty( $content_url ) ) {
			// TODO make this less shitty
			wp_die('there is a problem');
		}


		$results = wp_remote_get( $content_url );

		if ( is_wp_error( $results) ) {
			wp_die( $results->get_error_message() );
		}

		//echo $content_url;
		//die();

		$response_code = $results['response']['code'];
		if ( $response_code !== 200 ) {
			wp_die( "error requesting content from $content_url, error code  "
					. $response_code );
		}

		$data = json_decode( $results['body'] );

		update_post_meta( $post_id, '_scroll_content', $data->content );
		update_post_meta( $post_id, '_scroll_css', $data->css );
		update_post_meta( $post_id, '_scroll_fonts', $data->fonts );
		update_post_meta( $post_id, '_scroll_js',  $data->js );

	}

	/**
	 * Gives the user a scrollkit url where they can edit the post
	 */
	function build_edit_url($scrollkit_id) {
		return SCROLL_WP_SK_URL . "s/$scrollkit_id/edit";
	}

	/**
	 * Builds a url which serves a chunk of json with html, javascript, css and
	 * webfonts. e.g. http://www.scrollkit.com/s/qgPwxGA/content
	 */
	function build_content_url($scrollkit_id) {
		return SCROLL_WP_SK_URL . "s/$scrollkit_id/content";
	}

	/**
	 * Get the path to this plugin's settings view
	 */
	function get_settings_url() {
		return get_admin_url() . "options-general.php?page=" . SCROLL_WP_BASENAME;
	}

	/**
	 * Add the Scroll metabox to the post view so users can convert a post to a
	 * scroll
	 */
	function action_add_metaboxes() {
		add_meta_box( 'scroll', __( 'Scroll Kit', 'scroll' ),
				array( $this, 'metabox' ), 'post', 'side', 'core' );

		add_meta_box( 'scroll', __( 'Scroll Kit', 'scroll' ),
				array( $this, 'metabox' ), 'page', 'side', 'core' );
	}

	/**
	 * Active a scroll post, converting the post if it's not a scroll.
	 * if it's a a disabled scroll then enable it
	 */
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

	/**
	 * Creates a fork of an existing scroll
	 */
	function load_scroll($post_id) {
		// skid can be a scrollkit url or id
		$skid = isset($_GET['skid']) ? $_GET['skid'] : '';

		// Some people, when confronted with a problem, think
		// “I know, I'll use regular expressions.”
		// Now they have found true <3<3<3<3<3<3
		$pattern = '/\s*(https?:\/\/.*\/s\/)?([a-zA-Z0-9]+).*$/';

		$is_match = preg_match($pattern, $skid, $matches);
		if ( $is_match !== 1 || count($matches) < 3 ) {
			wp_die( 'There was an issue scrollkit URL or ID you provided' );
		}
		$scroll_id = $matches[2];

		// fetch the user entered api key from plugin's settings
		$options = get_option( 'scroll_wp_options' );
		$api_key = $options['scrollkit_api_key'];


		// collect all the data needed to send to sk
		$data = array();
		$data['title'] = get_the_title($post_id);
		$data['cms_id'] = $post_id;
		$data['cms_url'] = get_bloginfo('url') . '?scrollkit=update';
		$data['api_key'] = $api_key;
		$data['scroll_id'] = $scroll_id;

		$this->request_new_scroll($data, $post_id);

		$this->update_sk_post( $post_id );

		// send the user back to the post edit context
		// where they are notified that a post is a scroll
		$edit_url = get_edit_post_link($post_id , '');
		wp_safe_redirect($edit_url);
		exit;
	}

	/**
	 * Stop a scroll post from being served as a scroll, leaving the scroll
	 * data intact
	 */
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
	 * Converts a wordpress post into a scroll
	 */
	function convert_post() {
		$post_id = get_query_var('p');
		$post = get_post($post_id);

		// fetch the user entered api key from plugin's settings
		$options = get_option( 'scroll_wp_options' );
		$api_key = $options['scrollkit_api_key'];

		// collect all the data needed to send to sk
		$data = array();
		$data['title'] = get_the_title($post_id);
		$content = $post->post_content;
		$content = str_replace(PHP_EOL, '<br />&nbsp;', $content);
		$data['content'] = $content;
		$data['cms_id'] = $post_id;
		$data['cms_url'] = get_bloginfo('url') . '?scrollkit=update';
		$data['api_key'] = $api_key;

		$this->request_new_scroll($data, $post_id);

		// send the user back to the post edit context
		// where they are notified that a post is a scroll
		$edit_url = get_edit_post_link($post_id , '');
		wp_safe_redirect($edit_url);
	}

	function request_new_scroll($data, $post_id) {
		// send the data to scrollkit
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

		// handle wp errors (ssl stuff, can't connect to host, etc)
		if ( is_wp_error( $response ) ) {
			wp_die($response->get_error_message());
		}

		// Handle sk response
		$http_response_code = $response['response']['code'];

		switch ($http_response_code) {
			case 200:
				break;
			case 422:
				// api key error, redirect the user to this plugin's setting page
				// where there's a message indicating an api key issue
				$destination = add_query_arg('api-key-error', 'true',
						$this->get_settings_url());

				wp_safe_redirect($destination);
				exit;
			default:
				// probably a 500 error
				wp_die("Scroll Kit had an unexpected error, please contact"
						. " hey@scrollkit.com if this continues to happen",
						"Error with Scroll Kit WP");
		}

		$response_body = json_decode( $response['body'], true );

		update_post_meta($post_id, '_scroll_id', $response_body['sk_id']);

		// set some defaults
		update_post_meta($post_id, '_scroll_state', 'active');
		update_post_meta($post_id, '_scroll_content', '');
		update_post_meta($post_id, '_scroll_css', array());
		update_post_meta($post_id, '_scroll_fonts', '');
		update_post_meta($post_id, '_scroll_js', array());

	}

	/**
	 * Callback to replace the current template with our blank template
	 */
	function load_template() {
		return dirname( __FILE__ ) . '/template.php';
	}

	/**
	 * Sanitizes the api key
	 */
	function scroll_wp_validate_options($input) {
		$input['scrollkit_api_key'] = wp_filter_nohtml_kses(
				$input['scrollkit_api_key']);

		return $input;
	}

	/**
	 * Init plugin options to white list our options
	 */
	function scroll_wp_init() {
		register_setting( 'scroll_wp_plugin_options', 'scroll_wp_options',
				array( $this, 'scroll_wp_validate_options' ) );
	}

	/**
	 * Display a settings link on the main Plugins page
	 */
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

	/**
	 * Delete options table entries when plugin deactivated AND deleted
	 *
	 * note that this doesn't remove metadata associated with posts
	 */
	public static function scroll_wp_delete_plugin_options() {
		delete_option('scroll_wp_options');
	}

	/**
	 * Some defaults for the header and footer on first activation
	 */
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

	/**
	 * Render the settings view for inputting the api key, header and footer
	 */
	function scroll_wp_render_form() {
		include( dirname( __FILE__ ) . '/settings-view.php');
	}


	/**
	 * Adds a hidden modal after the editor stuff
	 *
	 * note:
	 * modal's are crappy, and it would make more sense to have this in
	 * in metabox-view.php, except the metabox stuff is inside a <form> making it
	 * impossible to nest a second form without using js or something ugly.
	 */
	function load_scroll_form(){
		global $pagenow,$typenow, $post;
		if (!in_array( $pagenow, array( 'post.php', 'post-new.php' )))
			return;
		?>
		<div id="sk-load-scroll" style="display:none">
			<h2>Load Existing Scroll</h2>
			<form method="GET" action="<?php bloginfo('url') ?>">
				<input type="hidden" name="scrollkit" value="load" />
				<input type="hidden" name="p" value="<?php the_ID() ?>" />
				<input name="skid" placeholder="http://www.scrollkit.com/s/f0Z9WbS/" />
				<input type="submit" value="Load Scroll" />
			</form>
		</div>
	<?php
	}
}

new Scroll();
