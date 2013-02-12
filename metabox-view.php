<?php

global $post;
$options = get_option( 'scroll_wp_options' );
$scrollkit_id = get_post_meta( $post->ID, '_scroll_id', true );

$state = get_post_meta( $post->ID, '_scroll_state', true );

// different text based on scroll state
$copy = array();

switch($state){
	case 'active':
		$copy['heading'] = "This Post is a Scroll";
		break;
	case 'inactive':
		$copy['heading'] = "This Post has an Inactive Scroll";
		$copy['activate'] = "Activate Scroll";
		break;
	default:
		$copy['heading'] = "Convert this Post into a Scroll";
		$copy['activate'] = "Convert Post";
}

?>

<h4><?php echo $copy['heading'] ?></h4>

<?php if (!empty($scrollkit_id)): ?>
<a href="<?php echo $this->build_scrollkit_edit_url($scrollkit_id) ?>" target="_blank">
	Edit Scroll
</a>
<?php endif; ?>

<?php if( $state !== 'active' ): ?>
<br>
<a href="<?php bloginfo('url') ?>/?scrollkit=activate&p=<?php echo $post->ID ?>">
	<?php echo $copy['activate'] ?>
</a>
<?php else: ?>

<div class="updated">
	<p>
		This post is a scroll. You can <a href="<?php echo $this->build_scrollkit_edit_url($scrollkit_id) ?>" target="_blank">edit this post with Scroll Kit</a>
	</p>
</div>

<?php endif ?>

<?php if( $state !== 'inactive' ): ?>
<br>
<a href="<?php bloginfo('url') ?>/?scrollkit=deactivate&p=<?php echo $post->ID ?>"
		title="Turn this back into a normal wordpress post">
	Dectivate Scroll
</a>
<?php endif ?>

<br>
<a href="<?php bloginfo('url') ?>/?scrollkit=delete&p=<?php echo $post->ID ?>"
		onclick="return confirm('This will permanently delete the scroll associated with this post, are you sure you want to delete it?');"
		title="Permanently deletes the scroll associated with this post">
	Delete Scroll
</a>

<?php if (WP_DEBUG === true): ?>
<pre>
DEBUG
_scroll_id: <?php echo get_post_meta( $post->ID, '_scroll_id', true ); ?>

_scroll_state: <?php echo get_post_meta( $post->ID, '_scroll_state', true ); ?>
</pre>
<?php endif ?>

