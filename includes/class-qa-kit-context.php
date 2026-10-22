<?php
/**
 * Collects page context and ACF field data for the toolbar and audit.
 */

defined( 'ABSPATH' ) || exit;

class QA_Kit_Context {

	/** ACF field types that never hold a value. */
	const ACF_LAYOUT_TYPES = array( 'tab', 'message', 'accordion' );

	/**
	 * All ACF field groups (DB, local JSON and PHP) with flattened field lists.
	 */
	public static function acf_field_groups() {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return array();
		}
		$out = array();
		foreach ( acf_get_field_groups() as $group ) {
			$out[] = array(
				'key'    => $group['key'],
				'title'  => $group['title'],
				'fields' => self::flatten_fields( acf_get_fields( $group ) ),
			);
		}
		return $out;
	}

	/**
	 * Flatten nested ACF fields (repeaters, groups, flexible layouts).
	 *
	 * @param array  $fields Fields.
	 * @param string $parent Parent field name.
	 */
	public static function flatten_fields( $fields, $parent = '' ) {
		$out = array();
		foreach ( (array) $fields as $field ) {
			$out[] = array(
				'key'      => $field['key'],
				'name'     => $field['name'],
				'label'    => $field['label'],
				'type'     => $field['type'],
				'required' => ! empty( $field['required'] ),
				'parent'   => $parent,
			);
			if ( ! empty( $field['sub_fields'] ) ) {
				$out = array_merge( $out, self::flatten_fields( $field['sub_fields'], $field['name'] ) );
			}
			if ( ! empty( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as $layout ) {
					if ( ! empty( $layout['sub_fields'] ) ) {
						$out = array_merge( $out, self::flatten_fields( $layout['sub_fields'], $field['name'] . ' > ' . $layout['name'] ) );
					}
				}
			}
		}
		return $out;
	}

	/**
	 * ACF fields assigned to the current queried object, with values and empty flags.
	 */
	public static function page_acf_fields() {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return null;
		}
		$acf_id = self::current_acf_id();
		if ( ! $acf_id ) {
			return array();
		}

		$out = array();
		foreach ( acf_get_field_groups( array( 'post_id' => $acf_id ) ) as $group ) {
			$fields = array();
			foreach ( (array) acf_get_fields( $group ) as $field ) {
				if ( in_array( $field['type'], self::ACF_LAYOUT_TYPES, true ) ) {
					continue;
				}
				$value    = get_field( $field['key'], $acf_id, false );
				$fields[] = array(
					'name'     => $field['name'],
					'label'    => $field['label'],
					'type'     => $field['type'],
					'required' => ! empty( $field['required'] ),
					'empty'    => self::is_empty_value( $value, $field['type'] ),
					'preview'  => self::preview_value( $value ),
				);
			}
			$out[] = array(
				'title'  => $group['title'],
				'key'    => $group['key'],
				'fields' => $fields,
			);
		}
		return $out;
	}

	/**
	 * ACF post_id string for the queried object.
	 */
	public static function current_acf_id() {
		$object = get_queried_object();
		if ( $object instanceof WP_Post ) {
			return $object->ID;
		}
		if ( $object instanceof WP_Term ) {
			return 'term_' . $object->term_id;
		}
		if ( $object instanceof WP_User ) {
			return 'user_' . $object->ID;
		}
		return 0;
	}

	/**
	 * Description of the queried object for reports.
	 */
	public static function current_object_label() {
		$object = get_queried_object();
		if ( $object instanceof WP_Post ) {
			return $object->post_type . ' #' . $object->ID . ' "' . get_the_title( $object ) . '"';
		}
		if ( $object instanceof WP_Term ) {
			return $object->taxonomy . ' term #' . $object->term_id . ' "' . $object->name . '"';
		}
		if ( $object instanceof WP_Post_Type ) {
			return $object->name . ' archive';
		}
		if ( is_search() ) {
			return 'search results';
		}
		if ( is_404() ) {
			return '404';
		}
		if ( is_home() ) {
			return 'blog index';
		}
		return 'other';
	}

	/**
	 * Empty check that does not flag false toggles or zero as empty.
	 *
	 * @param mixed  $value Raw field value.
	 * @param string $type  ACF field type.
	 */
	public static function is_empty_value( $value, $type ) {
		if ( 'true_false' === $type ) {
			return false;
		}
		if ( is_array( $value ) ) {
			return 0 === count( array_filter( $value, array( __CLASS__, 'has_content' ) ) );
		}
		return ! self::has_content( $value );
	}

	/**
	 * @param mixed $value Value.
	 */
	public static function has_content( $value ) {
		if ( is_array( $value ) ) {
			return count( array_filter( $value, array( __CLASS__, 'has_content' ) ) ) > 0;
		}
		return ! ( null === $value || false === $value || '' === $value );
	}

	/**
	 * Short preview of a raw value.
	 *
	 * @param mixed $value Value.
	 */
	public static function preview_value( $value ) {
		if ( is_array( $value ) ) {
			return 'array(' . count( $value ) . ')';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		$value = wp_strip_all_tags( (string) $value );
		return strlen( $value ) > 80 ? substr( $value, 0, 77 ) . '...' : $value;
	}

	/**
	 * Path relative to the WordPress root.
	 *
	 * @param string $path Absolute path.
	 */
	public static function relative_path( $path ) {
		$path = wp_normalize_path( $path );
		$root = wp_normalize_path( ABSPATH );
		return 0 === strpos( $path, $root ) ? substr( $path, strlen( $root ) ) : $path;
	}
}
