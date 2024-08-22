<?php
/**
 * Description: Generate breadcrumbs for WordPress.
 * Author:      Mahdi Yazdani
 * Author URI:  https://mahdiyazdani.com
 * Version:     1.0.0
 * Text Domain: wp-breadcrumbs
 * License:     GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Note: Underlying code is mostly based on WooCommerce Breadcrumbs.
 * 
 * @package WP_Breadcrumbs
 */

namespace Seo_Ready\Breadcrumbs;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Class Trails.
 */
class Trails {

	/**
	 * Breadcrumb trail.
	 * 
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $crumbs = array();

	/**
	 * Add a crumb so we don't get lost.
	 * 
	 * @since 1.0.0
	 *
	 * @param string $name Name.
	 * @param string $link Link.
	 * 
	 * @return void
	 */
	public function add_crumb( $name, $link = '' ) {

		$this->crumbs[] = array(
			wp_strip_all_tags( $name ),
			$link,
		);
	}

	/**
	 * Reset crumbs.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	public function reset() {

		$this->crumbs = array();
	}

	/**
	 * Get the breadcrumb.
	 * 
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_breadcrumb() {

		return $this->crumbs;
	}

	/**
	 * Generate breadcrumb trail.
	 * 
	 * @since 1.0.0
	 *
	 * @return array of breadcrumbs
	 */
	public function generate() {

		$conditionals = array(
			'is_home',
			'is_404',
			'is_attachment',
			'is_single',
			'is_page',
			'is_post_type_archive',
			'is_category',
			'is_tag',
			'is_author',
			'is_date',
			'is_tax',
		);

		if ( $this->is_wc_installed() ) {
			$conditions[] = 'is_product_category';
			$conditions[] = 'is_product_tag';
			$conditions[] = 'is_shop';
		}

		if ( ( ! is_front_page() && ! ( is_post_type_archive() && intval( get_option( 'page_on_front' ) ) === wc_get_page_id( 'shop' ) ) ) || is_paged() ) {
			foreach ( $conditionals as $conditional ) {
				if ( call_user_func( $conditional ) ) {
					call_user_func( array( $this, 'add_crumbs_' . substr( $conditional, 3 ) ) );
					break;
				}
			}

			$this->search_trail();
			$this->paged_trail();

			return $this->get_breadcrumb();
		}

		return array();
	}

	/**
	 * Prepend the shop page to shop breadcrumbs.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function prepend_shop_page() {

		$permalinks   = wc_get_permalink_structure();
		$shop_page_id = wc_get_page_id( 'shop' );
		$shop_page    = get_post( $shop_page_id );

		// If permalinks contain the shop page in the URI prepend the breadcrumb with shop.
		if ( $shop_page_id && $shop_page && isset( $permalinks['product_base'] ) && strstr( $permalinks['product_base'], '/' . $shop_page->post_name ) && intval( get_option( 'page_on_front' ) ) !== $shop_page_id ) {
			$this->add_crumb( get_the_title( $shop_page ), get_permalink( $shop_page ) );
		}
	}

	/**
	 * Is home trail..
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_home() {

		$this->add_crumb( single_post_title( '', false ) );
	}

	/**
	 * 404 trail.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_404() {

		$this->add_crumb( __( 'Error 404', 'wp-breadcrumbs' ) );
	}

	/**
	 * Attachment trail.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_attachment() {
		
		global $post;

		$this->add_crumbs_single( $post->post_parent, get_permalink( $post->post_parent ) );
		$this->add_crumb( get_the_title(), get_permalink() );
	}

	/**
	 * Single post trail.
	 * 
	 * @since 1.0.0
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $permalink Post permalink.
	 * 
	 * @return void
	 */
	protected function add_crumbs_single( $post_id = 0, $permalink = '' ) {

		if ( ! $post_id ) {
			global $post;
		} else {
			$post = get_post( $post_id );
		}

		if ( ! $permalink ) {
			$permalink = get_permalink( $post );
		}

		if ( 'product' === get_post_type( $post ) ) {
			$this->prepend_shop_page();

			$terms = wc_get_product_terms(
				$post->ID,
				'product_cat',
				array(
					'orderby' => 'parent',
					'order'   => 'DESC',
				)
			);

			if ( $terms ) {
				$main_term = $terms[0];
				$this->term_ancestors( $main_term->term_id, 'product_cat' );
				$this->add_crumb( $main_term->name, get_term_link( $main_term ) );
			}
		} elseif ( 'post' !== get_post_type( $post ) ) {
			$post_type = get_post_type_object( get_post_type( $post ) );

			if ( ! empty( $post_type->has_archive ) ) {
				$this->add_crumb( $post_type->labels->singular_name, get_post_type_archive_link( get_post_type( $post ) ) );
			}
		} else {
			// Get posts page ID.
			$posts_page_id = get_option( 'page_for_posts' );

			if ( $posts_page_id ) {
				$this->add_crumb( get_the_title( $posts_page_id ), get_permalink( $posts_page_id ) );
			}

			$cat = current( get_the_category( $post ) );
			if ( $cat ) {
				$this->term_ancestors( $cat->term_id, 'category' );
				$this->add_crumb( $cat->name, get_term_link( $cat ) );
			}
		}

		$this->add_crumb( get_the_title( $post ), $permalink );
	}

