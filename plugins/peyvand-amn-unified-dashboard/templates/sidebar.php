<?php
/**
 * ناوبری کناری.
 *
 * @var array $nav
 */

defined( 'ABSPATH' ) || exit;

$pas_crypto = PAS_Dashboard_Core_Bridge::crypto_ready();
?>
<nav class="pas-sidebar" id="pas-sidebar" aria-label="<?php esc_attr_e( 'ناوبری داشبورد', 'peyvand-amn-dashboard' ); ?>">
	<p class="pas-nav-label"><?php esc_html_e( 'ماژول‌ها', 'peyvand-amn-dashboard' ); ?></p>

	<ul class="pas-nav-list">
		<?php foreach ( $nav as $id => $item ) : ?>
			<li class="pas-nav-item<?php echo ! empty( $item['active'] ) ? ' is-active' : ''; ?>">
				<a href="<?php echo esc_url( $item['url'] ); ?>"<?php echo ! empty( $item['active'] ) ? ' aria-current="page"' : ''; ?>>
					<span class="dashicons <?php echo esc_attr( PAS_Dashboard_Module::icon_for( $id ) ); ?>" aria-hidden="true"></span>
					<span><?php echo esc_html( $item['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="pas-sidebar-foot">
		<p class="pas-muted">
			<span class="pas-dot <?php echo esc_attr( $pas_crypto ? 'pas-dot-good' : 'pas-dot-bad' ); ?>" aria-hidden="true"></span>
			<?php
			echo esc_html(
				$pas_crypto
					? __( 'رمزنگاری: فعال', 'peyvand-amn-dashboard' )
					: __( 'رمزنگاری: تنظیم نشده', 'peyvand-amn-dashboard' )
			);
			?>
		</p>
		<p class="pas-muted">
			<?php echo esc_html( sprintf( /* translators: %s: version */ __( 'نسخه %s', 'peyvand-amn-dashboard' ), PAS_DASHBOARD_VERSION ) ); ?>
		</p>
	</div>
</nav>
