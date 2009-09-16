<?php

/*
Plugin Name: Sitewide Tag Suggestion
Plugin URI: http://dev.commons.gc.cuny.edu
Description: Creates "choose from most popular sitewide tags" function on post edit screen
Version: 0.1
Author: Boone Gorges
Author URI: http://teleogistic.net
*/



add_action('admin_print_scripts', 'sitewide_tag_suggestion' );

function sitewide_tag_suggestion() {

 wp_print_scripts('jquery-ui-core');
 
 ?>

<script type="text/javascript">
//<![CDATA[
var tagCloud2;
(function($){
	tagCloud2 = {
		init : function() {
			$('.tagcloud2-link').click(function(){
				tagCloud2.get($(this).attr('id'));
				$(this).unbind().click(function(){
					$(this).siblings('.the-tagcloud').toggle();
					return false;
				});
				return false;
			});
		},

		get : function(id) {
			var tax = id.substr(id.indexOf('-')+1);

			$.post(ajaxurl, {'action':'get-tagcloud2','tax':tax}, function(r, stat) {
				if ( 0 == r || 'success' != stat )
					r = wpAjax.broken;

				r = $('<p id="tagcloud2-'+tax+'" class="the-tagcloud">'+r+'</p>');
				$('a', r).click(function(){
					var id = $(this).parents('p').attr('id');
					tag_flush_to_text(id.substr(id.indexOf('-')+1), this);
					return false;
				});

				$('#'+id).after(r);
			});
		}
	};

	$(document).ready(function(){tagCloud2.init();});
})(jQuery);



//]]>


</script>
<?php
}



