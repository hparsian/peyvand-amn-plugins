<?php
/**
 * REST — فقط مسیرهای خواندنی و با permission_callback صریح.
 *
 * هیچ مسیر نوشتنی از طریق REST ارائه نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Rest {

	const NS = 'pas-dashboard/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::NS,
			'/summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'summary' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
			)
		);

		register_rest_route(
			self::NS,
			'/case/(?P<id>\d+)/notes',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'case_notes' ),
				'permission_callback' => array( __CLASS__, 'can_read_case' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => static function ( $value ) {
							return absint( $value ) > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'health' ),
				'permission_callback' => static function () {
					return PAS_Dashboard_Security::can_manage();
				},
			)
		);
	}

	public static function can_read() {
		return PAS_Dashboard_Security::can_access_dashboard();
	}

	public static function can_read_case( $request ) {
		$case_id = absint( $request['id'] );

		return PAS_Dashboard_Security::can_view_clinical() && PAS_Dashboard_Security::can_access_case( $case_id );
	}

	public static function summary() {
		$user_id = get_current_user_id();
		$cases   = PAS_Dashboard_Core_Bridge::cases( $user_id, 500 );
		$stats   = PAS_Dashboard_Core_Bridge::client_stats();

		return rest_ensure_response(
			array(
				'cases'          => count( $cases ),
				'clients'        => (int) ( $stats['clients'] ?? 0 ),
				'review'         => (int) ( $stats['review'] ?? 0 ),
				'conflicts_open' => (int) ( $stats['conflicts_open'] ?? 0 ),
			)
		);
	}

	public static function case_notes( $request ) {
		$case_id = absint( $request['id'] );
		$notes   = PAS_Dashboard_Core_Bridge::notes( $case_id );
		$out     = array();

		foreach ( $notes as $note ) {
			if ( ! is_object( $note ) ) {
				continue;
			}
			$out[] = array(
				'id'             => (int) ( $note->id ?? 0 ),
				'session_date'   => (string) ( $note->session_date ?? '' ),
				'session_number' => (int) ( $note->session_number ?? 0 ),
				'status'         => (string) ( $note->status ?? '' ),
				'content'        => (string) ( $note->content ?? '' ),
			);
		}

		PAS_Dashboard_Audit_Log::record( 'rest.case_notes', 'case', $case_id, 'ok' );

		return rest_ensure_response( $out );
	}

	public static function health() {
		return rest_ensure_response(
			array(
				'core'         => PAS_Dashboard_Core_Bridge::has_core(),
				'case_file'    => PAS_Dashboard_Core_Bridge::has_case_file(),
				'crypto'       => PAS_Dashboard_Core_Bridge::crypto_ready(),
				'sms_bridge'   => PAS_Dashboard_Core_Bridge::sms_available(),
				'assessments'  => PAS_Dashboard_Core_Bridge::assessments_available(),
				'version'      => PAS_DASHBOARD_VERSION,
			)
		);
	}
}
