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


// put this into your wp-config.php if you are running scrollkit locally:
// define('SK_DEBUG_URL', 'http://localhost:3000/');
if ( defined('SK_DEBUG_URL') ) {
	define( 'SCROLL_WP_SK_URL', SK_DEBUG_URL );
} else {
	define( 'SCROLL_WP_SK_URL', 'http://www.scrollkit.com/' );
}

define( 'SCROLL_WP_API', SCROLL_WP_SK_URL . 'api/' );
define( 'SCROLL_WP_BASENAME', plugin_basename( __FILE__ ) );
define( 'SCROLL_WP_SETTINGS_URL', get_admin_url() . "options-general.php?page=" . SCROLL_WP_BASENAME );


class ScrollKit {

	function __construct() {
		add_action( 'admin_init'           , array( $this, 'action_admin_init' ) );
		add_action( 'admin_menu'           , array( $this, 'action_admin_menu') );
		add_action( 'add_meta_boxes'       , array( $this, 'action_add_metaboxes' ) );
		add_action( 'template_redirect'    , array( $this, 'action_template_redirect' ) );

		add_filter( 'query_vars'           , array( $this, 'filter_query_vars' ) );
		add_filter( 'admin_footer'         , array( $this, 'filter_admin_footer') );
		add_filter( 'plugin_action_links'  , array( $this, 'filter_plugin_action_links' ), 10, 2 );

		register_activation_hook( __FILE__ , array( $this, 'hook_add_defaults' ));
		register_uninstall_hook( __FILE__  , array( 'Scroll', 'hook_delete_plugin_options' ) );
	}

	/**
	 * Init plugin options to white list our options
	 */
	public function action_admin_init() {
		register_setting( 'scroll_wp_plugin_options', 'scroll_wp_options',
				array( $this, 'validate_options' ) );
	}

	/**
	 * Adds a menu page that's accessible from the settings category in wp-admin
	 */
	public function action_admin_menu() {
		add_options_page( 'Scroll Kit', 'Scroll Kit', 'manage_options',
				__FILE__, array( $this, 'render_settings_view' ) );
	}

	/**
	 * Add the Scroll metabox to the post view so users can convert a post to a
	 * scroll
	 */
	public function action_add_metaboxes() {
		add_meta_box( 'scroll', __( 'Scroll Kit', 'scroll' ),
				array( $this, 'metabox' ), 'post', 'side', 'core' );

		add_meta_box( 'scroll', __( 'Scroll Kit', 'scroll' ),
				array( $this, 'metabox' ), 'page', 'side', 'core' );
	}

	/**
	 * Checks if the post is a scroll every time a post is loaded
	 * and uses the appropriate template if there is a scroll
	 */
	public function action_template_redirect() {

		// deal with special scroll action calls - scrollkit will make these
		// when a user hits 'done' on scroll kit
		if ( get_query_var('scrollkit') ) {
			$this->handle_scroll_action( get_query_var('scrollkit') );
		}

		// if it's not a single post, don't render it as a scroll
		if ( !is_singular() ) {
			return;
		}

		// if the meta is set, call our template filter that renders a scroll
		if ( get_post_meta( get_the_ID(), '_scroll_state', true ) === 'active' ) {
			remove_filter( 'the_content', 'wpautop' );
			add_filter( 'template_include', array( $this, 'load_template' ), 100 );
		}
	}

	/**
	 * Adds scrollkit to the list of query variables that wordpress pays attention to
	 */
	public function filter_query_vars( $wp_vars ) {
		$wp_vars[] = 'scrollkit';
		return $wp_vars;
	}