function &get_terms_custom($taxonomies, $args = '') {
	global $wpdb;
	$empty_array = array();

	$single_taxonomy = false;
	if ( !is_array($taxonomies) ) {
		$single_taxonomy = true;
		$taxonomies = array($taxonomies);
	}

	foreach ( (array) $taxonomies as $taxonomy ) {
		if ( ! is_taxonomy($taxonomy) ) {
			$error = & new WP_Error('invalid_taxonomy', __('Invalid Taxonomy'));
			return $error;
		}
	}

	$in_taxonomies = "'" . implode("', '", $taxonomies) . "'";

	$defaults = array('orderby' => 'name', 'order' => 'ASC',
		'hide_empty' => true, 'exclude' => '', 'exclude_tree' => '', 'include' => '',
		'number' => '', 'fields' => 'all', 'slug' => '', 'parent' => '',
		'hierarchical' => true, 'child_of' => 0, 'get' => '', 'name__like' => '',
		'pad_counts' => false, 'offset' => '', 'search' => '', 'blog_id' => '');
	$args = wp_parse_args( $args, $defaults );
	$args['number'] = absint( $args['number'] );
	$args['offset'] = absint( $args['offset'] );
	if ( !$single_taxonomy || !is_taxonomy_hierarchical($taxonomies[0]) ||
		'' !== $args['parent'] ) {
		$args['child_of'] = 0;
		$args['hierarchical'] = false;
		$args['pad_counts'] = false;
	}

	if ( 'all' == $args['get'] ) {
		$args['child_of'] = 0;
		$args['hide_empty'] = 0;
		$args['hierarchical'] = false;
		$args['pad_counts'] = false;
	}
	extract($args, EXTR_SKIP);

	if ( $child_of ) {
		$hierarchy = _get_term_hierarchy($taxonomies[0]);
		if ( !isset($hierarchy[$child_of]) )
			return $empty_array;
	}

	if ( $parent ) {
		$hierarchy = _get_term_hierarchy($taxonomies[0]);
		if ( !isset($hierarchy[$parent]) )
			return $empty_array;
	}

	// $args can be whatever, only use the args defined in defaults to compute the key
	$filter_key = ( has_filter('list_terms_exclusions') ) ? serialize($GLOBALS['wp_filter']['list_terms_exclusions']) : '';
	$key = md5( serialize( compact(array_keys($defaults)) ) . serialize( $taxonomies ) . $filter_key );
	$last_changed = wp_cache_get('last_changed', 'terms');
	if ( !$last_changed ) {
		$last_changed = time();
		wp_cache_set('last_changed', $last_changed, 'terms');
	}
	$cache_key = "get_terms:$key:$last_changed";
	$cache = wp_cache_get( $cache_key, 'terms' );
	if ( false !== $cache ) {
		$cache = apply_filters('get_terms', $cache, $taxonomies, $args);
		return $cache;
	}

	$_orderby = strtolower($orderby);
	if ( 'count' == $_orderby )
		$orderby = 'tt.count';
	else if ( 'name' == $_orderby )
		$orderby = 't.name';
	else if ( 'slug' == $_orderby )
		$orderby = 't.slug';
	else if ( 'term_group' == $_orderby )
		$orderby = 't.term_group';
	elseif ( empty($_orderby) || 'id' == $_orderby )
		$orderby = 't.term_id';

	$orderby = apply_filters( 'get_terms_orderby', $orderby, $args );

	$where = '';
	$inclusions = '';
	if ( !empty($include) ) {
		$exclude = '';
		$exclude_tree = '';
		$interms = preg_split('/[\s,]+/',$include);
		if ( count($interms) ) {
			foreach ( (array) $interms as $interm ) {
				if (empty($inclusions))
					$inclusions = ' AND ( t.term_id = ' . intval($interm) . ' ';
				else
					$inclusions .= ' OR t.term_id = ' . intval($interm) . ' ';
			}
		}
	}

	if ( !empty($inclusions) )
		$inclusions .= ')';
	$where .= $inclusions;

	$exclusions = '';
	if ( ! empty( $exclude_tree ) ) {
		$excluded_trunks = preg_split('/[\s,]+/',$exclude_tree);
		foreach( (array) $excluded_trunks as $extrunk ) {
			$excluded_children = (array) get_terms($taxonomies[0], array('child_of' => intval($extrunk), 'fields' => 'ids'));
			$excluded_children[] = $extrunk;
			foreach( (array) $excluded_children as $exterm ) {
				if ( empty($exclusions) )
					$exclusions = ' AND ( t.term_id <> ' . intval($exterm) . ' ';
				else
					$exclusions .= ' AND t.term_id <> ' . intval($exterm) . ' ';

			}
		}
	}
	if ( !empty($exclude) ) {
		$exterms = preg_split('/[\s,]+/',$exclude);
		if ( count($exterms) ) {
			foreach ( (array) $exterms as $exterm ) {
				if ( empty($exclusions) )
					$exclusions = ' AND ( t.term_id <> ' . intval($exterm) . ' ';
				else
					$exclusions .= ' AND t.term_id <> ' . intval($exterm) . ' ';
			}
		}
	}

	if ( !empty($exclusions) )
		$exclusions .= ')';
	$exclusions = apply_filters('list_terms_exclusions', $exclusions, $args );
	$where .= $exclusions;

	if ( !empty($slug) ) {
		$slug = sanitize_title($slug);
		$where .= " AND t.slug = '$slug'";
	}

	if ( !empty($name__like) )
		$where .= " AND t.name LIKE '{$name__like}%'";

	if ( '' !== $parent ) {
		$parent = (int) $parent;
		$where .= " AND tt.parent = '$parent'";
	}

	if ( $hide_empty && !$hierarchical )
		$where .= ' AND tt.count > 0';

	// don't limit the query results when we have to descend the family tree
	if ( ! empty($number) && ! $hierarchical && empty( $child_of ) && '' === $parent ) {
		if( $offset )
			$limit = 'LIMIT ' . $offset . ',' . $number;
		else
			$limit = 'LIMIT ' . $number;

	} else
		$limit = '';

	if ( !empty($search) ) {
		$search = like_escape($search);
		$where .= " AND (t.name LIKE '%$search%')";
	}
	
	

	$selects = array();
	if ( 'all' == $fields )
		$selects = array('t.*', 'tt.*');
	else if ( 'ids' == $fields )
		$selects = array('t.term_id', 'tt.parent', 'tt.count');
	else if ( 'names' == $fields )
		$selects = array('t.term_id', 'tt.parent', 'tt.count', 't.name');
        $select_this = implode(', ', apply_filters( 'get_terms_fields', $selects, $args ));

	
	if ( !empty($blog_id) ) {
		$thistermsdb = 'wp_' . $blog_id . '_terms';
		$thistaxdb = 'wp_' . $blog_id . '_term_taxonomy';
		$query = "SELECT $select_this FROM $thistermsdb AS t INNER JOIN $thistaxdb AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN ($in_taxonomies) $where ORDER BY $orderby $order $limit"; }
	else
		$query = "SELECT $select_this FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN ($in_taxonomies) $where ORDER BY $orderby $order $limit";

	$terms = $wpdb->get_results($query);
	if ( 'all' == $fields ) {
		update_term_cache($terms);
	}

	if ( empty($terms) ) {
		wp_cache_add( $cache_key, array(), 'terms' );
		$terms = apply_filters('get_terms', array(), $taxonomies, $args);
		return $terms;
	}

	if ( $child_of ) {
		$children = _get_term_hierarchy($taxonomies[0]);
		if ( ! empty($children) )
			$terms = & _get_term_children($child_of, $terms, $taxonomies[0]);
	}

	// Update term counts to include children.
	if ( $pad_counts && 'all' == $fields )
		_pad_term_counts($terms, $taxonomies[0]);

	// Make sure we show empty categories that have children.
	if ( $hierarchical && $hide_empty && is_array($terms) ) {
		foreach ( $terms as $k => $term ) {
			if ( ! $term->count ) {
				$children = _get_term_children($term->term_id, $terms, $taxonomies[0]);
				if( is_array($children) )
					foreach ( $children as $child )
						if ( $child->count )
							continue 2;

				// It really is empty
				unset($terms[$k]);
			}
		}
	}
	reset ( $terms );

	$_terms = array();
	if ( 'ids' == $fields ) {
		while ( $term = array_shift($terms) )
			$_terms[] = $term->term_id;
		$terms = $_terms;
	} elseif ( 'names' == $fields ) {
		while ( $term = array_shift($terms) )
			$_terms[] = $term->name;
		$terms = $_terms;
	}

	if ( 0 < $number && intval(@count($terms)) > $number ) {
		$terms = array_slice($terms, $offset, $number);
	}

	wp_cache_add( $cache_key, $terms, 'terms' );

	$terms = apply_filters('get_terms', $terms, $taxonomies, $args);
	
	
	return $terms;
}




?>