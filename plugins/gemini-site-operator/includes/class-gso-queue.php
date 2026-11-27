<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * صف پس‌زمینه دسته‌بندی‌شده با کنترل کامل اپراتور.
 *
 * - قفل هم‌زمانی ترنزینت ۱۲۰ ثانیه‌ای با توکن مالکیت.
 * - Pause / Resume / Cancel / Retry.
 * - بودجه زمانی هر اجرا و زنجیره‌ی اجرای پیوسته (بدون انتظار ۵ دقیقه‌ای).
 * - مکث خودکار روی اتمام سهمیه روزانه و Backoff نمایی.
 */
class GSO_Queue {

	const LOCK       = 'gso_queue_lock';
	const LOCK_TTL   = 120;
	const CONTROL    = 'gso_queue_state';

	/**
	 * ثبت هوک‌ها.
	 */
	public static function init(): void {
		add_action( 'gso_queue_tick', array( __CLASS__, 'process' ) );
		add_action( 'gso_queue_followup', array( __CLASS__, 'process' ) );
		add_action( 'gso_scan_tick', array( 'GSO_Auditor', 'runner' ) );
		add_action( 'gso_scan_followup', array( 'GSO_Auditor', 'runner' ) );
		add_action( 'gso_watch_tick', array( 'GSO_Watcher', 'process_dirty' ) );
		add_action( 'gso_schedule_scan', array( __CLASS__, 'scheduled_scan' ) );
	}

	/**
	 * بازه پنج‌دقیقه‌ای اختصاصی.
	 *
	 * @param array $schedules بازه‌ها.
	 * @return array
	 */
	public static function schedules( array $schedules ): array {
		$schedules['gso_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Gemini Site Operator — هر ۵ دقیقه',
		);
		return $schedules;
	}

	/**
	 * اسکن زمان‌بندی‌شده دوره‌ای.
	 */
	public static function scheduled_scan(): void {
		$state = GSO_Auditor::state();
		if ( ! empty( $state['active'] ) ) {
			return;
		}
		GSO_Auditor::start();
	}

	/* ------------------------------------------------------------------ */
	/* کنترل صف                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * وضعیت کنترل صف.
	 *
	 * @return array
	 */
	public static function state(): array {
		$state = get_option( self::CONTROL, array() );
		$state = is_array( $state ) ? $state : array();
		return wp_parse_args(
			$state,
			array(
				'status'      => 'idle',
				'reason'      => '',
				'paused_at'   => 0,
				'resume_at'   => 0,
				'last_run'    => 0,
				'last_report' => array(),
			)
		);
	}

	/**
	 * ذخیره وضعیت کنترل.
	 *
	 * @param array $state وضعیت.
	 */
	private static function save_state( array $state ): void {
		update_option( self::CONTROL, $state, false );
	}

	/**
	 * مکث صف.
	 *
	 * @param string $reason    manual|quota|backoff.
	 * @param int    $resume_at زمان پیشنهادی از سرگیری.
	 * @return array
	 */
	public static function pause( string $reason = 'manual', int $resume_at = 0 ): array {
		$state              = self::state();
		$state['status']    = 'quota' === $reason ? 'paused_quota' : 'paused';
		$state['reason']    = sanitize_key( $reason );
		$state['paused_at'] = time();
		$state['resume_at'] = $resume_at > 0 ? $resume_at : 0;
		self::save_state( $state );
		if ( $state['resume_at'] > 0 ) {
			self::schedule_followup( max( 10, min( HOUR_IN_SECONDS, $state['resume_at'] - time() ) ) );
		}
		GSO_Logger::log( 'warning', 'queue.pause', 'صف متوقف شد.', array( 'reason' => $reason, 'resume_at' => $state['resume_at'] ) );
		return $state;
	}

