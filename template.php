<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>

<style>
  /*<![CDATA[*/
    #edge_bleed {margin:-8px;position:0px;padding:0px;}
  /*]]>*/
</style>
<div id = "edge_bleed">
<pre style="border: 1px red dotted;">
<?php echo get_post_meta($post->ID, '_scroll_js', true); ?>

<?php echo htmlspecialchars(get_post_meta($post->ID, '_scroll_fonts', true)); ?>
</pre>
<?php echo get_post_meta($post->ID, '_scroll_content', true); ?>
</div>
<?php endwhile; ?>
