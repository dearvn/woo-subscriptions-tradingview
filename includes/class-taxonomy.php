<?php
/**
 * Taxonomy class.
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
class Taxonomy {

	use Singleton;

	/**
	 * Constructor of Taxonomy class.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'wp_register_taxonomy_location' ) );

		add_action( 'init', array( $this, 'wp_register_taxonomy_project' ) );

		add_action( 'location_add_form_fields', array( $this, 'add_term_fields' ) );

		add_action( 'location_edit_form_fields', array( $this, 'edit_term_fields' ), 10, 2 );

		add_action( 'created_location', array( $this, 'save_term_fields' ) );

		add_action( 'edited_location', array( $this, 'save_term_fields' ) );
	}


	/**
	 * Register Taxonomy names Project.
	 *
	 * @return void
	 */
	public function wp_register_taxonomy_project() {
		$labels = array(
			'name'              => __( 'Projects' ),
			'singular_name'     => __( 'Project', ),
			'singular'          => __( 'Project' ),
			'search_items'      => __( 'Search Projects' ),
			'all_items'         => __( 'All Projects' ),
			'parent_item'       => __( 'Parent Project' ),
			'parent_item_colon' => __( 'Parent Project:' ),
			'edit_item'         => __( 'Edit Project' ),
			'update_item'       => __( 'Update Project' ),
			'add_new_item'      => __( 'Add New Project' ),
			'new_item_name'     => __( 'New Project Name' ),
			'menu_name'         => __( 'Project' ),
		);
		$args   = array(
			'hierarchical'       => true, // make it hierarchical (like categories).
			'labels'             => $labels,
			'public'             => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_tagcloud'      => true,
			'query_var'          => true,
			'show_in_quick_edit' => true,
			'rewrite'            => array( 'slug' => 'project' ),
			'meta_box_cb'        => array( $this, 'drop_project' ),
		);

		register_taxonomy( 'project', array( 'realestate' ), $args );
	}

	/**
	 * Init Location menu.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function wp_register_taxonomy_location() {
		$labels = array(
			'name'              => __( 'Locations' ),
			'singular_name'     => __( 'Location', ),
			'singular'          => __( 'Location' ),
			'search_items'      => __( 'Search Locations' ),
			'all_items'         => __( 'All Locations' ),
			'parent_item'       => __( 'Parent Location' ),
			'parent_item_colon' => __( 'Parent Location:' ),
			'edit_item'         => __( 'Edit Location' ),
			'update_item'       => __( 'Update Location' ),
			'add_new_item'      => __( 'Add New Location' ),
			'new_item_name'     => __( 'New Location Name' ),
			'menu_name'         => __( 'Location' ),
		);
		$args   = array(
			'hierarchical'       => true, // make it hierarchical (like categories).
			'labels'             => $labels,
			'public'             => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_tagcloud'      => true,
			'query_var'          => true,
			'show_in_quick_edit' => true,
			'rewrite'            => array( 'slug' => 'location' ),
			'meta_box_cb'        => array( $this, 'drop_location' ),

		);

		register_taxonomy( 'location', array( 'realestate' ), $args );
	}

	/**
	 * Get child term data by parent.
	 */
	public function drop_location( $post, $box ) {
		$terms    = get_the_terms( $post->ID, 'location' );
		$city     = 0;
		$district = 0;
		$ward     = 0;
		$street   = 0;
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_value = get_term_meta( $term->term_id, 'term_type', true );
				if ( $term_value == 'city' ) {
					$city = $term->term_id;
				} elseif ( $term_value == 'district' ) {
					$district = $term->term_id;
				} elseif ( $term_value == 'ward' ) {
					$ward = $term->term_id;
				} elseif ( $term_value == 'street' ) {
					$street = $term->term_id;
				}
			}
		}
		?>
		<div id="location-city" class="location-city" style="display:block">
			<label style="width:46px;display: inline-block;"><?php echo __( 'City' ); ?></label>
			<select name="city" id="dr-city" style="width:180px" class="location-city">
				<option value=""><?php echo __( 'Select City' ); ?></option>
				<?php
				$str        = '';
				$city_terms = get_terms(
					'location',
					array(
						'parent'     => 0,
						'orderby'    => 'name',
						'hide_empty' => false,
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
		</div>
		<br/>	
		<div id="location-dist" class="district"  style="display:block">
			<label style="width:46px;display: inline-block;"><?php echo __( 'District' ); ?></label>
			<select name="district" id="dr-district" style="width:180px" class="location-district">
				<option value=""><?php echo __( 'Select District' ); ?></option>
				<?php
				$str            = '';
				$district_terms = get_terms(
					'location',
					array(
						'parent'     => $city,
						'orderby'    => 'name',
						'hide_empty' => false,
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
				?>
			</select>
		</div>
		<br/>	
		<div id="ward" class="ward"  style="display:block">
			<label style="width:46px;display: inline-block;"><?php echo __( 'Ward' ); ?></label>
			<select name="ward" id="dr-ward" style="width:180px" class="location-ward">
				<option value=""><?php echo __( 'Select Ward' ); ?></option>
				<?php
				$str        = '';
				$ward_terms = get_terms(
					'location',
					array(
						'parent'     => $district,
						'orderby'    => 'name',
						'hide_empty' => false,
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
				?>
			</select>
		</div>
		<br/>	
		<div id="street" class="street"  style="display:block">
			<label style="width:46px;display: inline-block;"><?php echo __( 'Street' ); ?></label>
			<select name="street" id="dr-street" style="width:180px" class="location-street">
				<option value=""><?php echo __( 'Select Street' ); ?></option>
				<?php
				$str          = '';
				$street_terms = get_terms(
					'location',
					array(
						'parent'     => $district,
						'orderby'    => 'name',
						'hide_empty' => false,
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
				?>
			</select>
		</div>
		<?php
	}

	/**
	 * Get project data.
	 */
	public function drop_project( $post, $box ) {
		$terms    = get_the_terms( $post->ID, 'location' );
		$district = '';
		$project  = '';
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_value = get_term_meta( $term->term_id, 'term_type', true );
				if ( $term_value == 'district' ) {
					$district = $term->term_id;
					break;
				}
			}
		}
		$project_terms = get_the_terms( $post->ID, 'project' );
		if ( ! empty( $project_terms ) ) {
			foreach ( $project_terms as $term ) {
				$term_value = get_term_meta( $term->term_id, 'term_type', true );
				if ( $term_value == 'project' ) {
					$project = $term->term_id;
					break;
				}
			}
		}
		?>
		<div id="project" class="project"  style="display:block">
			<label style="width:46px;display: inline-block;"><?php echo __( 'Project' ); ?></label>
			<select name="project" id="dr-project" style="width:180px" class="location-project">
				<option value=""><?php echo __( 'Select One' ); ?></option>
				<?php
				$str           = '';
				$project_terms = get_terms(
					'project',
					array(
						'parent'     => $district,
						'orderby'    => 'name',
						'hide_empty' => false,
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
				?>
			</select>
		</div>

		<?php
	}

	public function add_term_fields( $taxonomy ) {
		$types = array(
			'city'      => 'City',
			'disctrict' => 'District',
			'ward'      => 'Ward',
			'street'    => 'Street',
		);
		?>
			<div class="form-field">
				<label for="term_type">Type</label>
				<select name="term_type" id="dr-term_type">
					<option value=""><?php echo __( 'Select One' ); ?></option>
					<?php
					$str = '';
					foreach ( $types as $key => $type ) {
						$str .= "<option value='" . $key . "'>";
						$str .= $type . '</option>';
					}
					echo $str;
					?>
				</select>
				<p>Type is city, district, ward and street.</p>
			</div>
			
		<?php
	}

	public function edit_term_fields( $term, $taxonomy ) {
		$types = array(
			'city'      => 'City',
			'disctrict' => 'District',
			'ward'      => 'Ward',
			'street'    => 'Street',
		);
		// Get meta data value.
		$text_field = get_term_meta( $term->term_id, 'term_type', true );

		?>
		<tr class="form-field">
			<th><label for="term_type">Type</label></th>
			<td>
				<select name="term_type" id="dr-term_type">
					<option value=""><?php echo __( 'Select One' ); ?></option>
					<?php
					$str = '';
					foreach ( $types as $key => $type ) {
						$str .= "<option value='" . $key . "'";
						$str .= ( $text_field == $key ) ? ' selected>' : '>';
						$str .= $type . '</option>';
					}
					echo $str;
					?>
				</select>
				<p class="description">Type is city, district, ward and street.</p>
			</td>
		</tr>
		<?php
	}

	function save_term_fields( $term_id ) {

		update_term_meta(
			$term_id,
			'term_type',
			sanitize_text_field( $_POST['term_type'] )
		);
	}
}
