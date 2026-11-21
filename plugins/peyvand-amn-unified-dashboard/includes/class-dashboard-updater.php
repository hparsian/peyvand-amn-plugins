<?php
/**
 * به‌روزرسانی از مخزن گیت‌هاب — موتور بررسی و تزریق آپدیت.
 *
 * مخزن، بسته‌های آمادهٔ نصب را در پوشهٔ `dist/` نگه می‌دارد؛ هر فایل
 * `<slug>-<version>.zip` ریشه‌ای هم‌نام با پوشهٔ افزونه دارد و همان چیزی است
 * که نصب دستی وردپرس انتظار دارد. این کلاس فهرست همان پوشه را از GitHub
 * می‌خواند، نسخهٔ هر افزونهٔ اکوسیستم را با نسخهٔ نصب‌شده مقایسه می‌کند و
 * نتیجه را به وردپرس می‌دهد؛ به این ترتیب آپدیت در «پیشخوان ← به‌روزرسانی‌ها»
 * و در همین داشبورد دیده می‌شود و دیگر لازم نیست بسته دستی دانلود و نصب شود.
 *
 * قواعد امنیتی:
 *   - فقط افزونه‌های فهرست سفید همین کلاس بررسی می‌شوند؛ هیچ اسلاگ دلخواهی از
 *     مخزن یا مرورگر نمی‌تواند پوشهٔ افزونهٔ دیگری را هدف آپدیت کند.
 *   - بسته فقط از میزبان‌های خود گیت‌هاب و با HTTPS پذیرفته می‌شود.
 *   - فهرست ۶ ساعت کش می‌شود؛ خطا ۱۵ دقیقه کش می‌شود تا سهمیهٔ API نسوزد.
 *   - توکن اختیاری است (مخزن عمومی بدون توکن کار می‌کند)، هرگز نمایش داده
 *     نمی‌شود و فقط به میزبان‌های همان مخزن فرستاده می‌شود.
 *   - هیچ عملیات نوشتنی این کلاس بدون nonce/کپابیلیتی اجرا نمی‌شود؛ ورودی
 *     نوشتنی فقط از Action Bus داشبورد می‌آید.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Updater {

	const REPO_DEFAULT   = 'hparsian/peyvand-amn-plugins';
	const BRANCH_DEFAULT = 'main';
	const DIST_PATH      = 'dist';
	const CACHE_KEY      = 'pas_dashboard_update_catalog';
	const CACHE_TTL      = 21600; // ۶ ساعت
	const FAILURE_TTL    = 900;   // ۱۵ دقیقه

	/** @var bool جلوگیری از بازگشت بازگشتی هنگام تزریق */
	private static $injecting = false;

	/**
	 * افزونه‌های اکوسیستم — فهرست سفید بسته (اسلاگ ← فایل اصلی).
	 *
	 * فقط همین فایل‌ها می‌توانند هدف آپدیت باشند. افزودن افزونهٔ تازه یعنی
	 * افزودن یک سطر اینجا، تا هیچ ورودی مخزن نتواند مسیر دلخواهی را بازنویسی
	 * کند.
	 *
	 * @return array<string,string>
	 */
	public static function plugins() {
		return array(
			'peyvand-amn-unified-dashboard' => 'peyvand-amn-unified-dashboard/peyvand-amn-unified-dashboard.php',
			'pivand-amn-core'               => 'pivand-amn-core/pivand-amn-core.php',
			'pivand-amn-case-file'          => 'pivand-amn-case-file/pivand-amn-case-file.php',
			'pivand-amn-client-portal'      => 'pivand-amn-client-portal/pivand-amn-client-portal.php',
			'pivand-amn-quiz-enterprise'    => 'pivand-amn-quiz-enterprise/pivand-amn-quiz-enterprise.php',
			'pivand-amn-sms-bridge'         => 'pivand-amn-sms-bridge/pivand-amn-sms-bridge.php',
			'pivand-amn-smtp-mailer'        => 'pivand-amn-smtp-mailer/pivand-amn-smtp-mailer.php',
			'pivand-amn-2fa'                => 'pivand-amn-2fa/pivand-amn-2fa.php',
			'pivand-amn-content-writer'     => 'pivand-amn-content-writer/pivand-amn-content-writer.php',
			'piwand-daily-feed'             => 'piwand-daily-feed/piwand-daily-feed.php',
			'payvand-contact'               => 'payvand-contact/payvand-contact.php',
			'payvand-amn-ui-kit'            => 'payvand-amn-ui-kit/payvand-amn-ui-kit.php',
		);
	}

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_updates' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 20, 3 );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'auto_update' ), 20, 2 );
		add_filter( 'http_request_args', array( __CLASS__, 'github_request_args' ), 20, 2 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_upgrade' ), 10, 2 );
	}

	/* ── تنظیمات ───────────────────────────────────────────────────────── */

	public static function repo() {
		$repo = (string) PAS_Dashboard_Permissions::setting( 'updates_repo', self::REPO_DEFAULT );
		$repo = trim( $repo );

		return preg_match( '#^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$#', $repo ) ? $repo : self::REPO_DEFAULT;
	}

	public static function branch() {
		$branch = trim( (string) PAS_Dashboard_Permissions::setting( 'updates_branch', self::BRANCH_DEFAULT ) );

		return preg_match( '#^[A-Za-z0-9._/-]{1,100}$#', $branch ) ? $branch : self::BRANCH_DEFAULT;
	}

	/**
	 * توکن اختیاری گیت‌هاب — ثابت wp-config اولویت دارد.
	 *
	 * وجود توکن فقط برای عبور از سهمیهٔ API و مخزن خصوصی لازم است؛ مقدار آن
	 * هرگز در HTML، پیام یا لاگ نوشته نمی‌شود.
	 */
	public static function token() {
		if ( defined( 'PAS_DASHBOARD_GITHUB_TOKEN' ) && PAS_DASHBOARD_GITHUB_TOKEN ) {
			return trim( (string) PAS_DASHBOARD_GITHUB_TOKEN );
		}

		$saved = PAS_Dashboard_Permissions::setting( 'github_token', '' );

		return is_string( $saved ) ? trim( $saved ) : '';
	}

	public static function has_token() {
		return self::token() !== '';
	}

	public static function auto_updates_enabled() {
		return (bool) PAS_Dashboard_Permissions::feature( 'enable_auto_updates' );
	}

	/* ── فهرست مخزن ────────────────────────────────────────────────────── */

	/**
	 * فهرست بسته‌های `dist/` — از کش یا GitHub Contents API.
	 *
	 * @return array{items:array<string,array>,error:string,fetched_at:int,repo:string,branch:string}
	 */
	public static function catalog( $force = false ) {
		$repo   = self::repo();
		$branch = self::branch();
		$cached = get_site_transient( self::CACHE_KEY );

		if ( ! $force && is_array( $cached ) && isset( $cached['repo'], $cached['branch'], $cached['items'] )
			&& $cached['repo'] === $repo && $cached['branch'] === $branch ) {
			return $cached;
		}

		$result = self::fetch_catalog( $repo, $branch );

		set_site_transient(
			self::CACHE_KEY,
			$result,
			$result['error'] === '' ? self::CACHE_TTL : self::FAILURE_TTL
		);

		return $result;
	}

	/** @return array{items:array<string,array>,error:string,fetched_at:int,repo:string,branch:string} */
	private static function fetch_catalog( $repo, $branch ) {
		$empty = array(
			'items'      => array(),
			'error'      => '',
			'fetched_at' => time(),
			'repo'       => $repo,
			'branch'     => $branch,
		);

		$url = sprintf(
			'https://api.github.com/repos/%s/contents/%s?ref=%s',
			$repo,
			self::DIST_PATH,
			rawurlencode( $branch )
		);

		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'Peyvand-Amn-Dashboard/' . ( defined( 'PAS_DASHBOARD_VERSION' ) ? PAS_DASHBOARD_VERSION : '1.0.0' ),
		);

		if ( self::has_token() ) {
			$headers['Authorization'] = 'Bearer ' . self::token();
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 12,
				'sslverify' => true,
				'headers'   => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			$empty['error'] = __( 'اتصال به گیت‌هاب برقرار نشد؛ آخرین وضعیت کش‌شده نمایش داده می‌شود.', 'peyvand-amn-dashboard' );

			return $empty;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 404 === $code ) {
			$empty['error'] = __( 'پوشهٔ dist در مخزن پیدا نشد؛ نام مخزن یا شاخه را در تنظیمات بررسی کنید.', 'peyvand-amn-dashboard' );

			return $empty;
		}

		if ( 403 === $code || 429 === $code ) {
			$empty['error'] = __( 'سهمیهٔ GitHub API تمام شده است. برای بررسی خودکار بدون وقفه، توکن گیت‌هاب را تنظیم کنید.', 'peyvand-amn-dashboard' );

			return $empty;
		}

		if ( 200 !== $code || ! is_array( $body ) ) {
			$empty['error'] = sprintf(
				/* translators: %d: HTTP status code */
				__( 'پاسخ نامعتبر از گیت‌هاب (کد %d).', 'peyvand-amn-dashboard' ),
				$code
			);

			return $empty;
		}

		foreach ( $body as $entry ) {
			if ( ! is_array( $entry ) || ( $entry['type'] ?? '' ) !== 'file' ) {
				continue;
			}

			$name = isset( $entry['name'] ) && is_string( $entry['name'] ) ? $entry['name'] : '';

			if ( ! preg_match( '#^([a-z0-9][a-z0-9._-]*?)-([0-9][0-9.]*)\.zip$#i', $name, $match ) ) {
				continue;
			}

			$slug    = strtolower( $match[1] );
			$version = rtrim( $match[2], '.' );

			if ( ! preg_match( '#^[a-z0-9][a-z0-9-]*$#', $slug ) || ! preg_match( '#^[0-9]+(?:\.[0-9]+)*$#', $version ) ) {
				continue;
			}

			$package = isset( $entry['download_url'] ) && is_string( $entry['download_url'] ) ? $entry['download_url'] : '';

			if ( ! self::is_allowed_package_url( $package ) ) {
				continue;
			}

			$sha = isset( $entry['sha'] ) && is_string( $entry['sha'] ) ? $entry['sha'] : '';

			// اگر دو بسته با یک اسلاگ منتشر شده باشد، نسخهٔ بالاتر برنده است.
			if ( isset( $empty['items'][ $slug ] ) && ! version_compare( $version, $empty['items'][ $slug ]['version'], '>' ) ) {
				continue;
			}

			$empty['items'][ $slug ] = array(
				'version' => $version,
				'package' => $package,
				'size'    => isset( $entry['size'] ) ? (int) $entry['size'] : 0,
				'sha'     => $sha,
				'name'    => $name,
			);
		}

		return $empty;
	}

	/** بسته فقط از HTTPS و میزبان‌های گیت‌هاب پذیرفته می‌شود. */
	public static function is_allowed_package_url( $url ) {
		if ( ! is_string( $url ) || $url === '' ) {
			return false;
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return false;
		}

		$host = strtolower( (string) ( $parts['host'] ?? '' ) );

		return in_array( $host, array( 'github.com', 'raw.githubusercontent.com', 'objects.githubusercontent.com', 'codeload.github.com' ), true );
	}

	public static function clear_cache() {
		delete_site_transient( self::CACHE_KEY );
	}

	/* ── وضعیت نصب‌شده ─────────────────────────────────────────────────── */

	/** @return array<string,array<string,string>> فهرست افزونه‌های نصب‌شده از وردپرس */
	private static function installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();

		return is_array( $plugins ) ? $plugins : array();
	}

	/**
	 * وضعیت کامل به‌روزرسانی برای نمایش در داشبورد.
	 *
	 * @return array{repo:string,branch:string,error:string,fetched_at:int,has_token:bool,auto_updates:bool,items:array<int,array>,summary:array<string,int>}
	 */
	public static function status() {
		$catalog  = self::catalog();
		$installed = self::installed_plugins();
		$known     = self::plugins();
		$items     = array();

		foreach ( $known as $slug => $plugin_file ) {
			$is_installed = isset( $installed[ $plugin_file ] );
			$current      = $is_installed && ! empty( $installed[ $plugin_file ]['Version'] )
				? (string) $installed[ $plugin_file ]['Version']
				: '';
			$latest       = isset( $catalog['items'][ $slug ] ) ? (string) $catalog['items'][ $slug ]['version'] : '';
			$name         = $is_installed && ! empty( $installed[ $plugin_file ]['Name'] )
				? (string) $installed[ $plugin_file ]['Name']
				: $slug;

			if ( ! $is_installed ) {
				$state  = 'absent';
				$detail = __( 'روی این سایت نصب نیست.', 'peyvand-amn-dashboard' );
			} elseif ( '' === $latest ) {
				$state  = $catalog['error'] !== '' ? 'unknown' : 'no_package';
				$detail = $catalog['error'] !== ''
					? $catalog['error']
					: __( 'هنوز بسته‌ای برای این افزونه در پوشهٔ dist مخزن نیست.', 'peyvand-amn-dashboard' );
			} elseif ( version_compare( $latest, $current, '>' ) ) {
				$state  = 'update';
				$detail = __( 'نسخهٔ تازه در مخزن منتشر شده است.', 'peyvand-amn-dashboard' );
			} else {
				$state  = 'current';
				$detail = __( 'نسخهٔ نصب‌شده با آخرین بستهٔ مخزن هم‌سو است.', 'peyvand-amn-dashboard' );
			}

			$items[] = array(
				'slug'      => (string) $slug,
				'file'      => (string) $plugin_file,
				'name'      => $name,
				'current'   => $current,
				'latest'    => $latest,
				'state'     => $state,
				'detail'    => $detail,
				'package'   => isset( $catalog['items'][ $slug ] ) ? (string) $catalog['items'][ $slug ]['package'] : '',
				'size'      => isset( $catalog['items'][ $slug ] ) ? (int) $catalog['items'][ $slug ]['size'] : 0,
			);
		}

		$summary = array( 'total' => count( $items ), 'update' => 0, 'current' => 0, 'absent' => 0, 'no_package' => 0, 'unknown' => 0 );

		foreach ( $items as $item ) {
			if ( isset( $summary[ $item['state'] ] ) ) {
				$summary[ $item['state'] ]++;
			}
		}

		return array(
			'repo'         => $catalog['repo'],
			'branch'       => $catalog['branch'],
			'error'        => (string) $catalog['error'],
			'fetched_at'   => (int) $catalog['fetched_at'],
			'has_token'    => self::has_token(),
			'auto_updates' => self::auto_updates_enabled(),
			'items'        => $items,
			'summary'      => $summary,
		);
	}

	/* ── تزریق در وردپرس ───────────────────────────────────────────────── */

	/**
	 * افزودن آپدیت‌ها به ترنزینت وردپرس؛ بدون این هوک، وردپرس هیچ خبری از
	 * بسته‌های گیت‌هاب ندارد.
	 */
	public static function inject_updates( $transient ) {
		if ( self::$injecting || ! is_object( $transient ) || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ) {
			return $transient;
		}

		self::$injecting = true;
		$catalog         = self::catalog();
		$installed       = self::installed_plugins();
		$known           = self::plugins();

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		foreach ( $known as $slug => $plugin_file ) {
			if ( ! isset( $installed[ $plugin_file ], $catalog['items'][ $slug ] ) ) {
				continue;
			}

			$latest  = (string) $catalog['items'][ $slug ]['version'];
			$current = ! empty( $installed[ $plugin_file ]['Version'] ) ? (string) $installed[ $plugin_file ]['Version'] : '';
			$package = (string) $catalog['items'][ $slug ]['package'];

			$item = array(
				'id'            => 'github.com/' . $catalog['repo'],
				'slug'          => (string) $slug,
				'plugin'        => (string) $plugin_file,
				'new_version'   => $latest,
				'url'           => 'https://github.com/' . $catalog['repo'],
				'package'       => $package,
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => '',
				'requires_php'  => '',
				'compatibility' => new stdClass(),
			);

			if ( version_compare( $latest, $current, '>' ) ) {
				unset( $transient->no_update[ $plugin_file ] );
				$transient->response[ $plugin_file ] = (object) $item;
			} else {
				unset( $transient->response[ $plugin_file ] );
				$transient->no_update[ $plugin_file ] = (object) $item;
			}
		}

		self::$injecting = false;

		return $transient;
	}

	/** پنجرهٔ «جزئیات» وردپرس برای افزونه‌های همین مخزن. */
	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) ) {
			return $result;
		}

		$slug = sanitize_key( (string) $args->slug );
		$known = self::plugins();

		if ( ! isset( $known[ $slug ] ) ) {
			return $result;
		}

		$catalog = self::catalog();
		$latest  = isset( $catalog['items'][ $slug ] ) ? (string) $catalog['items'][ $slug ]['version'] : '';

		if ( '' === $latest ) {
			return $result;
		}

		return (object) array(
			'name'          => $slug,
			'slug'          => $slug,
			'version'       => $latest,
			'author'        => 'Peyvand Amn',
			'homepage'      => 'https://github.com/' . $catalog['repo'],
			'download_link' => (string) $catalog['items'][ $slug ]['package'],
			'requires'      => '',
			'tested'        => '',
			'requires_php'  => '7.4',
			'last_updated'  => gmdate( 'Y-m-d', (int) $catalog['fetched_at'] ),
			'short_description' => __( 'بستهٔ رسمی اکوسیستم پیوند امن از مخزن گیت‌هاب.', 'peyvand-amn-dashboard' ),
			'sections'      => array(
				'description' => __( 'این نسخه از پوشهٔ dist مخزن رسمی پیوند امن خوانده می‌شود و همان بستهٔ نصب دستی است.', 'peyvand-amn-dashboard' ),
				'changelog'   => sprintf(
					/* translators: 1: version, 2: repository */
					__( 'نسخهٔ %1$s — فهرست تغییرات در مخزن: https://github.com/%2$s', 'peyvand-amn-dashboard' ),
					$latest,
					$catalog['repo']
				),
			),
		);
	}

	/** به‌روزرسانی خودکار فقط برای افزونه‌های همین فهرست سفید. */
	public static function auto_update( $update, $item ) {
		if ( ! self::auto_updates_enabled() || ! is_object( $item ) || empty( $item->plugin ) ) {
			return $update;
		}

		$file = (string) $item->plugin;

		if ( in_array( $file, self::plugins(), true ) ) {
			return true;
		}

		return $update;
	}

	/**
	 * توکن فقط به درخواست‌های همین مخزن افزوده می‌شود؛ نه به هر میزبان گیت‌هاب.
	 */
	public static function github_request_args( $args, $url ) {
		if ( ! self::has_token() || ! is_string( $url ) || ! is_array( $args ) ) {
			return $args;
		}

		$repo = self::repo();

		if ( false === strpos( $url, 'raw.githubusercontent.com/' . $repo . '/' )
			&& false === strpos( $url, 'api.github.com/repos/' . $repo )
			&& false === strpos( $url, 'github.com/' . $repo . '/' ) ) {
			return $args;
		}

		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}

		$args['headers']['Authorization'] = 'Bearer ' . self::token();

		return $args;
	}

	/** پس از هر آپدیت، فهرست دوباره خوانده شود تا وضعیت کهنه نماند. */
	public static function after_upgrade( $upgrader, $hook_extra ) {
		if ( is_array( $hook_extra ) && ( $hook_extra['action'] ?? '' ) === 'update' ) {
			self::clear_cache();
		}
	}

	/* ── عملیات نوشتنی (از Action Bus) ─────────────────────────────────── */

	/**
	 * ذخیرهٔ تنظیمات مخزن و به‌روزرسانی خودکار.
	 *
	 * @return array{ok:bool,message:string,id:int,redirect:string}
	 */
	public static function save_settings( array $post ) {
		$current = get_option( PAS_DASHBOARD_OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$current = array_merge( PAS_Dashboard_Permissions::default_settings(), $current );

		$repo = isset( $post['updates_repo'] ) && is_scalar( $post['updates_repo'] )
			? trim( (string) $post['updates_repo'] )
			: (string) $current['updates_repo'];

		if ( ! preg_match( '#^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$#', $repo ) ) {
			return array(
				'ok'       => false,
				'message'  => __( 'نام مخزن باید به شکل owner/repository باشد؛ چیزی ذخیره نشد.', 'peyvand-amn-dashboard' ),
				'id'       => 0,
				'redirect' => self::module_url(),
			);
		}

		$branch = isset( $post['updates_branch'] ) && is_scalar( $post['updates_branch'] )
			? trim( (string) $post['updates_branch'] )
			: (string) $current['updates_branch'];

		if ( ! preg_match( '#^[A-Za-z0-9._/-]{1,100}$#', $branch ) ) {
			$branch = self::BRANCH_DEFAULT;
		}

		$token = '';
		if ( isset( $post['github_token'] ) && is_scalar( $post['github_token'] ) ) {
			$token = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $post['github_token'] );
			$token = substr( (string) $token, 0, 255 );
		}

		$clear_token = ! empty( $post['github_token_clear'] );

		$current['updates_repo']      = $repo;
		$current['updates_branch']    = $branch;
		$current['enable_auto_updates'] = ! empty( $post['enable_auto_updates'] );

		if ( $clear_token ) {
			$current['github_token'] = '';
		} elseif ( $token !== '' ) {
			$current['github_token'] = $token;
		}

		update_option( PAS_DASHBOARD_OPTION, $current );
		self::clear_cache();

		return array(
			'ok'       => true,
			'message'  => __( 'تنظیمات به‌روزرسانی ذخیره شد؛ فهرست مخزن دوباره خوانده می‌شود.', 'peyvand-amn-dashboard' ),
			'id'       => 0,
			'redirect' => self::module_url(),
		);
	}

	/**
	 * بررسی دوبارهٔ مخزن (پاک‌کردن کش) — بدون تغییر هیچ فایلی.
	 *
	 * @return array{ok:bool,message:string,id:int,redirect:string}
	 */
	public static function refresh() {
		self::clear_cache();
		$status = self::status();

		if ( $status['error'] !== '' ) {
			return array(
				'ok'       => false,
				'message'  => $status['error'],
				'id'       => 0,
				'redirect' => self::module_url(),
			);
		}

		return array(
			'ok'       => true,
			'message'  => sprintf(
				/* translators: 1: available updates, 2: total packages */
				__( 'فهرست مخزن به‌روز شد: %1$d به‌روزرسانی در دسترس، %2$d بسته در مخزن.', 'peyvand-amn-dashboard' ),
				(int) $status['summary']['update'],
				count( $status['items'] )
			),
			'id'       => 0,
			'redirect' => self::module_url(),
		);
	}

	/**
	 * اجرای آپدیت از بستهٔ گیت‌هاب با upgrader خود وردپرس.
	 *
	 * نصب دستی حذف می‌شود؛ همان مسیر رسمی وردپرس فایل را جایگزین می‌کند،
	 * اما بسته از مخزن همین پروژه می‌آید. فقط مدیر سایت با کپابیلیتی
	 * `update_plugins` این کار را می‌کند و نتیجه در ممیزی ثبت می‌شود.
	 *
	 * @return array{ok:bool,message:string,id:int,redirect:string}
	 */
	public static function run( array $post ) {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return array(
				'ok'       => false,
				'message'  => __( 'کپابیلیتی به‌روزرسانی افزونه‌ها برای شما فعال نیست.', 'peyvand-amn-dashboard' ),
				'id'       => 0,
				'redirect' => self::module_url(),
			);
		}

		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return array(
				'ok'       => false,
				'message'  => __( 'نصب و تغییر فایل‌ها در این سایت غیرفعال است (DISALLOW_FILE_MODS).', 'peyvand-amn-dashboard' ),
				'id'       => 0,
				'redirect' => self::module_url(),
			);
		}

		$slug = isset( $post['slug'] ) && is_scalar( $post['slug'] ) ? sanitize_key( (string) $post['slug'] ) : '';
		$status = self::status();
		$files  = array();

		foreach ( $status['items'] as $item ) {
			if ( 'update' !== $item['state'] ) {
				continue;
			}
			if ( $slug !== '' && $item['slug'] !== $slug ) {
				continue;
			}
			$files[] = $item['file'];
		}

		if ( empty( $files ) ) {
			return array(
				'ok'       => false,
				'message'  => __( 'هیچ به‌روزرسانی‌ای برای اجرا وجود ندارد؛ ابتدا مخزن را بررسی کنید.', 'peyvand-amn-dashboard' ),
				'id'       => 0,
				'redirect' => self::module_url(),
			);
		}

		// ترنزینت تازه تا بسته‌های همین بررسی در آپ‌گریدر دیده شوند.
		delete_site_transient( 'update_plugins' );
		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		wp_update_plugins();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$results  = $upgrader->bulk_upgrade( $files );

		$done   = 0;
		$failed = 0;

		if ( is_array( $results ) ) {
			foreach ( $results as $result ) {
				if ( true === $result ) {
					$done++;
				} else {
					$failed++;
				}
			}
		} else {
			$failed = count( $files );
		}

		self::clear_cache();
		delete_site_transient( 'update_plugins' );

		if ( $done === 0 ) {
			$reason = '';

			if ( isset( $upgrader->skin->result ) && is_wp_error( $upgrader->skin->result ) ) {
				$reason = ' ' . $upgrader->skin->result->get_error_message();
			}

			return array(
				'ok'       => false,
				'message'  => sprintf(
					/* translators: %d: number of plugins */
					__( 'به‌روزرسانی %d افزونه انجام نشد؛ دسترسی نوشتن فایل یا اتصال به گیت‌هاب را بررسی کنید.', 'peyvand-amn-dashboard' ),
					count( $files )
				) . $reason,
				'id'       => 0,
				'redirect' => self::module_url(),
			);
		}

		return array(
			'ok'       => true,
			'message'  => $failed > 0
				? sprintf(
					/* translators: 1: updated, 2: failed */
					__( '%1$d افزونه به‌روز شد و %2$d افزونه ناموفق بود.', 'peyvand-amn-dashboard' ),
					$done,
					$failed
				)
				: sprintf(
					/* translators: %d: updated plugins */
					__( '%d افزونه از مخزن گیت‌هاب به‌روز شد.', 'peyvand-amn-dashboard' ),
					$done
				),
			'id'       => 0,
			'redirect' => self::module_url(),
		);
	}

	/* ── نمایش ─────────────────────────────────────────────────────────── */

	public static function state_label( $state ) {
		$labels = array(
			'update'     => __( 'به‌روزرسانی موجود', 'peyvand-amn-dashboard' ),
			'current'    => __( 'به‌روز', 'peyvand-amn-dashboard' ),
			'absent'     => __( 'نصب نشده', 'peyvand-amn-dashboard' ),
			'no_package' => __( 'بدون بسته در مخزن', 'peyvand-amn-dashboard' ),
			'unknown'    => __( 'نامشخص', 'peyvand-amn-dashboard' ),
		);

		return $labels[ $state ] ?? (string) $state;
	}

	public static function state_class( $state ) {
		$classes = array(
			'update'     => 'pas-badge-warn',
			'current'    => 'pas-badge-good',
			'absent'     => 'pas-badge-muted',
			'no_package' => 'pas-badge-warn',
			'unknown'    => 'pas-badge-danger',
		);

		return $classes[ $state ] ?? 'pas-badge-warn';
	}

	public static function module_url() {
		return add_query_arg(
			array(
				'page'   => PAS_DASHBOARD_SLUG,
				'module' => 'updates',
			),
			admin_url( 'admin.php' )
		);
	}
}
