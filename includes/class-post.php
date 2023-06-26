<?php
/**
 * Post class.
 *
 * @package WSTDV
 */

namespace WSTDV;

use WSTDV\Traits\Singleton;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add styles of scripts files inside this class.
 */
class Post {

	use Singleton;

	/**
	 * Constructor of Post class.
	 */
	private function __construct() {

		add_action( 'init', array( $this, 'wp_register_posttype_realestate' ) );

		add_action( 'save_post', array( $this, 'save_custom_taxonomy' ), 10, 2 );

		add_action( 'restrict_manage_posts', array( $this, 'filter_location_select' ) );

		add_filter( 'parse_query', array( $this, 'filter_realestate_query' ) );

		add_action( 'add_meta_boxes', array( $this, 'info_add_meta_box' ) );

		add_action( 'save_post', array( $this, 'info_save_meta_box_data' ) );

		add_filter( 'manage_realestate_posts_columns', array( $this, 'add_column_realestate_posts_columns' ) );

		add_action( 'manage_realestate_posts_custom_column', array( $this, 'add_realestate_posts_custom_column' ), 10, 2 );

		// Remove bulk actions.
		add_filter( 'bulk_actions-edit-realestate', array( $this, 'remove_bulk_actions' ) );

		// Hide search box in manage page.
		add_action( 'admin_footer', array( $this, 'hide_search_box' ), 10, 1 );
	}

	/**
	 * Remove search box in realestate list
	 */
	public function hide_search_box() {
		?>
		<style type="text/css">
			.post-type-realestate .search-box{ display:none } // only hide in realestate post type
		</style>
		<?php
	}

	/**
	 * Remove bulk actions
	 */
	public function remove_bulk_actions( $actions ) {
		// remove all actions.
		return array();
	}
	/**
	 * Custom header of post type list
	 *
	 * @param array $column column headers.
	 */
	public function add_column_realestate_posts_columns( $columns ) {
		$columns = array(
			'image'             => __( 'Image' ),
			'title'             => __( 'Title' ),
			'categories'        => __( 'Category' ),
			'taxonomy-location' => __( 'Location' ),
			'taxonomy-project'  => __( 'Project' ),
			'price'             => __( 'Price' ),
			'acreage'           => __( 'Acreage' ),

		);

		return $columns;
	}

	/**
	 * Custom value of post type list
	 *
	 * @param array $column_key column key.
	 * @param int   $post_id post id.
	 */
	public function add_realestate_posts_custom_column( $column_key, $post_id ) {
		if ( 'image' === $column_key ) {
			echo get_the_post_thumbnail( $post_id, array( 80, 80 ) );
		}

		if ( $column_key == 'price' ) {
			$price = get_post_meta( $post_id, '_wp_price', true );
			echo '<span>';
			echo esc_attr( $price );
			echo '</span>';
		}

		if ( $column_key == 'acreage' ) {
			$acreage = get_post_meta( $post_id, '_wp_acreage', true );
			echo '<span>';
			echo esc_attr( $acreage );
			echo '</span>';
		}
	}

	/**
	 * Add custom field to custom post type.
	 */
	public function info_add_meta_box() {
		// This will add the metabox for the member post type.
		$screens = array( 'realestate' );

		foreach ( $screens as $screen ) {

			add_meta_box(
				'info_sectionid',
				__( 'WooCommerce Subscription Trading View Info' ),
				array( $this, 'info_meta_box_callback' ),
				$screen
			);
		}
	}

	/**
	 * Prints the box content.
	 *
	 * @param WP_Post $post The object for the current post/page.
	 */
	public function info_meta_box_callback( $post ) {

		// Add a nonce field so we can check for it later.
		wp_nonce_field( 'info_save_meta_box_data', 'info_meta_box_nonce' );

		/*
		* Use get_post_meta() to retrieve an existing value
		* from the database and use the value for the form.
		*/
		$value = get_post_meta( $post->ID, '_wp_price', true );

		echo '<div style="display:inline-block"><label for="info_price">';
		echo __( 'Price' );
		echo '</label> ';
		echo '<input type="number" id="info_price" name="info_price" value="' . esc_attr( $value ) . '" size="25" />';
		echo '</div>';

		$value = get_post_meta( $post->ID, '_wp_acreage', true );

		echo '<div style="display:inline-block"><label for="info_acreage">';
		echo __( 'Acreage' );
		echo '</label> ';
		echo '<input type="number" id="info_acreage" name="info_acreage" value="' . esc_attr( $value ) . '" size="25" />m2';
		echo '</div>';
	}

