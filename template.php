<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>

<style>
  /*<![CDATA[*/
    #edge_bleed {margin:-8px;position:0px;padding:0px;}
  /*]]>*/
</style>
<div id = "edge_bleed">
<?php echo get_post_meta($post->ID, 'scroll', true); ?>
</div>					
<?php endwhile; ?>