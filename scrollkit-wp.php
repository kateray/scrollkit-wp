<?php
/*
Plugin Name: Scroll Kit
Plugin URI: http://scrollkit.com
Description: Adds a button to send a page's content to the scroll kit design interface, which generates custom html and css that override the page's default template.
Version: 0.1
Author: Scroll Kit
Author URI: http://scrollkit.com
License: GPL2
*/

// put this into your wp-config.php if you are running scrollkit locally:
// define('SK_DEBUG_URL', 'http://localhost:3000/');
if ( defined('SK_DEBUG_URL') ) {
	define( 'SCROLL_WP_SK_URL', SK_DEBUG_URL );
	define( 'SCROLL_WP_SK_ASSET_URL', SCROLL_WP_SK_URL );
} else {
	define( 'SCROLL_WP_SK_URL', 'http://www.scrollkit.com/' );
	// TODO this should be some sort of CDN instead of our rails app
	define( 'SCROLL_WP_SK_ASSET_URL', SCROLL_WP_SK_URL );
}

define( 'SCROLL_WP_API', SCROLL_WP_SK_URL . 'api/' );
define( 'SCROLL_WP_BASENAME', plugin_basename( __FILE__ ) );


class ScrollKit {

	function __construct() {
		add_action( 'admin_init'           , array( $this, 'action_admin_init' ) );
		add_action( 'admin_menu'           , array( $this, 'action_admin_menu') );
		add_action( 'add_meta_boxes'       , array( $this, 'action_add_metaboxes' ) );
		add_action( 'template_redirect'    , array( $this, 'action_template_redirect' ) );

		add_filter( 'query_vars'           , array( $this, 'filter_query_vars' ) );
		add_filter( 'admin_footer'         , array( $this, 'filter_admin_footer') );
		add_filter( 'plugin_action_links'  , array( $this, 'filter_plugin_action_links' ), 10, 2 );

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
				'scroll-kit', array( $this, 'render_settings_view' ) );
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
			$action = get_query_var('scrollkit');
			if ( $action === 'update' ) {
				// only scrollkit can hit the update endpoint
				$this->handle_scrollkit_update_request();
			} else {
				$this->handle_user_action( $action );
			}
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
	 * Adds scrollkit to the list of query variables that wordpress pays
	 * attention to
	 */
	public function filter_query_vars( $wp_vars ) {
		$wp_vars[] = 'scrollkit';
		$wp_vars[] = 'scrollkit_cms_id';
		return $wp_vars;
	}

	/**
	 * Adds a hidden modal after the editor stuff
	 *
	 * TODO consider using ajax requests within the metabox instead
	 *
	 */
	public function filter_admin_footer() {
		global $pagenow;

		// only load this in a post editing context
		if ( !in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
			return;
		}

		?>
			<div id="sk-load-scroll" style="display:none">
				<h2>Copy Existing Scroll</h2>
				<form method="GET" action="<?php bloginfo('url') ?>">
					<input type="hidden" name="scrollkit" value="load" />
					<input type="hidden" name="scrollkit_cms_id" value="<?php the_ID() ?>" />
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
			$settings_link = '<a href="' . menu_page_url( 'scroll-kit', false ) . '">' . __('Settings') . '</a>';

			// make the 'Settings' link appear first
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	/**
	 * Delete options table entries when plugin deactivated AND deleted
	 *
	 * Note: this doesn't remove metadata associated with existing scroll posts
	 */
	public static function hook_delete_plugin_options() {
		delete_option( 'scroll_wp_options' );
	}

	/**
	 * Loads our metabox for user controls like 'Convert to Scroll' etc
	 */
	public static function metabox() {
		@include dirname( __FILE__ ) . '/metabox-view.php';
	}

	/**
	 * Handles all user requests that manipulate scroll data
	 * e.g. update, deactive, activate, delete
	 */
	private function handle_user_action($method) {

		$post_id = get_query_var( 'scrollkit_cms_id' );

		if ( !current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'Insufficient permissions', '', array('response' => 401) );
		}

		if ( empty( $post_id ) ) {
			$this->log_error_and_die( 'No post id provided' );
		}

		if ( !get_post( $post_id ) ){
			$this->log_error_and_die( "Scroll Kit is trying to update a post that doesn't exist" );
		}

		if ( empty( $method ) ) {
			$this->log_error_and_die( 'No Scroll Kit method provided' );
		}

		switch ( $method ) {

			// a user can activate a non-scroll post, or a scroll post that is
			// deactivated
			case 'activate':
				$this->activate_post( $post_id );
				break;

			// a user can pass in a scrollkit URL so scrollkit knows to create a
			// copy of an existing scroll
			case 'load':
				$skid = isset( $_GET['skid'] ) ? $_GET['skid'] : '';
				$scroll_id = self::parse_scroll_id($skid);

				if ($scroll_id === null) {
					wp_die( 'There was an error with the scrollkit URL or ID provided' );
				}

				$this->copy_existing_scroll( $post_id, $scroll_id );
				break;

			// a user can deactive a scroll so the normal post is served
			case 'deactivate':
				$this->deactivate_post( $post_id );
				break;

			// a user can delete all scroll kit metadata associated with a post
			// note: it doesn't delete the scroll on scrollkit.com
			case 'delete':
				$this->delete_post( $post_id );
				break;
		}

		wp_safe_redirect( get_edit_post_link( $post_id, '' ) );
		exit;
	}

