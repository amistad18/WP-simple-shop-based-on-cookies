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

			// js and ajax
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_js' ) );
			add_action( 'wp_ajax_wpss_add_to_order', array( $this, 'ajax_add_to_order' ));
			add_action( 'wp_ajax_wpss_remove_from_order', array( $this, 'ajax_remove_from_order' ));
			add_action( 'wp_ajax_send_order', array( $this, 'ajax_send_order' ));

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

		/**
		* Method for managing plugins cookie
		*
		* Allows to update cookie, delete single product or delete cookie
		*
		* @param $product_id - (int) Product ID
		* 		 $quantity - (int) Product quantity
		* 		 $delete - (bool) If true deletes single product from order
		* 		 $delete_all - (bool) If true deletes cookie
		*
		* @since 0.1
		* @returns bool - if setcookie() successfully runs, it will return true
		*/
		private function update_plugin_cookie( $product_id, $quantity, $delete = false, $delete_all = false ){

			$site_url = parse_url( site_url() );
			$domain = $site_url['host'];
			$cookie = array();

			if( isset( $_COOKIE['wp_simple_shop'] ) ){

				$cookie = maybe_unserialize( $_COOKIE['wp_simple_shop'] );

				if( !empty( $quantity ) && !empty( $cookie[$product_id] ) ){
					$quantity = $quantity + absint( $cookie[$product_id] );
				}

			}

			if( !empty( $product_id ) && !empty( $quantity ) ){
				$cookie[$product_id] = $quantity;
			}

			if( $delete ){
				unset( $cookie[$product_id] );
			}

			if( $delete_all ){
				unset( $cookie );
			}

			$value = maybe_serialize( $cookie );

			return setcookie( 'wp_simple_shop', $value, strtotime( '+30 days' ), '/', $domain );

		}

		/**
		* Includes plugin's JS file on website front-end
		*
		* Registers and enqueues plugin JS file
		* Adds nonces as data variable for use in javascript code
		*
		* @since 0.1
		*/
		public function enqueue_frontend_js(){
			wp_register_script( 'wp_simple_shop_js', plugins_url( 'js/wp_simple_shop.js', __FILE__ ), array ( 'jquery' ), date( "mdGis", filemtime( plugin_dir_path( __FILE__ ) . 'js/wp_simple_shop.js' )), false );
			wp_enqueue_script( 'wp_simple_shop_js' );
			wp_localize_script( 'wp_simple_shop_js', 'data', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce_add_to_order' => wp_create_nonce( 'add_to_order' ),
				'nonce_remove_from_order' => wp_create_nonce( 'remove_from_order' ),
				'nonce_send_order' => wp_create_nonce( 'send_order' )
			));

		}

		/**
		* Callback for ajax action to add product to order
		*
		* It checks the nonce, checks if product_id is set, checks if quantity is set, then pases product_id and quantity to update_plugin_cookie method
		*
		* @since 0.1
		* @returns json - returns json object with message and response status
		*/
		public function ajax_add_to_order(){
			try {
				if( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'add_to_order' )){
					if( isset( $_POST['product_id'] ) && !empty( $_POST['product_id'] )){
						if( isset( $_POST['quantity'] ) && !empty( $_POST['quantity'] )){

							$cookie = $this->update_plugin_cookie( absint( $_POST['product_id'] ), absint( $_POST['quantity'] ) );

							if( $cookie != false ){

								$this->return_json_msg( 'Product successfully added to order.' );

							} else {
								throw new Exception('Adding product to order failed. Please contact website administrator.');
							}
						} else {
							throw new Exception('No quantity specified');
						}
					} else {
						throw new Exception('No Product ID specified');
					}
				} else {
					throw new Exception('You don\'t have access to this action');
				}
			} catch( Exception $e ){
				$this->return_json_msg($e->getMessage(), 500);
			}

			wp_die();
		}

		/**
		* Callback for ajax action to remove product from order
		*
		* It checks the nonce, checks if product_id is set, then pases product_id to update_plugin_cookie method
		*
		* @since 0.1
		* @returns json - returns json object with message and response status
		*/
		public function ajax_remove_from_order(){
			try {
				if( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'remove_from_order' )){
					if( isset( $_POST['product_id'] ) && !empty( $_POST['product_id'] )){

						$cookie = $this->update_plugin_cookie( absint( $_POST['product_id'] ), null, true );

						if( $cookie != false ){

							$this->return_json_msg( 'Product successfully removed from order.' );

						} else {
							throw new Exception('Removing product failed. Please contact website administrator.');
						}

					} else {
						throw new Exception('No Product ID specified');
					}
				} else {
					throw new Exception('You don\'t have access to this action');
				}
			} catch( Exception $e ){
				$this->return_json_msg($e->getMessage(), 500);
			}

			wp_die();
		}

		/**
		* Callback for ajax action to send request for sale offer
		*
		* It checks the nonce, then send email with send_order method, if successfull - delete the cookie
		*
		* @since 0.1
		* @returns json - returns json object with message and response status
		*/
		public function ajax_send_order(){
			try {
				if( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'send_order' )){

					$response = $this->send_order();

					if( $response != false ){

						$this->update_plugin_cookie( null, null, false, true );
						$this->return_json_msg( 'Order successfully sent.' );

					} else {
						throw new Exception('Sending order failed. Please contact website administrator.');
					}

				} else {
					throw new Exception('You don\'t have access to this action');
				}
			} catch( Exception $e ){
				$this->return_json_msg($e->getMessage(), 500);
			}

			wp_die();
		}

		/**
		* Method for sending email to shop owner with request for sale offer
		*
		* @since 0.2
		*
		* @returns	bool - whether the email contents were sent successfully
		*/
		private function send_order(){
			return true;
		}

		/**
		* Method for returning JSON responces for JS Ajax calls
		*
		* @since 0.1
		* @returns json - echos json object with message and response status
		*/
		public function return_json_msg( $message, $status = 200 ){
			header('Content-Type: application/json');
			$response = new stdClass();
			$response->status = $status;
			$response->msg = $message;
			echo json_encode($response);
			wp_die();
		}

	}
}

add_action( 'plugins_loaded', array( 'WPSimpleShop', 'instance' ) );