<?php
/**
 * Category class.
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
class Category {

	use Singleton;

	/**
	 * Constructor of Category class.
	 */
	private function __construct() {
		// Add category type meta.
		add_action( 'category_add_form_fields', array( $this, 'category_fields_new' ), 10, 2 );
		add_action( 'category_edit_form_fields', array( $this, 'category_fields_edit' ), 10, 2 );

		// Save category type field.
		add_action( 'created_category', array( $this, 'save_category_fields' ), 10, 2 );
		add_action( 'edited_category', array( $this, 'save_category_fields' ), 10, 2 );

		add_filter( 'manage_edit-category_columns', array( $this, 'category_column_header' ), 10, 2 );

		add_filter( 'manage_category_custom_column', array( $this, 'category_column_value' ), 10, 3 );

	}

	/**
	 * Add label into header of category list.
	 *
	 * @param array $columns input value.
	 * @return array $columns
	 */
	public function category_column_header( $columns ) {
		$columns['customval'] = __( 'Type' );
		return $columns;
	}

	/**
	 * Add value into the body of category list.
	 *
	 * @param string $empty input value.
	 * @param string $custom_column input value.
	 * @param int    $term_id input value.
	 * @return string $value
	 */
	public function category_column_value( $empty = '', $custom_column, $term_id ) {
		// Category custom field.
		$term_meta = get_option( "category_{$term_id}_category_type" );
		$value     = $term_meta ? $term_meta : '';

		return $value;
	}

	/**
	 * Add category type field into category form new.
	 *
	 * @param string $taxonomy input value.
	 * @return void
	 */
	public function category_fields_new( $taxonomy ): void {

		// Function has one field to pass – Taxonomy.
		wp_nonce_field( 'category_meta_new', 'category_meta_new_nonce' ); // Create a Nonce so that we can verify the integrity of our data.
		?>
		<label for='category_fa'>Category Type</label>
		<input type='text' id='category_type' name='category_type' style='width: 100%'>
		<p class='description'>Enter Category Type</p>
		<?php
	}

	/**
	 * Edit category type field into category form edit.
	 *
	 * @param \WP_Term $term input value.
	 * @param string   $taxonomy input value.
	 * @return void
	 */
	public function category_fields_edit( $term, $taxonomy ): void {

		wp_nonce_field( 'category_meta_edit', 'category_meta_edit_nonce' ); // Create a Nonce so that we can verify the integrity of our data.
		$category_type = get_option( "{$taxonomy}_{$term->term_id}_category_type" ); // Get the category_type if one is set already.
		?>
		<tr class='form-field'>
			<th scope='row' valign='top'>
			<label for='category_fa'>Category Type</label>
		</th>
		<td>
			<input name='category_type' id='category_fa' type='text'
			value="<?php echo ( ! empty( $category_type ) ) ? esc_html( $category_type ) : ''; ?>" style='width:100%;' />
			<p class='description'>Enter Category Type</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save category type field.
	 *
	 * @param \WP_Term $term_id input value.
	 * @return void
	 */
	public function save_category_fields( $term_id ) {

		$taxonomy      = 'category';
		$category_type = ! empty( $_POST['category_type'] ) ? sanitize_text_field( $_POST['category_type'] ) : '';

		update_option( "{$taxonomy}_{$term_id}_category_type", $category_type ); // Sanitize our data before adding to the database.
		
	}

}
