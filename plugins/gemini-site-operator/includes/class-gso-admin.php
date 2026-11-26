<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * منو، دارایی‌ها، ثبت تنظیمات و فرم‌های admin-post.
 */
class GSO_Admin {

	use GSO_Admin_Pages;
	use GSO_Admin_Ajax;

	const CAP = 'manage_options';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_post' ) );
		add_action( 'wp_ajax_gso_ajax', array( __CLASS__, 'ajax' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GSO_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'update_option_' . GSO_Settings::OPTION, array( __CLASS__, 'on_settings_saved' ), 10, 0 );
		GSO_SEO::init();
	}

	public static function menu(): void {
		add_menu_page(
			'Gemini Site Operator',
			'Gemini Operator',
			self::CAP,
			'gso-dashboard',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-superhero-alt',
			58
		);
		add_submenu_page( 'gso-dashboard', 'داشبورد اپراتور', 'داشبورد', self::CAP, 'gso-dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'gso-dashboard', 'صف اصلاح', 'صف اصلاح', self::CAP, 'gso-queue', array( __CLASS__, 'render_queue' ) );
		add_submenu_page( 'gso-dashboard', 'ابزارهای اپراتور', 'ابزارهای اپراتور', self::CAP, 'gso-tools', array( __CLASS__, 'render_tools' ) );
		add_submenu_page( 'gso-dashboard', 'تنظیمات', 'تنظیمات', self::CAP, 'gso-settings', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'gso-dashboard', 'گزارش عملیات', 'گزارش‌ها', self::CAP, 'gso-logs', array( __CLASS__, 'render_logs' ) );
	}

	/**
	 * بارگذاری CSS/JS فقط در صفحات افزونه.
	 *
	 * @param string $hook هوک صفحه.
	 */
	public static function assets( string $hook ): void {
		if ( false === strpos( $hook, 'gso-' ) ) {
			return;
		}
		wp_enqueue_style( 'gso-admin', GSO_URL . 'assets/css/admin.css', array(), GSO_VERSION );
		wp_enqueue_script( 'gso-admin', GSO_URL . 'assets/js/admin.js', array(), GSO_VERSION, true );
		wp_localize_script(
			'gso-admin',
			'GSO',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gso_ajax' ),
				'strings' => array(
					'working'  => 'در حال اجرا…',
					'confirm'  => 'مطمئن هستید؟ این تغییر با Snapshot ثبت می‌شود و قابل بازگشت است.',
					'error'    => 'خطا',
					'done'     => 'انجام شد',
					'copied'   => 'کپی شد',
				),
			)
		);
	}