	private function handle_scrollkit_update_request() {
		$post_id = get_query_var( 'scrollkit_cms_id' );

		if ( empty( $post_id ) ) {
			$this->log_error_and_die( 'No post id provided' );
		}

		if ( !get_post( $post_id ) ){
			$this->log_error_and_die( "Scroll Kit is trying to update a post that doesn't exist" );
		}

		if ( $this->is_api_key_valid() ) {
			$this->update_scroll_post( $post_id );
		} else {
			$this->log_error_and_die('Invalid api key', 401);
		}
	}

	/**
	 * Checks if there's a valid api key in a request
	 */
	private function is_api_key_valid() {
		$api_key = isset( $_GET['key'] ) ? $_GET['key'] : null;

		$options = get_option( 'scroll_wp_options', self::option_defaults() );

		if ( empty( $options['scrollkit_api_key'] ) || $api_key !== $options['scrollkit_api_key'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Logs an error to our plugin's option table
	 */
	private function log_error( $error_message ) {
		$options = get_option( 'scroll_wp_options', self::option_defaults() );
		$errors = array();

		if ( array_key_exists( 'errors', $options ) ) {
			$errors = $options['errors'];
		}

		$errors[] = $error_message;

		$options['errors'] = $errors;

		update_option( 'scroll_wp_options', $options );
	}

	/**
	 * Log an error to the DB and kill the connection
	 */
	private function log_error_and_die( $message, $http_response_code = 500 ) {
		$this->log_error( $message );
		wp_die( $message, '', array('response' => $http_response_code ) );
	}

	/**
	 * Updates wordpress' copy of a scroll post by fetching the data from
	 * scrollkit
	 */
	private function update_scroll_post( $post_id ) {
		$scroll_id = get_post_meta( $post_id, '_scroll_id', true );

		// fetch data from scroll kit
		$content_url = self::build_content_url( $scroll_id );
		$results = wp_remote_get( $content_url );

		if ( is_wp_error( $results) ) {
			$this->log_error_and_die( $results->get_error_message() );
		}

		$response_code = $results['response']['code'];
		if ( $response_code !== 200 ) {
			$this->log_error_and_die( "Error requesting content from $content_url, error code  " . $response_code );
		}

		$data = json_decode( $results['body'] );

		update_post_meta( $post_id , '_scroll_content' , wp_filter_post_kses( $data->content ) );
		update_post_meta( $post_id , '_scroll_style'   , wp_filter_post_kses( $data->style ) );
		update_post_meta( $post_id , '_scroll_js'      , $this->sanitize_url_array( $data->js_paths ) );
		update_post_meta( $post_id , '_scroll_fonts'   , $this->sanitize_text_array( $data->google_web_fonts ) );
		update_post_meta( $post_id , '_scroll_css'     , $this->sanitize_url_array( $data->css_paths ) );

		// trigger update incase the user has a cache
		clean_post_cache( $post_id );
	}

	public static function sanitize_url_array( $unsafe_url_array ) {
		$sanitized_urls = array();
		foreach ($unsafe_url_array as $unsafe_url) {
			if ( esc_url( $unsafe_url ) !== '' ) {
				$sanitized_urls[] = esc_url( $unsafe_url );
			}
		}
		return $sanitized_urls;
	}

	public static function sanitize_text_array( $unsafe_text_array ) {
		$sanitized_text = array();
		foreach ($unsafe_text_array as $unsafe_text) {
			$sanitized_text[] = sanitize_text_field( $unsafe_text );
		}
		return $sanitized_text;
	}

	/**
	 * Active a scroll post that's either not a scroll post yet, or it's a scroll
	 * post that's disabled
	 */
	private function activate_post( $post_id ) {
		$state = get_post_meta( $post_id, '_scroll_state', true );
		$state = empty( $state ) ? 'none' : $state;

		switch ( $state ) {
			case 'active':
				return;
			case 'inactive':
				update_post_meta( $post_id, '_scroll_state', 'active' );
				return;
			case 'none':
				$this->convert_post( $post_id );
				return;
		}
	}

	/**
	 * Creates a duplicate of an existing scroll
	 */
	private function copy_existing_scroll( $post_id, $scroll_id ) {

		// fetch the user entered api key from plugin's settings
		$options = get_option( 'scroll_wp_options', self::option_defaults() );
		$api_key = $options['scrollkit_api_key'];

		// collect all the data needed to send to sk
		$data = array();
		$data['title']     = get_the_title( $post_id );
		$data['cms_id']    = $post_id;
		$data['cms_url']   = get_bloginfo('url');
		$data['api_key']   = $api_key;
		$data['scroll_id'] = $scroll_id;

		$this->request_new_scroll( $data, $post_id );

		$this->update_scroll_post( $post_id );
	}

	/**
	 * Stop a scroll post from being served as a scroll, leaving the scroll
	 * data intact
	 */
	private function deactivate_post( $post_ID ) {
		$state = get_post_meta( $post_ID, '_scroll_state', true );

		// handle posts that are already deactivated
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
		$options = get_option( 'scroll_wp_options', self::option_defaults() );
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
	}

	/**
	 * Asks scrollkit for a scroll
	 *
	 * pass in a 'scroll_id' attribute on data to duplicate an existing scroll
	 *
	 * pass in 'content' and 'title' attributes if you want scrollkit to create a
	 * scroll with existing content
	 */
	private function request_new_scroll( $data, $post_id ) {
		// send the data to scrollkit
		$response = wp_remote_post( SCROLL_WP_API . 'new',  array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(),
				'body'        => $data,
				'cookies'     => array()
			)
		);

		// handle wp errors (can't connect to host, etc)
		if ( is_wp_error( $response ) ) {
			wp_die( $response->get_error_message() );
		}

		// handle sk response
		$http_response_code = $response['response']['code'];

		switch ( $http_response_code ) {
			case 200:
				break;
			case 422:
				// api key is incorrect, redirect the user to this plugin's setting page
				// where there's a message indicating an api key issue
				$destination = add_query_arg('api-key-error', 'true', menu_page_url( 'scroll-kit', false ) );

				wp_safe_redirect( $destination );
				exit;
			default:
				// probably a 500 error
				wp_die("Scroll Kit had an unexpected error, please contact hey@scrollkit.com if this continues to happen",
						"Error with Scroll Kit");
		}

		$response_body = json_decode( $response['body'], true );

		update_post_meta( $post_id, '_scroll_id', sanitize_text_field( $response_body['sk_id'] ) );
		update_post_meta( $post_id, '_scroll_state', 'active' );
	}

	/**
	 * Callback to replace the current template with our blank template
	 */
	public function load_template() {
		return dirname( __FILE__ ) . '/template.php';
	}

	/**
	 * Render the settings view for inputting the api key
	 */
	public function render_settings_view() {
		@include dirname( __FILE__ ) . '/settings-view.php';
	}

	/**
	 * Removes all scrollkit data associated with a post
	 */
	private static function delete_post( $post_id ) {
		delete_post_meta( $post_id, '_scroll_id' );
		delete_post_meta( $post_id, '_scroll_state' );
		delete_post_meta( $post_id, '_scroll_content' );
		delete_post_meta( $post_id, '_scroll_style' );
		delete_post_meta( $post_id, '_scroll_js' );
		delete_post_meta( $post_id, '_scroll_fonts' );
		delete_post_meta( $post_id, '_scroll_css' );
	}

	/**
	 * Pulls the scroll id from a variety of strings
	 * e.g.
	 *
	 * https://www.scrollkit.com/s/1IqDfAD/edit
	 * http://www.scrollkit.com/s/1IqDfAD/
	 * 1IqDfAD
	 *
	 * will all return 1IqDfAD
	 *
	 * returns null on invalid input
	 */
	public static function parse_scroll_id( $mixed ) {
		// Some people, when confronted with a problem, think
		// “I know, I'll use regular expressions.”
		// Now they have found true <3<3<3<3<3<3
		$pattern = '/\s*(https?:\/\/.*\/s\/)?([a-zA-Z0-9]+).*$/';

		$is_match = preg_match( $pattern, $mixed, $matches );
		if ( $is_match !== 1 || count( $matches ) < 3 ) {
			return null;
		}

		return $matches[2];
	}

	/**
	 * Sanitizes the api key
	 */
	public static function validate_options( $input ) {
		$output = array();
		$output['scrollkit_api_key'] = sanitize_text_field( $input['scrollkit_api_key'] );
		return $output;
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

	/**
	 * minimal template rendering with variables between {{ mustaches }}
	 */
	public static function render_template($data, $template){
		$rendered = $template;
		foreach ($data as $key => $val){
			$pattern = "/{{\s*" . $key . "\s*}}/";
			$rendered = preg_replace($pattern, $val, $rendered);
		}
		echo $rendered;
	}

	public static function option_defaults() {
		return array(
			"scrollkit_api_key" => "",
		);
	}
}

new ScrollKit();
