<!DOCTYPE html>
<html>
	<head>
		<title><?php the_title(get_the_ID()) | bloginfo( 'name' ) ?></title>
		<meta name="viewport" content="width=980">

		<?php foreach ( get_post_meta( get_the_ID(), '_scroll_css', true ) as $stylesheet): ?>
			<link href="<?php echo SCROLL_WP_SK_URL . $stylesheet ?>" media="screen" rel="stylesheet" type="text/css" />
		<?php endforeach; ?>
		<style type="text/css">
			<?php echo get_post_meta(get_the_ID(), '_scroll_style', true); ?>
		</style>
	</head>
	<body class="published">
		<div id="skrollr-body">
			<?php if (WP_DEBUG): ?>
				<!--
				scroll id: <?php get_post_meta(get_the_ID(), '_scroll_id', true); ?>
				-->
			<?php endif ?>

			<?php echo get_post_meta(get_the_ID(), '_scroll_content', true); ?>

		</div>

		<?php foreach(get_post_meta(get_the_ID(), '_scroll_js', true) as $script): ?>
			<script src="<?php echo SCROLL_WP_SK_URL . $script ?>" type="text/javascript"></script>
		<?php endforeach ?>

	</body>
</html>