	public static function register_settings(): void {
		register_setting(
			'gso_settings_group',
			GSO_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'GSO_Settings', 'sanitize' ),
				'default'           => GSO_Settings::defaults(),
			)
		);
	}

	public static function action_links( array $links ): array {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=gso-settings' ) ) . '">تنظیمات</a>';
		return $links;
	}

	/**
	 * زمان‌بندی مجدد پس از ذخیره تنظیمات.
	 */
	public static function on_settings_saved(): void {
		$next = wp_next_scheduled( 'gso_schedule_scan' );
		if ( $next ) {
			wp_unschedule_event( $next, 'gso_schedule_scan' );
		}
		GSO_Install::schedule_periodic_scan();
	}

	/**
	 * آدرس یک صفحه افزونه.
	 *
	 * @param string $page اسلاگ.
	 * @param array  $args پارامترها.
	 * @return string
	 */
	public static function page_url( string $page, array $args = array() ): string {
		$args = array_merge( array( 'page' => $page ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * ثبت پیام و ریدایرکت.
	 *
	 * @param string $page    صفحه.
	 * @param string $message پیام.
	 * @param string $type    success|error|warning.
	 * @param array  $args    پارامترهای اضافه.
	 */
	public static function redirect_notice( string $page, string $message, string $type = 'success', array $args = array() ): void {
		set_transient( 'gso_notice_' . get_current_user_id(), array( 'message' => $message, 'type' => $type ), 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( self::page_url( $page, $args ) );
		exit;
	}

	/**
	 * نمایش پیام ذخیره‌شده.
	 */
	public static function print_notice(): void {
		$key    = 'gso_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$class = 'notice-' . ( in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info' );
		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}

	/**
	 * هندلر فرم‌های admin-post (ابزارها، CSS، پاک‌سازی لاگ).
	 */
	public static function handle_admin_post(): void {
		if ( ! isset( $_POST['gso_action'] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی مجاز نیست.' );
		}
		$action = sanitize_key( (string) $_POST['gso_action'] );
		if ( ! check_admin_referer( 'gso_' . $action ) ) {
			wp_die( 'nonce نامعتبر است.' );
		}

		switch ( $action ) {
			case 'tool_generate':
				self::handle_tool_generate();
				break;
			case 'rewrite_prepare':
				self::handle_rewrite_prepare();
				break;
			case 'rewrite_apply':
				self::handle_rewrite_apply();
				break;
			case 'tool_meta':
				self::handle_tool_meta();
				break;
			case 'tool_alts':
				self::handle_tool_alts();
				break;
			case 'tool_links':
				self::handle_tool_links();
				break;
			case 'css_save':
				self::handle_css_save();
				break;
			case 'css_ai':
				self::handle_css_ai();
				break;
			case 'clear_logs':
				self::handle_clear_logs();
				break;
			case 'prune':
				GSO_Queue::prune();
				GSO_Snapshot::prune();
				self::redirect_notice( 'gso-logs', 'اقلام قدیمی صف و نسخه‌های پشتیبان پاک شدند.' );
				break;
		}
	}

	private static function handle_tool_generate(): void {
		$result = GSO_Generator::generate_content(
			array(
				'topic'        => sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) ),
				'post_type'    => sanitize_key( wp_unslash( $_POST['post_type'] ?? 'post' ) ),
				'status'       => sanitize_key( wp_unslash( $_POST['status'] ?? 'draft' ) ),
				'tone'         => sanitize_text_field( wp_unslash( $_POST['tone'] ?? '' ) ),
				'keywords'     => sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) ),
				'length'       => absint( $_POST['length'] ?? 900 ),
				'instructions' => sanitize_textarea_field( wp_unslash( $_POST['instructions'] ?? '' ) ),
			)
		);
		if ( is_wp_error( $result ) ) {
			self::redirect_notice( 'gso-tools', $result->get_error_message(), 'error' );
		}
		self::redirect_notice( 'gso-tools', 'محتوا ساخته شد: ' . $result['title'], 'success' );
	}

	private static function handle_rewrite_prepare(): void {
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( $post_id < 1 ) {
			self::redirect_notice( 'gso-tools', 'شناسه نوشته نامعتبر است.', 'error' );
		}
		$proposal = GSO_Generator::rewrite_post(
			$post_id,
			sanitize_textarea_field( wp_unslash( $_POST['instructions'] ?? '' ) ),
			sanitize_key( wp_unslash( $_POST['focus'] ?? 'all' ) )
		);
		if ( is_wp_error( $proposal ) ) {
			self::redirect_notice( 'gso-tools', $proposal->get_error_message(), 'error' );
		}
		set_transient(
			'gso_rewrite_' . get_current_user_id(),
			array( 'post_id' => $post_id, 'proposal' => $proposal ),
			30 * MINUTE_IN_SECONDS
		);
		self::redirect_notice( 'gso-tools', 'پیشنهاد بازنویسی آماده شد؛ پیش‌نمایش را بررسی و اعمال کنید.', 'success', array( 'preview' => $post_id ) );
	}

	private static function handle_rewrite_apply(): void {
		$pending = get_transient( 'gso_rewrite_' . get_current_user_id() );
		if ( ! is_array( $pending ) || empty( $pending['proposal'] ) ) {
			self::redirect_notice( 'gso-tools', 'پیشنهاد بازنویسی منقضی شده است؛ دوباره تولید کنید.', 'error' );
		}
		$result = GSO_Fixer::apply_rewrite( (int) $pending['post_id'], (array) $pending['proposal'] );
		delete_transient( 'gso_rewrite_' . get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			self::redirect_notice( 'gso-tools', $result->get_error_message(), 'error' );
		}
		self::redirect_notice( 'gso-tools', 'بازنویسی اعمال شد و نسخه قبلی برای بازگشت ثبت شد.' );
	}

	private static function handle_tool_meta(): void {
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( $post_id < 1 ) {
			self::redirect_notice( 'gso-tools', 'شناسه نوشته نامعتبر است.', 'error' );
		}
		$meta = GSO_Generator::meta_for_post( $post_id );
		if ( is_wp_error( $meta ) ) {
			self::redirect_notice( 'gso-tools', $meta->get_error_message(), 'error' );
		}
		$snapshot = GSO_Snapshot::capture(
			array(
				'issue_id'    => 0,
				'object_id'   => $post_id,
				'object_type' => (string) get_post_type( $post_id ),
				'label'       => 'پیش از تولید متای سئو',
				'payload'     => GSO_Snapshot::collect_state( (string) get_post_type( $post_id ), $post_id, 'meta' ),
			)
		);
		GSO_SEO::set_title( $post_id, $meta['meta_title'] );
		GSO_SEO::set_description( $post_id, $meta['meta_description'] );
		GSO_Logger::log( 'success', 'tool.meta', sprintf( 'متای سئوی «%s» به‌روزرسانی شد (نسخه #%d).', get_the_title( $post_id ), $snapshot ), array( 'post_id' => $post_id ) );
		self::redirect_notice( 'gso-tools', 'عنوان و توضیحات متا به‌روزرسانی شد (قابل بازگشت).' );
	}

	private static function handle_tool_alts(): void {
		$limit = min( 10, max( 1, absint( $_POST['limit'] ?? 5 ) ) );
		$ids   = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'fields'         => 'ids',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => '_wp_attachment_image_alt',
						'value' => '',
					),
				),
			)
		);
		if ( empty( $ids ) ) {
			self::redirect_notice( 'gso-tools', 'تصویری بدون Alt پیدا نشد.', 'warning' );
		}

		$done = 0;
		$fail = 0;
		foreach ( $ids as $id ) {
			$alt = GSO_Generator::alt_for_image( (int) $id, '', get_the_title( (int) $id ) );
			if ( is_wp_error( $alt ) ) {
				++$fail;
				continue;
			}
			GSO_Snapshot::capture(
				array(
					'issue_id'    => 0,
					'object_id'   => (int) $id,
					'object_type' => 'attachment',
					'label'       => 'پیش از تنظیم Alt',
					'payload'     => array( 'meta' => array( '_wp_attachment_image_alt' => get_post_meta( (int) $id, '_wp_attachment_image_alt', true ) ) ),
				)
			);
			update_post_meta( (int) $id, '_wp_attachment_image_alt', $alt );
			++$done;
			usleep( 300000 );
		}
		GSO_Logger::log( 'success', 'tool.alts', sprintf( 'تولید Alt تصاویر: %d موفق، %d ناموفق.', $done, $fail ) );
		self::redirect_notice( 'gso-tools', sprintf( '%d تصویر Alt گرفت و %d مورد ناموفق بود.', $done, $fail ), $fail > 0 ? 'warning' : 'success' );
	}

	private static function handle_tool_links(): void {
		$post_id = absint( $_POST['post_id'] ?? 0 );
		$result  = GSO_Generator::internal_links( $post_id );
		if ( is_wp_error( $result ) ) {
			self::redirect_notice( 'gso-tools', $result->get_error_message(), 'error' );
		}
		set_transient( 'gso_links_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
		self::redirect_notice( 'gso-tools', sprintf( '%d پیشنهاد لینک داخلی آماده شد.', count( $result['links'] ) ), 'success', array( 'links' => $post_id ) );
	}

	private static function handle_css_save(): void {
		if ( ! GSO_Settings::get( 'css_snippets' ) ) {
			self::redirect_notice( 'gso-tools', 'ویرایش CSS غیرفعال است؛ ابتدا از تنظیمات فعال کنید.', 'error' );
		}
		$css    = (string) wp_unslash( $_POST['css'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$result = GSO_Fixer::apply_css( $css );
		if ( is_wp_error( $result ) ) {
			self::redirect_notice( 'gso-tools', $result->get_error_message(), 'error' );
		}
		self::redirect_notice( 'gso-tools', 'CSS سفارشی ذخیره شد (نسخه قبلی قابل بازگشت است).' );
	}

	private static function handle_css_ai(): void {
		if ( ! GSO_Settings::get( 'css_snippets' ) ) {
			self::redirect_notice( 'gso-tools', 'ویرایش CSS غیرفعال است؛ ابتدا از تنظیمات فعال کنید.', 'error' );
		}
		$instruction = sanitize_textarea_field( wp_unslash( $_POST['instruction'] ?? '' ) );
		$css         = GSO_Generator::css_from_instruction( $instruction, (string) get_option( 'gso_custom_css', '' ) );
		if ( is_wp_error( $css ) ) {
			self::redirect_notice( 'gso-tools', $css->get_error_message(), 'error' );
		}
		GSO_Fixer::apply_css( $css );
		self::redirect_notice( 'gso-tools', 'CSS تولید و اعمال شد (نسخه قبلی قابل بازگشت است).' );
	}

	private static function handle_clear_logs(): void {
		GSO_Logger::clear();
		self::redirect_notice( 'gso-logs', 'گزارش عملیات پاک شد.' );
	}
}
