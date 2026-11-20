<?php
/**
 * آداپتر پیگیری و نظرسنجی — فقط APIهای عمومی افزونهٔ پنل مراجعان.
 *
 * لیدها با شمارهٔ موبایل به پرونده گره می‌خورند، پس دامنهٔ پرونده اعمال
 * می‌شود. نظرسنجی‌ها ناشناس ثبت می‌شوند و محتوای عمومی‌اند؛ تأیید/حذف
 * آن‌ها در سطح کلینیک و با کپابیلیتی انجام می‌شود، نه با پرونده.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Adapter_Followup {

	/** مراحل پیگیری مالک (Leads::FOLLOWUP_COLUMNS). */
	private const STAGES = array(
		'24h' => 'followed_up_24h',
		'72h' => 'followed_up_72h',
	);

	public static function available() {
		return class_exists( '\ZoojDarman\ClientPortal\Plugin' )
			&& method_exists( '\ZoojDarman\ClientPortal\Plugin', 'leads' )
			&& method_exists( '\ZoojDarman\ClientPortal\Plugin', 'survey' );
	}

	/** @return array<int,object> */
	public static function leads( $limit = 200 ) {
		if ( ! self::available() ) {
			return array();
		}

		try {
			$rows = \ZoojDarman\ClientPortal\Plugin::instance()->leads()->get_all( max( 1, min( 300, (int) $limit ) ) );
		} catch ( Throwable $e ) {
			return array();
		}

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<int,object> */
	public static function surveys( $limit = 100 ) {
		if ( ! self::available() ) {
			return array();
		}

		try {
			$rows = \ZoojDarman\ClientPortal\Plugin::instance()->survey()->get_pending_surveys( max( 1, min( 200, (int) $limit ) ) );
		} catch ( Throwable $e ) {
			return array();
		}

		return is_array( $rows ) ? $rows : array();
	}

	public static function lead( $lead_id ) {
		$lead_id = PAS_Dashboard_Validation::id( $lead_id );

		if ( $lead_id <= 0 || ! self::available() ) {
			return null;
		}

		try {
			$leads = \ZoojDarman\ClientPortal\Plugin::instance()->leads();

			if ( method_exists( $leads, 'get' ) ) {
				$row = $leads->get( $lead_id );

				if ( is_object( $row ) ) {
					return $row;
				}
			}

			// نسخهٔ قدیمی پنل: جست‌وجو در فهرست عمومی.
			foreach ( self::leads( 300 ) as $row ) {
				if ( is_object( $row ) && (int) ( $row->id ?? 0 ) === $lead_id ) {
					return $row;
				}
			}
		} catch ( Throwable $e ) {
			return null;
		}

		return null;
	}

	public static function survey( $survey_id ) {
		$survey_id = PAS_Dashboard_Validation::id( $survey_id );

		if ( $survey_id <= 0 || ! self::available() ) {
			return null;
		}

		try {
			$survey = \ZoojDarman\ClientPortal\Plugin::instance()->survey();

			if ( ! method_exists( $survey, 'get_survey' ) ) {
				return null;
			}

			$row = $survey->get_survey( $survey_id );
		} catch ( Throwable $e ) {
			return null;
		}

		return is_object( $row ) ? $row : null;
	}

	/** ثبت انجام پیگیری یک مرحله — idempotent. */
	public static function mark_followup( array $post ) {
		if ( ! self::available() ) {
			return new WP_Error( 'pas_dashboard_no_followup', __( 'قرارداد لید در افزونهٔ پنل مراجعان در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		$stage = PAS_Dashboard_Validation::key( $post['stage'] ?? '', 5 );

		if ( ! isset( self::STAGES[ $stage ] ) ) {
			return new WP_Error( 'pas_dashboard_lead_stage', __( 'مرحلهٔ پیگیری معتبر نیست.', 'peyvand-amn-dashboard' ) );
		}

		$lead = self::lead( $post['lead_id'] ?? 0 );

		if ( ! $lead ) {
			return new WP_Error( 'pas_dashboard_lead_missing', __( 'لید یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! PAS_Dashboard_Adapter_Portal::can_view_phone( (string) ( $lead->phone ?? '' ) ) ) {
			return new WP_Error( 'pas_dashboard_lead_scope', __( 'این لید برای مراجعان پرونده‌های شما نیست.', 'peyvand-amn-dashboard' ) );
		}

		$column = self::STAGES[ $stage ];

		if ( ! empty( $lead->booked ) ) {
			return new WP_Error( 'pas_dashboard_lead_booked', __( 'این لید نوبت گرفته است؛ پیگیری دستی لازم نیست.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! empty( $lead->{$column} ) ) {
			return array(
				'ok'       => true,
				'message'  => __( 'این پیگیری قبلاً ثبت شده است.', 'peyvand-amn-dashboard' ),
				'id'       => (int) $lead->id,
				'redirect' => self::module_url(),
			);
		}

		try {
			\ZoojDarman\ClientPortal\Plugin::instance()->leads()->mark_followed_up( (int) $lead->id, $column );
		} catch ( Throwable $e ) {
			return new WP_Error( 'pas_dashboard_lead_failed', __( 'ثبت پیگیری ناموفق بود.', 'peyvand-amn-dashboard' ) );
		}

		return array(
			'ok'       => true,
			'message'  => '24h' === $stage
				? __( 'پیگیری ۲۴ ساعته ثبت شد.', 'peyvand-amn-dashboard' )
				: __( 'پیگیری ۷۲ ساعته ثبت شد.', 'peyvand-amn-dashboard' ),
			'id'       => (int) $lead->id,
			'redirect' => self::module_url(),
		);
	}

	/** تأیید یا لغو تأیید نمایش عمومی یک نظرسنجی. */
	public static function moderate_survey( array $post ) {
		if ( ! self::available() ) {
			return new WP_Error( 'pas_dashboard_no_survey', __( 'قرارداد نظرسنجی در افزونهٔ پنل مراجعان در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		$survey = self::survey( $post['survey_id'] ?? 0 );

		if ( ! $survey ) {
			return new WP_Error( 'pas_dashboard_survey_missing', __( 'نظرسنجی یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		$approved = ! empty( $post['approved'] );

		try {
			\ZoojDarman\ClientPortal\Plugin::instance()->survey()->set_approved( (int) $survey->id, $approved );
		} catch ( Throwable $e ) {
			return new WP_Error( 'pas_dashboard_survey_failed', __( 'تغییر وضعیت نظرسنجی ناموفق بود.', 'peyvand-amn-dashboard' ) );
		}

		return array(
			'ok'       => true,
			'message'  => $approved
				? __( 'نظرسنجی تأیید شد و در صفحهٔ عمومی نمایش داده می‌شود.', 'peyvand-amn-dashboard' )
				: __( 'تأیید نظرسنجی لغو شد.', 'peyvand-amn-dashboard' ),
			'id'       => (int) $survey->id,
			'redirect' => self::module_url(),
		);
	}

	/** حذف برگشت‌ناپذیر یک نظرسنجی. */
	public static function delete_survey( array $post ) {
		if ( ! self::available() ) {
			return new WP_Error( 'pas_dashboard_no_survey', __( 'قرارداد نظرسنجی در افزونهٔ پنل مراجعان در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		$survey = self::survey( $post['survey_id'] ?? 0 );

		if ( ! $survey ) {
			return new WP_Error( 'pas_dashboard_survey_missing', __( 'نظرسنجی یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		try {
			\ZoojDarman\ClientPortal\Plugin::instance()->survey()->delete_survey( (int) $survey->id );
		} catch ( Throwable $e ) {
			return new WP_Error( 'pas_dashboard_survey_failed', __( 'حذف نظرسنجی ناموفق بود.', 'peyvand-amn-dashboard' ) );
		}

		return array(
			'ok'       => true,
			'message'  => __( 'نظرسنجی برای همیشه حذف شد.', 'peyvand-amn-dashboard' ),
			'id'       => (int) $survey->id,
			'redirect' => self::module_url(),
		);
	}

	/** آیا یک نظر واقعاً در شورت‌کد عمومی دیده می‌شود؟ (همان قاعدهٔ مالک) */
	public static function is_public_testimonial( $survey ) {
		return is_object( $survey )
			&& ! empty( $survey->approved )
			&& ! empty( $survey->consent )
			&& trim( (string) ( $survey->suggestions ?? '' ) ) !== '';
	}

	/** کپشن آمادهٔ اینستاگرام — همان قرارداد مالک. */
	public static function instagram_caption( $suggestion ) {
		if ( class_exists( '\ZoojDarman\ClientPortal\Survey' ) && method_exists( '\ZoojDarman\ClientPortal\Survey', 'build_instagram_caption' ) ) {
			return (string) \ZoojDarman\ClientPortal\Survey::build_instagram_caption( (string) $suggestion );
		}

		return (string) $suggestion;
	}

	/** مرحلهٔ پیگیری لازم برای یک لید ('' یعنی لازم نیست). */
	public static function needed_stage( $lead ) {
		if ( ! is_object( $lead ) || ! empty( $lead->booked ) ) {
			return '';
		}

		$created = strtotime( (string) ( $lead->created_at ?? '' ) . ' UTC' );

		if ( ! $created ) {
			return '';
		}

		$age = time() - $created;

		if ( empty( $lead->followed_up_24h ) && $age >= DAY_IN_SECONDS ) {
			return '24h';
		}

		if ( empty( $lead->followed_up_72h ) && $age >= 3 * DAY_IN_SECONDS ) {
			return '72h';
		}

		return '';
	}

	public static function module_url() {
		return add_query_arg(
			array(
				'page'   => PAS_DASHBOARD_SLUG,
				'module' => 'followup',
			),
			admin_url( 'admin.php' )
		);
	}
}
