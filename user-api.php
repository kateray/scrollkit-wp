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


$root = dirname(dirname(dirname(dirname(__FILE__))));

if (file_exists($root.'/wp-load.php')) {
	require_once($root.'/wp-load.php');
} else {
	require_once($root.'/wp-config.php');
}

$api_key = $_POST['api_key'];
$post_id = $_POST['post_id'];
$post = get_post($post_id);
$user = $post->post_author
$user_id = $user->ID;
update_user_meta($user_id, 'scroll_api_key', $api_key);
update_post_meta($post_id, 'user_api_key', $api_key);
?>