<?php
/**
 * ناوبری کناری — گروه‌بندی بر اساس وظیفه، نه بر اساس افزونه.
 *
 * @var array $nav
 */

defined( 'ABSPATH' ) || exit;

$pas_crypto = PAS_Dashboard_Core_Bridge::crypto_ready();

/**
 * گروه‌بندی ماژول‌ها: فقط گروه‌هایی که حداقل یک ماژول مجاز دارند رندر
 * می‌شوند. ترتیب گروه‌ها از کار روزمره به مدیریت می‌رود.
 */
$pas_groups        = PAS_Dashboard_Module::nav_groups();
$pas_current_group = isset( $module ) && $module instanceof PAS_Dashboard_Module
	? PAS_Dashboard_Module::group_for( $module->id )
	: '';
?>
<nav class="pvn-nav pas-sidebar" id="pas-sidebar" aria-label="<?php esc_attr_e( 'ناوبری داشبورد', 'peyvand-amn-dashboard' ); ?>">
	<?php if ( isset( $module ) && $module instanceof PAS_Dashboard_Module ) : ?>
		<p class="pvn-nav__now">
			<span class="pvn-nav__now-icon" aria-hidden="true"><?php echo PAS_Dashboard_Module::svg( $module->id, 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="pvn-nav__now-text">
				<small><?php esc_html_e( 'شما اینجا هستید', 'peyvand-amn-dashboard' ); ?></small>
				<b><?php echo esc_html( $module->title ); ?></b>
			</span>
		</p>
	<?php endif; ?>
	<?php foreach ( $pas_groups as $pas_group_label => $pas_ids ) : ?>
		<?php
		$pas_items = array();
		foreach ( $pas_ids as $pas_id ) {
			if ( isset( $nav[ $pas_id ] ) ) {
				$pas_items[ $pas_id ] = $nav[ $pas_id ];
			}
		}

		if ( empty( $pas_items ) ) {
			continue;
		}
		?>
		<div class="pvn-nav__group<?php echo esc_attr( $pas_current_group === $pas_group_label ? ' is-current' : '' ); ?>">
			<p class="pvn-nav__label"><?php echo esc_html( $pas_group_label ); ?></p>

			<?php foreach ( $pas_items as $pas_id => $pas_item ) : ?>
				<a
					class="pvn-nav__link"
					href="<?php echo esc_url( $pas_item['url'] ); ?>"
					<?php echo ! empty( $pas_item['active'] ) ? 'aria-current="page"' : ''; ?>
				>
					<?php echo PAS_Dashboard_Module::svg( $pas_id, 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php echo esc_html( $pas_item['label'] ); ?></span>
					<?php if ( $pas_id === 'notifications' && ! empty( $unread ) ) : ?>
						<span class="pvn-nav__count"><?php echo esc_html( (string) (int) $unread ); ?></span>
						<span class="screen-reader-text"><?php esc_html_e( 'اعلان خوانده‌نشده', 'peyvand-amn-dashboard' ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

	<div class="pvn-nav__foot">
		<p class="pvn-status">
			<span class="pvn-status__dot <?php echo esc_attr( $pas_crypto ? 'pvn-status__dot--ok' : 'pvn-status__dot--bad' ); ?>" aria-hidden="true"></span>
			<span>
				<?php
				echo esc_html(
					$pas_crypto
						? __( 'رمزنگاری بالینی فعال', 'peyvand-amn-dashboard' )
						: __( 'کلید رمزنگاری تنظیم نشده', 'peyvand-amn-dashboard' )
				);
				?>
			</span>
		</p>
		<p class="pvn-status">
			<?php echo PAS_Dashboard_Module::svg( 'lock', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span><?php echo esc_html( sprintf( /* translators: %s: version */ __( 'نسخه %s', 'peyvand-amn-dashboard' ), PAS_DASHBOARD_VERSION ) ); ?></span>
		</p>
	</div>
</nav>
