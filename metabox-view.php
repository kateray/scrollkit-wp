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

?>

	<a href="#TB_inline?height=300&amp;width=400&amp;inlineId=scrollkit-post-options"
		title="Scrollkit Post Settings"
		class="thickbox"
		id="scrollkit-post-options-trigger"
		>Edit this post with Scroll Kit</a>

	<div id="scrollkit-post-options" style="display:none">
		<h2>This Post is Now a Scroll</h2>

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

	</div>


<?php if( get_post_meta( $post->ID, '_scroll_state', true ) === 'active' ): ?>
<script>
window.onload = function(){
	jQuery('#scrollkit-post-options-trigger').click();
};
</script>
<?php endif; ?>
