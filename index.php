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

define( 'SCROLL_WP_SK_URL', 'http://localhost:3000/' );
define( 'SCROLL_WP_API', SCROLL_WP_SK_URL . 'api/' );

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

		// there are prettier ways to do this
		switch ( $method ){
			case 'update':
				$this->update_post();
				break;
			case 'convert':
				$this->convert_post();
				break;
		}
	}

	// lol global state
	function update_post() {
		$post_id = get_query_var('p');
		$post = get_post($post_id);
		$content_url = get_post_meta($post_id, '_scroll_content_url', true);

		if ( empty ( $post ) || empty ( $content_url ) ) {
			// i think this is broken
			return;
		}

		$data = json_decode ( $this->fetch_url( $content_url ) ) ;

		update_post_meta($post->ID, '_scroll_content', $data->content);
		update_post_meta($post->ID, '_scroll_css', $data->css);
		update_post_meta($post->ID, '_scroll_fonts', $data->fonts);

		$script_string = implode($data->js, ',');
		update_post_meta($post->ID, '_scroll_js', $script_string);

		$edit = get_edit_post_link( $post->ID , '');
		header("Location: $edit&message=1", true, 302);

		exit;
	}

	function create_edit_url($scrollkit_id) {
		return SCROLL_WP_SK_URL . "s/$scrollkit_id/edit";
	}

	function create_content_url($scrollkit_id) {
		return SCROLL_WP_SK_URL . "s/$scrollkit_id/content";
	}

	function convert_post() {
		$post_id = get_query_var('p');
		$post = get_post($post_id);

		$options = get_option('scroll_wp_options');
		$api_key = $options['scrollkit_api_key'];

		$data = array();
		$data['title'] = $post->post_title;
		$data['content'] = $post->post_content;
		$data['cms_id'] = $post_id;
		$data['api_key'] = $api_key;

		$response = $this->post_json_to_url(SCROLL_WP_API . 'new', $data);

		update_post_meta($post->ID, '_scroll_id', $response['sk_id']);

		$encoded_edit_link = urlencode($this->create_edit_url($response['sk_id']));

		$edit = get_edit_post_link($post->ID , '');

		header("Location: $edit&scrollkitpopup=$encoded_edit_link", true, 302);

		exit;
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

	// le double sigh...
	// sends json to a url, return whatever json it gives back in a php array
	function post_json_to_url ($url, $data) {

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
			die("eeek! response: $status");
		}

		curl_close($curl);

		$response = json_decode($json_response, true);
		return $response;
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
			<a href="/?scrollkit=convert&p=<?php echo $post->ID ?>">
				Convert to Scroll
			</a>
			<br>
			<a href="/?scrollkit=update&p=<?php echo $post->ID ?>">
				Manually pull changes
			</a>
			<?php
				if (!empty($_GET['scrollkitpopup'])):
					$url = urldecode($_GET['scrollkitpopup']);
					// ugh http://stackoverflow.com/questions/2587677/
			?>
				<script>
					window.open("<?php echo $url ?>", 'scroll kit', "height=600,width=1000");
				</script>
			<?php endif; ?>
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
			add_action('wp_head', array( $this, 'include_head' ) );
			add_filter('single_template', array( $this, 'load_template' ), 100);
		}

	}

	function include_head() {
		// global state lolololol
		global $post;
		//get_post_meta($post->ID, '
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


// Below is the code to uh, store a single <input>'s val in the db...

// Add menu page
function scroll_wp_add_options_page() {
	add_options_page('Scroll Kit WP', 'Scroll Kit WP', 'manage_options', __FILE__, 'scroll_wp_render_form');
}
add_action('admin_menu', 'scroll_wp_add_options_page');

function scroll_wp_render_form() {
	?>
	<div class="wrap">

		<div class="icon32" id="icon-options-general"><br></div>
		<h2>Scroll Kit WP</h2>
		<!--<p>You could have some words here if you are a fancy plugin</p>-->

		<form method="post" action="options.php">
			<?php settings_fields('scroll_wp_plugin_options'); ?>
			<?php $options = get_option('scroll_wp_options'); ?>

			<table class="form-table">
				<tr>
					<th scope="row">Scroll Kit API Key</th>
					<td>
						<input type="text" size="57" name="scroll_wp_options[scrollkit_api_key]" value="<?php echo $options['scrollkit_api_key']; ?>" />
						<br>
					 (TODO add link to get api key)
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>

<?php
}

// Sanitize and validate input. Accepts an array, return a sanitized array.
function scroll_wp_validate_options($input) {
	//TODO regex for our api key
	$input['scrollkit_api_key'] = wp_filter_nohtml_kses($input['scrollkit_api_key']);
	return $input;
}

// Init plugin options to white list our options
function scroll_wp_init(){
	register_setting( 'scroll_wp_plugin_options',
			'scroll_wp_options',
			'scroll_wp_validate_options' );
}
add_action('admin_init', 'scroll_wp_init' );

// Display a Settings link on the main Plugins page
function scroll_wp_action_links( $links, $file ) {

	if ( $file == plugin_basename( __FILE__ ) ) {
		$posk_links = '<a href="'.get_admin_url().'options-general.php?'
				. 'page=scroll-wp/index.php">'
				. __('Settings')
				. '</a>';
		// make the 'Settings' link appear first
		array_unshift( $links, $posk_links );
	}

	return $links;
}
add_filter( 'plugin_action_links', 'scroll_wp_action_links', 10, 2 );

// Delete options table entries ONLY when plugin deactivated AND deleted
function scroll_wp_delete_plugin_options() {
	delete_option('scroll_wp_options');
}
register_uninstall_hook(__FILE__, 'scroll_wp_delete_plugin_options');

// sets some default values on first activation
function scroll_wp_add_defaults () {
	$tmp = get_option('scroll_wp_options');
	if(!is_array($tmp)) {
		$arr = array(
			"scrollkit_api_key" => ""
		);
		update_option('scroll_wp_options', $arr);
	}
}

register_activation_hook(__FILE__, 'scroll_wp_add_defaults');
//register_deactivation_hook(__FILE__, 'scroll_wp_delete_plugin_options');
