<?php
/**
 * آزمون بارگذاری (load smoke test) — همه‌ی فایل‌های کلاس افزونه با PHP واقعی
 * بارگذاری می‌شوند تا خطای نحوی، کلاس تکراری، یا وابستگی حل‌نشده پیدا شود.
 *
 * این آزمون وردپرس را شبیه‌سازی می‌کند (فقط توابع حداقلی) و هیچ عملیاتی روی
 * پایگاه‌داده انجام نمی‌دهد. اجرا:
 *
 *   php tests/php/test-load.php
 */

define( 'ABSPATH', __DIR__ . '/' );

define( 'PAS_DASHBOARD_VERSION', '2.0.0' );
define( 'PAS_DASHBOARD_FILE', __DIR__ . '/plugin.php' );
define( 'PAS_DASHBOARD_DIR', __DIR__ . '/' );
define( 'PAS_DASHBOARD_URL', 'http://example.test/' );
define( 'PAS_DASHBOARD_SLUG', 'pas-unified-dashboard' );
define( 'PAS_DASHBOARD_CAP', 'pas_dashboard_access' );
define( 'PAS_DASHBOARD_OPTION', 'pas_dashboard_settings' );
define( 'PAS_DASHBOARD_DB_VERSION', '1.0.0' );
define( 'PAS_DASHBOARD_MAX_PER_PAGE', 100 );

/* ── حداقل شبیه‌سازی وردپرس (فقط برای بارگذاری، نه اجرای منطق) ───────── */

function add_action() { return true; }
function add_filter() { return true; }
function do_action() { return null; }
function apply_filters( $tag, $value ) { return $value; }
function has_action() { return false; }
function has_filter() { return false; }
function plugin_dir_path( $file ) { return rtrim( dirname( $file ), '/' ) . '/'; }
function plugin_dir_url() { return 'http://example.test/'; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function wp_roles() { return null; }
function get_option( $name, $default = false ) { return $default; }
function update_option() { return true; }
function current_user_can() { return false; }
function is_user_logged_in() { return false; }
function get_current_user_id() { return 0; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $v ) { return (string) $v; }
function esc_html__( $v ) { return (string) $v; }
function esc_attr__( $v ) { return (string) $v; }
function __( $v ) { return (string) $v; }
function _e( $v ) { echo $v; }
function wp_die( $message ) { throw new RuntimeException( 'wp_die: ' . $message ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_unslash( $v ) { return $v; }
function check_ajax_referer() { return true; }
function wp_verify_nonce() { return 1; }
function wp_create_nonce() { return 'nonce'; }
function admin_url( $path = '' ) { return 'http://example.test/wp-admin/' . $path; }
function rest_url( $path = '' ) { return 'http://example.test/wp-json/' . $path; }
function home_url( $path = '' ) { return 'http://example.test/' . $path; }
function wp_nonce_field() { return null; }
function submit_button() { return null; }
function checked() { return null; }
function selected() { return null; }
function disabled() { return null; }
function number_format_i18n( $n ) { return (string) $n; }
function size_format( $n ) { return (string) $n; }
function human_time_diff() { return '1 hour'; }
function current_time( $type ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ?? time() ); }
function get_userdata( $id ) { return null; }
function get_avatar_url() { return ''; }
function wp_kses_post( $v ) { return (string) $v; }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function wp_salt() { return 'test-salt'; }
function wp_generate_password() { return 'test-password'; }
function wp_hash() { return 'test-hash'; }
function wp_mail() { return true; }
function wp_schedule_event() { return true; }
function wp_next_scheduled() { return false; }
function wp_clear_scheduled_hook() { return true; }
function add_query_arg() { return 'http://example.test/'; }
function remove_query_arg() { return 'http://example.test/'; }
function paginate_links() { return ''; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_kses() { return ''; }
function wp_get_current_user() { return null; }
function wp_safe_redirect() { return true; }
function wp_redirect() { return true; }
function nocache_headers() { return null; }
function status_header() { return null; }
function wp_send_json_success() { return null; }
function wp_send_json_error() { return null; }

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->errors[ $code ][] = array( 'message' => $message, 'data' => $data );
		}

		public function get_error_code() { return key( $this->errors ); }
		public function get_error_message() {
			$code = $this->get_error_code();
			return $code && isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0]['message'] : '';
		}
	}
}

/* ── بارگذاری همه‌ی کلاس‌ها ─────────────────────────────────────────── */

$root    = dirname( __DIR__, 2 ) . '/plugins/peyvand-amn-unified-dashboard';
$core    = glob( $root . '/includes/class-dashboard-*.php' );
$modules = array_merge( array( $root . '/includes/modules.php' ), glob( $root . '/includes/modules/*.php' ) );
sort( $core );
sort( $modules );

$passed = 0;
$failed = 0;

function check( $label, $condition ) {
	global $passed, $failed;

	if ( $condition ) {
		$passed++;
		echo "PASS  $label\n";
	} else {
		$failed++;
		echo "FAIL  $label\n";
	}
}

$declared = array();

foreach ( array_merge( $core, $modules ) as $file ) {
	$source = file_get_contents( $file );
	preg_match_all( '/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/m', $source, $matches );

	require_once $file;

	$short = basename( $file );
	check( "loads $short", true );

	foreach ( $matches[1] as $name ) {
		$declared[] = $name;
		check( "declares $name", class_exists( $name, false ) );
	}
}

check( 'loaded every include file', count( $core ) + count( $modules ) >= 25, count( $core ) . '+' . count( $modules ) );
check( 'module classes extend the base module', is_subclass_of( 'PAS_Dashboard_Module_Overview', 'PAS_Dashboard_Module' ) );
check( 'base module is abstract', ( new ReflectionClass( 'PAS_Dashboard_Module' ) )->isAbstract() );
check( 'validation rejects negative id', PAS_Dashboard_Validation::id( '-5' ) === 0 );
check( 'validation accepts positive id', PAS_Dashboard_Validation::id( '17' ) === 17 );
check( 'security exposes notes_writable', method_exists( 'PAS_Dashboard_Security', 'notes_writable' ) );
check( 'security exposes authorized_case', method_exists( 'PAS_Dashboard_Security', 'authorized_case' ) );
check( 'bridge exposes has_core', method_exists( 'PAS_Dashboard_Core_Bridge', 'has_core' ) );
check( 'bridge exposes notes', method_exists( 'PAS_Dashboard_Core_Bridge', 'notes' ) );

echo "\nPASSED: $passed\nFAILED: $failed\n";
exit( $failed > 0 ? 1 : 0 );
