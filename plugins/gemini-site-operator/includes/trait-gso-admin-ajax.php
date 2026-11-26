<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * هندلرهای AJAX — همه با nonce و manage_options.
 */
trait GSO_Admin_Ajax {

	/**
	 * نقطه ورود AJAX.
	 */
	public static function ajax(): void {
		if ( ! check_ajax_referer( 'gso_ajax', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'nonce نامعتبر است.' ), 403 );
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی مجاز نیست.' ), 403 );
		}

		$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
		$id   = isset( $_POST['issue_id'] ) ? absint( $_POST['issue_id'] ) : 0;

		switch ( $task ) {
			case 'scan_start':
				$state = GSO_Auditor::start();
				$state = GSO_Auditor::tick();
				wp_send_json_success(
					array(
						'message'  => 'اسکن آغاز شد.',
						'state'    => self::public_state( $state ),
						'progress' => GSO_Auditor::progress( $state ),
					)
				);
				break;

			case 'scan_stop':
				$state = GSO_Auditor::stop();
				wp_send_json_success( array( 'message' => 'اسکن متوقف شد.', 'state' => self::public_state( $state ) ) );
				break;

			case 'scan_status':
				$state = GSO_Auditor::state();
				$logs  = GSO_Logger::recent( 1 );
				wp_send_json_success(
					array(
						'state'         => self::public_state( $state ),
						'progress'      => GSO_Auditor::progress( $state ),
						'progress_info' => GSO_Auditor::progress_info( $state ),
						'stats'         => GSO_Issues::stats(),
						'queue'         => GSO_Queue::counts(),
						'queue_state'   => GSO_Queue::state(),
						'rate'          => GSO_Limiter::status(),
						'last_log'      => $logs ? array( 'message' => (string) $logs[0]->message, 'created_at' => (string) $logs[0]->created_at ) : null,
					)
				);
				break;

			case 'queue_run':
				$report = GSO_Queue::process();
				wp_send_json_success(
					array(
						'message' => $report['locked']
							? 'صف در حال اجراست؛ کمی بعد دوباره تلاش کنید.'
							: sprintf( '%d اصلاح اعمال شد، %d پیشنهاد آماده شد، %d خطا.', $report['applied'], $report['proposed'], $report['failed'] ),
						'report'      => $report,
						'queue'       => GSO_Queue::counts(),
						'queue_state' => GSO_Queue::state(),
						'rate'        => GSO_Limiter::status(),
					)
				);
				break;

			case 'queue_status':
				wp_send_json_success( array( 'queue' => GSO_Queue::counts() ) );
				break;

			case 'queue_retry':
				$count = GSO_Queue::retry_failed();
				wp_send_json_success( array( 'message' => sprintf( '%d قلم به صف بازگشت.', $count ), 'queue' => GSO_Queue::counts(), 'queue_state' => GSO_Queue::state() ) );
				break;

			case 'queue_pause':
				$state = GSO_Queue::pause( 'manual' );
				wp_send_json_success( array( 'message' => 'صف متوقف شد؛ با «ادامه» از همان‌جا ادامه می‌یابد.', 'queue_state' => $state, 'queue' => GSO_Queue::counts() ) );
				break;

			case 'queue_resume':
				GSO_Queue::resume();
				wp_send_json_success( array( 'message' => 'صف از سر گرفته شد؛ ادامه در پس‌زمینه اجرا می‌شود.', 'queue_state' => GSO_Queue::state(), 'queue' => GSO_Queue::counts() ) );
				break;

			case 'queue_cancel':
				$state = GSO_Queue::cancel();
				wp_send_json_success( array( 'message' => 'صف لغو شد؛ اقلام در انتظار کنار گذاشته شدند (قابل بازگشت با ادامه).', 'queue_state' => $state, 'queue' => GSO_Queue::counts() ) );
				break;

			case 'queue_retry_item':
				$queue_id = isset( $_POST['queue_id'] ) ? absint( $_POST['queue_id'] ) : 0;
				if ( $queue_id < 1 ) {
					wp_send_json_error( array( 'message' => 'شناسه صف نامعتبر است.' ) );
				}
				GSO_Queue::retry_item( $queue_id );
				wp_send_json_success( array( 'message' => 'قلم صف برای اجرای مجدد آماده شد.', 'queue' => GSO_Queue::counts() ) );
				break;

			case 'queue_state':
				wp_send_json_success( array( 'queue_state' => GSO_Queue::state(), 'queue' => GSO_Queue::counts(), 'rate' => GSO_Limiter::status() ) );
				break;

			case 'test_api':
				$result = GSO_Gemini::test_connection();
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}
				wp_send_json_success( array( 'message' => 'اتصال برقرار است (' . $result['model'] . ').', 'reply' => $result['message'] ) );
				break;

			case 'fix_preview':
				$preview = GSO_Fixer::preview( $id );
				if ( is_wp_error( $preview ) ) {
					wp_send_json_error( array( 'message' => $preview->get_error_message() ) );
				}
				$issue = GSO_Issues::get( $id );
				$snaps = array();
				foreach ( GSO_Snapshot::for_issue( $id ) as $snap ) {
					$snaps[] = array(
						'id'          => (int) $snap->id,
						'label'       => (string) $snap->label,
						'created_at'  => (string) $snap->created_at,
						'restored_at' => (string) $snap->restored_at,
					);
				}
				wp_send_json_success(
					array(
						'message'  => 'پیش‌نمایش آماده است.',
						'title'    => $issue ? $issue->title : '',
						'issue'    => $id,
						'preview'  => $preview,
						'snapshots' => $snaps,
					)
				);
				break;