	/**
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function info_save_meta_box_data( $post_id ) {

		if ( ! isset( $_POST['info_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['info_meta_box_nonce'], 'info_save_meta_box_data' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		if ( ! isset( $_POST['info_price'] ) ) {
			return;
		}

		$price = sanitize_text_field( $_POST['info_price'] );

		update_post_meta( $post_id, '_wp_price', $price );

		$acreage = sanitize_text_field( $_POST['info_acreage'] );

		update_post_meta( $post_id, '_wp_acreage', $acreage );
	}

	/**
	 * Add query filter.
	 *
	 * @param WP_Query $query as query input.
	 * @return WP_Query
	 */
	public function filter_realestate_query( $query ) {
		global $pagenow;
		$post_type = ( isset( $_GET['post_type'] ) ) ? $_GET['post_type'] : 'post';
		if ( $pagenow == 'edit.php' && $post_type == 'realestate' ) {

			$city     = isset( $_GET['city'] ) ? (int) sanitize_text_field( $_GET['city'] ) : '';
			$district = isset( $_GET['district'] ) ? (int) sanitize_text_field( $_GET['district'] ) : '';
			$ward     = isset( $_GET['ward'] ) ? (int) sanitize_text_field( $_GET['ward'] ) : '';
			$street   = isset( $_GET['street'] ) ? (int) sanitize_text_field( $_GET['street'] ) : '';
			$project  = isset( $_GET['project'] ) ? (int) sanitize_text_field( $_GET['project'] ) : '';

			$tax_query = $query->get( 'tax_query' );
			$tax_query = is_array( $tax_query ) ? $tax_query : array();

			$term_ids = array();
			if ( ! empty( $street ) ) {
				$term_ids[] = $street;
			} elseif ( ! empty( $ward ) ) {
				$term_ids[] = $ward;
			} elseif ( ! empty( $district ) ) {
				$term_ids[] = $district;
			} elseif ( ! empty( $city ) ) {
				$term_ids[] = $city;
			}

			if ( ! empty( $term_ids ) ) {
				$tax_query[] = array(
					'taxonomy' => 'location',
					'terms'    => $term_ids,
				);
			}
			/*
			if (!empty($project)) {
				$tax_query[] = [
					'taxonomy' => 'project',
					'terms'    => $project,
				];

			}*/

			$query->set( 'tax_query', $tax_query );

			$price = isset( $_GET['price'] ) ? sanitize_text_field( $_GET['price'] ) : 0;

			if ( ! empty( $price ) ) {
				$prices = explode( '-', $price );
				$query->set(
					'meta_query',
					array(
						array(
							'key'     => '_wp_price',
							'compare' => 'BETWEEN',
							'value'   => $prices,
							'type'    => 'numeric',
						),
					)
				);
			}

			$acreage = isset( $_GET['acreage'] ) ? sanitize_text_field( $_GET['acreage'] ) : 0;

			if ( ! empty( $acreage ) ) {
				$acreages = explode( '-', $acreage );
				$query->set(
					'meta_query',
					array(
						array(
							'key'     => '_wp_acreage',
							'compare' => 'BETWEEN',
							'value'   => $acreages,
							'type'    => 'numeric',
						),
					)
				);
			}
		}
		return $query;
	}

