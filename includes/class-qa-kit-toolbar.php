<?php
/**
 * Front-end QA toolbar: admin bar menu, assets and page data.
 */

defined( 'ABSPATH' ) || exit;

class QA_Kit_Toolbar {

	/** @var string Template file WordPress chose for this request. */
	private static $template = '';

	/** @var array PHP notices and warnings captured during the request. */
	private static $php_errors = array();

	/** @var callable|null Previous error handler. */
	private static $previous_handler = null;

	public static function init() {
		self::$previous_handler = set_error_handler( array( __CLASS__, 'capture_php_error' ) );

		add_filter( 'template_include', array( __CLASS__, 'capture_template' ), PHP_INT_MAX );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 999 );
		add_action( 'wp_head', array( __CLASS__, 'early_capture_script' ), 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'page_data' ), 1 );

		// Breakpoint preview frames load the page without the admin bar or toolbar.
		if ( self::is_preview_frame() ) {
			add_filter( 'show_admin_bar', '__return_false' );
		}
	}

	private static function can_use() {
		return ! is_admin() && is_user_logged_in() && current_user_can( qa_kit_capability() ) && ! self::is_preview_frame();
	}

	private static function is_preview_frame() {
		return isset( $_GET['qa-kit-frame'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Record notices, warnings and deprecations, then let normal handling continue.
	 */
	public static function capture_php_error( $errno, $errstr, $errfile = '', $errline = 0 ) {
		if ( count( self::$php_errors ) < 50 && ( error_reporting() & $errno ) ) {
			$types              = array(
				E_WARNING         => 'Warning',
				E_NOTICE          => 'Notice',
				E_USER_WARNING    => 'Warning',
				E_USER_NOTICE     => 'Notice',
				E_DEPRECATED      => 'Deprecated',
				E_USER_DEPRECATED => 'Deprecated',
			);
			self::$php_errors[] = array(
				'type'    => isset( $types[ $errno ] ) ? $types[ $errno ] : 'Error ' . $errno,
				'message' => $errstr,
				'file'    => QA_Kit_Context::relative_path( $errfile ),
				'line'    => $errline,
			);
		}
		if ( self::$previous_handler ) {
			return call_user_func( self::$previous_handler, $errno, $errstr, $errfile, $errline );
		}
		return false;
	}

	public static function capture_template( $template ) {
		self::$template = $template;
		return $template;
	}

	public static function admin_bar( $bar ) {
		if ( ! self::can_use() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'qa-kit',
				'title' => '<span class="ab-icon dashicons dashicons-yes-alt" style="margin-top:2px"></span><span class="ab-label">QA Kit</span>',
				'href'  => '#qa-kit',
			)
		);
		$items = array(
			'all'         => 'Run all checks',
			'a11y'        => 'Accessibility',
			'structure'   => 'Structure and semantics',
			'seo'         => 'SEO',
			'acf'         => 'ACF fields',
			'images'      => 'Images',
			'performance' => 'Performance',
			'errors'      => 'Errors',
			'breakpoints' => 'Breakpoints',
			'report'      => 'Copy report for AI',
		);
		foreach ( $items as $id => $title ) {
			$bar->add_node(
				array(
					'parent' => 'qa-kit',
					'id'     => 'qa-kit-' . $id,
					'title'  => $title,
					'href'   => '#qa-kit-' . $id,
				)
			);
		}
		$bar->add_node(
			array(
				'parent' => 'qa-kit',
				'id'     => 'qa-kit-prompts',
				'title'  => 'Prompt library',
				'href'   => admin_url( 'tools.php?page=qa-kit' ),
			)
		);
	}

	/**
	 * Captures JS errors and failed resources from the top of the page,
	 * before the toolbar script loads.
	 */
	public static function early_capture_script() {
		if ( ! self::can_use() ) {
			return;
		}
		?>
<script id="qa-kit-capture">
(function(){var e=window.__qaKitErrors=[];function p(o){if(e.length<100)e.push(o);}
window.addEventListener('error',function(ev){var t=ev.target;if(t&&t!==window&&(t.src||t.href)){p({type:'resource',message:'Failed to load '+t.tagName.toLowerCase(),source:t.src||t.href});}else if(!/^ResizeObserver loop/.test(ev.message||'')){p({type:'js',message:ev.message,source:(ev.filename||'')+':'+(ev.lineno||0)});}},true);
window.addEventListener('unhandledrejection',function(ev){p({type:'promise',message:String(ev.reason&&ev.reason.message||ev.reason)});});
var ce=console.error;console.error=function(){try{p({type:'console',message:Array.prototype.map.call(arguments,String).join(' ')});}catch(x){}return ce.apply(console,arguments);};})();
</script>
		<?php
	}

	public static function enqueue() {
		if ( ! self::can_use() ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_script( 'qa-kit-toolbar', QA_KIT_URL . 'assets/js/toolbar.js', array(), QA_KIT_VERSION, true );
	}

	/**
	 * Page data printed before footer scripts, so every template part is known.
	 */
	public static function page_data() {
		if ( ! self::can_use() ) {
			return;
		}

		$theme_dirs = array_unique( array( wp_normalize_path( get_stylesheet_directory() ), wp_normalize_path( get_template_directory() ) ) );
		$parts      = array();
		foreach ( get_included_files() as $file ) {
			$file = wp_normalize_path( $file );
			foreach ( $theme_dirs as $dir ) {
				if ( 0 === strpos( $file, $dir . '/' ) ) {
					$parts[] = QA_Kit_Context::relative_path( $file );
				}
			}
		}

		$data = array(
			'version'   => QA_KIT_VERSION,
			'cssUrl'    => QA_KIT_URL . 'assets/css/toolbar.css?ver=' . QA_KIT_VERSION,
			'axeUrl'    => QA_KIT_URL . 'assets/vendor/axe.min.js?ver=' . QA_KIT_VERSION,
			'promptsUrl'  => admin_url( 'tools.php?page=qa-kit' ),
			'env'         => wp_get_environment_type(),
			'blogPublic'  => (bool) get_option( 'blog_public' ),
			'breakpoints' => array_map( 'intval', apply_filters( 'qa_kit_breakpoints', array( 375, 768, 1024, 1440 ) ) ),
			'page'        => array(
				'object'   => QA_Kit_Context::current_object_label(),
				'template' => self::$template ? QA_Kit_Context::relative_path( self::$template ) : '',
				'files'    => array_values( array_unique( $parts ) ),
				'theme'    => wp_get_theme()->get( 'Name' ),
			),
			'acf'       => QA_Kit_Context::page_acf_fields(),
			'phpErrors' => self::$php_errors,
		);

		wp_add_inline_script( 'qa-kit-toolbar', 'window.qaKitData = ' . wp_json_encode( $data ) . ';', 'before' );
	}
}
