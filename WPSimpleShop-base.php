<?php

/*
Plugin Name: WP Simple Shop based on cookies
Description: Simple shop for request sales offers for selected products. Without registration, based on cookies.
Version:     0.1
Author:      amistad18
Author URI:  http://amistad18.net
License:     GPL v2 or later
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

/**
 * Main plugin class.
 */
if ( !class_exists( 'WPSimpleShop' ) ) {

	class WPSimpleShop {

		/**
		* WPSimpleShop $instance
		* @var WPSimpleShop
		* @since 0.1
		*/
		public static $instance = NULL;

		/**
		* Custom post type slug name
		* @var string
		* @since 0.1
		*/
		public static $cpt_slug = 'produkty';

		/**
		* Custom taxonomy slug name
		* @var string
		* @since 0.1
		*/
		public static $taxonomy_slug = 'rodzaj';

		public function __construct() {
			// creata custom post type
			add_action( 'init', array( $this, 'create_custom_post_type' ) );
			// filter custom post type messages
			add_filter( 'post_updated_messages', array( $this, 'cpt_updated_messages') );
			// create custom taxonomies
			add_action( 'init', array( $this, 'create_taxonomy' ) );

		}

		/**
		* Get active instance
		* @since 0.1
		* @return WPSimpleShop instance
		*/
		public static function instance() {

			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		* Registering custom post type
		*
		* @since 0.1
		*/
		public function create_custom_post_type() {

			$labels = array(
				'name'					=> 'Produkty',
				'singular_name'			=> 'Produkt',
				'menu_name'				=> 'Produkty',
				'name_admin_bar'		=> 'Produkt',
				'add_new'				=> 'Dodaj Nowy',
				'add_new_item'			=> 'Dodaj Nowy Produkt',
				'new_item'				=> 'Nowy Produkt',
				'edit_item'				=> 'Edytuj Produkt',
				'view_item'				=> 'Wyświetl Produkt',
				'all_items'				=> 'Wszyskie Produkty',
				'search_items'			=> 'Przeszukaj Produkty',
				'parent_item_colon'		=> 'Rodzic Produkty:',
				'not_found'				=> 'Nie znaleziono produktów.',
				'not_found_in_trash'	=> 'Nie znaleziono produktów w koszu.'
			);

			$args = array(
				'labels'				=> $labels,
				'description'			=> 'Produkty w ofercie',
				'public'				=> true,
				'publicly_queryable'	=> true,
				'show_ui'				=> true,
				'show_in_menu'			=> true,
				'query_var'				=> true,
				'rewrite'				=> array( 'slug' => 'produkty' ),
				'capability_type'		=> 'post',
				'has_archive'			=> true,
				'hierarchical'			=> false,
				'menu_position'			=> 58,
				'menu_icon'				=> 'dashicons-cart',	
				'supports'				=> array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
				'taxonomies'			=> array( self::$taxonomy_slug )
			);

			register_post_type( self::$cpt_slug, $args );

		}

		/**
		* Registering custom taxonomy
		*
		* @since 0.1
		*/
		public function create_taxonomy(){

			$labels = array(
				'name'				=> 'Kategoria',
				'singular_name'		=> 'Kategoria',
				'search_items'		=> 'Przeszukaj Kategorie',
				'all_items'			=> 'Wszystkie Kategorie',
				'parent_item'		=> 'Rodzic Kategorii',
				'parent_item_colon'	=> 'Rodzic Kategorii:',
				'edit_item'			=> 'Edytuj Kategorię',
				'update_item'		=> 'Aktualizuj Kategorię',
				'add_new_item'		=> 'Dodaj Nową Kategorię',
				'new_item_name'		=> 'Nazwa Nowej Kategorii',
				'menu_name'			=> 'Kategorie',
			);

			$args = array(
				'hierarchical'			=> true,
				'labels'				=> $labels,
				'public'				=> true,
				'show_admin_column'		=> true,
				'update_count_callback'	=> '_update_post_term_count',
				'query_var'				=> true,
				'rewrite'				=> array( 'slug' => self::$taxonomy_slug ),
			);

			register_taxonomy( self::$taxonomy_slug, self::$cpt_slug, $args );

		}

		/**
		* Custom post type custom messages
		*
		* @since 0.1
		*/
		public function cpt_updated_messages( $messages ) {
			$post = get_post();
			$post_type = get_post_type( $post );
			$post_type_object = get_post_type_object( $post_type );

			$messages[ self::$cpt_slug ] = array(
				1 => 'Produkt zaktualizowany.',
				2 => 'Własne pole zaktualizowane.',
				3 => 'Własne pole usunięte.',
				4 => 'Produkt zaktualizowany.',
				5 => isset( $_GET['revision'] ) ? sprintf( 'Produkt przywrócony do wersji z %s', wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6 => 'Produkt opublikowany.',
				7 => 'Produkt zapisany.',
				8 => 'Produkt wysłany.',
				9 => sprintf(
					'Produkt zaplanowany na: <strong>%1$s</strong>.',
					date_i18n( 'M j, Y @ G:i', strtotime( $post->post_date ) )
				),
				10 => 'Szkic produktu zaktualizowany.'
			);

			if ( $post_type_object->publicly_queryable ) {
				$permalink = get_permalink( $post->ID );

				$view_link = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), 'Wyświetl Produkt' );
				$messages[ $post_type ][1] .= $view_link;
				$messages[ $post_type ][6] .= $view_link;
				$messages[ $post_type ][9] .= $view_link;

				$preview_permalink = add_query_arg( 'preview', 'true', $permalink );
				$preview_link = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), 'Podgląd Produktu' );
				$messages[ $post_type ][8]  .= $preview_link;
				$messages[ $post_type ][10] .= $preview_link;
			}

			return $messages;
		}

	}
}

add_action( 'plugins_loaded', array( 'WPSimpleShop', 'instance' ) );