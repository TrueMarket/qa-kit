<?php
/**
 * Flexible layout test page.
 *
 * Builds a private page on the theme's Flexible layout template with a section
 * for every block, block option and section option. Everything is read from the
 * template's ACF field group at build time, so blocks and options a site adds
 * are covered without changes here.
 *
 * Options change one at a time from a baseline, so every choice and toggle shows
 * up at least once without building every combination. Conditional logic is
 * followed: an option that only shows for two columns gets two columns.
 */

defined( 'ABSPATH' ) || exit;

class QA_Kit_Test_Page {

	const ACTION = 'qa_kit_test_page';
	const OPTION = 'qa_kit_test_page_id';
	const META   = '_qa_kit_fixture';
	const TITLE  = 'QA Kit: Flexible layout test';

	/** Field types whose every choice gets its own section. */
	const CHOICE_TYPES = array( 'select', 'button_group', 'radio' );

	/** Optional fields of these types start empty, then get a section with a value. */
	const OPTIONAL_TYPES = array( 'number', 'link' );

	/** Fields that hold other fields: their values are nested arrays. */
	const PARENT_TYPES = array( 'group', 'clone' );

	const VIDEO_URL = 'https://www.youtube.com/watch?v=jNQXAC9IVRw';

	const EMBED = '<iframe src="https://maps.google.com/maps?q=Calgary%2C%20Alberta&amp;output=embed" title="Sample map embed" width="600" height="450" style="border:0" loading="lazy"></iframe>';

	/** @var string[] Problems found while building, shown after the build. */
	private static $notes = array();

	/** @var int Numbers sample text so IDs made from it stay unique on the page. */
	private static $counter = 0;

	/** @var bool Make long sample content instead of typical content. */
	private static $long = false;

