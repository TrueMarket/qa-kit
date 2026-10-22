<?php
/**
 * Loads the markdown prompt library.
 *
 * Prompts come from qa-kit/prompts, plus a qa-prompts folder in the active
 * theme if one exists (for project-specific prompts), plus any folders added
 * with the 'qa_kit_prompt_dirs' filter. A later file with the same name wins,
 * so a theme can override a bundled prompt.
 */

defined( 'ABSPATH' ) || exit;

class QA_Kit_Prompts {

	public static function dirs() {
		$dirs = array( QA_KIT_DIR . 'prompts' );
		foreach ( array_unique( array( get_template_directory(), get_stylesheet_directory() ) ) as $theme_dir ) {
			if ( is_dir( $theme_dir . '/qa-prompts' ) ) {
				$dirs[] = $theme_dir . '/qa-prompts';
			}
		}
		return apply_filters( 'qa_kit_prompt_dirs', $dirs );
	}

	/**
	 * All prompts keyed by slug, sorted by their "order" front matter then title.
	 */
	public static function all() {
		$prompts = array();
		foreach ( self::dirs() as $dir ) {
			foreach ( (array) glob( trailingslashit( $dir ) . '*.md' ) as $file ) {
				$prompt = self::parse( $file );
				if ( $prompt ) {
					$prompts[ $prompt['slug'] ] = $prompt;
				}
			}
		}
		uasort(
			$prompts,
			function ( $a, $b ) {
				return array( $a['order'], $a['title'] ) <=> array( $b['order'], $b['title'] );
			}
		);
		return $prompts;
	}

	/**
	 * Parse a prompt file. Optional front matter supports "order" and "description".
	 *
	 * @param string $file Path.
	 */
	public static function parse( $file ) {
		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $raw ) {
			return null;
		}
		$raw  = str_replace( "\r\n", "\n", $raw );
		$meta = array();
		if ( preg_match( '/^---\n(.*?)\n---\n/s', $raw, $m ) ) {
			foreach ( explode( "\n", $m[1] ) as $line ) {
				if ( strpos( $line, ':' ) ) {
					list( $k, $v )          = array_map( 'trim', explode( ':', $line, 2 ) );
					$meta[ strtolower( $k ) ] = $v;
				}
			}
			$raw = substr( $raw, strlen( $m[0] ) );
		}
		$body  = trim( $raw );
		$title = preg_match( '/^#\s+(.+)$/m', $body, $t ) ? trim( $t[1] ) : basename( $file, '.md' );

		return array(
			'slug'        => basename( $file, '.md' ),
			'title'       => $title,
			'description' => isset( $meta['description'] ) ? $meta['description'] : '',
			'order'       => isset( $meta['order'] ) ? (int) $meta['order'] : 50,
			'body'        => $body,
			'file'        => $file,
		);
	}
}
