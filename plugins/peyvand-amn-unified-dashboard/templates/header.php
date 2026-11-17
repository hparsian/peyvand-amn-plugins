<?php
/**
 * سربرگ داشبورد.
 *
 * @var PAS_Dashboard_Module $module
 * @var int                  $unread
 * @var WP_User              $user
 */

defined( 'ABSPATH' ) || exit;
?>
<header class="pas-header">
	<div class="pas-header-main">
		<button type="button" class="pas-nav-toggle" aria-controls="pas-sidebar" aria-expanded="false">
			<span class="screen-reader-text"><?php esc_html_e( 'نمایش منو', 'peyvand-amn-dashboard' ); ?></span>
			<span aria-hidden="true">☰</span>
		</button>

		<div>
			<h1 class="pas-title"><?php echo esc_html( $module->title ); ?></h1>
			<?php if ( $module->description !== '' ) : ?>
				<p class="pas-subtitle"><?php echo esc_html( $module->description ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<div class="pas-header-side">
		<?php if ( PAS_Dashboard_Permissions::can( 'notifications' ) ) : ?>
			<a class="pas-unread" href="<?php echo esc_url( add_query_arg( array( 'page' => PAS_DASHBOARD_SLUG, 'module' => 'notifications' ), admin_url( 'admin.php' ) ) ); ?>">
				<span aria-hidden="true">🔔</span>
				<span class="pas-unread-count"><?php echo esc_html( (string) (int) $unread ); ?></span>
				<span class="screen-reader-text"><?php esc_html_e( 'اعلان خوانده‌نشده', 'peyvand-amn-dashboard' ); ?></span>
			</a>
		<?php endif; ?>

		<span class="pas-user">
			<?php echo esc_html( $user instanceof WP_User ? $user->display_name : '' ); ?>
		</span>
	</div>
</header>
