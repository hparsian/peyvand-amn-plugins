<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * خروجی صفحات پیشخوان.
 */
trait GSO_Admin_Pages {

	/**
	 * بررسی دسترسی صفحه.
	 */
	private static function guard(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'دسترسی به این صفحه مجاز نیست.', 'gemini-site-operator' ) );
		}
	}

	/**
	 * نشان سطح بحران.
	 *
	 * @param string $severity سطح.
	 * @return string
	 */
	private static function severity_badge( string $severity ): string {
		$map = array(
			'critical' => array( 'بحرانی', 'gso-badge-critical' ),
			'high'     => array( 'مهم', 'gso-badge-high' ),
			'low'      => array( 'بهینه‌سازی', 'gso-badge-low' ),
		);
		$item = $map[ $severity ] ?? array( $severity, 'gso-badge-low' );
		return '<span class="gso-badge ' . esc_attr( $item[1] ) . '">' . esc_html( $item[0] ) . '</span>';
	}

	/**
	 * برچسب وضعیت صف.
	 *
	 * @param string $status وضعیت.
	 * @return string
	 */
	private static function queue_status_label( string $status ): string {
		$map = array(
			'idle'         => 'آماده',
			'running'      => 'در حال اجرا',
			'paused'       => 'متوقف‌شده',
			'paused_quota' => 'متوقف — سهمیه تمام شده',
			'cancelled'    => 'لغوشده',
		);
		return $map[ $status ] ?? $status;
	}

	/**
	 * برچسب وضعیت ایراد.
	 *
	 * @param string $status وضعیت.
	 * @return string
	 */
	private static function status_badge( string $status ): string {
		$map = array(
			'open'     => array( 'باز', 'gso-status-open' ),
			'proposed' => array( 'پیشنهاد آماده', 'gso-status-proposed' ),
			'fixed'    => array( 'اصلاح‌شده', 'gso-status-fixed' ),
			'ignored'  => array( 'نادیده', 'gso-status-ignored' ),
			'failed'   => array( 'ناموفق', 'gso-status-failed' ),
		);
		$item = $map[ $status ] ?? array( $status, '' );
		return '<span class="gso-status ' . esc_attr( $item[1] ) . '">' . esc_html( $item[0] ) . '</span>';
	}

	/* ------------------------------------------------------------------ */
	/* داشبورد                                                            */
	/* ------------------------------------------------------------------ */

	public static function render_dashboard(): void {
		self::guard();

		$stats   = GSO_Issues::stats();
		$score   = GSO_Issues::health_score( $stats );
		$scan    = GSO_Auditor::state();
		$queue   = GSO_Queue::counts();
		$logs    = GSO_Logger::recent( 8 );
		$snaps   = GSO_Snapshot::recent( 5 );
		$last    = (int) get_option( 'gso_last_scan', 0 );
		$has_key = '' !== GSO_Settings::api_key();
		?>
		<div class="wrap gso-wrap">
			<h1 class="gso-title"><span class="dashicons dashicons-superhero-alt"></span> اپراتور هوشمند سایت</h1>
			<?php self::print_notice(); ?>

			<?php if ( ! $has_key ) : ?>
				<div class="notice notice-warning">
					<p>برای فعال‌شدن ابزارهای هوش مصنوعی، کلید Gemini API را در <a href="<?php echo esc_url( self::page_url( 'gso-settings' ) ); ?>">تنظیمات</a> وارد کنید. اسکن ساختاری بدون کلید هم کار می‌کند.</p>
				</div>
			<?php endif; ?>

			<div class="gso-grid gso-grid-4">
				<div class="gso-card gso-score-card">
					<div class="gso-score" style="--score: <?php echo esc_attr( (string) $score ); ?>">
						<span><?php echo esc_html( (string) $score ); ?></span>
					</div>
					<h2>امتیاز سلامت سایت</h2>
					<p class="gso-muted">بر پایه <?php echo esc_html( (string) $stats['total'] ); ?> ایراد ثبت‌شده</p>
				</div>
				<div class="gso-card">
					<h2>نواقص باز</h2>
					<ul class="gso-stat-list">
						<li><span class="gso-dot gso-dot-critical"></span> بحرانی <strong><?php echo esc_html( (string) $stats['critical'] ); ?></strong></li>
						<li><span class="gso-dot gso-dot-high"></span> مهم <strong><?php echo esc_html( (string) $stats['high'] ); ?></strong></li>
						<li><span class="gso-dot gso-dot-low"></span> بهینه‌سازی <strong><?php echo esc_html( (string) $stats['low'] ); ?></strong></li>
						<li class="gso-muted">اصلاح‌شده تا امروز: <?php echo esc_html( (string) $stats['fixed'] ); ?></li>
					</ul>
				</div>
				<div class="gso-card">
					<h2>وضعیت اسکن</h2>
					<?php if ( ! empty( $scan['active'] ) ) : ?>
						<p><span class="gso-live-dot"></span> در حال اسکن — مرحله <strong class="gso-scan-phase"><?php echo esc_html( $scan['phase'] ); ?></strong></p>
						<div class="gso-progress" data-total="<?php echo esc_attr( (string) $scan['total'] ); ?>"><span style="width:<?php echo esc_attr( (string) GSO_Auditor::progress( $scan ) ); ?>%"></span></div>
						<p class="gso-muted">
							<span class="gso-scan-processed"><?php echo esc_html( (string) $scan['processed'] ); ?></span> از
							<span class="gso-scan-total"><?php echo esc_html( (string) $scan['total'] ); ?></span> مورد —
							سرعت <span class="gso-scan-speed"><?php echo esc_html( (string) GSO_Auditor::progress_info( $scan )['speed'] ); ?></span> آیتم/دقیقه —
							خطا <span class="gso-scan-errors"><?php echo esc_html( (string) $scan['errors'] ); ?></span> —
							پایان تقریبی <span class="gso-scan-eta"><?php echo esc_html( (string) GSO_Auditor::progress_info( $scan )['eta_minutes'] ); ?></span> دقیقه
						</p>
					<?php else : ?>
						<p>اسکن فعال نیست.</p>
						<p class="gso-muted">آخرین اسکن: <?php echo $last ? esc_html( human_time_diff( $last, time() ) . ' پیش' ) : 'هنوز اجرا نشده'; ?> — <span class="gso-scan-processed"><?php echo esc_html( (string) $scan['processed'] ); ?></span> مورد بررسی‌شده در آخرین اجرا</p>
					<?php endif; ?>
					<p class="gso-muted gso-scan-lastlog">آخرین رویداد: <?php echo esc_html( $logs ? $logs[0]->message : '—' ); ?></p>
					<div class="gso-actions">
						<button type="button" class="button button-primary" data-gso-task="scan_start">شروع اسکن کل سایت</button>
						<button type="button" class="button" data-gso-task="scan_stop">توقف</button>
					</div>
				</div>
				<?php
				$qstate = GSO_Queue::state();
				$rate   = GSO_Limiter::status();
				?>
				<div class="gso-card">
					<h2>صف و نظارت</h2>
					<p>وضعیت صف: <strong class="gso-queue-status" data-status="<?php echo esc_attr( $qstate['status'] ); ?>"><?php echo esc_html( self::queue_status_label( (string) $qstate['status'] ) ); ?></strong></p>
					<?php if ( ! empty( $qstate['resume_at'] ) ) : ?>
						<p class="gso-muted gso-queue-resume">از سرگیری خودکار: <?php echo esc_html( human_time_diff( time(), (int) $qstate['resume_at'] ) ); ?> دیگر</p>
					<?php endif; ?>
					<ul class="gso-stat-list">
						<li>در انتظار <strong class="gso-q-pending"><?php echo esc_html( (string) $queue['pending'] ); ?></strong></li>
						<li>در حال اجرا <strong class="gso-q-running"><?php echo esc_html( (string) $queue['running'] ); ?></strong></li>
						<li>ناموفق <strong class="gso-q-failed"><?php echo esc_html( (string) $queue['failed'] ); ?></strong></li>
						<li>کنارگذاشته <strong class="gso-q-skipped"><?php echo esc_html( (string) $queue['skipped'] ); ?></strong></li>
					</ul>
					<p class="gso-muted">سهمیه امروز: <span class="gso-daily-used"><?php echo esc_html( (string) $rate['daily_used'] ); ?></span>/<span class="gso-daily-limit"><?php echo esc_html( (string) ( $rate['daily_limit'] > 0 ? $rate['daily_limit'] : '∞' ) ); ?></span> درخواست — دقیقه جاری: <?php echo esc_html( (string) $rate['minute_used'] ); ?>/<?php echo esc_html( (string) $rate['minute_limit'] ); ?></p>
					<?php if ( ! empty( $rate['backoff_until'] ) ) : ?>
						<p class="gso-muted gso-backoff">Backoff سطح <?php echo esc_html( (string) $rate['backoff_level'] ); ?> — تلاش بعدی <?php echo esc_html( human_time_diff( time(), (int) $rate['backoff_until'] ) ); ?> دیگر</p>
					<?php endif; ?>
					<p class="gso-muted">مصرف توکن این ماه: <?php echo esc_html( number_format_i18n( GSO_Gemini::monthly_usage() ) ); ?></p>
					<div class="gso-actions">
						<button type="button" class="button" data-gso-task="queue_run">اجرای صف</button>
						<button type="button" class="button" data-gso-task="queue_pause">توقف موقت</button>
						<button type="button" class="button" data-gso-task="queue_resume">ادامه</button>
						<button type="button" class="button gso-danger" data-gso-task="queue_cancel" data-gso-confirm="اقلام در انتظار کنار گذاشته شوند؟">لغو صف</button>
						<button type="button" class="button" data-gso-task="queue_retry">تلاش مجدد ناموفق‌ها</button>
						<button type="button" class="button" data-gso-task="test_api">تست اتصال Gemini</button>
					</div>
				</div>
			</div>

			<div class="gso-grid gso-grid-2">
				<div class="gso-card">
					<h2>گزارش آخرین عملیات</h2>
					<table class="widefat striped gso-table">
						<thead><tr><th>زمان</th><th>رویداد</th><th>پیام</th></tr></thead>
						<tbody>
						<?php if ( empty( $logs ) ) : ?>
							<tr><td colspan="3" class="gso-muted">هنوز رویدادی ثبت نشده است.</td></tr>
						<?php else : ?>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td class="gso-nowrap"><?php echo esc_html( $log->created_at ); ?></td>
									<td><code><?php echo esc_html( $log->action ); ?></code></td>
									<td><?php echo esc_html( $log->message ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
					<p><a href="<?php echo esc_url( self::page_url( 'gso-logs' ) ); ?>">گزارش کامل</a></p>
				</div>
				<div class="gso-card">
					<h2>آخرین نسخه‌های پشتیبان</h2>
					<table class="widefat striped gso-table">
						<thead><tr><th>#</th><th>برچسب</th><th>تاریخ</th><th>بازگشت</th></tr></thead>
						<tbody>
						<?php if ( empty( $snaps ) ) : ?>
							<tr><td colspan="4" class="gso-muted">نسخه‌ای ثبت نشده است.</td></tr>
						<?php else : ?>
							<?php foreach ( $snaps as $snap ) : ?>
								<tr>
									<td><?php echo esc_html( (string) $snap->id ); ?></td>
									<td><?php echo esc_html( $snap->label ); ?></td>
									<td class="gso-nowrap"><?php echo esc_html( $snap->created_at ); ?></td>
									<td>
										<?php if ( empty( $snap->restored_at ) || '0000-00-00 00:00:00' === $snap->restored_at ) : ?>
											<button type="button" class="button button-small gso-danger" data-gso-task="rollback" data-snapshot="<?php echo esc_attr( (string) $snap->id ); ?>">بازگردانی</button>
										<?php else : ?>
											<span class="gso-muted">بازگردانی‌شده</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* صف اصلاح                                                           */
	/* ------------------------------------------------------------------ */

	public static function render_queue(): void {
		self::guard();

		$severity    = isset( $_GET['severity'] ) ? sanitize_key( wp_unslash( $_GET['severity'] ) ) : '';
		$status      = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'open_all';
		$object_type = isset( $_GET['object_type'] ) ? sanitize_key( wp_unslash( $_GET['object_type'] ) ) : '';
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged       = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page    = 20;

		$args = array(
			'status'      => $status,
			'severity'    => $severity,
			'object_type' => $object_type,
			'search'      => $search,
			'limit'       => $per_page,
			'offset'      => ( $paged - 1 ) * $per_page,
		);

		$issues = GSO_Issues::query( $args );
		$total  = GSO_Issues::count( $args );
		$pages  = max( 1, (int) ceil( $total / $per_page ) );
		?>
		<div class="wrap gso-wrap">
			<h1 class="gso-title">صف اصلاح و ویرایشگر اپراتور</h1>
			<?php self::print_notice(); ?>

			<form method="get" class="gso-filters">
				<input type="hidden" name="page" value="gso-queue">
				<select name="severity">
					<option value="">همه سطوح</option>
					<option value="critical" <?php selected( $severity, 'critical' ); ?>>بحرانی</option>
					<option value="high" <?php selected( $severity, 'high' ); ?>>مهم</option>
					<option value="low" <?php selected( $severity, 'low' ); ?>>بهینه‌سازی</option>
				</select>
				<select name="status">
					<option value="open_all" <?php selected( $status, 'open_all' ); ?>>باز و پیشنهادی</option>
					<option value="open" <?php selected( $status, 'open' ); ?>>فقط باز</option>
					<option value="proposed" <?php selected( $status, 'proposed' ); ?>>پیشنهاد آماده</option>
					<option value="fixed" <?php selected( $status, 'fixed' ); ?>>اصلاح‌شده</option>
					<option value="ignored" <?php selected( $status, 'ignored' ); ?>>نادیده‌گرفته‌شده</option>
					<option value="failed" <?php selected( $status, 'failed' ); ?>>ناموفق</option>
				</select>
				<select name="object_type">
					<option value="">همه انواع</option>
					<?php foreach ( GSO_Settings::watched_post_types() as $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $object_type, $type ); ?>><?php echo esc_html( $type ); ?></option>
					<?php endforeach; ?>
					<option value="attachment" <?php selected( $object_type, 'attachment' ); ?>>attachment</option>
					<option value="term" <?php selected( $object_type, 'term' ); ?>>term</option>
					<option value="menu" <?php selected( $object_type, 'menu' ); ?>>menu</option>
					<option value="site" <?php selected( $object_type, 'site' ); ?>>site</option>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جست‌وجو در عنوان/توضیح">
				<button type="submit" class="button">فیلتر</button>
			</form>

			<div class="gso-bulkbar">
				<label><input type="checkbox" id="gso-select-all"> انتخاب همه در این صفحه</label>
				<button type="button" class="button button-primary" data-gso-task="bulk_fix">اصلاح دسته‌ای انتخاب‌شده‌ها</button>
				<button type="button" class="button" data-gso-task="bulk_enqueue">افزودن انتخاب‌شده‌ها به صف</button>
				<button type="button" class="button" data-gso-task="bulk_filter" data-severity="<?php echo esc_attr( $severity ); ?>" data-status="<?php echo esc_attr( $status ); ?>">افزودن نتایج فیلتر به صف</button>
				<span class="gso-muted">تعداد: <?php echo esc_html( (string) $total ); ?></span>
			</div>

			<table class="widefat striped gso-table gso-queue-table">
				<thead>
					<tr>
						<th class="gso-col-check"></th>
						<th>سطح</th>
						<th>ایراد و مورد</th>
						<th>نوع</th>
						<th>وضعیت</th>
						<th>عملیات</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $issues ) ) : ?>
					<tr><td colspan="6" class="gso-empty">موردی با این فیلترها پیدا نشد. اسکن را از داشبورد اجرا کنید.</td></tr>
				<?php else : ?>
					<?php foreach ( $issues as $issue ) : ?>
						<tr id="gso-issue-<?php echo esc_attr( (string) $issue->id ); ?>">
							<td><input type="checkbox" class="gso-issue-check" value="<?php echo esc_attr( (string) $issue->id ); ?>"></td>
							<td><?php echo self::severity_badge( $issue->severity ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
							<td>
								<strong><?php echo esc_html( $issue->title ); ?></strong>
								<div class="gso-desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $issue->description ), 30 ) ); ?></div>
								<?php $edit = GSO_Issues::edit_link( (string) $issue->object_type, (int) $issue->object_id ); ?>
								<?php if ( $edit ) : ?>
									<a href="<?php echo esc_url( $edit ); ?>" target="_blank" rel="noopener">ویرایش مورد ↗</a>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( GSO_Issues::issue_label( (string) $issue->issue_type ) ); ?></td>
							<td><?php echo self::status_badge( (string) $issue->status ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
							<td class="gso-row-actions">
								<button type="button" class="button button-small" data-gso-task="fix_preview" data-issue="<?php echo esc_attr( (string) $issue->id ); ?>">مقایسه قبل/بعد</button>
								<?php if ( 'fixed' !== $issue->status ) : ?>
									<button type="button" class="button button-small" data-gso-task="fix_prepare" data-issue="<?php echo esc_attr( (string) $issue->id ); ?>">پیشنهاد هوشمند</button>
									<button type="button" class="button button-small button-primary" data-gso-task="fix_apply" data-issue="<?php echo esc_attr( (string) $issue->id ); ?>">اعمال</button>
									<button type="button" class="button button-small" data-gso-task="issue_ignore" data-issue="<?php echo esc_attr( (string) $issue->id ); ?>">نادیده بگیر</button>
								<?php else : ?>
									<button type="button" class="button button-small" data-gso-task="issue_reopen" data-issue="<?php echo esc_attr( (string) $issue->id ); ?>">بازگشایی</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $pages,
								'prev_text' => '‹',
								'next_text' => '›',
							)
						)
					);
					?>
				</div></div>
			<?php endif; ?>

			<?php self::render_queue_status(); ?>
		</div>
		<?php self::render_modal(); ?>
		<?php
	}

	/**
	 * جدول اقلام صف.
	 */
	private static function render_queue_status(): void {
		$items  = GSO_Queue::recent( 15 );
		$state  = GSO_Queue::state();
		$counts = GSO_Queue::counts();
		$rate   = GSO_Limiter::status();
		?>
		<div class="gso-card gso-queue-control">
			<h2>کنترل صف پس‌زمینه</h2>
			<p>
				وضعیت: <strong class="gso-queue-status" data-status="<?php echo esc_attr( (string) $state['status'] ); ?>"><?php echo esc_html( self::queue_status_label( (string) $state['status'] ) ); ?></strong>
				<?php if ( ! empty( $state['resume_at'] ) ) : ?>
					<span class="gso-muted">— از سرگیری خودکار: <?php echo esc_html( human_time_diff( time(), (int) $state['resume_at'] ) ); ?> دیگر</span>
				<?php endif; ?>
			</p>
			<p class="gso-muted">
				در انتظار <?php echo esc_html( (string) $counts['pending'] ); ?> —
				در حال اجرا <?php echo esc_html( (string) $counts['running'] ); ?> —
				ناموفق <?php echo esc_html( (string) $counts['failed'] ); ?> —
				سهمیه امروز <?php echo esc_html( (string) $rate['daily_used'] ); ?>/<?php echo esc_html( (string) ( $rate['daily_limit'] > 0 ? $rate['daily_limit'] : '∞' ) ); ?>
			</p>
			<div class="gso-actions">
				<button type="button" class="button" data-gso-task="queue_run">اجرای صف</button>
				<button type="button" class="button" data-gso-task="queue_pause">توقف موقت</button>
				<button type="button" class="button" data-gso-task="queue_resume">ادامه</button>
				<button type="button" class="button gso-danger" data-gso-task="queue_cancel" data-gso-confirm="اقلام در انتظار کنار گذاشته شوند؟">لغو صف</button>
				<button type="button" class="button" data-gso-task="queue_retry">تلاش مجدد ناموفق‌ها</button>
			</div>
		</div>
		<h2 class="gso-section-title">آخرین اقلام صف</h2>
		<table class="widefat striped gso-table">
			<thead><tr><th>#</th><th>ایراد</th><th>نوع اجرا</th><th>وضعیت</th><th>تلاش</th><th>پیام</th></tr></thead>
			<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="6" class="gso-muted">صف خالی است.</td></tr>
			<?php else : ?>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $item->id ); ?></td>
						<td><?php echo esc_html( (string) ( $item->issue_title ?? ( '#' . $item->issue_id ) ) ); ?></td>
						<td><?php echo esc_html( 'auto' === $item->action ? 'خودکار' : 'دستی' ); ?></td>
						<td><?php echo esc_html( $item->status ); ?></td>
						<td><?php echo esc_html( (string) $item->attempts ); ?></td>
						<td><?php echo esc_html( (string) $item->message ); ?>
							<?php if ( in_array( $item->status, array( 'failed', 'skipped' ), true ) ) : ?>
								<button type="button" class="button button-small" data-gso-task="queue_retry_item" data-queue="<?php echo esc_attr( (string) $item->id ); ?>">تلاش مجدد</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* ابزارها                                                            */
	/* ------------------------------------------------------------------ */

	public static function render_tools(): void {
		self::guard();

		$rewrite = get_transient( 'gso_rewrite_' . get_current_user_id() );
		$links   = get_transient( 'gso_links_' . get_current_user_id() );
		$css     = (string) get_option( 'gso_custom_css', '' );
		?>
		<div class="wrap gso-wrap">
			<h1 class="gso-title">ابزارهای تولید و ویرایش اپراتور</h1>
			<?php self::print_notice(); ?>

			<div class="gso-grid gso-grid-2">
				<div class="gso-card">
					<h2>تولید محتوای تازه</h2>
					<form method="post">
						<?php wp_nonce_field( 'gso_tool_generate' ); ?>
						<input type="hidden" name="gso_action" value="tool_generate">
						<p><label>موضوع<br><input type="text" class="widefat" name="topic" required></label></p>
						<p class="gso-inline">
							<label>نوع
								<select name="post_type">
									<?php foreach ( GSO_Settings::watched_post_types() as $type ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label>وضعیت
								<select name="status">
									<option value="draft">پیش‌نویس</option>
									<option value="pending">در انتظار بازبینی</option>
									<option value="publish">انتشار</option>
								</select>
							</label>
							<label>طول تقریبی (کلمه)
								<input type="number" name="length" value="900" min="300" max="4000" step="100">
							</label>
						</p>
						<p><label>لحن<br><input type="text" class="widefat" name="tone" value="حرفه‌ای، روشن و کاربردی"></label></p>
						<p><label>کلیدواژه‌های هدف<br><input type="text" class="widefat" name="keywords"></label></p>
						<p><label>دستورهای ویژه<br><textarea class="widefat" name="instructions" rows="3"></textarea></label></p>
						<p><button type="submit" class="button button-primary">تولید محتوا</button> <span class="gso-muted">با مدل عمیق انجام می‌شود.</span></p>
					</form>
				</div>

				<div class="gso-card">
					<h2>بازنویسی صفحه موجود</h2>
					<form method="post">
						<?php wp_nonce_field( 'gso_rewrite_prepare' ); ?>
						<input type="hidden" name="gso_action" value="rewrite_prepare">
						<p><label>شناسه نوشته/برگه<br><input type="number" class="small-text" name="post_id" min="1" required></label></p>
						<p><label>تمرکز
							<select name="focus">
								<option value="all">همه‌جانبه</option>
								<option value="seo">سئو</option>
								<option value="readability">خوانایی</option>
								<option value="expand">غنی‌سازی</option>
							</select>
						</label></p>
						<p><label>دستور اپراتور<br><textarea class="widefat" name="instructions" rows="3" placeholder="مثلاً: لحن رسمی‌تر، افزودن بخش پرسش‌های متداول"></textarea></label></p>
						<p><button type="submit" class="button button-primary">تولید پیش‌نمایش بازنویسی</button></p>
					</form>

					<?php if ( is_array( $rewrite ) && ! empty( $rewrite['proposal'] ) ) : ?>
						<hr>
						<h3>پیش‌نمایش بازنویسی «<?php echo esc_html( get_the_title( (int) $rewrite['post_id'] ) ); ?>»</h3>
						<p><strong>عنوان پیشنهادی:</strong> <?php echo esc_html( $rewrite['proposal']['title'] ); ?></p>
						<textarea class="widefat gso-code" rows="10" readonly><?php echo esc_textarea( $rewrite['proposal']['content'] ); ?></textarea>
						<form method="post" class="gso-mt">
							<?php wp_nonce_field( 'gso_rewrite_apply' ); ?>
							<input type="hidden" name="gso_action" value="rewrite_apply">
							<button type="submit" class="button button-primary">اعمال بازنویسی (با Snapshot)</button>
						</form>
					<?php endif; ?>
				</div>

				<div class="gso-card">
					<h2>متای سئو برای یک صفحه</h2>
					<form method="post">
						<?php wp_nonce_field( 'gso_tool_meta' ); ?>
						<input type="hidden" name="gso_action" value="tool_meta">
						<p><label>شناسه نوشته<br><input type="number" class="small-text" name="post_id" min="1" required></label></p>
						<p><button type="submit" class="button">تولید و ذخیره متا</button> <span class="gso-muted">قبل از تغییر Snapshot ثبت می‌شود.</span></p>
					</form>

					<hr>
					<h2>Alt تصاویر</h2>
					<form method="post">
						<?php wp_nonce_field( 'gso_tool_alts' ); ?>
						<input type="hidden" name="gso_action" value="tool_alts">
						<p><label>حداکثر تعداد <input type="number" class="small-text" name="limit" value="5" min="1" max="10"></label></p>
						<p><button type="submit" class="button">تولید Alt برای تصاویر بدون متن جایگزین</button></p>
					</form>
				</div>

				<div class="gso-card">
					<h2>لینک‌سازی داخلی هوشمند</h2>
					<form method="post">
						<?php wp_nonce_field( 'gso_tool_links' ); ?>
						<input type="hidden" name="gso_action" value="tool_links">
						<p><label>شناسه نوشته مبدأ<br><input type="number" class="small-text" name="post_id" min="1" required></label></p>
						<p><button type="submit" class="button">پیشنهاد لینک داخلی</button></p>
					</form>
					<?php if ( is_array( $links ) && ! empty( $links['links'] ) ) : ?>
						<h3>پیشنهادها</h3>
						<ul class="gso-link-list">
							<?php foreach ( $links['links'] as $link ) : ?>
								<li><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['anchor'] ); ?></a> — <?php echo esc_html( $link['reason'] ); ?></li>
							<?php endforeach; ?>
						</ul>
						<textarea class="widefat gso-code" rows="4" readonly onclick="this.select()"><?php echo esc_textarea( $links['html'] ); ?></textarea>
					<?php endif; ?>

					<hr>
					<h2>تحلیل هوشمند یک صفحه</h2>
					<form method="post" data-gso-ajax-form="ai_review">
						<?php wp_nonce_field( 'gso_ai_review' ); ?>
						<p><label>شناسه نوشته<br><input type="number" class="small-text" name="post_id" min="1" required></label></p>
						<p><button type="submit" class="button">بررسی با Gemini</button></p>
					</form>
					<div class="gso-ajax-result"></div>
				</div>

				<div class="gso-card">
					<h2>CSS سفارشی</h2>
					<?php if ( ! GSO_Settings::get( 'css_snippets' ) ) : ?>
						<p class="gso-warning">ابتدا «فعال‌سازی ویرایش CSS» را در تنظیمات روشن کنید.</p>
					<?php else : ?>
						<form method="post">
							<?php wp_nonce_field( 'gso_css_save' ); ?>
							<input type="hidden" name="gso_action" value="css_save">
							<textarea class="widefat gso-code" name="css" rows="10"><?php echo esc_textarea( $css ); ?></textarea>
							<p><button type="submit" class="button button-primary">ذخیره CSS (با Snapshot)</button></p>
						</form>
						<hr>
						<form method="post">
							<?php wp_nonce_field( 'gso_css_ai' ); ?>
							<input type="hidden" name="gso_action" value="css_ai">
							<p><label>تولید با هوش مصنوعی<br><textarea class="widefat" name="instruction" rows="3" placeholder="مثلاً: فاصله پاراگراف‌ها را بیشتر و دکمه‌ها را گردتر کن"></textarea></label></p>
							<p><button type="submit" class="button">تولید و اعمال CSS</button></p>
						</form>
						<p class="gso-warning">هشدار: تغییر CSS می‌تواند ظاهر سایت را بشکند. نسخه قبلی در Snapshot ذخیره می‌شود.</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* تنظیمات                                                            */
	/* ------------------------------------------------------------------ */

	public static function render_settings(): void {
		self::guard();
		$settings = GSO_Settings::all();
		$masked   = GSO_Settings::masked_key();
		$public   = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<div class="wrap gso-wrap">
			<h1 class="gso-title">تنظیمات Gemini Site Operator</h1>
			<?php self::print_notice(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'gso_settings_group' ); ?>
				<div class="gso-grid gso-grid-2">
					<div class="gso-card">
						<h2>اتصال Gemini</h2>
						<table class="form-table">
							<tr>
								<th><label for="gso-api-key">کلید Gemini API</label></th>
								<td>
									<input type="password" id="gso-api-key" class="regular-text" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[api_key]" value="<?php echo esc_attr( $masked ); ?>" autocomplete="off">
									<p class="description">کلید فقط رمزنگاری‌شده در دیتابیس ذخیره و هرگز در فرانت‌اند نمایش داده نمی‌شود. برای پاک‌کردن، تیک زیر را بزنید.</p>
									<label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[clear_api_key]" value="1"> حذف کلید ذخیره‌شده</label>
								</td>
							</tr>
							<tr>
								<th>مدل اسکن/حجمی</th>
								<td><input type="text" class="regular-text" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[bulk_model]" value="<?php echo esc_attr( (string) $settings['bulk_model'] ); ?>">
									<p class="description">مثلاً gemini-2.5-flash — برای متا، Alt و کارهای حجیم.</p></td>
							</tr>
							<tr>
								<th>مدل عمیق/کدنویسی</th>
								<td><input type="text" class="regular-text" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[deep_model]" value="<?php echo esc_attr( (string) $settings['deep_model'] ); ?>">
									<p class="description">مثلاً gemini-2.5-pro — برای بازنویسی محتوا و تولید کد/CSS.</p></td>
							</tr>
							<tr>
								<th>پرامپت سیستم</th>
								<td><textarea class="large-text" rows="4" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[system_prompt]"><?php echo esc_textarea( (string) $settings['system_prompt'] ); ?></textarea></td>
							</tr>
							<tr>
								<th>دمای پاسخ</th>
								<td><input type="number" step="0.1" min="0" max="1" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[temperature]" value="<?php echo esc_attr( (string) $settings['temperature'] ); ?>"></td>
							</tr>
						</table>
						<p><button type="button" class="button" data-gso-task="test_api">تست اتصال</button></p>
					</div>

					<div class="gso-card">
						<h2>اجرا و محدودیت‌ها</h2>
						<table class="form-table">
							<tr>
								<th>سطح خودکارسازی</th>
								<td>
									<label><input type="radio" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[automation_level]" value="report_only" <?php checked( $settings['automation_level'], 'report_only' ); ?>> فقط گزارش‌دهی</label><br>
									<label><input type="radio" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[automation_level]" value="safe_auto" <?php checked( $settings['automation_level'], 'safe_auto' ); ?>> اصلاح خودکار موارد کم‌ریسک (Alt، متا)</label><br>
									<label><input type="radio" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[automation_level]" value="review_required" <?php checked( $settings['automation_level'], 'review_required' ); ?>> پیشنهاد خودکار + تأیید دستی تغییرات محتوایی/کدی</label>
								</td>
							</tr>
							<tr>
								<th>اسکن دوره‌ای</th>
								<td>
									<select name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[scan_interval]">
										<option value="off" <?php selected( $settings['scan_interval'], 'off' ); ?>>خاموش</option>
										<option value="hourly" <?php selected( $settings['scan_interval'], 'hourly' ); ?>>ساعتی</option>
										<option value="daily" <?php selected( $settings['scan_interval'], 'daily' ); ?>>روزانه</option>
										<option value="weekly" <?php selected( $settings['scan_interval'], 'weekly' ); ?>>هفتگی</option>
									</select>
								</td>
							</tr>
							<tr>
								<th>سقف درخواست در دقیقه</th>
								<td><input type="number" min="1" max="60" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[rate_limit_per_min]" value="<?php echo esc_attr( (string) $settings['rate_limit_per_min'] ); ?>"></td>
							</tr>
							<tr>
								<th>فاصله درخواست‌ها (میلی‌ثانیه)</th>
								<td><input type="number" min="0" max="10000" step="100" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[request_delay_ms]" value="<?php echo esc_attr( (string) $settings['request_delay_ms'] ); ?>"></td>
							</tr>
							<tr>
								<th>اندازه دسته اسکن</th>
								<td><input type="number" min="1" max="50" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[batch_size]" value="<?php echo esc_attr( (string) $settings['batch_size'] ); ?>">
									<p class="description">تعداد آیتم در هر تیک کران؛ برای هاست‌های ضعیف کم کنید.</p></td>
							</tr>
							<tr>
								<th>حداکثر درخواست هر اجرای صف (دستی)</th>
								<td><input type="number" min="1" max="50" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[max_requests_run]" value="<?php echo esc_attr( (string) $settings['max_requests_run'] ); ?>"></td>
							</tr>
							<tr>
								<th>اندازه دسته صف (کارهای سنگین)</th>
								<td><input type="number" min="1" max="10" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[queue_batch_size]" value="<?php echo esc_attr( (string) $settings['queue_batch_size'] ); ?>">
									<p class="description">تعداد اصلاح/تولید محتوا در هر چرخه پس‌زمینه؛ برای هاست ضعیف ۱ تا ۳.</p></td>
							</tr>
							<tr>
								<th>بودجه زمانی هر دسته (ثانیه)</th>
								<td>
									<label>اسکن <input type="number" class="small-text" min="5" max="120" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[scan_time_budget]" value="<?php echo esc_attr( (string) $settings['scan_time_budget'] ); ?>"></label>
									<label>صف <input type="number" class="small-text" min="5" max="240" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[queue_time_budget]" value="<?php echo esc_attr( (string) $settings['queue_time_budget'] ); ?>"></label>
									<p class="description">پس از پایان بودجه، دسته تمیز بسته می‌شود و ادامه در چرخه بعد اجرا می‌شود.</p>
								</td>
							</tr>
							<tr>
								<th>سقف درخواست روزانه</th>
								<td><input type="number" min="0" max="100000" step="10" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[daily_request_limit]" value="<?php echo esc_attr( (string) $settings['daily_request_limit'] ); ?>">
									<p class="description">۰ = بی‌نهایت. با پر شدن سقف، صف به‌طور خودکار تا ریست روز متوقف می‌شود.</p></td>
							</tr>
							<tr>
								<th>حداکثر تأخیر Backoff (ثانیه)</th>
								<td><input type="number" min="30" max="3600" step="10" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[backoff_max_seconds]" value="<?php echo esc_attr( (string) $settings['backoff_max_seconds'] ); ?>">
									<p class="description">در خطای 429/5xx، تأخیر نمایی ۲→۴→۸ ثانیه (با Jitter) تا این سقف افزایش می‌یابد.</p></td>
							</tr>
							<tr>
								<th>سوئیچ خودکار مدل</th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[auto_model_switch]" value="1" <?php checked( ! empty( $settings['auto_model_switch'] ) ); ?>> در خطای ۴۲۹/۵۰۳/۴۰۴ یک‌بار با مدل دیگر تلاش کن</label></td>
							</tr>
							<tr>
								<th>Timeout هر درخواست (ثانیه)</th>
								<td><input type="number" min="20" max="120" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[request_timeout]" value="<?php echo esc_attr( (string) $settings['request_timeout'] ); ?>">
									<p class="description">پیشنهاد: ۴۵ تا ۶۰ ثانیه. هرگز از بودجه زمانی دسته بیشتر نمی‌شود.</p></td>
							</tr>
							<tr>
								<th>حداکثر توکن خروجی</th>
								<td><input type="number" min="256" max="8192" step="128" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[max_output_tokens]" value="<?php echo esc_attr( (string) $settings['max_output_tokens'] ); ?>"></td>
							</tr>
						</table>
					</div>

					<div class="gso-card">
						<h2>محدوده محتوا و آستانه‌ها</h2>
						<table class="form-table">
							<tr>
								<th>انواع محتوا</th>
								<td>
									<?php foreach ( $public as $type ) : ?>
										<label class="gso-chip"><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, (array) $settings['post_types'], true ) ); ?>> <?php echo esc_html( $type->labels->singular_name ); ?> (<?php echo esc_html( $type->name ); ?>)</label>
									<?php endforeach; ?>
								</td>
							</tr>
							<tr>
								<th>پایش ووکامرس</th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[include_products]" value="1" <?php checked( ! empty( $settings['include_products'] ) ); ?>> محصولات را هم بررسی کن <?php echo GSO_Settings::has_woocommerce() ? '' : '<span class="gso-muted">(ووکامرس فعال نیست)</span>'; ?></label>
									<p><label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[woo_audit_enabled]" value="1" <?php checked( ! empty( $settings['woo_audit_enabled'] ) ); ?>> ممیزی تخصصی محصولات (توضیح، Alt، قیمت، موجودی، دسته/برچسب، ساختار)</label></p>
									<p><label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[woo_require_gallery]" value="1" <?php checked( ! empty( $settings['woo_require_gallery'] ) ); ?>> نبود گالری تصویر را هم ایراد بگیر</label></p>
									<p><label>حد کمینه کلمات توضیح کوتاه <input type="number" class="small-text" min="5" max="120" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[woo_short_desc_words]" value="<?php echo esc_attr( (string) $settings['woo_short_desc_words'] ); ?>"></label></p></td>
							</tr>
							<tr>
								<th>بررسی لینک‌ها</th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[enable_link_check]" value="1" <?php checked( ! empty( $settings['enable_link_check'] ) ); ?>> لینک‌های شکسته بررسی شوند</label>
									<p><label>حداکثر لینک هر صفحه <input type="number" class="small-text" min="1" max="50" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[link_check_limit]" value="<?php echo esc_attr( (string) $settings['link_check_limit'] ); ?>"></label></p></td>
							</tr>
							<tr>
								<th>تکراری‌ها و لینک داخلی</th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[seo_duplicate_check]" value="1" <?php checked( ! empty( $settings['seo_duplicate_check'] ) ); ?>> عنوان/توضیح متا و محتوای تکراری شناسایی شود</label>
									<p><label>کمینه کلمات برای بررسی لینک داخلی <input type="number" class="small-text" min="50" max="3000" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[internal_link_min_words]" value="<?php echo esc_attr( (string) $settings['internal_link_min_words'] ); ?>"></label></p>
									<p class="gso-muted">افزونه سئوی شناسایی‌شده: <?php echo esc_html( GSO_SEO::plugin_label() ); ?></p></td>
							</tr>
							<tr>
								<th>پایش هنگام ذخیره</th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[watch_on_save]" value="1" <?php checked( ! empty( $settings['watch_on_save'] ) ); ?>> هر محتوای تازه/ویرایش‌شده در صف کوتاه بررسی شود</label></td>
							</tr>
							<tr>
								<th>آستانه محتوای کم‌حجم</th>
								<td><input type="number" min="50" max="3000" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[thin_content_words]" value="<?php echo esc_attr( (string) $settings['thin_content_words'] ); ?>"> کلمه</td>
							</tr>
							<tr>
								<th>بازه عنوان سئو</th>
								<td><input type="number" class="small-text" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[min_meta_title]" value="<?php echo esc_attr( (string) $settings['min_meta_title'] ); ?>"> تا <input type="number" class="small-text" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[max_meta_title]" value="<?php echo esc_attr( (string) $settings['max_meta_title'] ); ?>"> کاراکتر</td>
							</tr>
							<tr>
								<th>بازه توضیحات متا</th>
								<td><input type="number" class="small-text" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[min_meta_desc]" value="<?php echo esc_attr( (string) $settings['min_meta_desc'] ); ?>"> تا <input type="number" class="small-text" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[max_meta_desc]" value="<?php echo esc_attr( (string) $settings['max_meta_desc'] ); ?>"> کاراکتر</td>
							</tr>
						</table>
					</div>

					<div class="gso-card">
						<h2>امنیت و داده‌ها</h2>
						<table class="form-table">
							<tr>
								<th>ویرایش CSS</th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[css_snippets]" value="1" <?php checked( ! empty( $settings['css_snippets'] ) ); ?>> فعال‌سازی ویرایشگر CSS سفارشی (با هشدار و Snapshot)</label></td>
							</tr>
							<tr>
								<th>تحلیل هوشمند خودکار</th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[ai_review_enabled]" value="1" <?php checked( ! empty( $settings['ai_review_enabled'] ) ); ?>> اجرای تحلیل Gemini برای صفحات بحرانی هنگام اسکن</label></td>
							</tr>
							<tr>
								<th>حذف داده‌ها هنگام حذف افزونه</th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( GSO_Settings::OPTION ); ?>[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> جدول‌ها و تنظیمات حذف شوند</label></td>
							</tr>
						</table>
						<p class="gso-muted">کلید API در دیتابیس رمزنگاری می‌شود (AES-256 با salt وردپرس). در صورت نبود OpenSSL، ذخیره‌سازی به‌صورت متنی با هشدار انجام می‌شود.</p>
					</div>
				</div>
				<?php submit_button( 'ذخیره تنظیمات' ); ?>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* گزارش‌ها                                                           */
	/* ------------------------------------------------------------------ */

	public static function render_logs(): void {
		self::guard();
		$logs  = GSO_Logger::recent( 150 );
		$snaps = GSO_Snapshot::recent( 40 );
		?>
		<div class="wrap gso-wrap">
			<h1 class="gso-title">گزارش عملیات و نسخه‌های پشتیبان</h1>
			<?php self::print_notice(); ?>

			<div class="gso-actions">
				<form method="post" class="gso-inline-form">
					<?php wp_nonce_field( 'gso_clear_logs' ); ?>
					<input type="hidden" name="gso_action" value="clear_logs">
					<button type="submit" class="button" data-gso-confirm="گزارش‌ها پاک شوند؟">پاک‌سازی گزارش‌ها</button>
				</form>
				<form method="post" class="gso-inline-form">
					<?php wp_nonce_field( 'gso_prune' ); ?>
					<input type="hidden" name="gso_action" value="prune">
					<button type="submit" class="button" data-gso-confirm="اقلام قدیمی صف و Snapshot‌ها حذف شوند؟">پاک‌سازی داده‌های قدیمی</button>
				</form>
			</div>

			<div class="gso-card">
				<h2>آخرین رویدادها</h2>
				<table class="widefat striped gso-table">
					<thead><tr><th>زمان</th><th>سطح</th><th>رویداد</th><th>پیام</th><th>جزئیات</th></tr></thead>
					<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="5" class="gso-muted">گزارشی وجود ندارد.</td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td class="gso-nowrap"><?php echo esc_html( $log->created_at ); ?></td>
								<td><span class="gso-level gso-level-<?php echo esc_attr( $log->level ); ?>"><?php echo esc_html( $log->level ); ?></span></td>
								<td><code><?php echo esc_html( $log->action ); ?></code></td>
								<td><?php echo esc_html( $log->message ); ?></td>
								<td><code class="gso-context"><?php echo esc_html( wp_trim_words( (string) $log->context, 20 ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="gso-card">
				<h2>Snapshot‌ها</h2>
				<table class="widefat striped gso-table">
					<thead><tr><th>#</th><th>مورد</th><th>برچسب</th><th>تاریخ</th><th>وضعیت</th><th>عملیات</th></tr></thead>
					<tbody>
					<?php if ( empty( $snaps ) ) : ?>
						<tr><td colspan="6" class="gso-muted">نسخه‌ای ثبت نشده است.</td></tr>
					<?php else : ?>
						<?php foreach ( $snaps as $snap ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $snap->id ); ?></td>
								<td><?php echo esc_html( GSO_Issues::object_label( (string) $snap->object_type, (int) $snap->object_id ) ); ?></td>
								<td><?php echo esc_html( $snap->label ); ?></td>
								<td class="gso-nowrap"><?php echo esc_html( $snap->created_at ); ?></td>
								<td><?php echo ( empty( $snap->restored_at ) || '0000-00-00 00:00:00' === $snap->restored_at ) ? '<span class="gso-status gso-status-proposed">آماده بازگشت</span>' : '<span class="gso-muted">بازگردانی‌شده</span>'; ?></td>
								<td>
									<?php if ( empty( $snap->restored_at ) || '0000-00-00 00:00:00' === $snap->restored_at ) : ?>
										<button type="button" class="button button-small gso-danger" data-gso-task="rollback" data-snapshot="<?php echo esc_attr( (string) $snap->id ); ?>">بازگردانی</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * مودال پیش‌نمایش قبل/بعد.
	 */
	private static function render_modal(): void {
		?>
		<div id="gso-modal" class="gso-modal" hidden>
			<div class="gso-modal-box" role="dialog" aria-modal="true" aria-labelledby="gso-modal-title">
				<button type="button" class="gso-modal-close" aria-label="بستن">×</button>
				<h2 id="gso-modal-title">پیش‌نمایش تغییر</h2>
				<div class="gso-modal-body"></div>
				<div class="gso-modal-actions"></div>
			</div>
		</div>
		<?php
	}
}