	/**
	 * Page trail.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_page() {

		global $post;

		if ( $post->post_parent ) {
			$parent_crumbs = array();
			$parent_id     = $post->post_parent;

			while ( $parent_id ) {
				$page            = get_post( $parent_id );
				$parent_id       = $page->post_parent;
				$parent_crumbs[] = array( get_the_title( $page->ID ), get_permalink( $page->ID ) );
			}

			$parent_crumbs = array_reverse( $parent_crumbs );

			foreach ( $parent_crumbs as $crumb ) {
				$this->add_crumb( $crumb[0], $crumb[1] );
			}
		}

		$this->add_crumb( get_the_title(), get_permalink() );
		$this->endpoint_trail();
	}

	/**
	 * Product category trail.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_product_category() {

		$current_term = $GLOBALS['wp_query']->get_queried_object();

		$this->prepend_shop_page();
		$this->term_ancestors( $current_term->term_id, 'product_cat' );
		$this->add_crumb( $current_term->name, get_term_link( $current_term, 'product_cat' ) );
	}

	/**
	 * Product tag trail.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_product_tag() {

		$current_term = $GLOBALS['wp_query']->get_queried_object();

		$this->prepend_shop_page();

		/* translators: %s: product tag */
		$this->add_crumb( sprintf( __( 'Products tagged &ldquo;%s&rdquo;', 'wp-breadcrumbs' ), $current_term->name ), get_term_link( $current_term, 'product_tag' ) );
	}

	/**
	 * Shop breadcrumb.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_shop() {

		if ( intval( get_option( 'page_on_front' ) ) === wc_get_page_id( 'shop' ) ) {
			return;
		}

		$_name = wc_get_page_id( 'shop' ) ? get_the_title( wc_get_page_id( 'shop' ) ) : '';

		if ( ! $_name ) {
			$product_post_type = get_post_type_object( 'product' );
			$_name             = $product_post_type->labels->name;
		}

		$this->add_crumb( $_name, get_post_type_archive_link( 'product' ) );
	}

	/**
	 * Post type archive trail.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_post_type_archive() {

		$post_type = get_post_type_object( get_post_type() );

		if ( $post_type ) {
			$this->add_crumb( $post_type->labels->name, get_post_type_archive_link( get_post_type() ) );
		}
	}

	/**
	 * Category trail.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_category() {

		$this_category = get_category( $GLOBALS['wp_query']->get_queried_object() );

		if ( 0 !== intval( $this_category->parent ) ) {
			$this->term_ancestors( $this_category->term_id, 'category' );
		}

		$this->add_crumb( single_cat_title( '', false ), get_category_link( $this_category->term_id ) );
	}

	/**
	 * Tag trail.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_tag() {

		$queried_object = $GLOBALS['wp_query']->get_queried_object();

		/* translators: %s: tag name */
		$this->add_crumb( sprintf( __( 'Posts tagged &ldquo;%s&rdquo;', 'wp-breadcrumbs' ), single_tag_title( '', false ) ), get_tag_link( $queried_object->term_id ) );
	}

	/**
	 * Add crumbs for date based archives.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_date() {

		if ( is_year() || is_month() || is_day() ) {
			$this->add_crumb( get_the_time( 'Y' ), get_year_link( get_the_time( 'Y' ) ) );
		}

		if ( is_month() || is_day() ) {
			$this->add_crumb( get_the_time( 'F' ), get_month_link( get_the_time( 'Y' ), get_the_time( 'm' ) ) );
		}

		if ( is_day() ) {
			$this->add_crumb( get_the_time( 'd' ) );
		}
	}

	/**
	 * Add crumbs for taxonomies.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_tax() {

		$this_term = $GLOBALS['wp_query']->get_queried_object();
		$taxonomy  = get_taxonomy( $this_term->taxonomy );

		$this->add_crumb( $taxonomy->labels->name );

		if ( 0 !== intval( $this_term->parent ) ) {
			$this->term_ancestors( $this_term->term_id, $this_term->taxonomy );
		}

		$this->add_crumb( single_term_title( '', false ), get_term_link( $this_term->term_id, $this_term->taxonomy ) );
	}

	/**
	 * Add a breadcrumb for author archives.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function add_crumbs_author() {

		global $author;

		$userdata = get_userdata( $author );

		/* translators: %s: author name */
		$this->add_crumb( sprintf( __( 'Author: %s', 'wp-breadcrumbs' ), $userdata->display_name ) );
	}

	/**
	 * Add crumbs for a term.
	 * 
	 * @since 1.0.0
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * 
	 * @return void
	 */
	protected function term_ancestors( $term_id, $taxonomy ) {

		$ancestors = get_ancestors( $term_id, $taxonomy );
		$ancestors = array_reverse( $ancestors );

		foreach ( $ancestors as $ancestor ) {
			$ancestor = get_term( $ancestor, $taxonomy );

			if ( ! is_wp_error( $ancestor ) && $ancestor ) {
				$this->add_crumb( $ancestor->name, get_term_link( $ancestor ) );
			}
		}
	}

	/**
	 * Endpoints.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function endpoint_trail() {

		$action         = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		$endpoint       = is_wc_endpoint_url() ? WC()->query->get_current_endpoint() : '';
		$endpoint_title = $endpoint ? WC()->query->get_endpoint_title( $endpoint, $action ) : '';

		if ( $endpoint_title ) {
			$this->add_crumb( $endpoint_title );
		}
	}

	/**
	 * Add a breadcrumb for search results.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function search_trail() {

		if ( is_search() ) {
			/* translators: %s: search term */
			$this->add_crumb( sprintf( __( 'Search results for &ldquo;%s&rdquo;', 'wp-breadcrumbs' ), get_search_query() ), remove_query_arg( 'paged' ) );
		}
	}

	/**
	 * Add a breadcrumb for pagination.
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	protected function paged_trail() {

		if ( get_query_var( 'paged' ) ) {
			/* translators: %d: page number */
			$this->add_crumb( sprintf( __( 'Page %d', 'wp-breadcrumbs' ), get_query_var( 'paged' ) ) );
		}
	}

	/**
	 * Check if the WooCommerce plugin is installed and return the url to activate it if so.
	 *
	 * @since 1.0.0
	 *
	 * @return string|bool
	 */
	private function is_wc_installed() {

		// Check if the plugin directory exists.
		if ( ! file_exists( WP_PLUGIN_DIR . '/woocommerce' ) ) {
			return false;
		}

		$plugins = get_plugins( '/woocommerce' );

		// Check if the plugin is installed.
		if ( empty( $plugins ) ) {
			return false;
		}

		$keys        = array_keys( $plugins );
		$plugin_file = 'woocommerce/' . $keys[0];
		$url         = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'activate',
					'plugin' => $plugin_file,
				),
				admin_url( 'plugins.php' )
			),
			'activate-plugin_' . $plugin_file
		);

		return $url;
	}
}
