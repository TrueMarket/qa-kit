<?php
/**
 * Tools > QA Kit: prompt library and ACF audit.
 */

defined( 'ABSPATH' ) || exit;

class QA_Kit_Admin {

	const SLUG = 'qa-kit';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( QA_KIT_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_management_page( 'QA Kit', 'QA Kit', qa_kit_capability(), self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=' . self::SLUG ) ) . '">Prompt library</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		if ( 'tools_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'qa-kit-admin', QA_KIT_URL . 'assets/css/admin.css', array(), QA_KIT_VERSION );
		wp_enqueue_script( 'qa-kit-admin', QA_KIT_URL . 'assets/js/admin.js', array(), QA_KIT_VERSION, true );
	}

	public static function render() {
		if ( ! current_user_can( qa_kit_capability() ) ) {
			return;
		}
		$prompts   = QA_Kit_Prompts::all();
		$run_audit = isset( $_GET['qa_kit_audit'] ) && check_admin_referer( 'qa_kit_audit' ); // phpcs:ignore WordPress.Security.NonceVerification
		$audit_url = wp_nonce_url( admin_url( 'tools.php?page=' . self::SLUG . '&qa_kit_audit=1#acf-audit' ), 'qa_kit_audit' );
		?>
		<div class="wrap qa-kit">
			<h1>QA Kit</h1>
			<p class="qa-kit-lead">
				Front-end checks run from the <strong>QA Kit</strong> menu in the admin bar on any page of the site.
				This page holds the prompt library for code-level QA with an AI agent, plus an ACF audit.
				Environment: <code><?php echo esc_html( wp_get_environment_type() ); ?></code>.
				Remove this plugin before launch.
			</p>

			<h2>How to use the prompts</h2>
			<ol class="qa-kit-steps">
				<li><strong>AI agent in the repo (best):</strong> open the project in your IDE and ask Claude code to: <code>Read wp-content/plugins/qa-kit/prompts/acf.md and run it</code>.</li>
				<li><strong>Chat:</strong> click <em>Copy prompt</em>, paste into the chat, then attach or paste the template files it asks for.</li>
				<li><strong>Page findings:</strong> on any front-end page, use <em>QA Kit &gt; Copy report for AI</em> and paste that report alongside the code.</li>
			</ol>

			<h2>Prompt library</h2>
			<p class="description">
				Bundled prompts live in <code><?php echo esc_html( QA_Kit_Context::relative_path( QA_KIT_DIR . 'prompts' ) ); ?></code>.
				Add project-specific prompts (or override a bundled one by file name) in a <code>qa-prompts</code> folder in the theme.
			</p>
			<div class="qa-kit-prompts">
				<?php foreach ( $prompts as $prompt ) : ?>
					<article class="qa-kit-card">
						<h3><?php echo esc_html( $prompt['title'] ); ?></h3>
						<?php if ( $prompt['description'] ) : ?>
							<p><?php echo esc_html( $prompt['description'] ); ?></p>
						<?php endif; ?>
						<p class="qa-kit-meta"><code><?php echo esc_html( $prompt['slug'] ); ?>.md</code></p>
						<div class="qa-kit-actions">
							<button type="button" class="button button-primary" data-copy="#qa-kit-prompt-<?php echo esc_attr( $prompt['slug'] ); ?>">Copy prompt</button>
						</div>
						<details>
							<summary>Preview</summary>
							<textarea readonly rows="14" id="qa-kit-prompt-<?php echo esc_attr( $prompt['slug'] ); ?>"><?php echo esc_textarea( $prompt['body'] ); ?></textarea>
						</details>
					</article>
				<?php endforeach; ?>
				<?php if ( ! $prompts ) : ?>
					<p>No prompts found.</p>
				<?php endif; ?>
			</div>

			<h2 id="acf-audit">ACF wiring audit</h2>
			<p class="description">
				Compares every registered ACF field with <code>get_field()</code>, <code>the_field()</code>, <code>have_rows()</code> and similar calls in the theme.
				Add plugin folders with the <code>qa_kit_scan_paths</code> filter.
			</p>
			<?php
			if ( $run_audit ) {
				self::render_audit( QA_Kit_ACF_Audit::run() );
			} else {
				echo '<p><a class="button button-primary" href="' . esc_url( $audit_url ) . '">Run ACF audit</a></p>';
			}
			?>
		</div>
		<?php
	}

	private static function render_audit( $result ) {
		if ( ! empty( $result['error'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $result['error'] ) . '</p></div>';
			return;
		}
		$s = $result['summary'];
		?>
		<ul class="qa-kit-summary">
			<li class="missing"><strong><?php echo (int) $s['missing']; ?></strong> missing</li>
			<li class="unused"><strong><?php echo (int) $s['unused']; ?></strong> unused</li>
			<li class="mentioned"><strong><?php echo (int) $s['mentioned']; ?></strong> mentioned only</li>
			<li class="used"><strong><?php echo (int) $s['used']; ?></strong> used</li>
			<li><?php echo (int) $result['files']; ?> files scanned</li>
		</ul>
		<p>
			<button type="button" class="button" data-copy="#qa-kit-audit-md">Copy audit as markdown</button>
			<label class="qa-kit-toggle"><input type="checkbox" id="qa-kit-show-used"> Show used fields</label>
		</p>
		<textarea hidden id="qa-kit-audit-md"><?php echo esc_textarea( QA_Kit_ACF_Audit::to_markdown( $result ) ); ?></textarea>
		<table class="widefat striped qa-kit-audit">
			<thead><tr><th>Status</th><th>Field</th><th>Type</th><th>Group</th><th>Note</th></tr></thead>
			<tbody>
			<?php foreach ( $result['rows'] as $row ) : ?>
				<tr class="status-<?php echo esc_attr( $row['status'] . ( $row['flag'] ? ' flagged' : '' ) ); ?>">
					<td><span class="qa-kit-pill <?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $row['status'] ); ?></span></td>
					<td><code><?php echo esc_html( $row['field'] ); ?></code><?php echo $row['label'] ? '<br><span class="description">' . esc_html( $row['label'] ) . '</span>' : ''; ?></td>
					<td><?php echo esc_html( $row['type'] ); ?></td>
					<td><?php echo esc_html( $row['group'] ); ?></td>
					<td><?php echo esc_html( $row['note'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
