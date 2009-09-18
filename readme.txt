=== Plugin Name ===
Contributors: boonebgorges, cuny-academic-commons
Tags: wpmu, sitewide tags, tags, post tags
Requires at least: WPMU 2.8
Tested up to: WPMU 2.8.4a
Stable tag: trunk

Creates "choose from most popular sitewide tags" function on post edit screen

== Description ==

This plugin inserts a link into the Post Tags box on the post edit screen that allows users to select from the most popular tags on the entire WPMU install.

Developed for the CUNY Academic Commons. Visit http://commons.gc.cuny.edu to learn more about this bitchen project.

== Installation ==

This plugin is only compatible with WPMU 2.8+! Donncha's Sitewide Tags plugin is required as well.

1. Drop the plugin file (sitewide-tag-suggestion.php) into mu-plugins/ 
1. Open wp-admin/admin-ajax.php. (Back it up first! You're hacking the core!) Find the line that says
	case 'add-comment' :
Add the following stuff, ***making sure to change the number following 
	'blog_id' =>
to the numerical blog id of your Sitewide Tags blog.***


`	// Additions for sitewide tag suggestions begin here

	case 'get-tagcloud2' :
	if ( !current_user_can( 'edit_posts' ) )
		die('-1');

	if ( isset($_POST['tax']) )
		$taxonomy = sanitize_title($_POST['tax']);
	else
		die('0');

	$tags = get_terms_custom( $taxonomy, array( 'number' => 45, 'orderby' => 'count', 'order' => 'DESC', 'blog_id' => 28 ) );

	if ( empty( $tags ) )
		die( __('No tags found!') );

	if ( is_wp_error($tags) )
		die($tags->get_error_message());

	foreach ( $tags as $key => $tag ) {
		$tags[ $key ]->link = '#';
		$tags[ $key ]->id = $tag->term_id;
	}

	// We need raw tag names here, so don't filter the output
	$return = wp_generate_tag_cloud( $tags, array('filter' => 0) );

	if ( empty($return) )
		die('0');

	echo $return;

	exit;
	break;

	// End additions`
1. Open wp-admin/edit-form-advanced.php. (Have you backed it up yet?) Find the line that says
`<p class="tagcloud-link hide-if-no-js"><a href="#titlediv" class="tagcloud-link" id="link-<?php echo $tax_name; ?>"><?php printf( __('Choose from the most used tags in %s'), $box['title'] ); ?></a></p>`
First, if you'd like to change this line's text to more clearly distinguish between the current blog's tags and the sitewide tags, you might consider replacing that line with the following:
`<p class="tagcloud-link hide-if-no-js"><a href="#titlediv" class="tagcloud-link" id="link-<?php echo $tax_name; ?>"><?php printf( __('Choose from the most used tags on this blog'), $box['title'] ); ?></a></p>`
After that line, add the following markup, which creates a link for your sitewide tags. You'll want to change my link text ("...all Commons blogs") to reflect your own site:
`<p class="tagcloud2-link hide-if-no-js"><a href="#titlediv" class="tagcloud2-link" id="link2-<?php echo $tax_name; ?>"><?php printf( __('Choose from the most used tags in all Commons blogs'), $box['title'] ); ?></a></p>`

*/

== Frequently Asked Questions ==

= Why do I have to hack the core so much to make the plugin work? =

Mostly because I am terrible at plugin programming. Suck it up. Remember, though, that these hacks will have to be reimplemented if you upgrade to a new version of WPMU. 


== Changelog ==

= 0.1 =
* Initial release