			case 'fix_prepare':
				if ( $id < 1 ) {
					wp_send_json_error( array( 'message' => 'شناسه نامعتبر است.' ) );
				}
				$payload = GSO_Fixer::prepare( $id );
				if ( is_wp_error( $payload ) ) {
					wp_send_json_error( array( 'message' => $payload->get_error_message() ) );
				}
				$preview = GSO_Fixer::preview( $id );
				wp_send_json_success(
					array(
						'message' => 'پیشنهاد آماده شد؛ می‌توانید مقایسه کنید و سپس اعمال کنید.',
						'preview' => is_wp_error( $preview ) ? null : $preview,
					)
				);
				break;

			case 'fix_apply':
				if ( $id < 1 ) {
					wp_send_json_error( array( 'message' => 'شناسه نامعتبر است.' ) );
				}
				$result = GSO_Fixer::apply( $id );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}
				wp_send_json_success(
					array(
						'message'     => sprintf( 'اصلاح اعمال شد؛ نسخه پشتیبان #%d قابل بازگشت است.', $result['snapshot_id'] ),
						'snapshot_id' => $result['snapshot_id'],
						'stats'       => GSO_Issues::stats(),
					)
				);
				break;

			case 'issue_ignore':
				if ( $id < 1 ) {
					wp_send_json_error( array( 'message' => 'شناسه نامعتبر است.' ) );
				}
				GSO_Issues::set_status( $id, 'ignored' );
				GSO_Logger::log( 'info', 'issue.ignore', sprintf( 'ایراد #%d نادیده گرفته شد.', $id ) );
				wp_send_json_success( array( 'message' => 'مورد نادیده گرفته شد.' ) );
				break;

			case 'issue_reopen':
				if ( $id < 1 ) {
					wp_send_json_error( array( 'message' => 'شناسه نامعتبر است.' ) );
				}
				GSO_Issues::set_status( $id, 'open' );
				wp_send_json_success( array( 'message' => 'مورد بازگشایی شد.' ) );
				break;

			case 'bulk_fix':
			case 'bulk_enqueue':
				$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
				$ids = array_values( array_filter( $ids ) );
				if ( empty( $ids ) ) {
					wp_send_json_error( array( 'message' => 'هیچ موردی انتخاب نشده است.' ) );
				}
				$added = GSO_Queue::bulk_enqueue( $ids, 'fix' );
				$msg   = sprintf( '%d مورد به صف افزوده شد.', $added );
				if ( 'bulk_fix' === $task ) {
					$msg .= ' اجرا در پس‌زمینه و در دسته‌های کوچک انجام می‌شود؛ برای جلوگیری از Timeout در این درخواست اجرا نمی‌شود.';
				}
				wp_send_json_success( array( 'message' => $msg, 'queue' => GSO_Queue::counts(), 'queue_state' => GSO_Queue::state(), 'stats' => GSO_Issues::stats() ) );
				break;

			case 'bulk_filter':
				$severity = isset( $_POST['severity'] ) ? sanitize_key( wp_unslash( $_POST['severity'] ) ) : '';
				$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'open_all';
				$issues   = GSO_Issues::query(
					array(
						'status'   => $status,
						'severity' => $severity,
						'limit'    => 50,
						'orderby'  => 'priority',
					)
				);
				$ids = array_map(
					static function ( $issue ) {
						return (int) $issue->id;
					},
					$issues
				);
				$added = GSO_Queue::bulk_enqueue( $ids, 'fix' );
				wp_send_json_success( array( 'message' => sprintf( '%d مورد از نتایج فیلتر به صف افزوده شد.', $added ), 'queue' => GSO_Queue::counts() ) );
				break;

			case 'rollback':
				$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
				$result      = GSO_Snapshot::restore( $snapshot_id );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}
				wp_send_json_success( array( 'message' => 'بازگشت به نسخه قبل انجام شد.', 'stats' => GSO_Issues::stats() ) );
				break;

			case 'ai_review':
				$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
				if ( $post_id < 1 ) {
					wp_send_json_error( array( 'message' => 'شناسه نوشته نامعتبر است.' ) );
				}
				$result = GSO_Auditor::ai_review_post( $post_id );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}
				wp_send_json_success(
					array(
						'message' => sprintf( '%d پیشنهاد هوشمند ثبت شد؛ در صف اصلاح ببینید.', $result['count'] ),
						'count'   => $result['count'],
					)
				);
				break;

			default:
				wp_send_json_error( array( 'message' => 'درخواست ناشناخته است.' ), 400 );
		}
	}

	/**
	 * وضعیت عمومی برای JSON.
	 *
	 * @param array $state وضعیت.
	 * @return array
	 */
	private static function public_state( array $state ): array {
		return array(
			'active'    => ! empty( $state['active'] ),
			'phase'     => (string) $state['phase'],
			'processed' => (int) $state['processed'],
			'found'     => (int) $state['found'],
			'errors'    => (int) $state['errors'],
			'offset'    => (int) $state['offset'],
			'total'     => (int) $state['total'],
			'last_tick' => (int) $state['last_tick'],
			'progress'  => GSO_Auditor::progress( $state ),
		);
	}
}
