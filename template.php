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

?>
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