	/**
	 * از سرگیری صف (شامل بازگرداندن اقلام لغوشده).
	 *
	 * @return array
	 */
	public static function resume(): array {
		global $wpdb;
		$state           = self::state();
		$was_cancelled   = 'cancelled' === $state['status'];
		$state['status'] = 'idle';
		$state['reason'] = '';
		$state['paused_at'] = 0;
		$state['resume_at'] = 0;
		self::save_state( $state );

		if ( $was_cancelled ) {
			$table = GSO_Install::table( 'queue' );
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"UPDATE %i SET status = 'pending' WHERE status = 'skipped' AND message LIKE %s",
					$table,
					'%' . $wpdb->esc_like( 'لغو' ) . '%'
				)
			);
		}

		GSO_Logger::log( 'info', 'queue.resume', 'صف از سر گرفته شد.', array( 'reopened' => $was_cancelled ) );
		self::schedule_followup( 10 );
		return $state;
	}

	/**
	 * لغو صف: توقف پردازش و کنارگذاشتن اقلام در انتظار (قابل بازگشت با Resume).
	 *
	 * @return array
	 */
	public static function cancel(): array {
		global $wpdb;
		$state              = self::state();
		$state['status']    = 'cancelled';
		$state['reason']    = 'manual';
		$state['paused_at'] = time();
		self::save_state( $state );

		$table = GSO_Install::table( 'queue' );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE %i SET status = 'skipped', message = %s, finished_at = %s WHERE status IN ('pending','running')",
				$table,
				'صف به‌دست اپراتور لغو شد.',
				current_time( 'mysql' )
			)
		);

		GSO_Logger::log( 'warning', 'queue.cancel', 'صف لغو شد و اقلام در انتظار کنار گذاشته شدند.' );
		return $state;
	}

	/**
	 * علامت‌گذاری اجرای فعال.
	 */
	private static function mark_running(): void {
		$state           = self::state();
		$state['status'] = 'running';
		self::save_state( $state );
	}

	/**
	 * آیا صف الان قابل اجراست؟
	 *
	 * @return bool
	 */
	public static function is_runnable(): bool {
		$status = self::state()['status'];
		return in_array( $status, array( 'idle', 'running' ), true );
	}

	/**
	 * قفل هم‌زمانی با توکن مالکیت.
	 *
	 * @return string|false
	 */
	private static function acquire_lock() {
		$current = get_transient( self::LOCK );
		if ( is_array( $current ) && ! empty( $current['expires'] ) && $current['expires'] > time() ) {
			return false;
		}
		$token = wp_generate_password( 20, false, false ) . '|' . time();
		set_transient( self::LOCK, array( 'token' => $token, 'expires' => time() + self::LOCK_TTL ), self::LOCK_TTL );
		return $token;
	}

	/**
	 * آزادسازی قفل فقط توسط مالک.
	 *
	 * @param string $token توکن.
	 */
	private static function release_lock( string $token ): void {
		$current = get_transient( self::LOCK );
		if ( is_array( $current ) && ( $current['token'] ?? '' ) === $token ) {
			delete_transient( self::LOCK );
		}
	}

	/**
	 * زمان‌بندی یک رویداد تک‌باره؛ در صورت وجود Action Scheduler از آن استفاده می‌شود.
	 *
	 * @param string $hook  نام هوک.
	 * @param int    $delay ثانیه.
	 */
	public static function schedule_event( string $hook, int $delay = 30 ): void {
		$hook = sanitize_key( $hook );
		if ( '' === $hook ) {
			return;
		}
		$when = time() + max( 5, $delay );

		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_next_scheduled_action' ) ) {
			if ( false === as_next_scheduled_action( $hook, array(), 'gemini-site-operator' ) ) {
				as_schedule_single_action( $when, $hook, array(), 'gemini-site-operator' );
			}
			return;
		}

		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_single_event( $when, $hook );
		}
	}

	/**
	 * حذف رویداد زمان‌بندی‌شده (از هر دو مسیر Action Scheduler و WP-Cron).
	 *
	 * @param string $hook نام هوک.
	 */
	public static function unschedule_event( string $hook ): void {
		$hook = sanitize_key( $hook );
		if ( '' === $hook ) {
			return;
		}
		if ( function_exists( 'as_unschedule_action' ) ) {
			as_unschedule_action( $hook, array(), 'gemini-site-operator' );
		}
		$timestamp = wp_next_scheduled( $hook );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
			$timestamp = wp_next_scheduled( $hook );
		}
	}

	/**
	 * اجرای پی‌درپی صف پس از پایان دسته (به‌جای انتظار تا تیک پنج‌دقیقه‌ای).
	 *
	 * @param int $delay ثانیه.
	 */
	public static function schedule_followup( int $delay = 30 ): void {
		self::schedule_event( 'gso_queue_followup', $delay );
	}

	/* ------------------------------------------------------------------ */
	/* عضویت در صف                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * افزودن یک ایراد به صف (با جلوگیری از تکرار).
	 *
	 * @param int    $issue_id شناسه.
	 * @param string $action   fix|auto.
	 * @return bool
	 */
	public static function enqueue( int $issue_id, string $action = 'fix' ): bool {
		global $wpdb;
		$table = GSO_Install::table( 'queue' );
		$issue = GSO_Issues::get( $issue_id );
		if ( ! $issue || in_array( $issue->status, array( 'fixed', 'ignored' ), true ) ) {
			return false;
		}

		$existing = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE issue_id = %d AND status IN ('pending','running')", $table, $issue_id )
		);
		if ( $existing > 0 ) {
			return false;
		}

		$wpdb->insert(
			$table,
			array(
				'issue_id'   => $issue_id,
				'action'     => in_array( $action, array( 'fix', 'auto' ), true ) ? $action : 'fix',
				'status'     => 'pending',
				'priority'   => $issue->severity,
				'message'    => '',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		$state = self::state();
		if ( in_array( $state['status'], array( 'idle', 'running' ), true ) ) {
			self::schedule_followup( 20 );
		}

		return true;
	}

	/**
	 * افزودن دسته‌ای.
	 *
	 * @param array  $issue_ids شناسه‌ها.
	 * @param string $action    نوع.
	 * @return int تعداد افزوده‌شده.
	 */
	public static function bulk_enqueue( array $issue_ids, string $action = 'fix' ): int {
		$count = 0;
		foreach ( array_unique( array_map( 'absint', $issue_ids ) ) as $issue_id ) {
			if ( $issue_id > 0 && self::enqueue( $issue_id, $action ) ) {
				++$count;
			}
		}
		if ( $count > 0 ) {
			GSO_Logger::log( 'info', 'queue.enqueue', sprintf( '%d مورد به صف اصلاح افزوده شد.', $count ), array( 'action' => $action ) );
		}
		return $count;
	}

	/**
	 * آمار صف.
	 *
	 * @return array
	 */
	public static function counts(): array {
		global $wpdb;
		$table = GSO_Install::table( 'queue' );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS c FROM %i GROUP BY status', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out   = array( 'pending' => 0, 'running' => 0, 'done' => 0, 'failed' => 0, 'skipped' => 0, 'total' => 0 );
		foreach ( $rows as $row ) {
			$out[ $row->status ] = (int) $row->c;
			$out['total']       += (int) $row->c;
		}
		return $out;
	}

	/**
	 * اقلام صف برای نمایش.
	 *
	 * @param int $limit تعداد.
	 * @return array
	 */
	public static function recent( int $limit = 30 ): array {
		global $wpdb;
		$queue  = GSO_Install::table( 'queue' );
		$issues = GSO_Install::table( 'issues' );
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT q.*, i.title AS issue_title, i.issue_type, i.severity
				 FROM %i q LEFT JOIN %i i ON i.id = q.issue_id
				 ORDER BY CASE q.status WHEN 'running' THEN 0 WHEN 'pending' THEN 1 WHEN 'failed' THEN 2 WHEN 'skipped' THEN 3 ELSE 4 END, CASE q.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 ELSE 2 END, q.id DESC
				 LIMIT %d",
				$queue,
				$issues,
				max( 1, min( 200, $limit ) )
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* اجرا                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * پردازش صف — هم از کران و هم دستی.
	 *
	 * @param int $max حداکثر اقلام (۰ = از تنظیمات).
	 * @return array گزارش اجرا.
	 */
	public static function process( int $max = 0 ): array {
		$state  = self::state();
		$report = array(
			'processed'    => 0,
			'applied'      => 0,
			'proposed'     => 0,
			'failed'       => 0,
			'skipped'      => 0,
			'deferred'     => 0,
			'remaining'    => 0,
			'locked'       => false,
			'timed_out'    => false,
			'quota_paused' => false,
			'state'        => $state['status'],
		);

		$now = time();

		// مکث دستی یا سهمیه: فقط در صورت وجود زمان از سرگیری، خودکار ادامه می‌دهد.
		if ( in_array( $state['status'], array( 'paused', 'paused_quota' ), true ) ) {
			if ( ! empty( $state['resume_at'] ) && $now >= (int) $state['resume_at'] ) {
				self::resume();
			} else {
				return $report;
			}
		}
		if ( 'cancelled' === self::state()['status'] ) {
			return $report;
		}

		$quota_until = GSO_Limiter::quota_until();
		if ( $quota_until > 0 ) {
			self::pause( 'quota', $quota_until );
			$report['quota_paused'] = true;
			$report['state']        = 'paused_quota';
			return $report;
		}

		$next_attempt = GSO_Limiter::next_attempt_at();
		if ( $next_attempt > $now ) {
			$report['deferred'] = $next_attempt - $now;
			self::schedule_followup( max( 10, min( HOUR_IN_SECONDS, $report['deferred'] ) ) );
			return $report;
		}

		$token = self::acquire_lock();
		if ( false === $token ) {
			$report['locked'] = true;
			return $report;
		}

		self::mark_running();
		$started = microtime( true );

		try {
			global $wpdb;
			$table = GSO_Install::table( 'queue' );

			// آزادسازی اقلام «running» جا‌مانده از اجرای ناموفق قبلی.
			$stale = gmdate( 'Y-m-d H:i:s', time() - ( self::LOCK_TTL * 5 ) );
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "UPDATE %i SET status = 'pending' WHERE status = 'running' AND started_at IS NOT NULL AND started_at < %s", $table, $stale )
			);

			$limit = $max > 0 ? $max : (int) GSO_Settings::get( 'queue_batch_size', 3 );
			$limit = max( 1, min( 25, $limit ) );
			$level = (string) GSO_Settings::get( 'automation_level', 'review_required' );

			$budget = (int) GSO_Settings::get( 'queue_time_budget', 25 );
			$budget = max( 5, min( 240, $budget ) );
			$deadline = (int) floor( $started ) + $budget;

			$items = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM %i WHERE status = 'pending' ORDER BY CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 ELSE 2 END, id ASC LIMIT %d",
					$table,
					$limit
				)
			);

			foreach ( $items as $item ) {
				if ( time() >= $deadline ) {
					$report['timed_out'] = true;
					break;
				}

				if ( GSO_Limiter::quota_until() > 0 ) {
					self::pause( 'quota', GSO_Limiter::quota_until() );
					$report['quota_paused'] = true;
					break;
				}
				if ( GSO_Limiter::next_attempt_at() > time() ) {
					++$report['deferred'];
					self::schedule_followup( max( 10, min( HOUR_IN_SECONDS, GSO_Limiter::next_attempt_at() - time() ) ) );
					break;
				}

				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array(
						'status'     => 'running',
						'started_at' => current_time( 'mysql' ),
						'attempts'   => (int) $item->attempts + 1,
					),
					array( 'id' => (int) $item->id )
				);

				$issue = GSO_Issues::get( (int) $item->issue_id );
				if ( ! $issue || in_array( $issue->status, array( 'fixed', 'ignored' ), true ) ) {
					self::finish( (int) $item->id, 'skipped', 'مورد از قبل بسته شده بود.' );
					++$report['skipped'];
					continue;
				}

				$auto = 'auto' === $item->action;
				if ( $auto && 'report_only' === $level ) {
					self::finish( (int) $item->id, 'skipped', 'حالت فقط گزارش‌دهی: اصلاحی اعمال نشد.' );
					++$report['skipped'];
					continue;
				}

				$payload = GSO_Issues::payload( $issue );
				if ( ! GSO_Fixer::is_ready( $payload ) ) {
					$prepared = GSO_Fixer::prepare( (int) $issue->id );
					if ( is_wp_error( $prepared ) ) {
						if ( ! self::maybe_retry( $item, $prepared ) ) {
							++$report['failed'];
						}
						if ( GSO_Limiter::quota_until() > 0 ) {
							$report['quota_paused'] = true;
							break;
						}
						continue;
					}
					++$report['proposed'];
				}

				if ( $auto ) {
					// safe_auto فقط موارد کم‌ریسک را می‌نویسد؛ review_required هرگز خودکار اعمال نمی‌کند.
					$needs_approval = 'review_required' === $level || ! GSO_Fixer::is_safe( (int) $issue->id );
					if ( $needs_approval ) {
						self::finish( (int) $item->id, 'done', 'پیشنهاد آماده شد و در انتظار تایید اپراتور ماند.' );
						++$report['proposed'];
						continue;
					}
				}

				$result = GSO_Fixer::apply( (int) $issue->id, false );
				if ( is_wp_error( $result ) ) {
					if ( ! self::maybe_retry( $item, $result ) ) {
						++$report['failed'];
					}
					if ( GSO_Limiter::quota_until() > 0 ) {
						$report['quota_paused'] = true;
						break;
					}
					continue;
				}

				self::finish( (int) $item->id, 'done', 'اصلاح با موفقیت اعمال شد.' );
				++$report['applied'];
				++$report['processed'];
			}

			$report['remaining'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, 'pending' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} catch ( \Throwable $e ) {
			++$report['failed'];
			GSO_Logger::log( 'error', 'queue.fatal', 'خطای غیرمنتظره در اجرای صف: ' . $e->getMessage() );
		} finally {
			$report['processed'] = max( $report['processed'], $report['applied'] + $report['proposed'] + $report['failed'] + $report['skipped'] );

			$state                = self::state();
			$state['last_run']    = time();
			$state['last_report'] = $report;
			if ( 'cancelled' !== $state['status'] && 'paused_quota' !== $state['status'] ) {
				$state['status'] = $report['remaining'] > 0 ? 'running' : 'idle';
			}
			self::save_state( $state );
			$report['state'] = $state['status'];

			self::release_lock( $token );

			if ( $report['remaining'] > 0 && 'cancelled' !== $state['status'] && 'paused_quota' !== $state['status'] && 'paused' !== $state['status'] ) {
				self::schedule_followup( $report['timed_out'] ? 15 : 45 );
			}
		}

		if ( $report['applied'] > 0 || $report['proposed'] > 0 || $report['failed'] > 0 || $report['quota_paused'] ) {
			GSO_Logger::log(
				$report['failed'] > 0 ? 'warning' : 'info',
				'queue.process',
				sprintf( 'اجرای صف: %d اصلاح، %d پیشنهاد، %d خطا، %d باقی‌مانده.', $report['applied'], $report['proposed'], $report['failed'], $report['remaining'] ),
				$report
			);
		}

		return $report;
	}

	/**
	 * پایان یک قلم صف.
	 *
	 * @param int    $id      شناسه.
	 * @param string $status  وضعیت.
	 * @param string $message پیام.
	 */
	private static function finish( int $id, string $status, string $message ): void {
		global $wpdb;
		$status = in_array( $status, array( 'done', 'failed', 'skipped', 'pending' ), true ) ? $status : 'done';
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			GSO_Install::table( 'queue' ),
			array(
				'status'      => $status,
				'message'     => sanitize_text_field( $message ),
				'finished_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * تلاش مجدد برای خطاهای موقت و مکث روی سهمیه.
	 *
	 * @param object   $item  قلم صف.
	 * @param WP_Error $error خطا.
	 * @return bool آیا دوباره در صف قرار گرفت.
	 */
	private static function maybe_retry( object $item, WP_Error $error ): bool {
		global $wpdb;
		$data      = $error->get_error_data();
		$data      = is_array( $data ) ? $data : array();
		$retryable = ! empty( $data['retryable'] );
		$quota     = ! empty( $data['quota'] ) || 'gso_quota' === $error->get_error_code();
		$attempts  = (int) $item->attempts;

		if ( $quota ) {
			$until = (int) ( $data['retry_after'] ?? 0 );
			self::pause( 'quota', time() + ( $until > 0 ? $until : GSO_Limiter::daily_reset_in() ) );
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				GSO_Install::table( 'queue' ),
				array(
					'status'  => 'pending',
					'message' => 'در انتظار ریست سهمیه روزانه.',
				),
				array( 'id' => (int) $item->id )
			);
			return true;
		}

		if ( $retryable && $attempts < 5 ) {
			$retry_after = max( 1, (int) ( $data['retry_after'] ?? 30 ) );
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				GSO_Install::table( 'queue' ),
				array(
					'status'  => 'pending',
					'message' => sanitize_text_field( sprintf( 'تلاش مجدد در %d ثانیه: %s', $retry_after, $error->get_error_message() ) ),
				),
				array( 'id' => (int) $item->id )
			);
			self::schedule_followup( max( 10, min( HOUR_IN_SECONDS, $retry_after ) ) );
			return true;
		}

		self::finish( (int) $item->id, 'failed', $error->get_error_message() );
		return false;
	}

	/**
	 * بازگرداندن اقلام ناموفق به صف.
	 *
	 * @return int
	 */
	public static function retry_failed(): int {
		global $wpdb;
		$table = GSO_Install::table( 'queue' );
		$count = (int) $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'pending', attempts = 0 WHERE status = %s", $table, 'failed' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $count > 0 ) {
			self::schedule_followup( 10 );
		}
		return $count;
	}

	/**
	 * تلاش مجدد یک قلم مشخص.
	 *
	 * @param int $id شناسه صف.
	 * @return bool
	 */
	public static function retry_item( int $id ): bool {
		global $wpdb;
		$table = GSO_Install::table( 'queue' );
		$done  = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'status'      => 'pending',
				'attempts'    => 0,
				'message'     => '',
				'started_at'  => null,
				'finished_at' => null,
			),
			array( 'id' => $id )
		);
		if ( $done ) {
			self::schedule_followup( 10 );
		}
		return (bool) $done;
	}

	/**
	 * پاک‌سازی اقلام تمام‌شده قدیمی.
	 *
	 * @param int $days روز.
	 */
	public static function prune( int $days = 14 ): void {
		global $wpdb;
		$table  = GSO_Install::table( 'queue' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $days ) * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE status IN ('done','failed','skipped') AND created_at < %s", $table, $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