	/**
	 * Add custom filters
	 */
	public function filter_location_select() {
		$post_type = ( isset( $_GET['post_type'] ) ) ? $_GET['post_type'] : 'post';

		if ( $post_type != 'realestate' ) {
			return;
		}
		$cat      = isset( $_GET['cat'] ) ? (int) sanitize_text_field( $_GET['cat'] ) : '';
		$city     = isset( $_GET['city'] ) ? (int) sanitize_text_field( $_GET['city'] ) : '';
		$district = isset( $_GET['district'] ) ? (int) sanitize_text_field( $_GET['district'] ) : '';
		$ward     = isset( $_GET['ward'] ) ? (int) sanitize_text_field( $_GET['ward'] ) : '';
		$street   = isset( $_GET['street'] ) ? (int) sanitize_text_field( $_GET['street'] ) : '';
		$project  = isset( $_GET['project'] ) ? (int) sanitize_text_field( $_GET['project'] ) : '';

		$price   = isset( $_GET['price'] ) ? (int) sanitize_text_field( $_GET['price'] ) : '';
		$acreage = isset( $_GET['acreage'] ) ? (int) sanitize_text_field( $_GET['acreage'] ) : '';

		?>
		<select name="city" id="filter-city" class="postform location-city" style="width:120px">
			<option value=""><?php echo __( 'All Cities' ); ?></option>
			<?php
			$str        = '';
			$city_terms = get_terms(
				'location',
				array(
					'parent'     => 0,
					'orderby'    => 'name',
					'hide__mpty' => false,
				)
			);
			foreach ( $city_terms as $term ) {
				if ( ! empty( $term->name ) ) {
					$str .= "<option value='" . $term->term_id . "'";
					$str .= ( $city == $term->term_id ) ? ' selected>' : '>';
					$str .= $term->name . '</option>';
				}
			}
			echo $str;
			?>
		</select>

		<select name="district" id="filter-district" class="postform location-district" style="width:120px">
			<option value=""><?php echo __( 'All Districts' ); ?></option>
			<?php
			if ( ! empty( $city ) ) {
				$str            = '';
				$district_terms = get_terms(
					'location',
					array(
						'parent'     => $city,
						'orderby'    => 'name',
						'hide__mpty' => false,
					)
				);
				foreach ( $district_terms as $term ) {
					if ( ! empty( $term->name ) ) {
						$str .= "<option value='" . $term->term_id . "'";
						$str .= ( $district == $term->term_id ) ? ' selected>' : '>';
						$str .= $term->name . '</option>';
					}
				}
				echo $str;
			}
			?>
		</select>

		<select name="ward" id="filter-ward" class="postform location-ward" style="width:120px">
			<option value=""><?php echo __( 'All Wards' ); ?></option>
			<?php
			if ( ! empty( $district ) ) {
				$str        = '';
				$ward_terms = get_terms(
					'location',
					array(
						'parent'     => $district,
						'orderby'    => 'name',
						'hide__mpty' => false,
						'meta_query' => array(
							array(
								'key'     => 'term_type',
								'value'   => 'ward',
								'compare' => '=',
							),
						),
					)
				);
				foreach ( $ward_terms as $term ) {
					if ( ! empty( $term->name ) ) {
						$str .= "<option value='" . $term->term_id . "'";
						$str .= ( $ward == $term->term_id ) ? ' selected>' : '>';
						$str .= $term->name . '</option>';
					}
				}
				echo $str;
			}
			?>
		</select>

		<select name="street" id="filter-street" class="postform location-street" style="width:120px">
			<option value=""><?php echo __( 'All Streets' ); ?></option>
			<?php
			if ( ! empty( $district ) ) {
				$str          = '';
				$street_terms = get_terms(
					'location',
					array(
						'parent'     => $district,
						'orderby'    => 'name',
						'hide__mpty' => false,
						'meta_query' => array(
							array(
								'key'     => 'term_type',
								'value'   => 'street',
								'compare' => '=',
							),
						),
					)
				);
				foreach ( $street_terms as $term ) {
					if ( ! empty( $term->name ) ) {
						$str .= "<option value='" . $term->term_id . "'";
						$str .= ( $street == $term->term_id ) ? ' selected>' : '>';
						$str .= $term->name . '</option>';
					}
				}
				echo $str;
			}
			?>
		</select>

		<select name="project" id="filter-project" class="postform location-project" style="width:120px">
			<option value=""><?php echo __( 'All Projects' ); ?></option>
			<?php
			if ( ! empty( $district ) ) {
				$str           = '';
				$project_terms = get_terms(
					'project',
					array(
						'parent'     => $district,
						'orderby'    => 'name',
						'hide__mpty' => false,
					)
				);
				foreach ( $project_terms as $term ) {
					if ( ! empty( $term->name ) ) {
						$str .= "<option value='" . $term->term_id . "'";
						$str .= ( $project == $term->term_id ) ? ' selected>' : '>';
						$str .= $term->name . '</option>';
					}
				}
				echo $str;
			}
			?>
		</select>

		<select name="price" id="filter-price" class="postform info-price" style="width:120px">
			<option value=""><?php echo __( 'All Prices' ); ?></option>
			<?php
				$options    = get_option( 'trading_view_option_name' );
				$str_prices = '';
			if ( ! empty( $options['prices'] ) ) {
				$prices = (array) json_decode( $options['prices'] );
				if ( ! empty( $prices[ $cat ] ) ) {
					foreach ( $prices[ $cat ] as $item ) {
						$itm         = (array) $item;
						$key         = key( $itm );
						$label       = reset( $itm );
						$str_prices .= "<option value='" . $key . "'";
						$str_prices .= ( $price == $key ) ? ' selected>' : '>';
						$str_prices .= $label . '</option>';
					}
				}
			}
				echo $str_prices;
			?>
		</select>

		<select name="acreage" id="filter-acreage" class="postform info-acreage" style="width:120px">
			<option value=""><?php echo __( 'All Acreages' ); ?></option>
			<?php
				$str_acreages = '';
			if ( ! empty( $options['acreages'] ) ) {
				$acreages = (array) json_decode( $options['acreages'] );
				if ( ! empty( $acreages ) ) {
					foreach ( $acreages as $item ) {
						$itm           = (array) $item;
						$key           = key( $itm );
						$label         = reset( $itm );
						$str_acreages .= "<option value='" . $key . "'";
						$str_acreages .= ( $acreage == $key ) ? ' selected>' : '>';
						$str_acreages .= $label . '</option>';
					}
				}
			}
				echo $str_acreages;
			?>
		</select>

		<?php
	}

