<?php
/**
 * ناوبری کناری.
 *
 * @var array $nav
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="pas-sidebar" id="pas-sidebar" aria-label="<?php esc_attr_e( 'ناوبری داشبورد', 'peyvand-amn-dashboard' ); ?>">
	<ul class="pas-nav-list">
		<?php foreach ( $nav as $id => $item ) : ?>
			<li class="pas-nav-item<?php echo ! empty( $item['active'] ) ? ' is-active' : ''; ?>">
				<a href="<?php echo esc_url( $item['url'] ); ?>"<?php echo ! empty( $item['active'] ) ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $item['label'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="pas-sidebar-foot">
		<p class="pas-muted">
			<?php echo esc_html( sprintf( /* translators: %s: version */ __( 'نسخه %s', 'peyvand-amn-dashboard' ), PAS_DASHBOARD_VERSION ) ); ?>
		</p>
		<p class="pas-muted">
			<?php
			echo esc_html(
				PAS_Dashboard_Core_Bridge::crypto_ready()
					? __( 'رمزنگاری: فعال', 'peyvand-amn-dashboard' )
					: __( 'رمزنگاری: تنظیم نشده', 'peyvand-amn-dashboard' )
			);
			?>
		</p>
	</div>
</nav>
