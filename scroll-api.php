<?php
$root = dirname(dirname(dirname(dirname(__FILE__))));

if (file_exists($root.'/wp-load.php')) {
	require_once($root.'/wp-load.php');
} else {
	require_once($root.'/wp-config.php');
}

$api_key = $_POST['api_key'];
$post_id = $_POST["post_id"];
$post = get_post($post_id);

$custom_field = $_POST["scroll"];

$my_post = array();
$my_post['ID'] = $post_id;
$my_post['post_title'] = $_POST["title"];
$my_post['post_content'] = $_POST["content"];

wp_update_post( $my_post );

update_post_meta($post_id, 'scroll', $custom_field);


?>