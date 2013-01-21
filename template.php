<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width" />
<title><?php wp_title( '|', true, 'right' ); ?></title>
<?php // wp_head(); ?>
<?php
	// TODO
	// consider using a wordpress-y way to do this with hooks
	$stylesheets = get_post_meta($post->ID, '_scroll_css', true);
	foreach($stylesheets as $stylesheet):
?>
	<link href="<?php echo $stylesheet ?>" media="screen" rel="stylesheet" type="text/css" />
<?php endforeach ?>

</head>

<body <?php body_class(); ?>>

<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>

<style>
  /*<![CDATA[*/
    #edge_bleed {margin:-8px;position:0px;padding:0px;}
  /*]]>*/
</style>
<div id="edge_bleed">

<?php echo get_post_meta($post->ID, '_scroll_content', true); ?>
</div>
<?php endwhile; ?>

<?php
	$scripts = get_post_meta($post->ID, '_scroll_js', true);
	foreach($scripts as $script):
?>
	<script src="<?php echo $script ?>" type="text/javascript"></script>
<?php endforeach ?>

</body>
