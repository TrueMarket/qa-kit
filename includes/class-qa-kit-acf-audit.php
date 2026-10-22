<?php
/**
 * ACF wiring audit: compares registered fields with field calls in theme code.
 */

defined( 'ABSPATH' ) || exit;

class QA_Kit_ACF_Audit {

	const CALLS = 'get_field|the_field|get_sub_field|the_sub_field|have_rows|get_field_object|get_sub_field_object|update_field|update_sub_field|delete_field';

	/**
	 * Folders to scan. Defaults to the active theme (and parent).
	 * Add custom plugins or mu-plugins with the 'qa_kit_scan_paths' filter.
	 */
	public static function paths() {
		$paths = array_unique( array( get_stylesheet_directory(), get_template_directory() ) );
		return apply_filters( 'qa_kit_scan_paths', $paths );
	}

	/**
	 * Run the audit.
	 *
	 * @return array{rows: array, summary: array, error?: string}
	 */
	public static function run() {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return array(
				'rows'    => array(),
				'summary' => array(),
				'error'   => 'ACF is not active.',
			);
		}

		$defined = array();
		foreach ( QA_Kit_Context::acf_field_groups() as $group ) {
			foreach ( $group['fields'] as $field ) {
				if ( in_array( $field['type'], QA_Kit_Context::ACF_LAYOUT_TYPES, true ) || '' === $field['name'] ) {
					continue;
				}
				$field['group'] = $group['title'];
				$defined[]      = $field;
			}
		}

		$scan  = self::scan();
		$calls = $scan['calls'];   // name => [ 'file:line', ... ].
		$text  = $scan['strings']; // Every quoted string in scanned files => true.
		$rows  = array();
		$known = array();

		// Same name defined twice with different types.
		$types = array();
		foreach ( $defined as $field ) {
			$types[ $field['name'] ][ $field['type'] ][] = $field['group'];
		}

		foreach ( $defined as $field ) {
			$known[ $field['name'] ] = true;
			$known[ $field['key'] ]  = true;

			$where = array_merge(
				isset( $calls[ $field['name'] ] ) ? $calls[ $field['name'] ] : array(),
				isset( $calls[ $field['key'] ] ) ? $calls[ $field['key'] ] : array()
			);

			if ( $where ) {
				$status = 'used';
				$note   = implode( ', ', array_slice( array_unique( $where ), 0, 3 ) ) . ( count( $where ) > 3 ? ' +' . ( count( $where ) - 3 ) : '' );
			} elseif ( isset( $text[ $field['name'] ] ) || isset( $text[ $field['key'] ] ) ) {
				$status = 'mentioned';
				$note   = 'Name appears as a string but not in an ACF function call. Check it is read via get_fields(), a parent group array, or a block.';
			} else {
				$status = 'unused';
				$note   = 'Defined but never referenced in scanned code.';
			}

			$conflict = count( $types[ $field['name'] ] ) > 1;
			if ( $conflict ) {
				$note .= ' Name is also defined with type(s): ' . implode( ', ', array_diff( array_keys( $types[ $field['name'] ] ), array( $field['type'] ) ) ) . '.';
			}

			$rows[] = array(
				'status' => $status,
				'field'  => $field['name'],
				'label'  => $field['label'],
				'type'   => $field['type'],
				'group'  => $field['group'] . ( $field['parent'] ? ' > ' . $field['parent'] : '' ),
				'note'   => $note,
				'flag'   => $conflict,
			);
		}

		foreach ( $calls as $name => $where ) {
			if ( isset( $known[ $name ] ) ) {
				continue;
			}
			$rows[] = array(
				'status' => 'missing',
				'field'  => $name,
				'label'  => '',
				'type'   => '',
				'group'  => '',
				'note'   => 'Called in code but no field with this name exists: ' . implode( ', ', array_slice( array_unique( $where ), 0, 3 ) ),
				'flag'   => false,
			);
		}

		$order = array(
			'missing'   => 0,
			'unused'    => 1,
			'mentioned' => 2,
			'used'      => 3,
		);
		usort(
			$rows,
			function ( $a, $b ) use ( $order ) {
				return array( $order[ $a['status'] ], $a['group'], $a['field'] ) <=> array( $order[ $b['status'] ], $b['group'], $b['field'] );
			}
		);

		$summary = array_fill_keys( array_keys( $order ), 0 );
		foreach ( $rows as $row ) {
			$summary[ $row['status'] ]++;
		}

		return array(
			'rows'    => $rows,
			'summary' => $summary,
			'files'   => $scan['files'],
		);
	}

	/**
	 * Find ACF calls with literal field names, and collect all quoted strings.
	 */
	private static function scan() {
		$calls   = array();
		$strings = array();
		$count   = 0;
		$pattern = '/\b(?:' . self::CALLS . ')\s*\(\s*([\'"])([A-Za-z0-9_\-]+)\1/';

		foreach ( self::paths() as $path ) {
			if ( ! is_dir( $path ) ) {
				continue;
			}
			$iterator = new RecursiveIteratorIterator(
				new RecursiveCallbackFilterIterator(
					new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
					function ( $file ) {
						return ! in_array( $file->getFilename(), array( 'node_modules', 'vendor', '.git', 'acf-json' ), true );
					}
				)
			);
			foreach ( $iterator as $file ) {
				if ( ! preg_match( '/\.(php|twig|blade\.php)$/', $file->getFilename() ) ) {
					continue;
				}
				$contents = file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( false === $contents ) {
					continue;
				}
				$count++;
				$relative = QA_Kit_Context::relative_path( $file->getPathname() );

				if ( preg_match_all( $pattern, $contents, $matches, PREG_OFFSET_CAPTURE ) ) {
					foreach ( $matches[2] as $match ) {
						$line                  = substr_count( substr( $contents, 0, $match[1] ), "\n" ) + 1;
						$calls[ $match[0] ][]  = basename( $relative ) . ':' . $line;
					}
				}
				if ( preg_match_all( '/([\'"])([A-Za-z0-9_\-]+)\1/', $contents, $quoted ) ) {
					foreach ( $quoted[2] as $string ) {
						$strings[ $string ] = true;
					}
				}
			}
		}

		return array(
			'calls'   => $calls,
			'strings' => $strings,
			'files'   => $count,
		);
	}

	/**
	 * Audit result as markdown, for pasting into an AI prompt.
	 *
	 * @param array $result Result of run().
	 */
	public static function to_markdown( $result ) {
		if ( ! empty( $result['error'] ) ) {
			return $result['error'];
		}
		$out   = array();
		$out[] = '## ACF wiring audit';
		$out[] = '';
		$out[] = sprintf(
			'%d missing, %d unused, %d mentioned only, %d used (%d files scanned)',
			$result['summary']['missing'],
			$result['summary']['unused'],
			$result['summary']['mentioned'],
			$result['summary']['used'],
			$result['files']
		);
		$out[] = '';
		$out[] = '| Status | Field | Type | Group | Note |';
		$out[] = '| --- | --- | --- | --- | --- |';
		foreach ( $result['rows'] as $row ) {
			if ( 'used' === $row['status'] && ! $row['flag'] ) {
				continue;
			}
			$out[] = '| ' . implode( ' | ', array_map( array( __CLASS__, 'md_cell' ), array( $row['status'], $row['field'], $row['type'], $row['group'], $row['note'] ) ) ) . ' |';
		}
		return implode( "\n", $out );
	}

	private static function md_cell( $value ) {
		return str_replace( array( '|', "\n" ), array( '\|', ' ' ), (string) $value );
	}
}
