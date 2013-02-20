<?php

global $post;
$options = get_option( 'scroll_wp_options' );
$scrollkit_id = get_post_meta( $post->ID, '_scroll_id', true );

$state = get_post_meta( $post->ID, '_scroll_state', true );

// different text based on scroll state
$copy = array();

switch($state){
	case 'active':
		$copy['heading'] = "This post is a scroll";
		break;
	case 'inactive':
		$copy['heading'] = "This post has an inactive scroll";
		$copy['activate'] = "Activate";
		break;
	default:
		$copy['heading'] = "This post is not a scroll";
		$copy['activate'] = "Convert";
}

?>

<h4><?php echo $copy['heading'] ?></h4>

<?php if (!empty($scrollkit_id)): ?>
<a href="<?php echo $this->build_edit_url($scrollkit_id) ?>"
		target="_blank"
		class="button">
	Edit
</a>
<?php endif; ?>

<?php if( $state !== 'active' ): ?>
<a href="<?php bloginfo('url') ?>/?scrollkit=activate&p=<?php echo $post->ID ?>"
		class="button js-sk-disable-on-dirty">
	<?php echo $copy['activate'] ?>
</a>


<a href="#TB_inline?height=155&width=300&inlineId=sk-load-scroll"
	class="button thickbox js-sk-disable-on-dirty">
	Duplicate Existing Scroll
</a>
<?php else: ?>

<div class="updated">
	<p>
		This post is a scroll.
		<a href="<?php echo $this->build_edit_url($scrollkit_id) ?>" target="_blank">
			Edit this post with Scroll Kit
		</a>
	</p>
</div>

<?php endif ?>

<?php if ( $state === 'active' ): ?>
<a href="<?php bloginfo('url') ?>/?scrollkit=deactivate&p=<?php echo $post->ID ?>"
		title="Turn this back into a normal wordpress post"
		class="button js-sk-disable-on-dirty">
	Dectivate
</a>
<?php endif ?>

<?php if ( !empty( $state ) ): ?>
<a href="<?php bloginfo('url') ?>/?scrollkit=delete&p=<?php echo $post->ID ?>"
		onclick="return confirm('This will permanently delete the scroll associated with this post, are you sure you want to delete it?');"
		title="Permanently deletes the scroll associated with this post"
		class="button js-sk-disable-on-dirty">
	Delete
</a>
<?php endif ?>

<div class="js-sk-enable-on-dirty" style="visibility: hidden;">
	<p>Save this post to activate Scroll Kit features</p>
</div>

<?php if (WP_DEBUG === true): ?>
<pre>
DEBUG
_scroll_id: <?php echo get_post_meta( $post->ID, '_scroll_id', true ); ?>

_scroll_state: <?php echo get_post_meta( $post->ID, '_scroll_state', true ); ?>
</pre>
<?php endif ?>

<script>
	(function(){
		var $ = jQuery
			, postStatus = "<?php echo get_post_status() ?>";

		isPostDirty = function(){
			debugger
			if (postStatus === 'auto-draft')
				return true;

			var mce = typeof(tinymce) != 'undefined' ? tinymce.activeEditor : false, title, content;

			if ( mce && !mce.isHidden() ) {
				return mce.isDirty();
			} else {
				if ( fullscreen && fullscreen.settings.visible ) {
					title = $('#wp-fullscreen-title').val() || '';
					content = $("#wp_mce_fullscreen").val() || '';
				} else {
					title = $('#post #title').val() || '';
					content = $('#post #content').val() || '';
				}

				return ( ( title || content ) && title + content != autosaveLast );
			}
		}

		var disableIfDirty = function() {
			if ( isPostDirty() ) {
				$('.js-sk-disable-on-dirty').addClass('button-disabled');
				$('.js-sk-enable-on-dirty').css('visibility', 'visible');
			}
		}

		$('#title, #content').on('keydown', disableIfDirty);

		// hook into the tiny mce iframe's iframe that lives within
		// #content_ifr
		$(window).load(function() {
			$('#content_ifr').contents().on('keydown', disableIfDirty);
		});

		disableIfDirty();

	})();
</script>
