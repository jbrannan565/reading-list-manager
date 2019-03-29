<?php
/*
Plugin Name: Reading Lists
Description: A simple plugin for managing reading lists 
Version: 0.1.0
Author: Jared Brannan
Text Domain: reading-list-manager
Domain Path: /reading_list_manager
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Create Custom Post Type: Book
add_action( 'init', 'codex_book_init' );
function codex_book_init() {
	$labels = array(
		'name'               => _x( 'Books', 'post type general name', 'your-plugin-textdomain' ),
		'singular_name'      => _x( 'Book', 'post type singular name', 'your-plugin-textdomain' ),
		'menu_name'          => _x( 'Books', 'admin menu', 'your-plugin-textdomain' ),
		'name_admin_bar'     => _x( 'Book', 'add new on admin bar', 'your-plugin-textdomain' ),
		'add_new'            => _x( 'Add New', 'book', 'your-plugin-textdomain' ),
		'add_new_item'       => __( 'Add New Book', 'your-plugin-textdomain' ),
		'new_item'           => __( 'New Book', 'your-plugin-textdomain' ),
		'edit_item'          => __( 'Edit Book', 'your-plugin-textdomain' ),
		'view_item'          => __( 'View Book', 'your-plugin-textdomain' ),
		'all_items'          => __( 'All Books', 'your-plugin-textdomain' ),
		'search_items'       => __( 'Search Books', 'your-plugin-textdomain' ),
		'parent_item_colon'  => __( 'Parent Books:', 'your-plugin-textdomain' ),
		'not_found'          => __( 'No books found.', 'your-plugin-textdomain' ),
		'not_found_in_trash' => __( 'No books found in Trash.', 'your-plugin-textdomain' )
	);

	$args = array(
		'labels'             => $labels,
        'description'        => __( 'Description.', 'your-plugin-textdomain' ),
        'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'book' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
        'menu_position'      => null,
        'menu_icon'          => 'dashicons-book',
		'supports'           => array( 'title', 'author', 'thumbnail', 'excerpt', 'custom-fields' )
	);

	register_post_type( 'book', $args );
}

// Shortcode to access books: [mp_books]
add_shortcode( 'mp_books', 'format_books');
function format_books() {
	$args = array( 
		'post_type'   => 'book',
        'meta_key'    => 'priority',
		'orderby'     => 'meta_value_num',
		'order'       => 'ASC',
		'numberposts' => -1,
	);
	$books = get_posts( $args );
	$html = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">';
	$userid = wp_get_current_user()->data->ID;
	foreach( $books as $book ) {
		if ( $book->post_type == 'book' && $book->post_author == $userid ) {
			$rating = get_google_rating( $book->post_title );
			$priority = get_post_field( 'priority', $book->ID );
			$author = get_post_field( 'author', $book->ID );
			$html .= '<div class="mp_book"><h4>';
			if ( current_user_can( 'delete_posts', $book->ID ))
				$html .= ' <a class="f_button" href="' . get_delete_post_link( $book->ID ) . '">X</a> ';
			$html .= $priority; 
			$html .= '. <a style="text-decoration: none;" href="';
			$html .= get_permalink( $book->ID ) . '">' . $book->post_title;
			$html .= '</a> <small>' . $author ;
			$html .= '</small> ';
			if ( $rating['rating'] && $rating['rating_count']) {
				for ( $i = round( $rating['rating'] ); $i > 0; $i-- )
					$html .= '<span class="fa fa-star checked"></span>';
				for ( $i = 5 - round( $rating['rating'] ); $i > 0; $i-- )
					$html .= '<span class="fa fa-star"></span>';
				//$html .= ' Rating Count: '. $rating['rating_count'] . '</p>';
			}
			$html .= '</h4>';
			if ( $book->post_excerpt )
				$html .= '<p id="mp_excerpt">- ' . $book->post_excerpt . '</p>';
			$html .= '</div>';
		}
	}
	return $html;
}

// filter books out of main post view and by current user
add_action( 'pre_get_posts', 'add_custom_viewer_to_query' );
function add_custom_viewer_to_query( $query ) {
	$userid = wp_get_current_user()->data->ID;
    if ( !is_admin() && !is_home() ) {
		$query->set( 'post_type', array( 'post', 'book' ) );
	}
	if ( is_admin() ) {
		$query->set( 'author', $userid );
	}
}

// register style sheets
add_action( 'wp_enqueue_scripts', 'assets' );
function assets() {
	wp_register_style( 'learn_plugin', plugins_url('learn_plugin/styles.css') );
	wp_enqueue_style( 'learn_plugin' );
}

// gets book rating from google books api
function get_google_rating( $booktitle ) {
	$booktitle = addslashes( $booktitle );
	$url = "https://www.googleapis.com/books/v1/volumes?q=" . str_replace(' ', '+', $booktitle );
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	$result=curl_exec($ch);
	curl_close($ch);
	$i = 0;
	$data = json_decode($result, true)['items'][$i]['volumeInfo'];
	while ( $data['averageRating'] == null && $data != null ) {
		$data = json_decode($result, true)['items'][$i]['volumeInfo'];
		$i = $i + 1;
	}
	$ret_data = array(
		'rating' => $data['averageRating'],
		'rating_count' => $data['ratingsCount']
	);
	return $ret_data;
}
