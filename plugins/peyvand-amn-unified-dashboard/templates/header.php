<?php
/**
 * سربرگ داشبورد — نوار بالا.
 *
 * @var PAS_Dashboard_Module $module
 * @var int                  $unread
 * @var WP_User              $user
 */

defined( 'ABSPATH' ) || exit;

$pas_display = $user instanceof WP_User ? (string) $user->display_name : '';
$pas_initial = $pas_display !== ''
	? ( function_exists( 'mb_substr' ) ? mb_substr( $pas_display, 0, 1 ) : substr( $pas_display, 0, 1 ) )
	: '?';

$pas_home = add_query_arg(
	array(
		'page'   => PAS_DASHBOARD_SLUG,
		'module' => PAS_Dashboard_Permissions::default_module(),
	),
	admin_url( 'admin.php' )
);

$pas_today = PAS_Dashboard_Core_Bridge::jalali( gmdate( 'Y-m-d' ) );
$pas_bell  = add_query_arg(
	array(
		'page'   => PAS_DASHBOARD_SLUG,
		'module' => 'notifications',
	),
	admin_url( 'admin.php' )
);
?>
<header class="pvn-topbar">
	<div class="pvn-topbar-start">
		<button type="button" class="pvn-burger pas-nav-toggle" aria-controls="pas-sidebar" aria-expanded="false">
			<span class="screen-reader-text"><?php esc_html_e( 'نمایش منو', 'peyvand-amn-dashboard' ); ?></span>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
				stroke-linecap="round" aria-hidden="true" focusable="false">
				<path d="M4 7h16M4 12h16M4 17h16"/>
			</svg>
		</button>

		<a class="pvn-brand" href="<?php echo esc_url( $pas_home ); ?>">
			<span class="pvn-mark" aria-hidden="true"></span>
			<span class="pvn-brand-text">
				<strong><?php esc_html_e( 'پیوند امن', 'peyvand-amn-dashboard' ); ?></strong>
				<small><?php esc_html_e( 'داشبورد درمانگر', 'peyvand-amn-dashboard' ); ?></small>
			</span>
		</a>

		<span class="pvn-crumb" aria-hidden="true">
			<?php echo PAS_Dashboard_Module::svg( $module->id, 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<b><?php echo esc_html( $module->title ); ?></b>
		</span>
	</div>

	<div class="pvn-topbar-end">
		<span class="pvn-date"><?php echo esc_html( sprintf( /* translators: %s: Jalali date */ __( 'امروز · %s', 'peyvand-amn-dashboard' ), $pas_today ) ); ?></span>

		<?php if ( PAS_Dashboard_Permissions::can( 'notifications' ) ) : ?>
			<a class="pvn-icon-btn pvn-bell" href="<?php echo esc_url( $pas_bell ); ?>">
				<?php echo PAS_Dashboard_Module::svg( 'notifications', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( $unread > 0 ) : ?>
					<span class="pvn-bell__count"><?php echo esc_html( (string) (int) $unread ); ?></span>
				<?php endif; ?>
				<span class="screen-reader-text">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: unread count */
							__( '%d اعلان خوانده‌نشده', 'peyvand-amn-dashboard' ),
							(int) $unread
						)
					);
					?>
				</span>
			</a>
		<?php endif; ?>

		<span class="pvn-user">
			<span class="pvn-avatar" aria-hidden="true"><?php echo esc_html( $pas_initial ); ?></span>
			<span class="pvn-user__name"><?php echo esc_html( $pas_display ); ?></span>
		</span>
	</div>
</header>