	/**
	 * Save custom taxonomy into post.
	 *
	 * @param array $post_id input value.
	 * @return void
	 */
	public function save_custom_taxonomy( $post_id ) {
		$term_ids = array();
		if ( isset( $_REQUEST['city'] ) ) {
			$term_ids[] = (int) sanitize_text_field( $_POST['city'] );
		}
		if ( isset( $_REQUEST['district'] ) ) {
			$term_ids[] = (int) sanitize_text_field( $_POST['district'] );
		}

		if ( isset( $_REQUEST['ward'] ) ) {
			$term_ids[] = (int) sanitize_text_field( $_POST['ward'] );
		}

		if ( isset( $_REQUEST['street'] ) ) {
			$term_ids[] = (int) sanitize_text_field( $_POST['street'] );
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, 'location' );
		}

		if ( isset( $_REQUEST['project'] ) ) {
			wp_set_object_terms( $post_id, (int) sanitize_text_field( $_POST['project'] ), 'project' );
		}

	}

	/**
	 * Register Posttype names RealEstate.
	 *
	 * @return void
	 */
	public function wp_register_posttype_realestate() {

		// Set UI labels for Custom Post Type
		$labels = array(
			'name'               => _x( 'WooCommerce Subscription Trading Views', 'Post Type General Name' ),
			'singular_name'      => _x( 'WooCommerce Subscription Trading View', 'Post Type Singular Name' ),
			'menu_name'          => __( 'WooCommerce Subscription Trading Views' ),
			'parent_item_colon'  => __( 'Parent WooCommerce Subscription Trading View' ),
			'all_items'          => __( 'All WooCommerce Subscription Trading Views' ),
			'view_item'          => __( 'View WooCommerce Subscription Trading View' ),
			'add_new_item'       => __( 'Add New WooCommerce Subscription Trading View' ),
			'add_new'            => __( 'Add New' ),
			'edit_item'          => __( 'Edit WooCommerce Subscription Trading View' ),
			'update_item'        => __( 'Update WooCommerce Subscription Trading View' ),
			'search_items'       => __( 'Search WooCommerce Subscription Trading View' ),
			'not_found'          => __( 'Not Found' ),
			'not_found_in_trash' => __( 'Not found in Trash' ),
		);

		// Set other options for Custom Post Type

		$args = array(
			'label'               => __( 'WooCommerce Subscription Trading View' ),
			'description'         => __( 'WooCommerce Subscription Trading View news and reviews' ),
			'labels'              => $labels,
			// Features this CPT supports in Post Editor.
			'supports'            => array( 'title', 'editor', 'thumbnail', 'post-formats' ),
			// You can associate this CPT with a taxonomy or custom taxonomy.
			'taxonomies'          => array( 'location', 'category' ),
			/*
			 A hierarchical CPT is like Pages and can have
			* Parent and child items. A non-hierarchical CPT
			* is like Posts.
			*/
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 5,
			'can__xport'          => true,
			'has_archive'         => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			// 'show_in_rest' => true,
			'rewrite'             => array( 'slug' => 'realestate' ),
		);

		// Registering your Custom Post Type
		register_post_type( 'realestate', $args );
	}


}
