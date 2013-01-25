<?php

global $post;
$options = get_option( 'scroll_wp_options' );
$scrollkit_id = get_post_meta( $post->ID, '_scroll_id', true );
global $post;
wp_enqueue_script(
	'scrollkit-wp',
	SCROLL_WP_URL . 'scrollkit-wp.js',
	array('jquery')
);

// needed
// ======
// activate link
// deactive link
// delete link

?>

	<?php echo get_post_meta( $post->ID, '_scroll_state', true ); ?>
	<?php if (!empty($scrollkit_id)): ?>
		<a href="<?php echo $this->build_scrollkit_edit_url($scrollkit_id) ?>"
				target="_blank">
			Edit this Scroll
		</a>
	<?php endif; ?>

	<br>
	<a href="<?php bloginfo('url') ?>/?scrollkit=activate&p=<?php echo $post->ID ?>">
		Convert to Scroll or Activate Scroll
	</a>

	<br>
	<a href="<?php bloginfo('url') ?>/?scrollkit=deactivate&p=<?php echo $post->ID ?>"
			title="Turn this back into a normal wordpress post">
		Dectivate Scroll
	</a>

	<br>
	<a href="<?php bloginfo('url') ?>/?scrollkit=delete&p=<?php echo $post->ID ?>"
			onclick="return confirm('This will permanently delete the scroll associated with this post, are you sure you want to delete it?');"
			title="Permanently deletes the scroll associated with this post">
		Delete Scroll
	</a>

	<?php
		// XXX popup blockers prevent this from opening
		// launch the editor popup
		if (!empty($_GET['scrollkitpopup'])):
			$url = urldecode($_GET['scrollkitpopup']);
			// ugh http://stackoverflow.com/questions/2587677/
	?>
		<script>
			window.open("<?php echo $url ?>", 'scroll kit', "height=600,width=1000");
		</script>
	<?php endif; ?>
