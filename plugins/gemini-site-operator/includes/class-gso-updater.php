<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * به‌روزرسانی افزونه از مخزن GitHub (بدون انتشار در wordpress.org).
 *
 * نسخهٔ تازه از هدر فایل اصلی روی شاخهٔ main خوانده می‌شود و بستهٔ نصبی
 * از dist/gemini-site-operator-<version>.zip همان شاخه پیشنهاد می‌شود.
 * نتیجه شش ساعت کش می‌شود تا هر بار بارگذاری پیشخوان به GitHub درخواست نزند.
 *
 * برای فورک: ثابت‌های GSO_GITHUB_REPO و GSO_GITHUB_BRANCH را در wp-config.php تعریف کنید.
 */
class GSO_Updater {

	const SLUG  = 'gemini-site-operator';
	const CACHE = 'gso_github_release';
	const TTL   = 6 * HOUR_IN_SECONDS;

	/**
	 * ثبت فیلترها.
	 */
	public static function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ), 99 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'auto_update' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush_cache' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'enable_auto_update' ) );
		add_action( 'activated_plugin', array( __CLASS__, 'flush_cache_after_activation' ) );
	}

	/**
	 * مخزن GitHub (owner/repo).
	 *
	 * @return string
	 */
	public static function repo(): string {
		return defined( 'GSO_GITHUB_REPO' ) ? (string) GSO_GITHUB_REPO : 'hparsian/peyvand-amn-plugins';
	}

	/**
	 * شاخه‌ای که نسخهٔ منتشرشده روی آن است.
	 *
	 * @return string
	 */
	public static function branch(): string {
		return defined( 'GSO_GITHUB_BRANCH' ) ? (string) GSO_GITHUB_BRANCH : 'main';
	}

	/**
	 * مسیر افزونه نسبت به پوشهٔ افزونه‌ها.
	 *
	 * @return string
	 */
	public static function plugin_file(): string {
		return plugin_basename( GSO_FILE );
	}

	/**
	 * روشن‌کردن به‌روزرسانی خودکار برای همین افزونه در وردپرس.
	 */
	public static function enable_auto_update(): void {
		$auto = (array) get_site_option( 'auto_update_plugins', array() );
		$file = self::plugin_file();
		if ( in_array( $file, $auto, true ) ) {
			return;
		}
		$auto[] = $file;
		update_site_option( 'auto_update_plugins', array_values( array_unique( $auto ) ) );
	}

	/**
	 * فیلتر خودکار برای شرایطی که گزینهٔ سایت در دسترس نیست (مثلاً چندسایتی).
	 *
	 * @param bool   $update وضعیت پیش‌فرض.
	 * @param object $item   قلم به‌روزرسانی.
	 * @return bool
	 */
	public static function auto_update( $update, $item ) {
		if ( is_object( $item ) && isset( $item->plugin ) && self::plugin_file() === $item->plugin ) {
			return (bool) apply_filters( 'gso_github_auto_update', true );
		}
		return $update;
	}

	/**
	 * افزودن نسخهٔ GitHub به ترنزینت به‌روزرسانی‌ها.
	 *
	 * @param mixed $transient ترنزینت.
	 * @return mixed
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		if ( is_admin() && ! empty( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::refresh();
		}
		$file      = self::plugin_file();
		$installed = ( ! empty( $transient->checked ) && is_array( $transient->checked ) && isset( $transient->checked[ $file ] ) )
			? (string) $transient->checked[ $file ]
			: self::installed_version();
		$release   = self::release();
		if ( null === $release || ! version_compare( $release['version'], $installed, '>' ) ) {
			return $transient;
		}

		$local = self::local_headers();
		$item  = (object) array(
			'id'            => 'github.com/' . self::repo(),
			'slug'          => self::SLUG,
			'plugin'        => $file,
			'new_version'   => $release['version'],
			'url'           => $release['url'],
			'package'       => $release['package'],
			'requires'      => $local['Requires'],
			'requires_php'  => $local['RequiresPHP'],
			'tested'        => $local['Tested'],
			'icons'         => array(),
			'banners'       => array(),
			'compatibility' => new stdClass(),
		);

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$transient->response[ $file ] = $item;
		return $transient;
	}

	/**
	 * اطلاعات «مشاهده جزئیات» در صفحهٔ به‌روزرسانی‌ها.
	 *
	 * @param mixed  $result نتیجهٔ پیش‌فرض.
	 * @param string $action نوع درخواست.
	 * @param object $args   آرگومان‌ها.
	 * @return mixed
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$release = self::release();
		if ( null === $release ) {
			return $result;
		}
		$local = self::local_headers();

		return (object) array(
			'name'          => $local['Name'],
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => $local['Author'],
			'homepage'      => $release['url'],
			'requires'      => $local['Requires'],
			'tested'        => $local['Tested'],
			'requires_php'  => $local['RequiresPHP'],
			'download_link' => $release['package'],
			'last_updated'  => gmdate( 'Y-m-d' ),
			'sections'      => array(
				'description' => wpautop( esc_html( $local['Description'] ) ),
				'changelog'   => self::changelog_html(),
			),
		);
	}

	/**
	 * پاک‌کردن کش پس از هر به‌روزرسانی افزونه.
	 *
	 * @param object $upgrader   آپگریدر.
	 * @param array  $hook_extra دادهٔ هوک.
	 */
	public static function flush_cache( $upgrader, $hook_extra ): void {
		if ( ! is_array( $hook_extra ) || 'update' !== ( $hook_extra['action'] ?? '' ) ) {
			return;
		}
		delete_site_transient( self::CACHE );
		self::enable_auto_update();
	}

	/**
	 * پاک‌کردن کش پس از فعال‌سازی (مثلاً با آپلود دستی بستهٔ تازه).
	 *
	 * @param string $plugin مسیر افزونهٔ فعال‌شده.
	 */
	public static function flush_cache_after_activation( string $plugin ): void {
		if ( self::plugin_file() === $plugin ) {
			delete_site_transient( self::CACHE );
		}
	}

	/**
	 * آخرین نسخهٔ موجود روی GitHub.
	 *
	 * @return array{version:string,package:string,url:string}|null
	 */
	private static function release(): ?array {
		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return isset( $cached['version'] ) ? $cached : null;
		}

		$version = self::remote_version();
		if ( null === $version ) {
			set_site_transient( self::CACHE, array(), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$package = sprintf(
			'https://raw.githubusercontent.com/%s/%s/dist/%s-%s.zip',
			self::repo(),
			self::branch(),
			self::SLUG,
			rawurlencode( $version )
		);

		// اگر بستهٔ متناظر در مخزن نبود، به‌روزرسانی پیشنهاد نمی‌شود تا کاربر
		// با نصب شکست‌خورده یا بستهٔ نامنطبق روبه‌رو نشود.
		if ( ! self::package_available( $package ) ) {
			GSO_Logger::log(
				'warning',
				'updater.package',
				sprintf( 'نسخهٔ %s در GitHub هست ولی بستهٔ dist/%s-%s.zip پیدا نشد؛ به‌روزرسانی پیشنهاد نشد.', $version, self::SLUG, $version )
			);
			set_site_transient( self::CACHE, array(), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$release = array(
			'version' => $version,
			'package' => $package,
			'url'     => 'https://github.com/' . self::repo(),
		);
		set_site_transient( self::CACHE, $release, self::TTL );
		return $release;
	}

	/**
	 * خواندن نسخه از هدر فایل اصلی روی شاخهٔ GitHub.
	 *
	 * @return string|null
	 */
	private static function remote_version(): ?string {
		$url = sprintf(
			'https://raw.githubusercontent.com/%s/%s/plugins/%s/%s.php',
			self::repo(),
			self::branch(),
			self::SLUG,
			self::SLUG
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 3,
				'headers'     => array( 'Accept' => 'text/plain' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( ! preg_match( '/^\s*\*?\s*Version:\s*([0-9][0-9A-Za-z.\-]*)\s*$/mi', $body, $match ) ) {
			return null;
		}
		$version = trim( $match[1] );
		return '' !== $version ? $version : null;
	}

	/**
	 * آیا بستهٔ پیشنهادی قابل دریافت است؟
	 *
	 * @param string $url نشانی بسته.
	 * @return bool
	 */
	private static function package_available( string $url ): bool {
		$head = wp_remote_head( $url, array( 'timeout' => 8, 'redirection' => 3 ) );
		if ( ! is_wp_error( $head ) ) {
			$code = (int) wp_remote_retrieve_response_code( $head );
			if ( 200 === $code ) {
				return true;
			}
			if ( in_array( $code, array( 404, 410 ), true ) ) {
				return false;
			}
		}

		// برخی هاست‌ها HEAD را می‌بندند؛ با درخواست یک‌بایتی بررسی می‌کنیم.
		$probe = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'headers'     => array( 'Range' => 'bytes=0-0' ),
			)
		);
		if ( is_wp_error( $probe ) ) {
			// شبکه در دسترس نیست؛ آپگریدر خودش در زمان نصب خطا را گزارش می‌کند.
			return true;
		}
		return ! in_array( (int) wp_remote_retrieve_response_code( $probe ), array( 404, 410 ), true );
	}

	/**
	 * هدرهای محلی افزونه.
	 *
	 * @return array
	 */
	private static function local_headers(): array {
		$headers = get_file_data(
			GSO_FILE,
			array(
				'Name'        => 'Plugin Name',
				'Description' => 'Description',
				'Author'      => 'Author',
				'Version'     => 'Version',
				'Requires'    => 'Requires at least',
				'Tested'      => 'Tested up to',
				'RequiresPHP' => 'Requires PHP',
			)
		);
		return array(
			'Name'        => (string) ( $headers['Name'] ?? self::SLUG ),
			'Description' => (string) ( $headers['Description'] ?? '' ),
			'Author'      => (string) ( $headers['Author'] ?? '' ),
			'Version'     => (string) ( $headers['Version'] ?? GSO_VERSION ),
			'Requires'    => (string) ( $headers['Requires'] ?? '6.6' ),
			'Tested'      => (string) ( $headers['Tested'] ?? '' ),
			'RequiresPHP' => (string) ( $headers['RequiresPHP'] ?? '8.1' ),
		);
	}

	/**
	 * نسخهٔ نصب‌شده از هدر فایل اصلی افزونه.
	 *
	 * @return string
	 */
	public static function installed_version(): string {
		$headers = self::local_headers();
		return (string) $headers['Version'];
	}

	/**
	 * آخرین نسخهٔ منتشرشده — برای نمایش در تنظیمات و دکمهٔ به‌روزرسانی.
	 *
	 * @return array{version:string,package:string,url:string}|null
	 */
	public static function latest_release(): ?array {
		return self::release();
	}

	/**
	 * پاک‌کردن کش انتشار تا «بررسی به‌روزرسانی» واقعاً به GitHub برود.
	 */
	public static function refresh(): void {
		delete_site_transient( self::CACHE );
	}

	/**
	 * نصب آخرین نسخه با آپ‌گریدر رسمی وردپرس.
	 *
	 * بسته همان dist مخزن است؛ مسیر نصب همان مسیر «پیشخوان ← به‌روزرسانی‌ها»
	 * است، فقط از داخل خود افزونه هم در دسترس می‌شود.
	 *
	 * @return true|WP_Error
	 */
	public static function run_update() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return new WP_Error( 'gso_upgrade_cap', 'کپابیلیتی به‌روزرسانی افزونه‌ها برای شما فعال نیست.' );
		}

		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return new WP_Error( 'gso_upgrade_locked', 'نصب و تغییر فایل‌ها در این سایت غیرفعال است (DISALLOW_FILE_MODS).' );
		}

		self::refresh();
		delete_site_transient( 'update_plugins' );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
			$skin_file = ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
			if ( file_exists( $skin_file ) ) {
				require_once $skin_file;
			}
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- بهترین تلاش؛ روی هاست‌های محدود بی‌اثر است.
		}

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		wp_update_plugins();

		$file      = self::plugin_file();
		$transient = get_site_transient( 'update_plugins' );

		if ( ! is_object( $transient ) || empty( $transient->response[ $file ] ) ) {
			$release = self::latest_release();
			if ( is_array( $release ) && version_compare( $release['version'], self::installed_version(), '>' ) ) {
				if ( ! is_object( $transient ) ) {
					$transient = new stdClass();
				}
				$transient = self::inject_update( $transient );
				set_site_transient( 'update_plugins', $transient );
			}
		}

		if ( ! is_object( $transient ) || empty( $transient->response[ $file ] ) ) {
			return new WP_Error( 'gso_upgrade_none', 'نسخهٔ تازه‌تری برای نصب پیدا نشد؛ ابتدا «بررسی به‌روزرسانی» را بزنید.' );
		}

		$upgrader = class_exists( 'Automatic_Upgrader_Skin' )
			? new Plugin_Upgrader( new Automatic_Upgrader_Skin() )
			: new Plugin_Upgrader( new WP_Upgrader_Skin() );

		$result = $upgrader->upgrade( $file );

		if ( true === $result ) {
			GSO_Logger::log( 'success', 'updater.apply', 'افزونه از مخزن GitHub به آخرین نسخه به‌روزرسانی شد.' );
			return true;
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$message = '';
		if ( isset( $upgrader->skin->result ) && is_wp_error( $upgrader->skin->result ) ) {
			$message = $upgrader->skin->result->get_error_message();
		}
		if ( '' === $message && ! empty( $upgrader->skin->errors ) && is_array( $upgrader->skin->errors ) ) {
			$message = implode( ' ', array_map( 'wp_strip_all_tags', $upgrader->skin->errors ) );
		}
		if ( '' === $message ) {
			$message = 'به‌روزرسانی انجام نشد؛ دسترسی نوشتن فایل یا اتصال به گیت‌هاب را بررسی کنید.';
		}

		return new WP_Error( 'gso_upgrade_failed', $message );
	}

	/**
	 * تغییرات نسخه به‌صورت HTML برای مودال جزئیات.
	 *
	 * @return string
	 */
	private static function changelog_html(): string {
		$file = GSO_DIR . 'CHANGELOG.md';
		if ( ! is_readable( $file ) ) {
			return '';
		}
		$markdown = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$markdown = mb_substr( $markdown, 0, 6000 );
		$html     = esc_html( $markdown );
		$html     = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $html );
		$html     = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $html );
		$html     = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
		return wpautop( $html );
	}
}