	/** @var array Fixture and post IDs already looked up during this build. */
	private static $cache = array();

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Find the template's sections repeater, the columns repeater inside it (if
	 * any) and the flexible content field that holds the blocks.
	 *
	 * @return array|WP_Error
	 */
	public static function schema() {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return new WP_Error( 'qa_kit_no_acf', 'ACF is not active.' );
		}

		$template  = apply_filters( 'qa_kit_test_page_template', 'templates/flexible-layout.php' );
		$templates = wp_get_theme()->get_page_templates( null, 'page' );
		if ( ! isset( $templates[ $template ] ) ) {
			return new WP_Error( 'qa_kit_no_template', sprintf( 'The active theme has no %s page template.', $template ) );
		}

		foreach ( acf_get_field_groups() as $group ) {
			if ( ! self::targets( $group, $template ) ) {
				continue;
			}
			foreach ( (array) acf_get_fields( $group ) as $field ) {
				if ( 'repeater' !== $field['type'] ) {
					continue;
				}
				foreach ( self::scope( $field['sub_fields'] ) as $child ) {
					$columns = 'repeater' === $child['type'] ? $child : null;
					$blocks  = $columns ? self::first_of_type( $columns['sub_fields'], 'flexible_content' ) : $child;
					if ( $blocks && 'flexible_content' === $blocks['type'] && ! empty( $blocks['layouts'] ) ) {
						return array(
							'template'      => $template,
							'template_name' => $templates[ $template ],
							'group'         => $group,
							'sections'      => $field,
							'columns'       => $columns,
							'blocks'        => $blocks,
						);
					}
				}
			}
		}

		return new WP_Error(
			'qa_kit_no_fields',
			sprintf( 'No ACF field group on the "%s" template has a repeater of sections holding a flexible content field of blocks.', $templates[ $template ] )
		);
	}

	/**
	 * The test page, if it has been built and is not in the trash.
	 *
	 * @return WP_Post|null
	 */
	public static function page() {
		$id   = (int) get_option( self::OPTION );
		$post = $id ? get_post( $id ) : null;
		return $post && 'trash' !== $post->post_status ? $post : null;
	}

	/**
	 * IDs of everything QA Kit created: the test page and its placeholder media.
	 *
	 * @return int[]
	 */
	public static function leftovers() {
		return get_posts(
			array(
				'post_type'   => array( 'page', 'attachment' ),
				'post_status' => array_keys( get_post_stati() ),
				'meta_key'    => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);
	}

	/**
	 * Create or rebuild the test page.
	 *
	 * @return array|WP_Error Page ID, section count and notes.
	 */
	public static function build() {
		$schema = self::schema();
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}

		self::$notes   = array();
		self::$counter = 0;
		self::$cache   = array();
		$rows          = self::plan( $schema );
		$page          = self::page();

		$id = wp_insert_post(
			array(
				'ID'            => $page ? $page->ID : 0,
				'post_type'     => 'page',
				'post_status'   => 'private',
				'post_title'    => self::TITLE,
				'post_name'     => 'qa-kit-flexible-layout-test',
				'page_template' => $schema['template'],
				'meta_input'    => array( self::META => 'test-page' ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		// Clear the old build first: rows only write the fields they use, so
		// values from a previous build would linger in hidden fields.
		delete_field( $schema['sections']['key'], $id );
		update_field( $schema['sections']['key'], $rows, $id );
		update_option( self::OPTION, $id, false );

		return array(
			'id'       => $id,
			'sections' => count( $rows ),
			'notes'    => array_values( self::$notes ),
		);
	}

	/**
	 * Delete the test page and placeholder media.
	 *
	 * @return int Number of items deleted.
	 */
	public static function delete() {
		$ids = self::leftovers();
		foreach ( $ids as $id ) {
			if ( 'attachment' === get_post_type( $id ) ) {
				wp_delete_attachment( $id, true );
			} else {
				wp_delete_post( $id, true );
			}
		}
		delete_option( self::OPTION );
		return count( $ids );
	}

	/**
	 * Form handler for the buttons on Tools > QA Kit.
	 */
	public static function handle() {
		if ( ! current_user_can( qa_kit_capability() ) ) {
			wp_die( 'You are not allowed to do that.', 403 );
		}
		check_admin_referer( self::ACTION );

		if ( isset( $_POST['qa_kit_do'] ) && 'delete' === sanitize_key( wp_unslash( $_POST['qa_kit_do'] ) ) ) {
			$count  = self::delete();
			$notice = array(
				'type'    => 'success',
				'message' => sprintf( 'Deleted the test page and its placeholder media (%d items).', $count ),
			);
		} else {
			$result = self::build();
			if ( is_wp_error( $result ) ) {
				$notice = array(
					'type'    => 'error',
					'message' => $result->get_error_message(),
				);
			} else {
				$notice = array(
					'type'    => $result['notes'] ? 'warning' : 'success',
					'message' => sprintf( 'Built the test page with %d sections.', $result['sections'] ),
					'notes'   => $result['notes'],
				);
			}
		}

		set_transient( self::notice_key(), $notice, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'tools.php?page=' . QA_Kit_Admin::SLUG ) . '#test-page' );
		exit;
	}

	/**
	 * The result of the last button press, shown once.
	 *
	 * @return array|null
	 */
	public static function pull_notice() {
		$notice = get_transient( self::notice_key() );
		if ( ! $notice ) {
			return null;
		}
		delete_transient( self::notice_key() );
		return $notice;
	}

	private static function notice_key() {
		return 'qa_kit_test_page_notice_' . get_current_user_id();
	}

	/**
	 * Every section of the page, as rows for update_field().
	 *
	 * @param array $schema From schema().
	 */
	private static function plan( $schema ) {
		$sections = self::shape( $schema['sections']['sub_fields'] );
		$title    = self::title_field( $sections );
		$skip     = $title ? array( $title['key'] ) : array();
		$base     = self::baseline( $sections );
		$layouts  = array_values( $schema['blocks']['layouts'] );
		$planned  = array();

		// Each block with typical content, built fresh on every call so IDs
		// made from sample text never repeat on the page.
		$typical = array();
		foreach ( $layouts as $layout ) {
			$shape     = self::shape( $layout['sub_fields'] );
			$typical[] = function () use ( $layout, $shape ) {
				return self::layout_row( $layout, $shape, self::baseline( $shape ) );
			};
		}
		$all = function () use ( $typical ) {
			return array_map( 'call_user_func', $typical );
		};
		$alone = function ( $make ) {
			return function ( $column ) use ( $make ) {
				return 0 === $column ? call_user_func( $make ) : array();
			};
		};

		// Blocks: each block on its own, then one section per block option.
		foreach ( $layouts as $layout ) {
			$shape = self::shape( $layout['sub_fields'] );
			foreach ( self::variants( $shape, self::baseline( $shape ) ) as $variant ) {
				$values    = $variant['values'];
				$planned[] = array(
					'group'   => 'Blocks',
					'title'   => 'Block — ' . $layout['label'] . ( $variant['label'] ? ' — ' . $variant['label'] : '' ),
					'values'  => $base,
					'content' => $alone(
						function () use ( $layout, $shape, $values ) {
							return array( self::layout_row( $layout, $shape, $values ) );
						}
					),
				);
			}
		}

		// Section options: one section per option value, with a different block
		// in each column so columns differ in height.
		foreach ( self::variants( $sections, $base, $skip ) as $variant ) {
			$planned[] = array(
				'group'   => 'Section options',
				'title'   => 'Section — ' . ( $variant['label'] ? $variant['label'] : 'Baseline' ),
				'values'  => $variant['values'],
				'content' => function ( $column ) use ( $typical ) {
					return array( call_user_func( $typical[ $column % count( $typical ) ] ) );
				},
			);
		}

		// Combined: every block stacked in one section, on each background (or
		// other unconditional choice) and spread across the most columns.
		$planned[] = array(
			'group'   => 'Combined',
			'title'   => 'Combined — All blocks',
			'values'  => $base,
			'content' => $alone( $all ),
		);
		foreach ( $sections['scope'] as $field ) {
			if ( ! in_array( $field['type'], self::CHOICE_TYPES, true ) || ! empty( $field['conditional_logic'] ) ) {
				continue;
			}
			foreach ( self::alternatives( $field, self::current( $field, $base ) ) as $alt ) {
				$values                  = $base;
				$values[ $field['key'] ] = $alt['value'];
				$planned[]               = array(
					'group'   => 'Combined',
					'title'   => 'Combined — All blocks — ' . $alt['label'],
					'values'  => $values,
					'content' => $alone( $all ),
				);
			}
		}
		$columns = $schema['columns'];
		if ( $columns && self::bounded( $columns ) && (int) $columns['max'] > 1 ) {
			$values                    = $base;
			$values[ $columns['key'] ] = (int) $columns['max'];
			$planned[]                 = array(
				'group'   => 'Combined',
				'title'   => sprintf( 'Combined — All blocks across %d columns', $columns['max'] ),
				'values'  => $values,
				'content' => function ( $column, $count ) use ( $typical ) {
					$rows = array();
					foreach ( $typical as $i => $make ) {
						if ( $i % $count === $column ) {
							$rows[] = call_user_func( $make );
						}
					}
					return $rows;
				},
			);
		}

		// Edge cases: blocks with nothing filled in, and blocks with far more
		// content than the design expects.
		$planned[] = array(
			'group'   => 'Edge cases',
			'title'   => 'Edge case — Empty fields',
			'values'  => $base,
			'content' => $alone(
				function () use ( $layouts ) {
					$rows = array();
					foreach ( $layouts as $layout ) {
						$rows[] = array( 'acf_fc_layout' => $layout['name'] );
					}
					return $rows;
				}
			),
		);
		$planned[] = array(
			'group'   => 'Edge cases',
			'title'   => 'Edge case — Long content, with a section title that keeps going well past the width of a single line on most screens',
			'values'  => $base,
			'content' => $alone(
				function () use ( $all ) {
					self::$long = true;
					$rows       = $all();
					self::$long = false;
					return $rows;
				}
			),
		);

		// Section titles become IDs, so keep them unique.
		$seen = array( 'contents' => true );
		foreach ( $planned as &$section ) {
			$unique = $section['title'];
			for ( $n = 2; isset( $seen[ sanitize_title( $unique ) ] ); $n++ ) {
				$unique = $section['title'] . ' (' . $n . ')';
			}
			$seen[ sanitize_title( $unique ) ] = true;
			$section['title']                  = $unique;
		}
		unset( $section );

		// Contents first, linking to every section.
		$contents = $title ? self::contents_row( $schema, $layouts, $planned ) : null;
		if ( $contents ) {
			array_unshift(
				$planned,
				array(
					'group'   => '',
					'title'   => 'Contents',
					'values'  => $base,
					'content' => $alone(
						function () use ( $contents ) {
							return array( $contents );
						}
					),
				)
			);
		}

		$rows = array();
		foreach ( $planned as $section ) {
			if ( $title ) {
				$section['values'][ $title['key'] ] = $section['title'];
			}
			$rows[] = self::section_row( $schema, $sections, $section );
		}
		return $rows;
	}

	/**
	 * A text block listing every section, or null if no block has a WYSIWYG field.
	 */
	private static function contents_row( $schema, $layouts, $planned ) {
		$html  = '<p>' . sprintf(
			'Built by QA Kit on %1$s from the <strong>%2$s</strong> field group. Each section changes one option from the baseline, and its title says which. Rebuild this page from Tools &gt; QA Kit after blocks or options change.',
			esc_html( wp_date( get_option( 'date_format' ) ) ),
			esc_html( $schema['group']['title'] )
		) . '</p>';
		$group = '';
		foreach ( $planned as $section ) {
			if ( $section['group'] !== $group ) {
				$html .= ( $group ? '</ul>' : '' ) . '<h3>' . esc_html( $section['group'] ) . '</h3><ul>';
				$group = $section['group'];
			}
			$html .= '<li><a href="#' . esc_attr( sanitize_title( $section['title'] ) ) . '">' . esc_html( $section['title'] ) . '</a></li>';
		}
		$html .= '</ul>';

		foreach ( $layouts as $layout ) {
			$shape = self::shape( $layout['sub_fields'] );
			foreach ( $shape['scope'] as $field ) {
				if ( 'wysiwyg' === $field['type'] ) {
					$values                  = self::baseline( $shape );
					$values[ $field['key'] ] = $html;
					return self::layout_row( $layout, $shape, $values );
				}
			}
		}
		return null;
	}

	/**
	 * One section row. $section['content'] returns the blocks for column $i of $count.
	 */
	private static function section_row( $schema, $shape, $section ) {
		$values  = $section['values'];
		$content = $section['content'];
		$columns = $schema['columns'];
		$blocks  = $schema['blocks']['key'];

		if ( ! $columns ) {
			$values[ $blocks ] = $content( 0, 1 );
		}

		$rows_for = function ( $field, $count ) use ( $columns, $content, $blocks ) {
			if ( ! $columns || $field['key'] !== $columns['key'] ) {
				return self::repeater_rows( $field, $count );
			}
			$column_shape = self::shape( $columns['sub_fields'] );
			$rows         = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$values            = self::baseline( $column_shape, $i );
				$values[ $blocks ] = $content( $i, $count );
				$rows[]            = self::build_row( $column_shape, $values );
			}
			return $rows;
		};

		return self::build_row( $shape, $values, $rows_for );
	}

	private static function layout_row( $layout, $shape, $values ) {
		return array( 'acf_fc_layout' => $layout['name'] ) + self::build_row( $shape, $values );
	}

	/**
	 * Rows for a repeater inside a block (buttons, dropdowns). Each row moves
	 * choices on by one, so three buttons cycle through the button styles.
	 */
	private static function repeater_rows( $field, $count ) {
		$shape = self::shape( $field['sub_fields'] );
		$rows  = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$rows[] = self::build_row( $shape, self::baseline( $shape, $i ) );
		}
		return $rows;
	}

	/**
	 * Turn planned values into the nested array update_field() takes, keyed by
	 * field key. Hidden fields are left out, as the ACF editor does. Repeaters
	 * are planned as a row count and built here with $rows_for.
	 */
	private static function build_row( $shape, $values, $rows_for = null, $fields = null ) {
		if ( ! $rows_for ) {
			$rows_for = function ( $field, $count ) {
				return self::repeater_rows( $field, $count );
			};
		}
		$row = array();
		foreach ( null === $fields ? $shape['fields'] : $fields as $field ) {
			if ( ! isset( $shape['index'][ $field['key'] ] ) || ! self::visible( $shape['index'][ $field['key'] ], $shape['index'], $values ) ) {
				continue;
			}
			if ( in_array( $field['type'], self::PARENT_TYPES, true ) ) {
				$row[ $field['key'] ] = self::build_row( $shape, $values, $rows_for, $field['sub_fields'] );
			} elseif ( isset( $values[ $field['key'] ] ) ) {
				$value                = $values[ $field['key'] ];
				$row[ $field['key'] ] = 'repeater' === $field['type'] ? $rows_for( $field, (int) $value ) : $value;
			}
		}
		return $row;
	}

	/**
	 * The baseline row, then one row per other value of each field.
	 *
	 * @param array    $shape From shape().
	 * @param array    $base  Baseline values.
	 * @param string[] $skip  Field keys not to vary.
	 * @return array[] Each with a 'label' (empty for the baseline) and 'values'.
	 */
	private static function variants( $shape, $base, $skip = array() ) {
		$out = array(
			array(
				'label'  => '',
				'values' => $base,
			),
		);
		foreach ( $shape['scope'] as $field ) {
			if ( in_array( $field['key'], $skip, true ) ) {
				continue;
			}
			foreach ( self::alternatives( $field, self::current( $field, $base ) ) as $alt ) {
				$values                  = $base;
				$values[ $field['key'] ] = $alt['value'];
				if ( self::reveal( $field, $shape['index'], $values ) ) {
					$out[] = array(
						'label'  => $alt['label'],
						'values' => $values,
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Values other than $current to try for a field, with labels for section titles.
	 *
	 * @return array[] Each with 'value' and 'label'.
	 */
	private static function alternatives( $field, $current ) {
		$label = $field['label'] ? $field['label'] : $field['name'];
		$type  = $field['type'];
		$out   = array();

		if ( in_array( $type, self::CHOICE_TYPES, true ) ) {
			foreach ( (array) self::setting( $field, 'choices', array() ) as $value => $text ) {
				if ( (string) $value !== (string) $current ) {
					$out[] = array(
						'value' => (string) $value,
						'label' => $label . ': ' . $text,
					);
				}
			}
		} elseif ( 'true_false' === $type ) {
			$out[] = array(
				'value' => $current ? 0 : 1,
				'label' => $label . ': ' . ( $current ? 'off' : 'on' ),
			);
		} elseif ( 'range' === $type ) {
			foreach ( array_unique( array( self::range_min( $field ), self::range_max( $field ) ) ) as $value ) {
				if ( (string) $value !== (string) $current ) {
					$out[] = array(
						'value' => $value,
						'label' => $label . ': ' . $value,
					);
				}
			}
		} elseif ( 'repeater' === $type && self::bounded( $field ) ) {
			for ( $n = max( 1, (int) $field['min'] ); $n <= (int) $field['max']; $n++ ) {
				if ( (int) $current !== $n ) {
					$out[] = array(
						'value' => $n,
						'label' => $label . ': ' . $n,
					);
				}
			}
		} elseif ( null === $current && empty( $field['required'] ) && in_array( $type, self::OPTIONAL_TYPES, true ) ) {
			$value = self::sample( $field );
			$out[] = array(
				'value' => $value,
				'label' => is_scalar( $value ) ? $label . ': ' . $value : 'with ' . $label,
			);
		} elseif ( 'text' === $type && empty( $field['required'] ) && null !== $current ) {
			$out[] = array(
				'value' => '',
				'label' => 'without ' . $label,
			);
		}

		return $out;
	}

	/**
	 * Values for a new row: defaults, sample content, and the first choice where
	 * there is no default. Row $i moves each choice on by $i.
	 */
	private static function baseline( $shape, $i = 0 ) {
		$overrides = apply_filters( 'qa_kit_test_page_baseline', array( 'show_section_title' => 1 ) );
		$values    = array();
		foreach ( $shape['scope'] as $field ) {
			$value = isset( $overrides[ $field['name'] ] ) ? $overrides[ $field['name'] ] : self::initial( $field, $i );
			if ( null !== $value ) {
				$values[ $field['key'] ] = $value;
			}
		}
		return $values;
	}

	private static function initial( $field, $i ) {
		$type = $field['type'];

		if ( in_array( $type, self::CHOICE_TYPES, true ) ) {
			$keys = array_map( 'strval', array_keys( (array) self::setting( $field, 'choices', array() ) ) );
			if ( ! $keys ) {
				return null;
			}
			$default = self::setting( $field, 'default_value', '' );
			$start   = array_search( (string) ( is_array( $default ) ? reset( $default ) : $default ), $keys, true );
			return $keys[ ( (int) $start + $i ) % count( $keys ) ];
		}
		if ( 'true_false' === $type ) {
			return ( (int) self::setting( $field, 'default_value', 0 ) + $i ) % 2;
		}
		if ( 'range' === $type ) {
			$default = self::setting( $field, 'default_value', '' );
			return '' !== (string) $default ? $default : self::range_min( $field );
		}
		if ( 'repeater' === $type ) {
			return self::bounded( $field ) ? max( 1, (int) $field['min'] ) : max( 3, (int) $field['min'] );
		}
		if ( 'flexible_content' === $type || in_array( $type, self::PARENT_TYPES, true ) ) {
			return null;
		}
		if ( empty( $field['required'] ) && in_array( $type, self::OPTIONAL_TYPES, true ) ) {
			return null;
		}
		return self::sample( $field );
	}

	/**
	 * Sample content for a field. Text is numbered so IDs made from it stay unique.
	 */
	private static function sample( $field ) {
		$label = strtolower( $field['label'] ? $field['label'] : $field['name'] );
		$text  = self::$long
			? sprintf( 'Sample %s %d with far more words than the design expects, to check that long text wraps instead of overflowing', $label, ++self::$counter )
			: sprintf( 'Sample %s %d', $label, ++self::$counter );

		switch ( $field['type'] ) {
			case 'text':
				return $text;
			case 'textarea':
				return self::is_embed( $field ) ? self::EMBED : $text . '.';
			case 'wysiwyg':
				if ( self::$long ) {
					return self::html( 'long' );
				}
				// Follow the field's own guidance, e.g. "Required: 1-2 sentences".
				return false !== stripos( (string) $field['instructions'], 'sentence' ) ? self::html( 'short' ) : self::html( 'rich' );
			case 'image':
				return self::fixture( self::$long ? 'qa-kit-portrait.jpg' : 'qa-kit-landscape.jpg' );
			case 'gallery':
				return array_filter( array( self::fixture( 'qa-kit-landscape.jpg' ), self::fixture( 'qa-kit-portrait.jpg' ) ) );
			case 'file':
				return self::fixture( 'qa-kit-sample.pdf' );
			case 'link':
				return array(
					'title'  => $text,
					'url'    => home_url( '/' ),
					'target' => '',
				);
			case 'url':
			case 'page_link':
				return home_url( '/' );
			case 'email':
				return 'qa@example.com';
			case 'oembed':
				return self::VIDEO_URL;
			case 'number':
				$value = 300;
				$min   = self::setting( $field, 'min', '' );
				$max   = self::setting( $field, 'max', '' );
				$value = '' !== (string) $min ? max( $value, (float) $min ) : $value;
				return '' !== (string) $max ? min( $value, (float) $max ) : $value;
			case 'range':
				return self::range_max( $field );
			case 'relationship':
				return self::posts( $field, 3 );
			case 'post_object':
				$ids = self::posts( $field, self::setting( $field, 'multiple', 0 ) ? 3 : 1 );
				return self::setting( $field, 'multiple', 0 ) ? $ids : ( $ids ? $ids[0] : null );
			case 'checkbox':
				$keys = array_keys( (array) self::setting( $field, 'choices', array() ) );
				return $keys ? array( (string) $keys[0] ) : null;
			case 'select':
			case 'button_group':
			case 'radio':
				return self::initial( $field, 0 );
			case 'true_false':
				return 1;
			case 'color_picker':
				return '#2271b1';
			case 'date_picker':
				return wp_date( 'Ymd' );
			case 'date_time_picker':
				return wp_date( 'Y-m-d H:i:s' );
			case 'time_picker':
				return '09:00:00';
		}

		self::note( sprintf( '"%s" (%s field) was left empty: QA Kit has no sample content for this field type.', $field['label'], $field['type'] ) );
		return null;
	}

	private static function html( $kind ) {
		$link = esc_url( home_url( '/' ) );
		$para = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>';

		if ( 'short' === $kind ) {
			return '<p>A short quote of a sentence or two, the length editors are asked to write.</p>';
		}
		if ( 'long' === $kind ) {
			$url = 'https://example.com/a-very-long-address/with-no-spaces-in-it/so-it-cannot-wrap-between-words/and-may-overflow-its-container-on-small-screens';
			return '<h2>A long heading that keeps going to check how headings wrap when an editor writes far more than the design expects</h2>'
				. "\n<p>An unbroken URL: <a href=\"" . esc_url( $url ) . '">' . esc_html( $url ) . "</a></p>\n"
				. str_repeat( $para . "\n", 4 );
		}
		return "<h2>Heading level two</h2>\n"
			. '<p>Body copy with <a href="' . $link . "\">a text link</a>, <strong>bold</strong> and <em>italic</em> words. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>\n"
			. "<h3>Heading level three</h3>\n"
			. "<ul>\n<li>A list item</li>\n<li>A longer list item that runs on so it wraps onto a second line in narrow columns</li>\n<li>A third list item</li>\n</ul>\n"
			. "<ol>\n<li>First step</li>\n<li>Second step</li>\n</ol>";
	}

	private static function is_embed( $field ) {
		return (bool) preg_match( '/embed|iframe/i', $field['name'] . ' ' . $field['label'] . ' ' . $field['instructions'] );
	}

	/**
	 * Recent published posts of the types a relationship or post object field allows.
	 */
	private static function posts( $field, $count ) {
		$types = array_filter( (array) self::setting( $field, 'post_type', array() ) );
		$types = $types ? $types : array( 'post' );
		$key   = 'posts:' . implode( ',', $types ) . ':' . $count;
		if ( ! isset( self::$cache[ $key ] ) ) {
			self::$cache[ $key ] = get_posts(
				array(
					'post_type'   => $types,
					'post_status' => 'publish',
					'numberposts' => $count,
					'fields'      => 'ids',
				)
			);
			if ( ! self::$cache[ $key ] ) {
				self::note( sprintf( '"%s" was left empty: there are no published %s to pick.', $field['label'], implode( ', ', $types ) ) );
			}
		}
		return self::$cache[ $key ];
	}

	/**
	 * Attachment ID for a bundled placeholder file, imported on first use.
	 */
	private static function fixture( $file ) {
		if ( isset( self::$cache[ $file ] ) ) {
			return self::$cache[ $file ];
		}

		$found = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'meta_key'    => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => $file, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);
		self::$cache[ $file ] = $found ? $found[0] : self::import( $file );
		return self::$cache[ $file ];
	}

	private static function import( $file ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( $file );
		if ( ! $tmp || ! copy( QA_KIT_DIR . 'assets/fixtures/' . $file, $tmp ) ) {
			self::note( sprintf( 'Could not copy %s to a temporary file.', $file ) );
			return 0;
		}
		$id = media_handle_sideload(
			array(
				'name'     => $file,
				'tmp_name' => $tmp,
			),
			0,
			'QA Kit placeholder'
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			self::note( sprintf( 'Could not import %s: %s', $file, $id->get_error_message() ) );
			return 0;
		}
		update_post_meta( $id, self::META, $file );
		if ( wp_attachment_is_image( $id ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', 'QA Kit placeholder image' );
		}
		return $id;
	}

	/**
	 * Change the fields a field's conditional logic depends on until it shows.
	 *
	 * @param array $field  Field to show.
	 * @param array $index  From shape().
	 * @param array $values Planned values, changed in place on success.
	 */
	private static function reveal( $field, $index, &$values ) {
		if ( self::visible( $field, $index, $values ) ) {
			return true;
		}
		if ( $field['_qa_group'] && ! self::reveal( $index[ $field['_qa_group'] ], $index, $values ) ) {
			return false;
		}
		if ( self::conditions_met( $field, $index, $values ) ) {
			return true;
		}
		foreach ( (array) $field['conditional_logic'] as $rules ) {
			$try = $values;
			self::apply_rules( (array) $rules, $index, $try );
			if ( self::visible( $field, $index, $try ) ) {
				$values = $try;
				return true;
			}
		}
		return false;
	}

	/**
	 * Set the fields in one AND group of conditional rules so the rules match.
	 */
	private static function apply_rules( $rules, $index, &$values ) {
		$ranges = array();
		foreach ( $rules as $rule ) {
			if ( ! isset( $rule['field'], $index[ $rule['field'] ] ) ) {
				continue;
			}
			$target = $index[ $rule['field'] ];
			$key    = $target['key'];
			$want   = isset( $rule['value'] ) ? (string) $rule['value'] : '';

			// Collect < and > from every rule, met or not, so a repeater that
			// must have more than 1 and fewer than 3 rows gets 2.
			if ( '<' === $rule['operator'] ) {
				$ranges[ $key ]['max'] = (int) ceil( (float) $want ) - 1;
				continue;
			}
			if ( '>' === $rule['operator'] ) {
				$ranges[ $key ]['min'] = (int) floor( (float) $want ) + 1;
				continue;
			}
			if ( self::rule_met( $rule, $index, $values ) ) {
				continue;
			}
			switch ( $rule['operator'] ) {
				case '==':
					$values[ $key ] = 'repeater' === $target['type'] ? (int) $want : $want;
					break;
				case '==contains':
					$values[ $key ] = 'checkbox' === $target['type'] ? array( $want ) : $want;
					break;
				case '!=':
					$values[ $key ] = self::other_value( $target, $want );
					break;
				case '==empty':
					$values[ $key ] = '';
					break;
				case '!=empty':
					$values[ $key ] = self::sample( $target );
					break;
			}
		}

		foreach ( $ranges as $key => $range ) {
			$target = $index[ $key ];
			$lo     = isset( $range['min'] ) ? $range['min'] : null;
			$hi     = isset( $range['max'] ) ? $range['max'] : null;
			if ( 'repeater' === $target['type'] ) {
				$lo = max( (int) $lo, 1, (int) $target['min'] );
				$hi = self::bounded( $target ) ? min( null === $hi ? PHP_INT_MAX : $hi, (int) $target['max'] ) : $hi;
			}
			$current = self::rule_value( $target, $values );
			$fits    = null !== $current && '' !== $current && ( null === $lo || $current >= $lo ) && ( null === $hi || $current <= $hi );
			if ( ! $fits ) {
				$values[ $key ] = null !== $lo ? $lo : $hi;
			}
		}
	}

	private static function other_value( $field, $not ) {
		if ( 'true_false' === $field['type'] ) {
			return $not ? 0 : 1;
		}
		foreach ( array_keys( (array) self::setting( $field, 'choices', array() ) ) as $value ) {
			if ( (string) $value !== $not ) {
				return (string) $value;
			}
		}
		return self::sample( $field );
	}

	private static function visible( $field, $index, $values ) {
		if ( ! self::conditions_met( $field, $index, $values ) ) {
			return false;
		}
		return ! $field['_qa_group'] || self::visible( $index[ $field['_qa_group'] ], $index, $values );
	}

	/**
	 * ACF conditional logic: any group of rules where every rule matches.
	 */
	private static function conditions_met( $field, $index, $values ) {
		if ( empty( $field['conditional_logic'] ) || ! is_array( $field['conditional_logic'] ) ) {
			return true;
		}
		foreach ( $field['conditional_logic'] as $rules ) {
			$met = true;
			foreach ( (array) $rules as $rule ) {
				if ( ! self::rule_met( $rule, $index, $values ) ) {
					$met = false;
					break;
				}
			}
			if ( $met ) {
				return true;
			}
		}
		return false;
	}

	private static function rule_met( $rule, $index, $values ) {
		// A rule pointing outside this row can't be checked here, so it doesn't block.
		if ( ! isset( $rule['field'], $index[ $rule['field'] ] ) ) {
			return true;
		}
		$value = self::rule_value( $index[ $rule['field'] ], $values );
		$want  = isset( $rule['value'] ) ? (string) $rule['value'] : '';
		$empty = null === $value || '' === $value || array() === $value;

		switch ( $rule['operator'] ) {
			case '==':
				return ! is_array( $value ) && (string) $value === $want;
			case '!=':
				return is_array( $value ) || (string) $value !== $want;
			case '==empty':
				return $empty;
			case '!=empty':
				return ! $empty;
			case '<':
				return ! $empty && (float) $value < (float) $want;
			case '>':
				return ! $empty && (float) $value > (float) $want;
			case '==contains':
				return is_array( $value ) ? in_array( $want, array_map( 'strval', $value ), true ) : false !== strpos( (string) $value, $want );
		}
		return true;
	}

	/**
	 * A field's value as conditional logic sees it: repeaters count their rows.
	 */
	private static function rule_value( $field, $values ) {
		$value = isset( $values[ $field['key'] ] ) ? $values[ $field['key'] ] : null;
		if ( in_array( $field['type'], array( 'repeater', 'flexible_content' ), true ) ) {
			return is_array( $value ) ? count( $value ) : (int) $value;
		}
		return $value;
	}

	private static function current( $field, $values ) {
		return isset( $values[ $field['key'] ] ) ? $values[ $field['key'] ] : null;
	}

	/**
	 * The fields of one row, plus a lookup by key (and by original key for
	 * cloned fields, which conditional logic still uses).
	 */
	private static function shape( $fields ) {
		$scope = self::scope( $fields );
		$index = array();
		foreach ( $scope as $field ) {
			$index[ $field['key'] ] = $field;
			if ( isset( $field['__key'] ) ) {
				$index[ $field['__key'] ] = $field;
			}
		}
		return array(
			'fields' => $fields,
			'scope'  => $scope,
			'index'  => $index,
		);
	}

	/**
	 * The fields of one row, with group sub fields flattened in because
	 * conditional logic can point across groups. Each field records its parent
	 * group in '_qa_group'. Fields that hold no value are skipped.
	 */
	private static function scope( $fields, $group = '' ) {
		$out = array();
		foreach ( (array) $fields as $field ) {
			if ( '' === $field['name'] || in_array( $field['type'], QA_Kit_Context::ACF_LAYOUT_TYPES, true ) ) {
				continue;
			}
			$field['_qa_group'] = $group;
			$out[]              = $field;
			if ( in_array( $field['type'], self::PARENT_TYPES, true ) && ! empty( $field['sub_fields'] ) ) {
				$out = array_merge( $out, self::scope( $field['sub_fields'], $field['key'] ) );
			}
		}
		return $out;
	}

	private static function first_of_type( $fields, $type ) {
		foreach ( self::scope( $fields ) as $field ) {
			if ( $type === $field['type'] ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * The text field that names each section: the first one outside a group.
	 */
	private static function title_field( $shape ) {
		foreach ( $shape['scope'] as $field ) {
			if ( 'text' === $field['type'] && '' === $field['_qa_group'] ) {
				return $field;
			}
		}
		return null;
	}

	private static function targets( $group, $template ) {
		foreach ( (array) $group['location'] as $rules ) {
			foreach ( (array) $rules as $rule ) {
				if ( 'page_template' === $rule['param'] && '==' === $rule['operator'] && $template === $rule['value'] ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function bounded( $field ) {
		return (int) self::setting( $field, 'max', 0 ) > 0;
	}

	private static function range_min( $field ) {
		$min = self::setting( $field, 'min', '' );
		return '' !== (string) $min ? $min : 0;
	}

	private static function range_max( $field ) {
		$max = self::setting( $field, 'max', '' );
		return '' !== (string) $max ? $max : 100;
	}

	private static function setting( $field, $name, $default ) {
		return isset( $field[ $name ] ) ? $field[ $name ] : $default;
	}

	private static function note( $message ) {
		self::$notes[ $message ] = $message;
	}
}