	/**
	 * Adds a hidden modal after the editor stuff
	 *
	 * note:
	 * modal's are crappy, and it would make more sense to have this in
	 * in metabox-view.php, except the metabox stuff is inside a <form> making it
	 * impossible to nest a second form without using js or something ugly.
	 */
	public function filter_admin_footer() {
		global $pagenow, $typenow, $post;

		// only load this in a post editing context
		if ( !in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
			return;
		}

		?>
		<div id="sk-load-scroll" style="display:none">
			<h2>Copy Existing Scroll</h2>
			<form method="GET" action="<?php bloginfo('url') ?>">
				<input type="hidden" name="scrollkit" value="load" />
				<input type="hidden" name="p" value="<?php the_ID() ?>" />
				<input name="skid" placeholder="http://www.scrollkit.com/s/f0Z9WbS/" size="30" />
				<input type="submit" value="Load Scroll" />
			</form>
		</div>
	<?php
	}

	/**
	 * Display a settings link on the main Plugins page
	 */
	public function filter_plugin_action_links( $links, $file ) {

		if ( $file == plugin_basename( __FILE__ ) ) {
			$settings_link = '<a href="' . SCROLL_WP_SETTINGS_URL . '">' . __('Settings') . '</a>';

			// make the 'Settings' link appear first
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	/**
	 * Some defaults for the header and footer on first activation
	 */
	public function hook_add_defaults() {
		$tmp = get_option( 'scroll_wp_options' );
		if ( !is_array( $tmp ) ) {

			$arr = array(
				"scrollkit_api_key" => "",
				"template_header" => ScrollKit::template_header_default(),
				"template_footer" => ScrollKit::template_footer_default()
			);

			update_option( 'scroll_wp_options', $arr );
		}
	}

	/**
	 * Delete options table entries when plugin deactivated AND deleted
	 *
	 * note that this doesn't remove metadata associated with posts
	 */
	public static function hook_delete_plugin_options() {
		delete_option( 'scroll_wp_options' );
	}


	/**
	 * Functionality the user to send content to scroll
	 */
	public static function metabox() {
		include( dirname( __FILE__ ) . '/metabox-view.php' );
	}

	/**
	 * Handles all requests that manipulate scroll data
	 * e.g. update, deactive, activate, delete
	 */
	private function handle_scroll_action($method) {

		$this->authenticate_request();

		$post_id = get_queried_object_id();
		if ( empty( $post_id) ) {
			$post_id = get_query_var( 'p' );
		}

		if ( empty( $post_id ) ) {
			$this->log_error_and_die( 'No post id provided' );
		}

		if ( empty( $method ) ) {
			$this->log_error_and_die( 'No method provided' );
		}

		switch ( $method ) {
			case 'update':
				$this->update_sk_post( $post_id );
				exit;
			case 'activate':
				$this->activate_post( $post_id );
				break;
			case 'load':
				// forking a scroll, figure out what scrollkit id they want

				$skid = isset( $_GET['skid'] ) ? $_GET['skid'] : '';

				// Some people, when confronted with a problem, think
				// “I know, I'll use regular expressions.”
				// Now they have found true <3<3<3<3<3<3
				$pattern = '/\s*(https?:\/\/.*\/s\/)?([a-zA-Z0-9]+).*$/';

				$is_match = preg_match( $pattern, $skid, $matches );
				if ( $is_match !== 1 || count( $matches ) < 3 ) {
					wp_die( 'There was an issue scrollkit URL or ID you provided' );
				}
				$scroll_id = $matches[2];

				$this->load_scroll( $post_id, $scroll_id );

				break;
			case 'deactivate':
				$this->deactivate_post( $post_id );
				break;
			case 'delete':
				$this->delete_post( $post_id );
				break;
		}

		wp_safe_redirect( get_edit_post_link( get_queried_object_id(), '' ) );
		exit;
	}

	/**
	 * Ensures a GET variable 'key' exists and matches the api key
	 * in the DB
	 *
	 * if it doesn't match, a 401 error is produced and errors are logged
	 * to the plugin's option table
	 */
	private function is_api_key_valid() {
		$api_key = isset( $_GET['key'] ) ? $_GET['key'] : null;

		$options = get_option( 'scroll_wp_options' );

		if ( empty( $options['scrollkit_api_key'] ) || $api_key !== $options['scrollkit_api_key'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if an admin is signed in or if the correct sk api key is provided
	 * kills the request if neither of those conditions or met
	 */
	private function authenticate_request() {
		if ( !current_user_can( 'edit_posts' ) && !$this->is_api_key_valid()) {
			$this->log_error_and_die( 'Invalid API Key', 401 );
		}
	}

	/**
	 * Logs an error to our plugin's option table
	 */
	private function log_error( $error_message ) {
		$options = get_option( 'scroll_wp_options' );
		$errors = array();

		if ( array_key_exists( 'errors', $options ) ) {
			$errors = $options['errors'];
		}

		$errors[] = $error_message;

		$options['errors'] = $errors;

		update_option( 'scroll_wp_options', $options );
	}

	private function log_error_and_die( $message, $http_response_code = 500 ) {
		$this->log_error( $message );
		wp_die( $message, '', array('response' => $http_response_code ) );
	}

	/**
	 * Updates wordpress' copy of a scroll post by fetching the data from
	 * scrollkit
	 */
	private function update_sk_post( $post_id ) {

		$post = get_post( $post_id );

		$scroll_id = get_post_meta( $post_id, '_scroll_id', true );
		$content_url = ScrollKit::build_content_url( $scroll_id );

		if ( empty( $post ) || empty( $content_url ) ) {
			$this->log_error_and_die( 'Empty post or content URL' );
		}

		$results = wp_remote_get( $content_url );

		if ( is_wp_error( $results) ) {
			$this->log_error_and_die( $results->get_error_message() );
		}

		$response_code = $results['response']['code'];
		if ( $response_code !== 200 ) {
			$this->log_error_and_die( "error requesting content from $content_url, error code  " . $response_code );
		}

		$data = json_decode( $results['body'] );

		update_post_meta( $post_id, '_scroll_content', $data->content );
		update_post_meta( $post_id, '_scroll_css', $data->css );
		update_post_meta( $post_id, '_scroll_fonts', $data->fonts );
		update_post_meta( $post_id, '_scroll_js',  $data->js );

		// trigger update to invalidate cache
		wp_update_post( array( 'ID' => $post_id ) );
	}

	/**
	 * Active a scroll post, converting the post if it's not a scroll.
	 * if it's a a disabled scroll then enable it
	 */
	private function activate_post( $post_ID ) {
		$state = get_post_meta( $post_ID, '_scroll_state', true );
		$state = empty( $state ) ? 'none' : $state;

		switch ( $state ) {
			case 'active':
				return;
			case 'inactive':
				update_post_meta( $post_ID, '_scroll_state', 'active' );
				return;
			case 'none':
				$this->convert_post( $post_ID );
				return;
		}
	}

	/**
	 * Creates a fork of an existing scroll
	 */
	private function load_scroll( $post_id, $scroll_id ) {

		// fetch the user entered api key from plugin's settings
		$options = get_option( 'scroll_wp_options' );
		$api_key = $options['scrollkit_api_key'];

		// collect all the data needed to send to sk
		$data = array();
		$data['title'] = get_the_title( $post_id );
		$data['cms_id'] = $post_id;
		$data['cms_url'] = get_bloginfo('url');
		$data['api_key'] = $api_key;
		$data['scroll_id'] = $scroll_id;

		$this->request_new_scroll( $data, $post_id );

		$this->update_sk_post( $post_id );

		// send the user back to the post edit context
		// where they are notified that a post is a scroll
		$edit_url = get_edit_post_link( $post_id , '' );
		wp_safe_redirect( $edit_url );
		exit;
	}

	/**
	 * Stop a scroll post from being served as a scroll, leaving the scroll
	 * data intact
	 */
	private function deactivate_post( $post_ID ) {
		$state = get_post_meta( $post_ID, '_scroll_state', true );
		if ( empty( $state ) || $state === 'inactive' ) {
			return;
		}
		update_post_meta( $post_ID, '_scroll_state', 'inactive' );
	}

	/**
	 * Converts a wordpress post into a scroll
	 */
	private function convert_post( $post_id ) {
		$post = get_post( $post_id );

		// fetch the user entered api key from plugin's settings
		$options = get_option( 'scroll_wp_options' );
		$api_key = $options['scrollkit_api_key'];

		// collect all the data needed to send to sk
		$data = array();
		$data['title'] = get_the_title( $post_id );

		//replace new lines with br tags for scrollkit
		$data['content'] = str_replace( PHP_EOL, '<br />&nbsp;', $post->post_content );

		$data['cms_id'] = $post_id;
		$data['cms_url'] = get_bloginfo('url'); // . '?scrollkit=update';
		$data['api_key'] = $api_key;

		$this->request_new_scroll( $data, $post_id );

		// send the user back to the post edit context
		// where they are notified that a post is a scroll
		wp_safe_redirect( get_edit_post_link( $post_id , '' ) );
	}

	private function request_new_scroll( $data, $post_id ) {
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

		// handle wp errors (can't connect to host, etc)
		if ( is_wp_error( $response ) ) {
			wp_die( $response->get_error_message() );
		}

		// Handle sk response
		$http_response_code = $response['response']['code'];

		switch ( $http_response_code ) {
			case 200:
				break;
			case 422:
				// api key is incorrect, redirect the user to this plugin's setting page
				// where there's a message indicating an api key issue
				$destination = add_query_arg('api-key-error', 'true', SCROLL_WP_SETTINGS_URL);

				wp_safe_redirect( $destination );
				exit;
			default:
				// probably a 500 error
				wp_die("Scroll Kit had an unexpected error, please contact"
						. " hey@scrollkit.com if this continues to happen",
						"Error with Scroll Kit");
		}

		$response_body = json_decode( $response['body'], true );

		update_post_meta( $post_id, '_scroll_id', $response_body['sk_id'] );
		update_post_meta( $post_id, '_scroll_state', 'active' );
	}

	/**
	 * Callback to replace the current template with our blank template
	 */
	public function load_template() {
		return dirname( __FILE__ ) . '/template.php';
	}

	/**
	 * Render the settings view for inputting the api key, header and footer
	 */
	public static function render_settings_view() {
		include( dirname( __FILE__ ) . '/settings-view.php');
	}

	/**
	 * Removes all scrollkit data associated with a post
	 */
	private static function delete_post( $post_id ) {
		delete_post_meta( $post_id, '_scroll_id' );
		delete_post_meta( $post_id, '_scroll_state' );
		delete_post_meta( $post_id, '_scroll_content' );
		delete_post_meta( $post_id, '_scroll_css' );
		delete_post_meta( $post_id, '_scroll_fonts' );
		delete_post_meta( $post_id, '_scroll_js' );
	}

	/**
	 * Sanitizes the api key
	 */
	public static function validate_options( $input ) {
		$input['scrollkit_api_key'] = wp_filter_nohtml_kses( $input['scrollkit_api_key'] );
		return $input;
	}


	/**
	 * Gives the user a scrollkit url where they can edit the post
	 */
	public static function build_edit_url( $scrollkit_id ) {
		return SCROLL_WP_SK_URL . "s/$scrollkit_id/edit";
	}

	/**
	 * Builds a url which serves a chunk of json with html, javascript, css and
	 * webfonts. e.g. http://www.scrollkit.com/s/qgPwxGA/content
	 */
	public static function build_content_url( $scrollkit_id ) {
		return SCROLL_WP_SK_URL . "s/$scrollkit_id/content";
	}

	public static function template_header_default() {

		$blog_title = get_bloginfo( 'name' );
		return <<<EOT
<!DOCTYPE html>
<html>
	<head>
		<title>{{ title }} | $blog_title</title>
		<meta name="viewport" content="width=980">
		{{ stylesheets }}
	</head>
	<body class="published">
		<div id="skrollr-body">
EOT;
	}

	public static function template_footer_default() {
		return <<<EOT
		</div>
		{{ scripts }}
	</body>
</html>
EOT;
	}

}

new ScrollKit();
