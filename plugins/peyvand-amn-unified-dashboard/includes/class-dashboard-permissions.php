<?php
/**
 * ماتریس دسترسی و دروازه‌های ویژگی.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Permissions {

	const ACCESS_DASHBOARD    = 'dashboard';
	const ACCESS_CLINICAL     = 'clinical';
	const ACCESS_MANAGE       = 'manage';
	const ACCESS_APPOINTMENTS = 'appointments';
	const ACCESS_ASSESSMENTS  = 'assessments';
	const ACCESS_MESSAGES     = 'messages';
	const ACCESS_FOLLOWUP     = 'followup';

	/** تنظیمات پیش‌فرض داشبورد — هیچ تنظیم افزونه‌ی دیگری را بازنویسی نمی‌کند. */
	public static function default_settings() {
		return array(
			'per_page'              => 20,
			'enable_notes'          => true,
			'enable_notifications'  => true,
			'default_range_days'    => 30,
			'delete_data_on_uninstall' => false,
			'hidden_menus'          => array(),
		);
	}

	/**
	 * منوهای قابل پنهان‌سازی — لیست سفید و بسته.
	 *
	 * فقط همان منوهایی که در این جدول هستند قابل پنهان‌شدن‌اند؛ هیچ اسلاگ
	 * دلخواهی از مرورگر پذیرفته نمی‌شود و منوی خود داشبورد یا منوهای هستهٔ
	 * وردپرس هرگز در این فهرست نیستند.
	 *
	 * @return array<string,array{label:string,type:string,parent:string}>
	 */
	public static function menu_choices() {
		return array(
			'pas-core-overview'  => array(
				'label'  => __( 'زیرساخت پیوند امن (هستهٔ مشترک)', 'peyvand-amn-dashboard' ),
				'type'   => 'menu',
				'parent' => '',
			),
			'pas-case-files'     => array(
				'label'  => __( 'پرونده‌های بالینی', 'peyvand-amn-dashboard' ),
				'type'   => 'menu',
				'parent' => '',
			),
			'zdp-admin'          => array(
				'label'  => __( 'پنل زوج‌درمانی (پنل مراجعان)', 'peyvand-amn-dashboard' ),
				'type'   => 'menu',
				'parent' => '',
			),
			'psy-quiz'           => array(
				'label'  => __( 'آزمون‌های روان‌سنجی', 'peyvand-amn-dashboard' ),
				'type'   => 'menu',
				'parent' => '',
			),
			'pas-sms-bridge'     => array(
				'label'  => __( 'پل پیامک', 'peyvand-amn-dashboard' ),
				'type'   => 'menu',
				'parent' => '',
			),
			'aco-writer'         => array(
				'label'  => __( 'نویسندهٔ هوشمند', 'peyvand-amn-dashboard' ),
				'type'   => 'menu',
				'parent' => '',
			),
			'payvand-messages'   => array(
				'label'  => __( 'فرم ارتباط سریع (پیام‌ها)', 'peyvand-amn-dashboard' ),
				'type'   => 'menu',
				'parent' => '',
			),
			'zsm-smtp-mailer'    => array(
				'label'  => __( 'تنظیمات ارسال ایمیل (SMTP)', 'peyvand-amn-dashboard' ),
				'type'   => 'submenu',
				'parent' => 'options-general.php',
			),
			'zsm-mail-log'       => array(
				'label'  => __( 'گزارش ایمیل‌ها', 'peyvand-amn-dashboard' ),
				'type'   => 'submenu',
				'parent' => 'options-general.php',
			),
		);
	}

	/** منوهای پنهانِ ذخیره‌شده، محدود به لیست سفید. @return array<int,string> */
	public static function hidden_menus() {
		$saved   = self::setting( 'hidden_menus', array() );
		$saved   = is_array( $saved ) ? $saved : array();
		$allowed = array_keys( self::menu_choices() );
		$hidden  = array();

		foreach ( $saved as $slug ) {
			$slug = is_scalar( $slug ) ? sanitize_key( (string) $slug ) : '';

			if ( $slug !== '' && in_array( $slug, $allowed, true ) ) {
				$hidden[] = $slug;
			}
		}

		return array_values( array_unique( $hidden ) );
	}

	/**
	 * ذخیرهٔ پنهان‌سازی منوها از Action Bus داشبورد.
	 *
	 * فقط آپشن خود داشبورد نوشته می‌شود؛ هیچ تغییری در تنظیمات یا کپابیلیتی
	 * افزونهٔ دیگری انجام نمی‌شود. nonce، کپابیلیتی و ممیزی در لایهٔ عملیات است.
	 *
	 * @return array{ok:bool,message:string,id:int,redirect:string}
	 */
	public static function save_hidden_menus( array $post ) {
		$current = get_option( PAS_DASHBOARD_OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$current = array_merge( self::default_settings(), $current );

		$allowed   = array_keys( self::menu_choices() );
		$submitted = isset( $post['hidden_menus'] ) ? (array) $post['hidden_menus'] : array();
		$clean     = array();

		foreach ( $submitted as $slug ) {
			$slug = sanitize_key( is_scalar( $slug ) ? (string) $slug : '' );

			if ( $slug !== '' && in_array( $slug, $allowed, true ) ) {
				$clean[] = $slug;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		$current['hidden_menus'] = $clean;
		update_option( PAS_DASHBOARD_OPTION, $current );

		return array(
			'ok'       => true,
			'message'  => $clean
				? sprintf(
					/* translators: %d: number of hidden menus */
					__( '%d منو برای کاربران بدون دسترسی مدیریت پنهان شد.', 'peyvand-amn-dashboard' ),
					count( $clean )
				)
				: __( 'هیچ منویی پنهان نشد؛ همهٔ منوها دیده می‌شوند.', 'peyvand-amn-dashboard' ),
			'id'       => 0,
			'redirect' => PAS_Dashboard_Adapter_Ecosystem::module_url(),
		);
	}

	/**
	 * پنهان‌کردن منوهای انتخاب‌شده — فقط برای کاربران بدون `manage_options`.
	 *
	 * مدیر سایت همیشه همهٔ منوها را می‌بیند تا راه بازگشت به تنظیمات افزونه‌ها
	 * هرگز بسته نشود. این فقط نمایش منو را عوض می‌کند و هیچ کپابیلیتی را لغو
	 * نمی‌کند؛ کنترل دسترسی همچنان در خود افزونهٔ مالک است.
	 */
	public static function hide_menus() {
		if ( ! function_exists( 'remove_menu_page' ) || current_user_can( 'manage_options' ) ) {
			return;
		}

		$hidden = self::hidden_menus();

		if ( empty( $hidden ) ) {
			return;
		}

		$choices = self::menu_choices();

		foreach ( $hidden as $slug ) {
			if ( ! isset( $choices[ $slug ] ) ) {
				continue;
			}

			$choice = $choices[ $slug ];

			if ( 'submenu' === $choice['type'] && $choice['parent'] !== '' ) {
				remove_submenu_page( $choice['parent'], $slug );
			} else {
				remove_menu_page( $slug );
			}
		}
	}

	public static function settings() {
		$saved = get_option( PAS_DASHBOARD_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$settings = array_merge( self::default_settings(), $saved );

		/**
		 * فیلتر تنظیمات داشبورد (بدون ذخیره‌سازی).
		 */
		return (array) apply_filters( 'pas_dashboard_settings', $settings );
	}

	public static function setting( $key, $default = null ) {
		$settings = self::settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	public static function feature( $key ) {
		$value = self::setting( $key, false );

		return (bool) $value;
	}

	/**
	 * ماتریس ماژول‌ها: فقط همین‌جا تعریف می‌شود.
	 *
	 * @return array<string,array{label:string,access:string,feature:string,class:string}>
	 */
	public static function matrix() {
		return array(
			'overview'      => array(
				'label'   => __( 'نمای کلی', 'peyvand-amn-dashboard' ),
				// نمای کلی شمار پرونده و نام مراجع را نشان می‌دهد؛ پس همان
				// سطح دسترسی داده‌ی بالینی لازم است، نه فقط ورود به داشبورد.
				'access'  => self::ACCESS_CLINICAL,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Overview',
			),
			'patients'      => array(
				'label'   => __( 'مراجعان', 'peyvand-amn-dashboard' ),
				// نام و شماره‌ی مراجع داده‌ی حساس است: دسترسی بالینی الزامی است.
				'access'  => self::ACCESS_CLINICAL,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Patients',
			),
			'case_files'    => array(
				'label'   => __( 'پرونده‌ها', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_CLINICAL,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Case_Files',
			),
			'assessments'   => array(
				'label'   => __( 'آزمون‌ها', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_ASSESSMENTS,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Assessments',
			),
			'appointments'  => array(
				'label'   => __( 'نوبت‌ها', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_APPOINTMENTS,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Appointments',
			),
			'reports'       => array(
				'label'   => __( 'گزارش‌ها', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_CLINICAL,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Reports',
			),
			'communications' => array(
				'label'   => __( 'ارتباطات', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_MESSAGES,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Communications',
			),
			'followup'      => array(
				'label'   => __( 'پیگیری و نظرسنجی', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_FOLLOWUP,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Followup',
			),
			'notifications' => array(
				'label'   => __( 'اعلان‌ها', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_DASHBOARD,
				'feature' => 'enable_notifications',
				'class'   => 'PAS_Dashboard_Module_Notifications',
			),
			'ecosystem'     => array(
				'label'   => __( 'یکپارچگی‌ها', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_MANAGE,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Ecosystem',
			),
			'system_health' => array(
				'label'   => __( 'سلامت سیستم', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_MANAGE,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_System_Health',
			),
			'settings'      => array(
				'label'   => __( 'تنظیمات', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_MANAGE,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Settings',
			),
			'audit'         => array(
				'label'   => __( 'ممیزی', 'peyvand-amn-dashboard' ),
				'access'  => self::ACCESS_MANAGE,
				'feature' => '',
				'class'   => 'PAS_Dashboard_Module_Audit',
			),
		);
	}

	public static function access_allowed( $access ) {
		switch ( $access ) {
			case self::ACCESS_CLINICAL:
				return PAS_Dashboard_Security::can_view_clinical();
			case self::ACCESS_MANAGE:
				return PAS_Dashboard_Security::can_manage();
			case self::ACCESS_APPOINTMENTS:
				return is_user_logged_in() && (
					current_user_can( 'manage_options' )
					|| current_user_can( defined( 'PAS_DASHBOARD_CAP_APPOINTMENTS' ) ? PAS_DASHBOARD_CAP_APPOINTMENTS : 'manage_options' )
				);
			case self::ACCESS_ASSESSMENTS:
				return is_user_logged_in() && (
					current_user_can( 'manage_options' )
					|| current_user_can( defined( 'PAS_DASHBOARD_CAP_ASSESSMENTS' ) ? PAS_DASHBOARD_CAP_ASSESSMENTS : 'manage_options' )
				);
			case self::ACCESS_MESSAGES:
				return is_user_logged_in() && (
					current_user_can( 'manage_options' )
					|| current_user_can( defined( 'PAS_DASHBOARD_CAP_MESSAGES' ) ? PAS_DASHBOARD_CAP_MESSAGES : 'manage_options' )
				);
			case self::ACCESS_FOLLOWUP:
				return is_user_logged_in() && (
					current_user_can( 'manage_options' )
					|| current_user_can( defined( 'PAS_DASHBOARD_CAP_FOLLOWUP' ) ? PAS_DASHBOARD_CAP_FOLLOWUP : 'manage_options' )
				);
			case self::ACCESS_DASHBOARD:
			default:
				return PAS_Dashboard_Security::can_access_dashboard();
		}
	}

	public static function can( $module_id ) {
		$matrix = self::matrix();

		if ( ! isset( $matrix[ $module_id ] ) ) {
			return false;
		}

		$module = $matrix[ $module_id ];

		if ( ! self::access_allowed( $module['access'] ) ) {
			return false;
		}

		if ( $module['feature'] !== '' && ! self::feature( $module['feature'] ) ) {
			return false;
		}

		return true;
	}

	/** @return array<string,array> */
	public static function visible_modules() {
		$visible = array();

		foreach ( self::matrix() as $id => $module ) {
			if ( self::can( $id ) ) {
				$visible[ $id ] = $module;
			}
		}

		return $visible;
	}

	public static function default_module() {
		$visible = self::visible_modules();

		if ( isset( $visible['overview'] ) ) {
			return 'overview';
		}

		$keys = array_keys( $visible );

		return $keys ? (string) $keys[0] : '';
	}

	public static function grant_default_caps() {
		$admin = get_role( 'administrator' );

		if ( $admin && ! $admin->has_cap( PAS_DASHBOARD_CAP ) ) {
			$admin->add_cap( PAS_DASHBOARD_CAP );
		}

		if ( $admin && defined( 'PAS_DASHBOARD_CAP_APPOINTMENTS' ) && ! $admin->has_cap( PAS_DASHBOARD_CAP_APPOINTMENTS ) ) {
			$admin->add_cap( PAS_DASHBOARD_CAP_APPOINTMENTS );
		}

		foreach ( array( 'PAS_DASHBOARD_CAP_ASSESSMENTS', 'PAS_DASHBOARD_CAP_MESSAGES', 'PAS_DASHBOARD_CAP_FOLLOWUP' ) as $const ) {
			if ( $admin && defined( $const ) && ! $admin->has_cap( constant( $const ) ) ) {
				$admin->add_cap( constant( $const ) );
			}
		}

		// یکپارچگی‌ها (تست ایمیل، صف SMTP) فقط برای مدیر است، نه درمانگر.
		if ( $admin && defined( 'PAS_DASHBOARD_CAP_ECOSYSTEM' ) && ! $admin->has_cap( PAS_DASHBOARD_CAP_ECOSYSTEM ) ) {
			$admin->add_cap( PAS_DASHBOARD_CAP_ECOSYSTEM );
		}

		// نقش‌هایی که از قبل دسترسی پرونده‌های بالینی دارند، بدون گرفتن
		// manage_options، باید بتوانند وارد داشبورد شوند.
		if ( ! defined( 'PAS_CF_CAP' ) || ! function_exists( 'wp_roles' ) ) {
			return;
		}

		$roles = wp_roles();
		if ( ! $roles || empty( $roles->roles ) ) {
			return;
		}

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && $role->has_cap( PAS_CF_CAP ) && ! $role->has_cap( PAS_DASHBOARD_CAP ) ) {
				$role->add_cap( PAS_DASHBOARD_CAP );
			}

			if ( $role && $role->has_cap( PAS_CF_CAP ) && defined( 'PAS_DASHBOARD_CAP_APPOINTMENTS' ) && ! $role->has_cap( PAS_DASHBOARD_CAP_APPOINTMENTS ) ) {
				$role->add_cap( PAS_DASHBOARD_CAP_APPOINTMENTS );
			}

			foreach ( array( 'PAS_DASHBOARD_CAP_ASSESSMENTS', 'PAS_DASHBOARD_CAP_MESSAGES', 'PAS_DASHBOARD_CAP_FOLLOWUP' ) as $const ) {
				if ( $role && $role->has_cap( PAS_CF_CAP ) && defined( $const ) && ! $role->has_cap( constant( $const ) ) ) {
					$role->add_cap( constant( $const ) );
				}
			}
		}
	}
}
